<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function confirmReceipt(Request $request, Order $order, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 403);
        abort_unless((int) $vendorOrder->order_id === (int) $order->id, 404);

        DB::transaction(function () use ($vendorOrder, $orders): void {
            $lockedVendorOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVendorOrder->status === 'delivered', 422, 'Only delivered vendor orders can be confirmed.');

            $lockedVendorOrder->update([
                'status' => 'completed',
                'customer_receipt_confirmed_at' => $lockedVendorOrder->customer_receipt_confirmed_at ?? now(),
                'completed_at' => $lockedVendorOrder->completed_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($lockedVendorOrder);
        });

        return response()->json([
            'message' => 'Receipt confirmed.',
            'order' => $order->fresh([
                'payment',
                'vendorOrders.deliveryAssignment',
                'vendorOrders.items.product.primaryImage',
            ]),
        ]);
    }
}
