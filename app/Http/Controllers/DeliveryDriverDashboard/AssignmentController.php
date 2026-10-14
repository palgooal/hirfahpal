<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\OrderStatusHistory;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user('delivery_driver');
        $data = $request->validate([
            'status' => ['nullable', 'in:assigned,accepted,picked_up,out_for_delivery,delivered,failed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            DeliveryAssignment::query()
                ->with(['vendorOrder.order.customer', 'vendorOrder.order.address', 'vendorOrder.vendor.profile', 'vendorOrder.items.product.primaryImage'])
                ->where('delivery_driver_id', $driver->id)
                ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
                ->latest()
                ->paginate($data['per_page'] ?? 12)
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
            $this->recordHistory($lockedAssignment, 'delivery_accepted');
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
            $this->recordHistory($lockedAssignment, 'out_for_delivery');
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
            $this->recordHistory($lockedAssignment, 'delivered');

            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($assignment, 'Delivery marked delivered. Awaiting customer receipt confirmation.');
    }

    public function markFailed(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate(['failure_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($assignment, $data): void {
            $lockedAssignment = DeliveryAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAssignment->status, ['accepted', 'picked_up', 'out_for_delivery'], true), 422, 'Only active deliveries can be reported as failed.');

            $lockedAssignment->update([
                'status' => 'failed',
                'failed_at' => $lockedAssignment->failed_at ?? now(),
                'failure_reason' => $data['failure_reason'],
            ]);
            $this->recordHistory($lockedAssignment, 'delivery_failed', $data['failure_reason']);
        });

        return $this->assignmentResponse($assignment, 'Delivery failure reported.');
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

    private function recordHistory(DeliveryAssignment $assignment, string $status, ?string $note = null): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $assignment->vendorOrder->order_id,
            'vendor_order_id' => $assignment->vendor_order_id,
            'status' => $status,
            'actor_type' => 'delivery_driver',
            'actor_id' => $assignment->delivery_driver_id,
            'note' => $note,
        ]);
    }
}
