<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

/**
 * ORD-BE-001 (CUSTOMER-ACCOUNT-A1): the delivery address of an order is copied at checkout and stays as it was,
 * whatever later happens to the saved address, its city or its governorate. Vendor, delivery-driver and customer
 * JSON expose that copy as delivery_address and as the deprecated "address" key; legacy orders (no copy) use a
 * marked fallback.
 */
class OrderDeliveryAddressSnapshotTest extends TestCase
{
    use InteractsWithStorefront;
    use RefreshDatabase;

    /** Keys of the old "address" payload that described the mutable saved-address row and are not restored. */
    private const LIVE_ADDRESS_KEYS = ['label', 'is_default', 'created_at', 'updated_at'];

    private Governorate $governorate;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->governorate = Governorate::query()->forceCreate(['name' => 'الخليل', 'slug' => 'hebron', 'status' => 'active']);
        $this->city = City::query()->forceCreate(['governorate_id' => $this->governorate->id, 'name' => 'دورا', 'slug' => 'dura', 'status' => 'active']);
    }

    // ---------------------------------------------------------------- capture at checkout

    public function test_a_saved_address_is_copied_at_checkout(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);

        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);

        $this->assertSame('checkout', $order->delivery_address_source);
        $this->assertSame($address->id, $order->customer_address_id);
        $this->assertSame([
            'recipient_name' => 'ليلى أحمد',
            'phone' => '0599123456',
            'governorate_id' => $this->governorate->id,
            'governorate_name' => 'الخليل',
            'city_id' => $this->city->id,
            'city_name' => 'دورا',
            'address_line' => 'شارع الصف، بناية 4',
            'notes' => 'الطابق الثاني',
            'latitude' => '31.5326000',
            'longitude' => '35.0998000',
            'source' => 'checkout',
            'is_historical' => true,
        ], $order->deliveryAddress()->toArray());
        $this->assertTrue($order->hasDeliverySnapshot());
    }

    public function test_a_new_address_is_copied_at_checkout(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->storefrontProduct();
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $this->post(route('customer.checkout.store'), [
            'addressChoice' => 'new', 'paymentMethod' => 'cod', 'recipient_name' => 'Recipient', 'phone' => '0500000000',
            'governorate_id' => $this->governorate->id, 'address_line' => 'New real street',
        ])->assertRedirect(route('customer.dashboard'));

        $order = Order::query()->sole();
        $this->assertSame('checkout', $order->delivery_address_source);
        $this->assertSame(CustomerAddress::query()->sole()->id, $order->customer_address_id);
        $address = $order->deliveryAddress();
        $this->assertSame(['Recipient', '0500000000', $this->governorate->id, 'الخليل', 'New real street'],
            [$address->recipientName, $address->phone, $address->governorateId, $address->governorateName, $address->addressLine]);
        $this->assertNull($address->cityId, 'The new-address form has no city (unchanged).');
        $this->assertNull($address->cityName);
    }

    public function test_a_json_checkout_without_an_address_keeps_working_and_is_marked_none(): void
    {
        $customer = Customer::factory()->create();

        $order = $this->checkout($customer, []);

        $this->assertNull($order->customer_address_id);
        $this->assertSame('none', $order->delivery_address_source);
        $this->assertSame(['source' => 'none', 'is_historical' => true],
            array_filter($order->deliveryAddress()->toArray(), fn ($value) => $value !== null));
    }

    // ---------------------------------------------------------------- later changes

    public function test_editing_the_saved_address_does_not_change_the_order(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);

        $address->update(['recipient_name' => 'Someone else', 'phone' => '0590000000', 'address_line' => 'Another street', 'city_id' => null]);

        $delivery = $order->fresh()->deliveryAddress();
        $this->assertSame(['ليلى أحمد', '0599123456', 'شارع الصف، بناية 4', 'دورا'],
            [$delivery->recipientName, $delivery->phone, $delivery->addressLine, $delivery->cityName]);
    }

    public function test_deleting_the_saved_address_keeps_the_delivery_address(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);

        $this->actingAs($customer, 'customer')->deleteJson(route('customer.addresses.destroy', $address))->assertOk();

        $order->refresh();
        $this->assertNull($order->customer_address_id, 'The reference is cleared (ON DELETE SET NULL).');
        $this->assertSame('شارع الصف، بناية 4', $order->deliveryAddress()->addressLine);
        $this->assertTrue($order->deliveryAddress()->isHistorical);
    }

    public function test_city_and_governorate_renames_and_deletions_keep_the_names(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->checkout($customer, ['customer_address_id' => $this->savedAddress($customer)->id]);

        $this->city->update(['name' => 'Renamed city']);
        $this->governorate->update(['name' => 'Renamed governorate']);
        $renamed = $order->fresh()->deliveryAddress();
        $this->assertSame(['دورا', 'الخليل', $this->city->id, $this->governorate->id],
            [$renamed->cityName, $renamed->governorateName, $renamed->cityId, $renamed->governorateId]);

        $this->city->delete();
        $withoutCity = $order->fresh()->deliveryAddress();
        $this->assertNull($withoutCity->cityId);
        $this->assertSame('دورا', $withoutCity->cityName);
        $this->assertSame($this->governorate->id, $withoutCity->governorateId);

        // Deleting the governorate cascades to its cities; both ids end NULL, both names stay. This second order
        // is placed after the rename above, so its snapshot holds the governorate's name at that time.
        $other = City::query()->forceCreate(['governorate_id' => $this->governorate->id, 'name' => 'يطا', 'slug' => 'yatta', 'status' => 'active']);
        $second = $this->checkout($customer, ['customer_address_id' => $this->savedAddress($customer, ['city_id' => $other->id])->id], 'second');
        $this->governorate->delete();
        $gone = $second->fresh()->deliveryAddress();
        $this->assertSame([null, 'Renamed governorate', null, 'يطا'], [$gone->governorateId, $gone->governorateName, $gone->cityId, $gone->cityName]);
        $this->assertSame(['الخليل', 'دورا'], [$order->fresh()->delivery_governorate_name, $order->fresh()->delivery_city_name], 'The first order is untouched.');
    }

    // ---------------------------------------------------------------- ownership

    public function test_another_customers_address_is_refused_and_no_order_is_created(): void
    {
        $customer = Customer::factory()->create();
        $foreign = $this->savedAddress(Customer::factory()->create());
        $this->fillCart($customer);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['customer_address_id' => $foreign->id, 'payment_method' => 'cod'])
            ->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_an_address_deleted_after_the_first_check_is_refused_inside_the_transaction(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $this->fillCart($customer);

        // The address disappears right after the cart row is locked, i.e. after the early ownership check.
        $deleted = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use ($address, &$deleted): void {
            if (! $deleted && str_contains($query->sql, 'from "carts" where "carts"."id" = ?')) {
                $deleted = true;
                DB::table('customer_addresses')->where('id', $address->id)->delete();
            }
        });

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['customer_address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertForbidden();

        $this->assertTrue($deleted, 'The race was reproduced.');
        $this->assertSame(0, Order::query()->count());
        $this->assertSame('active', Cart::query()->where('customer_id', $customer->id)->value('status'), 'Rolled back.');
    }

    // ---------------------------------------------------------------- JSON

    public function test_vendor_json_exposes_the_snapshot_as_delivery_address_and_address(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);
        $vendorOrder = $order->vendorOrders()->sole();
        $address->update(['address_line' => 'Edited after checkout', 'is_default' => true]);

        $vendor = $vendorOrder->vendor;
        $index = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.orders.index'))->assertOk();
        $show = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $vendorOrder))->assertOk();

        foreach ([$index->json('orders.data.0.order'), $show->json('order.order')] as $payload) {
            $this->assertSnapshotPayload($payload, $address->id, $customer->id);
        }
    }

    public function test_delivery_driver_json_exposes_the_snapshot(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);
        $driver = $this->approvedDriver();
        $assignment = DeliveryAssignment::query()->create([
            'vendor_order_id' => $order->vendorOrders()->sole()->id, 'delivery_driver_id' => $driver->id, 'status' => 'assigned', 'assigned_at' => now(),
        ]);
        $address->update(['address_line' => 'Edited after checkout']);

        $index = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.index'))->assertOk();
        $show = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $assignment))->assertOk();
        $overview = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.overview'))->assertOk();

        foreach ([$index->json('data.0.vendor_order.order'), $show->json('vendor_order.order'), $overview->json('recent_assignments.0.vendor_order.order')] as $payload) {
            $this->assertSnapshotPayload($payload, $address->id, $customer->id);
        }
    }

    public function test_customer_order_json_exposes_the_snapshot(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $order = $this->checkout($customer, ['customer_address_id' => $address->id]);
        $address->update(['address_line' => 'Edited after checkout']);

        $payload = $this->actingAs($customer, 'customer')->getJson(route('customer.orders.show', $order))->assertOk()->json();
        $this->assertSnapshotPayload($payload, $address->id, $customer->id);

        // After the saved address is deleted, the deprecated id becomes null (never fabricated); the snapshot stays.
        $this->actingAs($customer, 'customer')->deleteJson(route('customer.addresses.destroy', $address))->assertOk();
        $afterDelete = $this->actingAs($customer, 'customer')->getJson(route('customer.orders.show', $order))->assertOk()->json();
        $this->assertSnapshotPayload($afterDelete, null, $customer->id);
    }

    public function test_responses_that_never_had_an_address_do_not_gain_one(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $this->fillCart($customer);

        $checkout = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['customer_address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated()->json();
        $this->assertNoDeliveryAddressAnywhere($checkout, 'checkout response');
        $this->assertNoRawDeliveryColumns($checkout);

        $order = Order::query()->findOrFail($checkout['id']);
        $vendorOrder = $order->vendorOrders()->sole();
        $vendor = $vendorOrder->vendor;
        $now = now();
        $disputeId = DB::table('disputes')->insertGetId(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id,
            'subject' => 'Late', 'description' => 'Late delivery', 'created_at' => $now, 'updated_at' => $now]);
        $returnId = DB::table('return_requests')->insertGetId(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id,
            'reason' => 'Damaged', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('reviews')->insert(['customer_id' => $customer->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'vendor_id' => $vendor->id,
            'reviewable_type' => 'vendor', 'rating' => 5, 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('commissions')->insert(['vendor_order_id' => $vendorOrder->id, 'vendor_id' => $vendor->id, 'rate' => 10, 'base_amount' => 25, 'amount' => 2.5,
            'created_at' => $now, 'updated_at' => $now]);

        $vendorPaths = [
            'vendor disputes' => route('vendor.dashboard.disputes.index'),
            'vendor dispute' => route('vendor.dashboard.disputes.show', $disputeId),
            'vendor returns' => route('vendor.dashboard.returns.index'),
            'vendor return' => route('vendor.dashboard.returns.show', $returnId),
            'vendor reviews' => route('vendor.dashboard.reviews.index'),
            'vendor commissions' => route('vendor.dashboard.commissions.index'),
            'vendor overview' => route('vendor.dashboard.overview'),
        ];
        foreach ($vendorPaths as $context => $url) {
            $payload = $this->actingAs($vendor, 'vendor')->getJson($url)->assertOk()->json();
            $this->assertGreaterThan(0, $this->assertNoDeliveryAddressAnywhere($payload, $context), "{$context}: the order is in the payload.");
        }

        $customerOrders = $this->actingAs($customer, 'customer')->getJson(route('customer.orders.index'))->assertOk()->json();
        $this->assertGreaterThan(0, $this->assertNoDeliveryAddressAnywhere($customerOrders, 'customer orders index'));

        DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['status' => 'delivered', 'delivered_at' => $now]);
        $receipt = $this->actingAs($customer, 'customer')
            ->patchJson(route('customer.orders.vendor-orders.confirm-receipt', [$order, $vendorOrder]))->assertOk()->json();
        $this->assertGreaterThan(0, $this->assertNoDeliveryAddressAnywhere($receipt, 'receipt confirmation'));
    }

    // ---------------------------------------------------------------- legacy

    public function test_a_legacy_order_falls_back_to_the_saved_address_marked_as_not_historical(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $legacy = $this->legacyOrder($customer, $address->id);

        $delivery = $legacy->deliveryAddress();
        $this->assertFalse($legacy->hasDeliverySnapshot());
        $this->assertSame('legacy_saved_address', $delivery->source);
        $this->assertFalse($delivery->isHistorical);
        $this->assertSame(['شارع الصف، بناية 4', 'دورا', 'الخليل'], [$delivery->addressLine, $delivery->cityName, $delivery->governorateName]);

        $address->update(['address_line' => 'Edited later']);
        $this->assertSame('Edited later', $legacy->fresh()->deliveryAddress()->addressLine, 'The fallback is the current saved address.');

        $address->delete();
        $missing = $legacy->fresh()->deliveryAddress();
        $this->assertSame('legacy_missing', $missing->source);
        $this->assertFalse($missing->isHistorical);
        $this->assertNull($missing->addressLine);

        $payload = $this->actingAs($customer, 'customer')->getJson(route('customer.orders.show', $legacy))->assertOk()->json();
        $this->assertSame('legacy_missing', $payload['delivery_address']['source']);
        $this->assertFalse($payload['delivery_address']['is_historical']);
        $this->assertSame(['id' => null, 'customer_id' => $customer->id] + $payload['delivery_address'], $payload['address']);
        $this->assertNoRawDeliveryColumns($payload);
    }

    public function test_the_legacy_fallback_resolves_once_without_queries_or_side_effects(): void
    {
        $customer = Customer::factory()->create();
        $address = $this->savedAddress($customer);
        $legacyId = $this->legacyOrder($customer, $address->id)->id;
        $snapshotId = $this->checkout($customer, ['customer_address_id' => $address->id])->id;

        foreach ([$legacyId => 'legacy_saved_address', $snapshotId => 'checkout'] as $id => $source) {
            $order = Order::query()->with(['address.governorate', 'address.city'])->findOrFail($id);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $first = json_encode($order->exposeDeliveryAddress());
            $second = json_encode($order);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertSame(0, $queries, "{$source}: no query while serializing.");
            $this->assertSame($first, $second, "{$source}: serializing twice gives the same payload.");
            $this->assertSame($source, json_decode($first, true)['delivery_address']['source']);
            $this->assertSame($source === 'checkout', json_decode($first, true)['delivery_address']['is_historical']);
        }

        // An order that is not opted in serializes without any delivery address, and is not changed by
        // resolving its delivery address.
        $plain = Order::query()->findOrFail($legacyId);
        $this->assertSame('legacy_saved_address', $plain->deliveryAddress()->source);
        $this->assertFalse($plain->relationLoaded('address'), 'The fallback sets no relation.');
        $array = $plain->toArray();
        foreach (['delivery_address', 'address', ...Order::DELIVERY_COLUMNS] as $key) {
            $this->assertArrayNotHasKey($key, $array, $key);
        }
    }

    // ---------------------------------------------------------------- immutability

    public function test_the_snapshot_cannot_be_changed_through_eloquent(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->checkout($customer, ['customer_address_id' => $this->savedAddress($customer)->id]);

        foreach (['delivery_phone' => '0590000000', 'delivery_address_source' => null, 'delivery_city_name' => 'x', 'delivery_latitude' => '1.0000000'] as $column => $value) {
            try {
                $order->fresh()->update([$column => $value]);
                $this->fail("{$column} was changed.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('checkout snapshot', $exception->getMessage());
            }
        }

        $order->fresh()->update(['status' => 'processing']);
        $this->assertSame('processing', $order->fresh()->status, 'Other columns still update.');
        $this->assertSame('0599123456', $order->fresh()->delivery_phone);
    }

    // ---------------------------------------------------------------- helpers

    private function assertSnapshotPayload(?array $order, ?int $savedAddressId, int $customerId): void
    {
        $this->assertNotNull($order);
        $this->assertSame('شارع الصف، بناية 4', $order['delivery_address']['address_line'], 'The snapshot, not the edited saved address.');
        $this->assertSame('checkout', $order['delivery_address']['source']);
        $this->assertTrue($order['delivery_address']['is_historical']);
        $this->assertArrayNotHasKey('id', $order['delivery_address']);
        $this->assertArrayHasKey('address', $order, 'The deprecated key is kept.');
        $this->assertSame(['id' => $savedAddressId, 'customer_id' => $customerId] + $order['delivery_address'], $order['address'],
            'The deprecated key carries the snapshot and the two still-valid identifiers.');
        foreach (self::LIVE_ADDRESS_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $order['address'], "No live saved-address key: {$key}");
        }
        $this->assertNoRawDeliveryColumns($order);
    }

    private function assertNoRawDeliveryColumns(array $order): void
    {
        foreach (Order::DELIVERY_COLUMNS as $column) {
            $this->assertArrayNotHasKey($column, $order, "Raw column {$column} is hidden.");
        }
    }

    /**
     * Every order-shaped array in a JSON payload (one with "customer_address_id") carries no delivery address: no
     * "delivery_address", no "address", no raw delivery_* column.
     *
     * @return int the number of orders found
     */
    private function assertNoDeliveryAddressAnywhere(array $payload, string $context): int
    {
        $found = 0;
        $walk = function (array $node) use (&$walk, &$found, $context): void {
            if (array_key_exists('customer_address_id', $node)) {
                $found++;
                foreach (['delivery_address', 'address', ...Order::DELIVERY_COLUMNS] as $key) {
                    $this->assertArrayNotHasKey($key, $node, "{$context}: {$key}");
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($payload);

        return $found;
    }

    private function savedAddress(Customer $customer, array $attributes = []): CustomerAddress
    {
        return $customer->addresses()->create(array_merge([
            'label' => 'Home', 'recipient_name' => 'ليلى أحمد', 'phone' => '0599123456',
            'governorate_id' => $this->governorate->id, 'city_id' => $this->city->id,
            'address_line' => 'شارع الصف، بناية 4', 'notes' => 'الطابق الثاني', 'latitude' => 31.5326, 'longitude' => 35.0998,
        ], $attributes));
    }

    private function fillCart(Customer $customer, string $slug = 'test-product'): Product
    {
        $product = $this->storefrontProduct($slug);
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

        return $product;
    }

    private function checkout(Customer $customer, array $input, string $slug = 'test-product'): Order
    {
        $this->fillCart($customer, $slug);

        $id = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), $input + ['payment_method' => 'cod'])
            ->assertCreated()
            ->json('id');

        return Order::query()->findOrFail($id);
    }

    private function legacyOrder(Customer $customer, ?int $addressId): Order
    {
        // An order created before the snapshot existed: no delivery_* values.
        return Order::query()->create([
            'number' => 'LEGACY-1', 'customer_id' => $customer->id, 'customer_address_id' => $addressId,
            'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending',
        ]);
    }

    private function approvedDriver(): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

        return $driver;
    }
}
