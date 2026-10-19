<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $orders = Order::query()
            ->with(['vendorOrders.vendor.profile', 'vendorOrders.items', 'items', 'reviews'])
            ->where('customer_id', $request->user('customer')->id)
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 10))));

        return $request->expectsJson() ? response()->json($orders) : view('pages.customer-dashboard', [
            'user' => $request->user('customer'), 'latestOrder' => $orders->first(), 'displayOrders' => $orders,
            'favoriteCount' => $request->user('customer')->favorites()->count(), 'orderPage' => true,
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse|View
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 403);

        $order->load([
            'address.governorate',
            'address.city',
            'items.product.primaryImage',
            'payment',
            'vendorOrders.vendor.profile',
            'vendorOrders.deliveryDriver',
            'vendorOrders.deliveryAssignment',
            'vendorOrders.items.product.primaryImage',
            'reviews',
        ]);
        // ORD-BE-001: the order's delivery address is the checkout snapshot.
        $order->exposeDeliveryAddress();

        return $request->expectsJson() ? response()->json($order) : view('pages.customer-dashboard', [
            'user' => $request->user('customer'), 'latestOrder' => $order, 'displayOrders' => collect([$order]),
            'favoriteCount' => $request->user('customer')->favorites()->count(), 'orderPage' => true,
        ]);
    }

    public function confirmReceipt(Request $request, Order $order, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse|RedirectResponse
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

        $freshOrder = $order->fresh([
            'payment',
            'vendorOrders.deliveryAssignment',
            'vendorOrders.items.product.primaryImage',
            'reviews',
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Receipt confirmed.', 'order' => $freshOrder])
            : back()->with('success', 'تم تأكيد استلام الطلب بنجاح.');
    }
}
