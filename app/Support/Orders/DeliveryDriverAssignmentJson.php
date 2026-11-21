<?php

namespace App\Support\Orders;

use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryDriverProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Storefront\CustomerProductJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * DRIVER-E1: a delivery assignment as the driver's JSON responses publish it (assignments index / show, overview and
 * every assignment action), built field by field. SRS §2.3: the driver sees what the delivery needs (the recipient,
 * their phone and the checkout delivery address, the items and quantities, the pickup store and the mission's
 * progress), never the platform commission, the store's moderation records, the customer's email, the payment
 * gateway data or the prices and totals. The keys and nesting are kept, and a relation appears only when the endpoint
 * loaded it, so an attribute added to a model later is not published by default.
 */
class DeliveryDriverAssignmentJson
{
    /** Never assigned_by (the assigning admin) or return_confirmed_by (the admin who closed the custody). */
    private const ASSIGNMENT = ['id', 'vendor_order_id', 'delivery_driver_id', 'status', 'assigned_at', 'picked_up_at',
        'delivered_at', 'failed_at', 'failure_code', 'failure_reason', 'returned_at', 'declined_at', 'decline_code', 'decline_note',
        'return_declared_at', 'notes', 'cod_amount', 'cod_collected_amount', 'cod_collected_at', 'created_at', 'updated_at'];

    /** Never commission_amount, rejection_reason, vendor_response_due_at or the money columns. */
    private const VENDOR_ORDER = ['id', 'number', 'order_id', 'vendor_id', 'delivery_driver_id', 'status', 'ready_at',
        'delivered_at', 'customer_receipt_confirmed_at', 'completed_at', 'created_at', 'updated_at'];

    /** Identifiers, statuses and the payment method; never the totals (the cash to collect is not defined per vendor order). */
    private const ORDER = ['id', 'number', 'customer_id', 'customer_address_id', 'status', 'payment_method', 'payment_status',
        'created_at', 'updated_at'];

    /** Whether and how the order is paid; never amount, refunded_amount, provider, provider_reference or payload. */
    private const PAYMENT = ['method', 'status', 'paid_at'];

    /** What is handed over; never product_sku or the prices. */
    private const ITEM = ['id', 'vendor_order_id', 'product_id', 'product_name', 'quantity'];

    /** The customer's display name; their phone for the delivery is the address snapshot's, never the account email. */
    private const CUSTOMER = ['id', 'name'];

    /** Where the order is picked up; never commission_rate, approval or rejection data, or the store's media. */
    private const VENDOR_PROFILE = ['store_name', 'address_line', 'governorate_id', 'city_id'];

    /** The driver's own account; never the password, remember token or verification and login timestamps. */
    private const DRIVER = ['id', 'name', 'email', 'phone', 'avatar', 'status', 'created_at', 'updated_at'];

    /** The driver's own operational profile; never approval_status, approved_at, approved_by or rejection_reason. */
    private const DRIVER_PROFILE = ['id', 'delivery_driver_id', 'governorate_id', 'city_id', 'vehicle_type', 'vehicle_plate',
        'is_available', 'created_at', 'updated_at'];

    private const PLACE = ['id', 'name'];

    /** @return array<string, mixed> */
    public static function assignment(DeliveryAssignment $assignment): array
    {
        // E2-MVP batch 02: the delivery address only while the task is active (DeliveryDriverAssignmentCard::ACTIVE_STATUSES).
        $active = DeliveryDriverAssignmentCard::isActive($assignment);

        return self::only($assignment, self::ASSIGNMENT) + self::relations($assignment, [
            'vendorOrder' => fn (?VendorOrder $vendorOrder) => $vendorOrder ? self::vendorOrder($vendorOrder, $active) : null,
        ]);
    }

    /** @return array<string, mixed> the signed-in driver as the overview shows them */
    public static function driver(DeliveryDriver $driver): array
    {
        return self::only($driver, self::DRIVER) + self::relations($driver, [
            'profile' => fn (?DeliveryDriverProfile $profile) => $profile ? self::only($profile, self::DRIVER_PROFILE) + self::places($profile) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function vendorOrder(VendorOrder $vendorOrder, bool $active): array
    {
        return self::only($vendorOrder, self::VENDOR_ORDER) + self::relations($vendorOrder, [
            'order' => fn (?Order $order) => $order ? self::order($order, $active) : null,
            'vendor' => fn (?Vendor $vendor) => $vendor ? self::vendor($vendor) : null,
            'items' => fn ($items) => $items->map(fn (OrderItem $item) => self::item($item))->all(),
            // The current assignment, without its vendor order again.
            'deliveryAssignment' => fn (?DeliveryAssignment $assignment) => $assignment ? self::only($assignment, self::ASSIGNMENT) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function order(Order $order, bool $active): array
    {
        $data = self::only($order, self::ORDER);

        // ORD-BE-001: only when the endpoint exposed it (index, show, overview), exactly as exposeDeliveryAddress() prepared
        // it; E2-MVP batch 02: and only for an active task (a finished one keeps no recipient address or phone).
        if ($active && in_array('delivery_address', $order->getAppends(), true)) {
            $data['delivery_address'] = $order->delivery_address;
            $data['address'] = $order->getRelation('address')->toArray();
        }

        return $data + self::relations($order, [
            'customer' => fn (?Customer $customer) => $customer ? self::only($customer, self::CUSTOMER) : null,
            'payment' => fn (?Payment $payment) => $payment ? self::only($payment, self::PAYMENT) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function vendor(Vendor $vendor): array
    {
        return ['id' => $vendor->id, 'name' => $vendor->name] + self::relations($vendor, [
            'profile' => fn ($profile) => $profile ? self::only($profile, self::VENDOR_PROFILE) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function item(OrderItem $item): array
    {
        return self::only($item, self::ITEM) + self::relations($item, [
            'product' => fn (?Product $product) => $product ? CustomerProductJson::reference($product) : null,
        ]);
    }

    /** @return array<string, mixed> the profile's loaded governorate and city, by id and name */
    private static function places(DeliveryDriverProfile $profile): array
    {
        return self::relations($profile, [
            'governorate' => fn ($place) => $place ? self::only($place, self::PLACE) : null,
            'city' => fn ($place) => $place ? self::only($place, self::PLACE) : null,
        ]);
    }

    /** @return array<string, mixed> the allowed attributes, as the model serializes them (casts and date format kept) */
    private static function only(Model $model, array $keys): array
    {
        return Arr::only($model->attributesToArray(), $keys);
    }

    /**
     * The given relations that the model has loaded, under their serialized (snake case) keys; one not loaded is left
     * out, so no query is run here.
     *
     * @param  array<string, callable>  $relations
     * @return array<string, mixed>
     */
    private static function relations(Model $model, array $relations): array
    {
        $data = [];
        foreach ($relations as $relation => $serialize) {
            if ($model->relationLoaded($relation)) {
                $data[Str::snake($relation)] = $serialize($model->getRelation($relation));
            }
        }

        return $data;
    }
}
