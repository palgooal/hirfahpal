<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user('delivery_driver');

        return response()->json(
            DeliveryAssignment::query()
                ->with(['vendorOrder.order.customer', 'vendorOrder.order.address', 'vendorOrder.vendor.profile', 'vendorOrder.items.product.primaryImage'])
                ->where('delivery_driver_id', $driver->id)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->paginate($request->integer('per_page', 12))
        );
    }

    public function show(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);

        return response()->json($assignment->load([
            'vendorOrder.order.customer',
            'vendorOrder.order.address',
            'vendorOrder.vendor.profile',
            'vendorOrder.items.product.primaryImage',
        ]));
    }

    public function accept(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($assignment): void {
            $lockedAssignment = DeliveryAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedAssignment->status === 'assigned', 422, 'Only assigned deliveries can be accepted.');

            $lockedAssignment->update(['status' => 'accepted']);
        });

        return $this->assignmentResponse($assignment, 'Delivery assignment accepted.');
    }

    public function pickUp(Request $request, DeliveryAssignment $assignment, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($assignment, $orders): void {
            $lockedAssignment = DeliveryAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAssignment->status, ['assigned', 'accepted', 'picked_up'], true), 422, 'Only assigned deliveries can be picked up.');

            $lockedAssignment->update([
                'status' => 'picked_up',
                'picked_up_at' => $lockedAssignment->picked_up_at ?? now(),
            ]);

            $vendorOrder = $lockedAssignment->vendorOrder()->lockForUpdate()->firstOrFail();
            abort_unless(in_array($vendorOrder->status, ['assigned', 'out_for_delivery'], true), 422, 'Only assigned vendor orders can be picked up.');

            $vendorOrder->update(['status' => 'out_for_delivery']);
            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($assignment, 'Delivery picked up.');
    }

    public function markDelivered(Request $request, DeliveryAssignment $assignment, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($assignment, $orders): void {
            $lockedAssignment = DeliveryAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAssignment->status, ['picked_up', 'out_for_delivery'], true), 422, 'Only picked up deliveries can be marked delivered.');

            $lockedAssignment->update([
                'status' => 'delivered',
                'delivered_at' => $lockedAssignment->delivered_at ?? now(),
            ]);

            $vendorOrder = $lockedAssignment->vendorOrder()->lockForUpdate()->firstOrFail();
            abort_unless($vendorOrder->status === 'out_for_delivery', 422, 'Only out for delivery vendor orders can be marked delivered.');

            $vendorOrder->update([
                'status' => 'delivered',
                'delivered_at' => $vendorOrder->delivered_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($assignment, 'Delivery marked delivered. Awaiting customer receipt confirmation.');
    }

    private function authorizeAssignment(Request $request, DeliveryAssignment $assignment): void
    {
        abort_unless((int) $assignment->delivery_driver_id === (int) $request->user('delivery_driver')->id, 404);
    }

    private function assignmentResponse(DeliveryAssignment $assignment, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'assignment' => $assignment->fresh([
                'vendorOrder.order.payment',
                'vendorOrder.order.customer',
                'vendorOrder.vendor.profile',
                'vendorOrder.deliveryAssignment',
            ]),
        ]);
    }
}
