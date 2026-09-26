<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 403);
        abort_unless(in_array($order->status, ['completed', 'partially_delivered'], true), 422, 'Order is not ready for review.');

        $data = $request->validate([
            'vendor_order_id' => ['required', 'integer', 'exists:vendor_orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'product_comment' => ['nullable', 'string', 'max:1000'],
            'vendor_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'vendor_comment' => ['nullable', 'string', 'max:1000'],
            'driver_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'driver_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendorOrder = $order->vendorOrders()->findOrFail($data['vendor_order_id']);
        $reviews = [];

        if (! empty($data['product_rating']) && ! empty($data['product_id'])) {
            $reviews[] = $order->reviews()->create([
                'customer_id' => $order->customer_id,
                'vendor_order_id' => $vendorOrder->id,
                'product_id' => $data['product_id'],
                'reviewable_type' => 'product',
                'rating' => $data['product_rating'],
                'comment' => $data['product_comment'] ?? null,
            ]);
        }

        $reviews[] = $order->reviews()->create([
            'customer_id' => $order->customer_id,
            'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $vendorOrder->vendor_id,
            'reviewable_type' => 'vendor',
            'rating' => $data['vendor_rating'],
            'comment' => $data['vendor_comment'] ?? null,
        ]);

        if (! empty($data['driver_rating']) && $vendorOrder->delivery_driver_id) {
            $reviews[] = $order->reviews()->create([
                'customer_id' => $order->customer_id,
                'vendor_order_id' => $vendorOrder->id,
                'delivery_driver_id' => $vendorOrder->delivery_driver_id,
                'reviewable_type' => 'delivery_driver',
                'rating' => $data['driver_rating'],
                'comment' => $data['driver_comment'] ?? null,
            ]);
        }

        return response()->json($reviews, 201);
    }
}
