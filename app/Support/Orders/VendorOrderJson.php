<?php

namespace App\Support\Orders;

use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\VendorOrder;
use App\Support\Storefront\CustomerProductJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * E2-MVP batch 05 (VEN-BE-023, the vendor order show endpoint only): a vendor order as its vendor's JSON publishes it,
 * built field by field so a model's later columns are never published by default. The vendor gets what fulfilling the
 * order needs: their order and its amounts, the items and stock reservations, the parent order's number and status, the
 * customer's name, and the delivery's progress with the driver's name and phone for the pickup. Never the customer's
 * email or account, the delivery address (recipient, phone, location, notes), the parent order's totals (other vendors'
 * share) or payment data, the driver's email, approval or account data, the cash collected on delivery, the cash
 * custody, or internal admin references (assigned_by, return_confirmed_by) and the drivers' notes.
 *
 * The keys and nesting of the former serialization are kept, and a relation appears only when it was loaded.
 */
class VendorOrderJson
{
    private const VENDOR_ORDER = ['id', 'number', 'order_id', 'vendor_id', 'delivery_driver_id', 'status', 'subtotal', 'delivery_fee',
        'commission_amount', 'total', 'vendor_response_due_at', 'accepted_at', 'rejected_at', 'rejection_reason', 'ready_at', 'delivered_at',
        'customer_receipt_confirmed_at', 'completed_at', 'created_at', 'updated_at'];

    /**
     * The parent order's number and status only (VEN-BE-023 decisions): never its totals (the other vendors' share), the
     * payment method or status (the vendor is not a party to the payment or its collection), the customer or saved
     * address references, or the delivery address (the vendor hands the order to the delivery; the driver alone deals with
     * the recipient, SRS §2.2-2.3; ORD-BE-001's vendor part).
     */
    private const ORDER = ['id', 'number', 'status', 'created_at', 'updated_at'];

    private const CUSTOMER = ['id', 'name'];

    private const ITEM = ['id', 'order_id', 'vendor_order_id', 'product_id', 'product_name', 'product_sku', 'quantity', 'unit_price', 'line_total',
        'created_at', 'updated_at'];

    /** The delivery's progress; never assigned_by, notes, the drivers' texts, return_confirmed_by or the cash collected. */
    private const ASSIGNMENT = ['id', 'vendor_order_id', 'delivery_driver_id', 'status', 'assigned_at', 'picked_up_at', 'delivered_at', 'failed_at',
        'failure_code', 'returned_at', 'return_declared_at', 'created_at', 'updated_at'];

    /** Who picks the order up; never the email, account, verification or approval data. */
    private const DRIVER = ['id', 'name', 'phone'];

    private const RESERVATION = ['id', 'product_id', 'order_id', 'vendor_order_id', 'order_item_id', 'quantity', 'status', 'reserved_at', 'released_at',
        'committed_at', 'created_at', 'updated_at'];

    /** @return array<string, mixed> */
    public static function vendorOrder(VendorOrder $vendorOrder): array
    {
        return self::only($vendorOrder, self::VENDOR_ORDER) + self::relations($vendorOrder, [
            'order' => fn (?Order $order) => $order ? self::order($order) : null,
            'items' => fn ($items) => $items->map(fn (OrderItem $item) => self::item($item))->all(),
            'deliveryAssignment' => fn (?DeliveryAssignment $assignment) => $assignment ? self::assignment($assignment) : null,
            'deliveryDriver' => fn (?DeliveryDriver $driver) => $driver ? self::only($driver, self::DRIVER) : null,
            'stockReservations' => fn ($reservations) => $reservations->map(fn (StockReservation $reservation) => self::reservation($reservation))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function order(Order $order): array
    {
        // No delivery address, whatever was loaded (VEN-BE-023: the vendor part of ORD-BE-001 no longer publishes it).
        return self::only($order, self::ORDER) + self::relations($order, [
            'customer' => fn (?Customer $customer) => $customer ? self::only($customer, self::CUSTOMER) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function item(OrderItem $item): array
    {
        return self::only($item, self::ITEM) + self::relations($item, [
            'product' => fn (?Product $product) => $product ? CustomerProductJson::reference($product) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function assignment(DeliveryAssignment $assignment): array
    {
        return self::only($assignment, self::ASSIGNMENT) + self::relations($assignment, [
            'deliveryDriver' => fn (?DeliveryDriver $driver) => $driver ? self::only($driver, self::DRIVER) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function reservation(StockReservation $reservation): array
    {
        return self::only($reservation, self::RESERVATION) + self::relations($reservation, [
            'product' => fn (?Product $product) => $product ? CustomerProductJson::reference($product) : null,
        ]);
    }

    /** @return array<string, mixed> the allowed attributes, as the model serializes them (casts and date format kept) */
    private static function only(Model $model, array $keys): array
    {
        return Arr::only($model->attributesToArray(), $keys);
    }

    /**
     * The given relations that the model has loaded, under their serialized (snake case) keys; one not loaded is left out.
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
