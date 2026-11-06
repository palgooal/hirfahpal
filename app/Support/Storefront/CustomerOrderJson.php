<?php

namespace App\Support\Storefront;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * JSON-EXPOSURE-01B: a customer's own order as its JSON responses publish it (customer.orders.index / show,
 * confirm-receipt and checkout), built field by field. The existing keys and nesting are kept, but each record carries
 * only its allowed attributes, and a relation appears only when the endpoint loaded it (as before). Never the vendor
 * or driver account, the store's commercial or moderation records, the platform commission, the payment gateway data,
 * the dispatch records, internal deadlines or reasons, SKUs or stock, so an attribute added to a model later is not
 * published by default.
 */
class CustomerOrderJson
{
    /** The customer's own order: identifiers, statuses, totals, their note and timestamps. */
    private const ORDER = ['id', 'number', 'customer_id', 'customer_address_id', 'status', 'payment_method', 'payment_status',
        'subtotal', 'delivery_total', 'discount_total', 'grand_total', 'notes', 'completed_at', 'created_at', 'updated_at'];

    /** Never commission_amount, vendor_response_due_at, rejection_reason or delivery_driver_id. */
    private const VENDOR_ORDER = ['id', 'number', 'order_id', 'vendor_id', 'status', 'subtotal', 'delivery_fee', 'total',
        'accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at', 'created_at', 'updated_at'];

    /** The purchased quantity and prices; never product_sku. */
    private const ITEM = ['id', 'order_id', 'vendor_order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total',
        'created_at', 'updated_at'];

    /** Never provider, provider_reference or payload. */
    private const PAYMENT = ['id', 'order_id', 'method', 'status', 'amount', 'refunded_amount', 'paid_at', 'created_at', 'updated_at'];

    /** The delivery's progress; never assigned_by, notes, failure_reason or the internal identifiers. */
    private const DELIVERY_ASSIGNMENT = ['status', 'assigned_at', 'picked_up_at', 'delivered_at', 'failed_at'];

    /** The customer's own review; never customer_id, vendor_id or delivery_driver_id. */
    private const REVIEW = ['id', 'order_id', 'vendor_order_id', 'product_id', 'reviewable_type', 'rating', 'comment', 'status',
        'created_at', 'updated_at'];

    /**
     * @param  bool  $withDeliveryAddress  the order detail only: its delivery address snapshot and the deprecated
     *                                     "address" key, exactly as Order::exposeDeliveryAddress() prepared them (ORD-BE-001)
     * @return array<string, mixed>
     */
    public static function order(Order $order, bool $withDeliveryAddress = false): array
    {
        $data = self::only($order, self::ORDER);

        if ($withDeliveryAddress) {
            $data['delivery_address'] = $order->delivery_address;
            $data['address'] = $order->getRelation('address')->toArray();
        }

        return $data + self::relations($order, [
            'items' => fn ($items) => $items->map(fn (OrderItem $item) => self::item($item))->all(),
            'payment' => fn (?Payment $payment) => $payment ? self::only($payment, self::PAYMENT) : null,
            'vendorOrders' => fn ($vendorOrders) => $vendorOrders->map(fn (VendorOrder $vendorOrder) => self::vendorOrder($vendorOrder))->all(),
            'reviews' => fn ($reviews) => $reviews->map(fn (Review $review) => self::review($review))->all(),
        ]);
    }

    /** @return array<string, mixed> one of the customer's reviews (also the review store response) */
    public static function review(Review $review): array
    {
        return self::only($review, self::REVIEW);
    }

    /** @return array<string, mixed> */
    private static function vendorOrder(VendorOrder $vendorOrder): array
    {
        return self::only($vendorOrder, self::VENDOR_ORDER) + self::relations($vendorOrder, [
            'vendor' => fn (?Vendor $vendor) => CustomerProductJson::vendor($vendor),
            // The assigned driver's display name only.
            'deliveryDriver' => fn (?DeliveryDriver $driver) => $driver ? ['name' => $driver->name] : null,
            'deliveryAssignment' => fn (?DeliveryAssignment $assignment) => $assignment ? self::only($assignment, self::DELIVERY_ASSIGNMENT) : null,
            'items' => fn ($items) => $items->map(fn (OrderItem $item) => self::item($item))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function item(OrderItem $item): array
    {
        return self::only($item, self::ITEM) + self::relations($item, [
            'product' => fn (?Product $product) => $product ? CustomerProductJson::reference($product) : null,
        ]);
    }

    /** @return array<string, mixed> the allowed attributes, as the model serializes them (casts and date format kept) */
    private static function only(Model $model, array $keys): array
    {
        return Arr::only($model->attributesToArray(), $keys);
    }

    /**
     * The given relations that the model has loaded, under their serialized (snake case) keys; one not loaded is left
     * out, as before, so no query is run here.
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
