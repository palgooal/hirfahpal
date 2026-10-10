<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\RejectVendorOrderRequest;
use App\Models\VendorOrder;
use App\Support\Inventory\StockCommitmentException;
use App\Support\Inventory\StockReservationLifecycle;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $orders = VendorOrder::query()
            ->with(['order.customer', 'order.address', 'items.product.primaryImage', 'deliveryAssignment.deliveryDriver.profile'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('number', 'like', '%'.$search.'%'))
                        ->orWhereHas('order.customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->boolean('needs_response'), fn ($query) => $query->where('status', 'pending'))
            ->latest()
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        return response()->json([
            'orders' => $orders,
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        return response()->json([
            'order' => $vendorOrder->load([
                'order.customer',
                'order.address',
                'items.product.images',
                'deliveryAssignment.deliveryDriver.profile',
                'deliveryDriver.profile',
                'stockReservations.product',
            ]),
            'allowed_actions' => $this->allowedActions($vendorOrder),
        ]);
    }

    /**
     * Vendor Accept is the stock commitment point (VEN-BE-019): the order
     * state change and the stock commitment succeed or roll back together.
     */
    public function accept(Request $request, VendorOrder $vendorOrder, StockReservationLifecycle $stock, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        try {
            DB::transaction(function () use ($vendorOrder, $stock, $orders): void {
                // Re-read under lock so a repeated or concurrent Accept sees the new state.
                $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->status === 'pending', 422, 'Only pending orders can be accepted.');

                $stock->commitForVendorOrder($lockedOrder);

                $lockedOrder->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);

                $orders->syncParentForVendorOrder($lockedOrder);
            });
        } catch (StockCommitmentException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->orderResponse($vendorOrder, 'Order accepted successfully.');
    }

    public function reject(RejectVendorOrderRequest $request, VendorOrder $vendorOrder, StockReservationLifecycle $stock, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($request, $vendorOrder, $stock, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === 'pending', 422, 'Only pending orders can be rejected.');

            $lockedOrder->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $request->validated('rejection_reason'),
            ]);

            // Release only: physical stock was never decremented for these reservations.
            $stock->releaseForVendorOrder($lockedOrder);
            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order rejected successfully.');
    }

    public function markPreparing(Request $request, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($vendorOrder, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['accepted', 'preparing'], true), 422, 'Only accepted orders can move to preparing.');

            $lockedOrder->update([
                'status' => 'preparing',
                'accepted_at' => $lockedOrder->accepted_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order marked as preparing.');
    }

    public function markReady(Request $request, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($vendorOrder, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['accepted', 'preparing', 'ready_for_delivery'], true), 422, 'Only accepted or preparing orders can be marked ready.');

            $lockedOrder->update([
                'status' => 'ready_for_delivery',
                'accepted_at' => $lockedOrder->accepted_at ?? now(),
                'ready_at' => $lockedOrder->ready_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order marked as ready for delivery.');
    }

    private function authorizeOrder(Request $request, VendorOrder $vendorOrder): void
    {
        abort_unless((int) $vendorOrder->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    private function orderResponse(VendorOrder $vendorOrder, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'order' => $vendorOrder->fresh(['order.customer', 'items.product.primaryImage']),
            'allowed_actions' => $this->allowedActions($vendorOrder->fresh()),
        ]);
    }

    private function allowedActions(VendorOrder $vendorOrder): array
    {
        return [
            'accept' => $vendorOrder->status === 'pending',
            'reject' => $vendorOrder->status === 'pending',
            'mark_preparing' => in_array($vendorOrder->status, ['accepted', 'preparing'], true),
            'mark_ready' => in_array($vendorOrder->status, ['accepted', 'preparing', 'ready_for_delivery'], true),
        ];
    }

    private function statuses(): array
    {
        return [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'preparing' => 'Preparing',
            'ready_for_delivery' => 'Ready for delivery',
            'assigned' => 'Assigned',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
    }
}
