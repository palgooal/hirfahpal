<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\VendorOrder;
use App\Support\Orders\DeliveryDriverAssignmentJson;
use App\Support\Orders\DeliveryReasons;
use App\Support\Orders\DriverCash;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user('delivery_driver');
        $data = $request->validate([
            'status' => ['nullable', 'in:assigned,accepted,picked_up,out_for_delivery,delivered,failed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $assignments = DeliveryAssignment::query()
            ->with(['vendorOrder.order.customer', 'vendorOrder.order.address.governorate', 'vendorOrder.order.address.city', 'vendorOrder.vendor.profile', 'vendorOrder.items.product.primaryImage'])
            ->where('delivery_driver_id', $driver->id)
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->latest()
            ->paginate($data['per_page'] ?? 12);

        // ORD-BE-001: the order's delivery address is the checkout snapshot.
        $assignments->getCollection()->each(fn (DeliveryAssignment $assignment) => $assignment->vendorOrder?->order?->exposeDeliveryAddress());

        // DRIVER-E1: only the fields the delivery needs (SRS §2.3), same pagination shape.
        return response()->json($assignments->through(fn (DeliveryAssignment $assignment) => DeliveryDriverAssignmentJson::assignment($assignment)));
    }

    public function show(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($request, $assignment);

        $assignment->load([
            'vendorOrder.order.customer',
            'vendorOrder.order.address.governorate',
            'vendorOrder.order.address.city',
            'vendorOrder.vendor.profile',
            'vendorOrder.items.product.primaryImage',
        ]);
        // ORD-BE-001: the order's delivery address is the checkout snapshot.
        $assignment->vendorOrder?->order?->exposeDeliveryAddress();

        return response()->json(DeliveryDriverAssignmentJson::assignment($assignment));
    }

    public function accept(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($request, $assignment): void {
            [, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless($lockedAssignment->status === 'assigned', 422, 'Only assigned deliveries can be accepted.');

            $lockedAssignment->update(['status' => 'accepted']);
            $this->recordHistory($lockedAssignment, 'delivery_accepted');
        });

        return $this->assignmentResponse($request, $assignment, 'Delivery assignment accepted.');
    }

    public function pickUp(Request $request, DeliveryAssignment $assignment, OrderStatusLifecycle $orders): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($request, $assignment, $orders): void {
            [$vendorOrder, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless(in_array($lockedAssignment->status, ['assigned', 'accepted', 'picked_up'], true), 422, 'Only assigned deliveries can be picked up.');
            abort_unless(in_array($vendorOrder->status, ['assigned', 'out_for_delivery'], true), 422, 'Only assigned vendor orders can be picked up.');

            $lockedAssignment->update([
                'status' => 'picked_up',
                'picked_up_at' => $lockedAssignment->picked_up_at ?? now(),
            ]);

            $vendorOrder->update(['status' => 'out_for_delivery']);
            $this->recordHistory($lockedAssignment, 'out_for_delivery');
            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($request, $assignment, 'Delivery picked up.');
    }

    public function markDelivered(Request $request, DeliveryAssignment $assignment, OrderStatusLifecycle $orders): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);
        // E2-MVP batch 05: a cash-on-delivery order needs the amount the driver collected; it is checked against the
        // captured amount under the locks below.
        $data = $request->validate([
            'collected_amount' => ['nullable', 'regex:'.DriverCash::AMOUNT_PATTERN],
        ]);

        DB::transaction(function () use ($request, $assignment, $orders, $data): void {
            [$vendorOrder, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless(in_array($lockedAssignment->status, ['picked_up', 'out_for_delivery'], true), 422, 'Only picked up deliveries can be marked delivered.');
            abort_unless($vendorOrder->status === 'out_for_delivery', 422, 'Only out for delivery vendor orders can be marked delivered.');

            // Vendor order → assignment → parent order: the order of the status sync below.
            $parent = Order::query()->whereKey($vendorOrder->order_id)->lockForUpdate()->first(['id', 'payment_method', 'payment_status']);
            $collection = [];

            if (DriverCash::isCashOnDelivery($parent)) {
                // The captured amount; an attempt assigned before batch 05 has none, so the vendor order total is used.
                $due = DriverCash::cents($lockedAssignment->cod_amount ?? $vendorOrder->total);
                $collected = array_key_exists('collected_amount', $data) && $data['collected_amount'] !== null ? DriverCash::cents($data['collected_amount']) : null;

                if ($collected !== $due) {
                    throw ValidationException::withMessages([
                        'collected_amount' => 'يجب تأكيد تحصيل المبلغ المستحق كاملًا ('.DriverCash::format($due).'). إن لم يُحصَّل كاملًا، سجّل تعذّر التوصيل بسبب «تعذّر الدفع».',
                    ]);
                }
                // Never twice for one vendor order, whatever the attempt (checked under the vendor order lock).
                abort_if(DeliveryAssignment::query()->where('vendor_order_id', $vendorOrder->id)->whereNotNull('cod_collected_at')->exists(), 422, 'The cash of this order was already collected.');

                $collection = ['cod_collected_amount' => DriverCash::format($due), 'cod_collected_at' => now()];
            } elseif (! DriverCash::isPrepaid($parent)) {
                abort(422, 'This online order is not marked paid, so it cannot be delivered.');
            }

            $lockedAssignment->update([
                'status' => 'delivered',
                'delivered_at' => $lockedAssignment->delivered_at ?? now(),
                ...$collection,
            ]);

            $vendorOrder->update([
                'status' => 'delivered',
                'delivered_at' => $vendorOrder->delivered_at ?? now(),
            ]);
            $this->recordHistory($lockedAssignment, 'delivered');

            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($request, $assignment, 'Delivery marked delivered. Awaiting customer receipt confirmation.');
    }

    /**
     * E2-MVP batch 03: the driver declines a task they have not accepted yet, with a reason from the list ("other" needs a
     * text). The attempt is kept (cancelled, marked declined_at) and the vendor order goes back to Ready for Delivery for
     * the admin to assign again by hand; nothing is reassigned automatically.
     */
    public function decline(Request $request, DeliveryAssignment $assignment, OrderStatusLifecycle $orders): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'decline_code' => ['required', 'string', Rule::in(array_keys(DeliveryReasons::DECLINE))],
            'decline_note' => ['nullable', 'string', 'max:1000', 'required_if:decline_code,'.DeliveryReasons::OTHER],
        ]);
        $note = filled($data['decline_note'] ?? null) ? trim($data['decline_note']) : null;

        DB::transaction(function () use ($request, $assignment, $orders, $data, $note): void {
            [$vendorOrder, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless($lockedAssignment->status === 'assigned' && $vendorOrder->status === 'assigned', 422, 'Only a task not accepted yet can be declined.');

            $lockedAssignment->update([
                'status' => 'cancelled',
                'declined_at' => now(),
                'decline_code' => $data['decline_code'],
                'decline_note' => $note,
            ]);
            $vendorOrder->update([
                'status' => 'ready_for_delivery',
                'delivery_driver_id' => null,
            ]);
            $this->recordHistory($lockedAssignment, 'delivery_declined', $this->reasonNote(DeliveryReasons::DECLINE, $data['decline_code'], $note));
            $orders->syncParentForVendorOrder($vendorOrder);
        });

        return $this->assignmentResponse($request, $assignment, 'Delivery task declined. It returns to the platform for reassignment.');
    }

    public function markFailed(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);
        // E2-MVP batch 03: a reason from the list (payment_failed when the cash was not collected in full), with an
        // optional note; "other" needs the note.
        $data = $request->validate([
            'failure_code' => ['required', 'string', Rule::in(array_keys(DeliveryReasons::FAILURE))],
            'failure_reason' => ['nullable', 'string', 'max:1000', 'required_if:failure_code,'.DeliveryReasons::OTHER],
        ]);
        $note = filled($data['failure_reason'] ?? null) ? trim($data['failure_reason']) : null;

        DB::transaction(function () use ($request, $assignment, $data, $note): void {
            [$vendorOrder, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless(in_array($lockedAssignment->status, ['accepted', 'picked_up', 'out_for_delivery'], true), 422, 'Only active deliveries can be reported as failed.');

            // Older rows created before picked_up_at existed may carry the pickup state without its timestamp.
            $hadPickedUp = $lockedAssignment->picked_up_at !== null
                || in_array($lockedAssignment->status, ['picked_up', 'out_for_delivery'], true);

            $lockedAssignment->update([
                'status' => 'failed',
                'failed_at' => $lockedAssignment->failed_at ?? now(),
                'failure_code' => $data['failure_code'],
                'failure_reason' => $note,
            ]);
            $this->recordHistory($lockedAssignment, 'delivery_failed', $this->reasonNote(DeliveryReasons::FAILURE, $data['failure_code'], $note));

            // No package has left the vendor yet: reopen the order immediately for a new assignment.
            if (! $hadPickedUp) {
                $vendorOrder->update([
                    'status' => 'ready_for_delivery',
                    'delivery_driver_id' => null,
                ]);
            }
        });

        return $this->assignmentResponse($request, $assignment, 'Delivery failure reported.');
    }

    /**
     * A failed delivery after pickup remains in the driver's custody until the platform confirms the vendor has it back.
     * E2-MVP custody fix: the driver only declares the return (return_declared_at, once); the assignment, the vendor order
     * and the custody are unchanged. The admin confirmation (VendorOrderManagementController::confirmReturn) alone closes
     * the custody and puts the order back in the assignment queue.
     */
    public function returnToVendor(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);

        DB::transaction(function () use ($request, $assignment): void {
            [$vendorOrder, $lockedAssignment] = $this->lockForDriver($request, $assignment);
            abort_unless($lockedAssignment->status === 'failed' && $vendorOrder->status === 'out_for_delivery', 422, 'Only a picked-up failed delivery can be returned.');
            abort_unless($lockedAssignment->return_declared_at === null, 422, 'The return has already been declared.');

            $lockedAssignment->update(['return_declared_at' => now()]);
            $this->recordHistory($lockedAssignment, 'return_declared');
        });

        return $this->assignmentResponse($request, $assignment, 'Return declared. The platform will confirm it with the vendor.');
    }

    private function authorizeAssignment(Request $request, DeliveryAssignment $assignment): void
    {
        abort_unless((int) $assignment->delivery_driver_id === (int) $request->user('delivery_driver')->id, 404);
    }

    /**
     * VEN-BE-020 (slice 2B-1): inside the action's transaction, locks the vendor order, then the assignment (the order
     * Admin assignment uses, so the two cannot deadlock on each other), and re-checks on the locked row that the assignment
     * still belongs to this driver: an Admin reassignment committed after the route check makes the request 404, with
     * nothing written.
     *
     * @return array{0: VendorOrder, 1: DeliveryAssignment}
     */
    private function lockForDriver(Request $request, DeliveryAssignment $assignment): array
    {
        $vendorOrder = VendorOrder::query()->whereKey($assignment->vendor_order_id)->lockForUpdate()->firstOrFail();
        $lockedAssignment = DeliveryAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
        abort_unless((int) $lockedAssignment->vendor_order_id === (int) $vendorOrder->id, 404);
        abort_unless((int) $lockedAssignment->delivery_driver_id === (int) $request->user('delivery_driver')->id, 404);
        $lockedAssignment->setRelation('vendorOrder', $vendorOrder);

        return [$vendorOrder, $lockedAssignment];
    }

    private function assignmentResponse(Request $request, DeliveryAssignment $assignment, string $message): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'message' => $message,
            'assignment' => DeliveryDriverAssignmentJson::assignment($assignment->fresh([
                'vendorOrder.order.payment',
                'vendorOrder.order.customer',
                'vendorOrder.vendor.profile',
                'vendorOrder.deliveryAssignment',
            ])),
        ]);
    }

    /** The history note of a coded reason: "code — label", then the driver's text when there is one. */
    private function reasonNote(array $reasons, string $code, ?string $text): string
    {
        return $code.' — '.DeliveryReasons::label($reasons, $code).($text !== null ? ': '.$text : '');
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
