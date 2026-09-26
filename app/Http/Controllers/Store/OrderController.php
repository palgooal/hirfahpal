<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Order::query()
                ->with('vendorOrders.vendor.profile')
                ->where('customer_id', $request->user('customer')->id)
                ->latest()
                ->paginate($request->integer('per_page', 10))
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 403);

        return response()->json($order->load([
            'address',
            'items.product.primaryImage',
            'payment',
            'vendorOrders.vendor.profile',
            'vendorOrders.deliveryDriver',
            'vendorOrders.deliveryAssignment',
            'vendorOrders.items.product.primaryImage',
        ]));
    }
}
