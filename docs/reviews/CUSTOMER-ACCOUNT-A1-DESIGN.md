# CUSTOMER-ACCOUNT-A1 — Order Delivery Address Snapshot (Design)

| Field | Value |
|---|---|
| Version | 1.0 |
| Status | DESIGN — Pending Approval (not implemented) |
| Project | HIRFAH |
| Scope | Checkout, orders, vendor and delivery-driver order responses |
| Date | 2026-10-18 |
| Finding | [ORD-BE-001](HIRFAH-REVIEW-FINDINGS.md#ord-be-001--order-delivery-address-is-a-live-reference-to-the-customers-saved-address) (OPEN) |
| References | CUSTOMER-ACCOUNT-01, CUSTOMER-ACCOUNT-A1 (discovery reports, session only) |

> Documentation only. No application code, migration, or data was changed while writing this design.
>
> **Implementation note (CUSTOMER-ACCOUNT-A1-IMPLEMENTATION-01 and ORD-BE-001-FIX-03, 2026-10-18, not committed).** §6 is implemented as an explicit opt-in, `Order::exposeDeliveryAddress()`, called only at the address-bearing paths (vendor orders index/show, delivery-driver assignments index/show and overview, customer order show), which eager-load `address.governorate` / `address.city` for the legacy fallback. The raw `delivery_*` columns are hidden. The deprecated `address` payload is the snapshot plus `id` (`customer_address_id`) and `customer_id`. A first global `Order::toArray()` override was removed because it exposed the delivery address on unrelated endpoints (ORD-BE-001-REVIEW-02). The migration's `down()` refuses whenever `delivery_address_source IS NOT NULL`, and `up()` verifies column types and indexes. The admin controllers are unchanged (R-5). Status of ORD-BE-001: OPEN.

## 1. Approved decisions

| # | Decision |
|---|---|
| D1 | The delivery address snapshot is stored in explicit columns on `orders`. |
| D2 | The snapshot is captured inside the checkout transaction. |
| D3 | It preserves recipient, phone, governorate/city IDs and names, address line, notes and coordinates. |
| D4 | `customer_address_id` stays only as an optional reference to the saved address that was used. |
| D5 | Vendor and delivery-driver responses use the snapshot as the authoritative delivery address. |
| D6 | JSON compatibility is kept where feasible, but the mutable saved address is never exposed as the order's delivery address. |
| D7 | Legacy development orders without a snapshot use an explicitly identified fallback. |
| D8 | No historical backfill command. |
| D9 | JSON checkout without an address keeps its current behavior in this phase. |
| D10 | The finding is recorded as OPEN (ORD-BE-001). |

Context: the project is in development; there are no production orders. Development currently has 0 orders (read-only check, CUSTOMER-ACCOUNT-A1).

## 2. Problem (summary)

`orders.customer_address_id` (`create_orders_table.php:15`, nullable, `nullOnDelete`) is the only address data on an order. Every reader goes through the live `Order::address()` relation (`Order.php:40-42`):

- vendor JSON — `VendorDashboard/OrderController.php:22, 52`
- delivery-driver JSON — `DeliveryDriverDashboard/AssignmentController.php:25, 39`, `OverviewController.php:26`
- customer — `Store/OrderController.php:36`
- admin — `Dashboard/OrderManagementController.php:45`, `Dashboard/VendorOrderManagementController.php:106`

Editing a saved address (`AddressController::update`) rewrites the delivery address of every order that used it; deleting it (`destroy`) nulls the reference, so the order loses its address, including orders out for delivery. Deleting a city or governorate nulls it on the saved address as well.

## 3. Migration

File: `database/migrations/2026_10_18_000000_add_delivery_address_snapshot_to_orders_table.php`

| Column | Type | Null | Constraint / note |
|---|---|---|---|
| `delivery_address_source` | `string(20)` | yes | `checkout` = snapshot captured; `none` = order placed without an address (D9); `NULL` = legacy order created before this migration |
| `delivery_recipient_name` | `string(255)` | yes | |
| `delivery_phone` | `string(32)` | yes | same limit as checkout validation |
| `delivery_governorate_id` | `foreignId` | yes | `constrained('governorates')->nullOnDelete()` |
| `delivery_governorate_name` | `string(255)` | yes | name at checkout time |
| `delivery_city_id` | `foreignId` | yes | `constrained('cities')->nullOnDelete()` |
| `delivery_city_name` | `string(255)` | yes | name at checkout time |
| `delivery_address_line` | `string(500)` | yes | same limit as checkout validation |
| `delivery_notes` | `text` | yes | the address notes, not `orders.notes` |
| `delivery_latitude` | `decimal(10,7)` | yes | same precision as `customer_addresses` |
| `delivery_longitude` | `decimal(10,7)` | yes | |

- All columns are nullable: existing rows stay valid and no existing row is written.
- The foreign keys create the indexes on `delivery_governorate_id` / `delivery_city_id` (MySQL) used for area-based driver assignment queries.
- `customer_address_id` is unchanged (D4).
- `up()`: if the columns already exist, `assertTargetState()` checks their names, nullability and the two foreign keys (`set null`) and refuses any other shape without changing anything; partial presence is refused (pattern of `add_moderation_columns_to_products`).
- `down()`: refuses while any order has `delivery_address_source = 'checkout'` (dropping the columns would erase immutable delivery history), otherwise drops the foreign keys and columns. `migrate:fresh` is not affected by this guard (it drops tables directly). See decision R-1.

## 4. Checkout transaction and locking sequence

`CheckoutController::store()`; validation, the early ownership check (403) and the cart checks stay as they are.

```
DB::transaction:
  1. lock cart                       Cart::whereKey(..)->lockForUpdate()            (unchanged)
  2. resolve the delivery address    (new)
     a. addressChoice = 'new'  → create the CustomerAddress as today; the new row is used as the source
     b. customer_address_id set → CustomerAddress::whereKey($id)
                                     ->where('customer_id', $customer->id)
                                     ->lockForUpdate()->first()
                                   null → abort 403 (ownership re-checked inside the transaction;
                                   a concurrent delete now ends in 403, not an FK error)
     c. no address (JSON only, D9) → no source; delivery_address_source = 'none'
  3. load names                      governorate / city of the source row (plain reads)
  4. lock products in ascending id   (unchanged, VEN-BE-019)
  5. Order::create([... existing fields ..., ...snapshot fields, 'delivery_address_source' => 'checkout'|'none'])
  6. vendor orders, items, reservations, cart → ordered   (unchanged)
```

- Lock order is cart → one address row → products (ascending). `AddressController::update/destroy` take the address row lock implicitly through their `UPDATE`/`DELETE`; a concurrent edit is ordered before or after the snapshot, never mixed into it.
- The snapshot is built by one helper (`DeliveryAddress::fromCustomerAddress()`, §5) so that checkout is the only writer.

## 5. Order model

`app/Models/Order.php`

- `$fillable`: the eleven `delivery_*` columns.
- `casts()`: `delivery_latitude` / `delivery_longitude` → `decimal:7`.
- Constants: `DELIVERY_SOURCE_CHECKOUT = 'checkout'`, `DELIVERY_SOURCE_NONE = 'none'`.
- Relations: `deliveryGovernorate()` and `deliveryCity()` (`belongsTo`, informational only — the names come from the snapshot).
- `address()` is kept and documented as **the saved address used at checkout, not the delivery address**. No new code reads it for delivery.
- `hasDeliverySnapshot(): bool` — `delivery_address_source !== null`.
- `deliveryAddress(): DeliveryAddress` — the authoritative delivery address (accessor `delivery_address`):

| Order state | Returned value | `source` | `is_historical` |
|---|---|---|---|
| `delivery_address_source = 'checkout'` | the snapshot columns | `checkout` | `true` |
| `delivery_address_source = 'none'` | all fields `null` | `none` | `true` (truly no address) |
| `NULL`, `customer_address_id` set and the row exists | the **current** saved address (legacy fallback) | `legacy_saved_address` | `false` |
| `NULL`, no reference or the row is gone | all fields `null` | `legacy_missing` | `false` |

- Immutability: a `static::updating` hook throws `LogicException` when any `delivery_*` attribute is dirty on an existing order. Limits (documented in the model, as for `ProductAdminAction`): raw queries are not stopped, and `ON DELETE SET NULL` still clears the two IDs when a city or governorate is deleted.

`app/Support/Orders/DeliveryAddress.php` (new): a readonly value object (`Arrayable`, `JsonSerializable`) with `recipient_name`, `phone`, `governorate_id`, `governorate_name`, `city_id`, `city_name`, `address_line`, `notes`, `latitude`, `longitude`, `source`, `is_historical`; plus `fromCustomerAddress(CustomerAddress)` (used by checkout and the legacy fallback) and `fromOrder(Order)`.

## 6. JSON compatibility

Current shape: vendor and driver responses serialize `order.address` as the live `CustomerAddress` (keys `id`, `customer_id`, `governorate_id`, `city_id`, `label`, `recipient_name`, `phone`, `address_line`, `notes`, `latitude`, `longitude`, `is_default`, `created_at`, `updated_at`). No JavaScript in `resources/js` reads it; consumers are tests and API clients.

| Key in the order payload | After implementation |
|---|---|
| `delivery_address` | **New, canonical.** The `DeliveryAddress` payload (§5), including `source` and `is_historical`. |
| `address` | **Kept for compatibility, deprecated.** Same `DeliveryAddress` payload as `delivery_address` — never the live saved address. Common keys (`recipient_name`, `phone`, `governorate_id`, `city_id`, `address_line`, `notes`, `latitude`, `longitude`) keep their names and meaning; `id`, `customer_id`, `label`, `is_default`, `created_at`, `updated_at` are no longer present. |
| `customer_address_id` | Unchanged (order attribute). |

Mechanism: the vendor and driver controllers stop eager-loading `order.address` / `vendorOrder.order.address` and, for each serialized order, call one helper (`DeliveryAddress::exposeOn(Order $order)`) that appends `delivery_address` and sets the `address` relation to the same value object (Eloquent serializes an `Arrayable` relation value). The same helper is used by the customer order JSON (`Store/OrderController`). Admin views (`dashboard.orders.show`, `dashboard.vendor-orders.show`) switch their eager load from `address.city` / `order.address` to the snapshot columns; they display no address today and their rendering is unchanged here.

Removing the deprecated `address` key is a later, separate decision (R-3).

## 7. Legacy orders (D7, D8)

- Legacy = `delivery_address_source IS NULL` (created before the migration). Only development data can be in this state.
- No backfill command (D8). Legacy orders read through the fallback of §5, which is **explicitly marked** `source = legacy_saved_address`, `is_historical = false`: it is the saved address as it is now, not as it was at checkout.
- A legacy order whose address was deleted returns `legacy_missing` with null fields.
- Consumers that must not act on a non-historical address can check `is_historical`.

## 8. Deleted or renamed cities and governorates

| Event | `delivery_*_id` | `delivery_*_name` | Delivery address shown |
|---|---|---|---|
| Rename | unchanged | unchanged (name at checkout) | original name |
| Delete city | `NULL` (`nullOnDelete`) | unchanged | original name, no id |
| Delete governorate | `NULL`; its cities are cascade-deleted, so `delivery_city_id` → `NULL` too | unchanged | original names, no ids |
| Inactive (`status = inactive`) | unchanged | unchanged | original |

The names are the display source; the IDs are informational and for area queries.

## 9. Test plan (SQLite `:memory:`)

New `tests/Feature/OrderDeliveryAddressSnapshotTest.php`:

1. Checkout with a saved address: every snapshot column equals the address (including governorate/city names) and `source = checkout`.
2. Checkout with `addressChoice = new`: the snapshot equals the submitted fields (city name `null`, as today).
3. JSON checkout without an address: order created as today, `source = none`, fields `null`.
4. Editing the saved address after checkout leaves the snapshot unchanged; vendor and driver JSON show the original values.
5. Deleting the saved address: snapshot kept; `customer_address_id` becomes `NULL`.
6. Renaming / deleting the city and the governorate: names kept, IDs nulled on delete.
7. Another customer's address at checkout is refused (403) and no order is created; a deleted address between validation and the transaction ends in 403, not a 500.
8. Vendor JSON (`index`, `show`, action responses) and driver JSON (`index`, `show`, overview, action responses): `delivery_address` = snapshot; `address` = the same payload; no live-address keys (`is_default`, `label`, `customer_id`).
9. Customer order JSON: `delivery_address` is the snapshot.
10. Legacy order (`source` `NULL`): `legacy_saved_address` with `is_historical = false`; without the row: `legacy_missing`.
11. Immutability: updating a `delivery_*` attribute through Eloquent throws; status updates still work.
12. Migration: columns, nullability, both foreign keys `set null`; re-run and unexpected shapes refused; `down()` refused while a `checkout` snapshot exists, allowed otherwise.
13. Regression: `StorefrontCheckoutPageTest`, `CustomerBackendTest`, `CustomerDashboardPageTest`, `VendorOrderLifecycleTest`, delivery-driver tests, `CustomerGuestCartMergeTest`, then the full suite.

Optional (isolated MySQL, `hirfah_fk_test_*`): an address edit racing a checkout is ordered by the row lock.

## 10. Files expected to change

| File | Change |
|---|---|
| `database/migrations/2026_10_18_000000_add_delivery_address_snapshot_to_orders_table.php` | new |
| `app/Support/Orders/DeliveryAddress.php` | new value object |
| `app/Models/Order.php` | fillable, casts, constants, relations, `deliveryAddress()`, immutability hook, `address()` doc |
| `app/Http/Controllers/Store/CheckoutController.php` | address resolution inside the transaction, snapshot fields |
| `app/Http/Controllers/VendorDashboard/OrderController.php` | snapshot exposure instead of `order.address` |
| `app/Http/Controllers/DeliveryDriverDashboard/AssignmentController.php` | same |
| `app/Http/Controllers/DeliveryDriverDashboard/OverviewController.php` | same |
| `app/Http/Controllers/Store/OrderController.php` | same (customer JSON) |
| `app/Http/Controllers/Dashboard/OrderManagementController.php`, `VendorOrderManagementController.php` | eager load the snapshot instead of the live address (no UI change) |
| `tests/Feature/OrderDeliveryAddressSnapshotTest.php` | new |
| existing tests asserting `order.address` keys, if any | updated to the compatible payload |
| `docs/reviews/HIRFAH-REVIEW-FINDINGS.md` | ORD-BE-001 resolution notes after implementation |

Not changed: `customer_addresses`, `AddressController`, address and checkout views, delivery fee calculation.

## 11. Risks

| # | Risk | Mitigation |
|---|---|---|
| K-1 | API clients reading removed `address` keys (`id`, `label`, `is_default`, …) | Deprecated `address` keeps the common keys; removal is a later decision |
| K-2 | Legacy fallback shows today's saved address | Marked `legacy_saved_address`, `is_historical = false`; development data only |
| K-3 | Lock order change in checkout | One extra row lock between the cart and the products; products keep ascending order (VEN-BE-019) |
| K-4 | Immutability is Eloquent-level only | Documented; checkout is the only writer |
| K-5 | Personal data duplicated in `orders` | Orders cascade with the customer; no extra retention |
| K-6 | `addressChoice = new` creates addresses without `city_id`, so the snapshot has no city | Unchanged in this phase (out of scope) |
| K-7 | Orders without an address still possible via JSON (D9) | Recorded as `source = none`; making the address mandatory is a later decision |

## 12. Remaining decisions

| # | Decision | Recommendation |
|---|---|---|
| R-1 | `down()` refuses while `checkout` snapshots exist (project pattern), or drops freely during development | Refuse (consistent with the project; `migrate:fresh` unaffected) |
| R-2 | Immutability hook on `delivery_*` (throw on update) | Yes |
| R-3 | Timeline to remove the deprecated `address` key from order JSON | After API consumers move to `delivery_address` |
| R-4 | Whether vendors should receive the full delivery address and phone (unchanged exposure today) | Keep as today; review with the delivery contract |
| R-5 | Displaying the delivery address on the admin and customer order pages | Separate UI phase |
