<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 403);

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
        abort_unless($vendorOrder->status === 'completed', 422, 'Vendor order is not ready for review.');
        $reviews = [];

        if (! empty($data['product_rating']) && ! empty($data['product_id'])) {
            abort_unless(
                $vendorOrder->items()->where('product_id', $data['product_id'])->exists(),
                422,
                'Product was not received in this vendor order.'
            );

            $reviews[] = $order->reviews()->updateOrCreate([
                'vendor_order_id' => $vendorOrder->id,
                'product_id' => $data['product_id'],
                'reviewable_type' => 'product',
            ], [
                'customer_id' => $order->customer_id,
                'rating' => $data['product_rating'],
                'comment' => $data['product_comment'] ?? null,
            ]);
        }

        $reviews[] = $order->reviews()->updateOrCreate([
            'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $vendorOrder->vendor_id,
            'reviewable_type' => 'vendor',
        ], [
            'customer_id' => $order->customer_id,
            'rating' => $data['vendor_rating'],
            'comment' => $data['vendor_comment'] ?? null,
        ]);

        if (! empty($data['driver_rating']) && $vendorOrder->delivery_driver_id) {
            $reviews[] = $order->reviews()->updateOrCreate([
                'vendor_order_id' => $vendorOrder->id,
                'delivery_driver_id' => $vendorOrder->delivery_driver_id,
                'reviewable_type' => 'delivery_driver',
            ], [
                'customer_id' => $order->customer_id,
                'rating' => $data['driver_rating'],
                'comment' => $data['driver_comment'] ?? null,
            ]);
        }

        return $request->expectsJson()
            ? response()->json($reviews, 201)
            : back()->with('success', 'تم حفظ تقييمك بنجاح.');
    }
}
