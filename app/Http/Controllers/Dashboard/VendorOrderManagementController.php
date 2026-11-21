<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryDriverProfile;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Inventory\StockReservationLifecycle;
use App\Support\Orders\DeliveryReasons;
use App\Support\Orders\DriverCash;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VendorOrderManagementController extends Controller
{
    /**
     * VEN-BE-020 (slice 2A): the vendor order statuses a driver can be assigned from (SRS §4.3, §4.5, FR-ADM-019): Ready
     * for Delivery, plus the current reassignment from Assigned and Out for Delivery, kept for now as the only recovery
     * paths (reassignment rules and assignment history are not decided yet). Every other status is refused with 422.
     */
    public const ASSIGNABLE_STATUSES = ['ready_for_delivery', 'assigned'];

    /** VEN-BE-020 (slice 2A): statuses before Ready for Delivery, explained on the order page instead of the form. */
    public const NOT_READY_STATUSES = ['pending', 'accepted', 'preparing'];

    /** E2-MVP batch 04: the vendor order statuses an admin may cancel from (the driver has not picked the goods up). */
    public const CANCELLABLE_STATUSES = ['pending', 'accepted', 'preparing', 'ready_for_delivery', 'assigned'];

    /** E2-MVP batch 04: a delivery attempt the driver still holds before pickup. */
    private const PRE_PICKUP_ASSIGNMENT_STATUSES = ['assigned', 'accepted'];

    /** E2-MVP batch 04: a delivery attempt with the goods in the driver's hands. */
    private const IN_CUSTODY_ASSIGNMENT_STATUSES = ['picked_up', 'out_for_delivery'];

    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'vendor-orders.view');

        $vendorOrders = VendorOrder::query()
            ->with(['order.customer', 'vendor.profile', 'deliveryDriver', 'deliveryAssignment.deliveryDriver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('number', 'like', '%'.$search.'%'))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('vendor.profile', fn ($profileQuery) => $profileQuery->where('store_name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            // E2-MVP batch 03: a task declined by its driver or a failed delivery, still waiting for the admin (once the
            // order is assigned again its latest attempt is the new one, and it leaves this list).
            ->when($request->boolean('delivery_issue'), function ($query) {
                $query->where(function ($issues) {
                    $issues->where(fn ($declinedOrFailed) => $declinedOrFailed
                        ->whereIn('status', ['ready_for_delivery', 'out_for_delivery'])
                        ->whereHas('deliveryAssignment', fn ($assignment) => $assignment->where(
                            fn ($issue) => $issue->where('status', 'failed')->orWhereNotNull('declined_at')
                        )))
                        // E2-MVP batch 04: a task still with a driver who is no longer eligible (rejected or blocked).
                        ->orWhere(fn ($ineligible) => $ineligible
                            ->whereIn('status', ['assigned', 'out_for_delivery'])
                            ->whereHas('deliveryDriver', fn ($driver) => self::ineligibleDrivers($driver)));
                });
            })
            ->when($request->boolean('needs_attention'), function ($query) {
                $query->where('status', 'pending')
                    ->whereNotNull('vendor_response_due_at')
                    ->where('vendor_response_due_at', '<=', now()->addHours(3));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.vendor-orders.index', [
            'vendorOrders' => $vendorOrders,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'vendor-orders.create');

        return view('dashboard.vendor-orders.create', [
            'orders' => Order::query()->with('customer')->latest()->limit(100)->get(),
            'vendors' => Vendor::query()->with('profile')->orderBy('name')->get(),
            // E2-MVP batch 05: a vendor order created by an admin starts the lifecycle at Pending, like a checkout one.
            'statuses' => array_intersect_key($this->statuses(), ['pending' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.create');

        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'number' => ['nullable', 'string', 'max:255', 'unique:vendor_orders,number'],
            // E2-MVP batch 05: Pending only, so the order goes through the vendor, delivery and collection steps.
            'status' => ['required', Rule::in(['pending'])],
            'subtotal' => ['required', 'regex:'.DriverCash::AMOUNT_PATTERN],
            'delivery_fee' => ['nullable', 'regex:'.DriverCash::AMOUNT_PATTERN],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'regex:'.DriverCash::AMOUNT_PATTERN],
            'vendor_response_due_at' => ['nullable', 'date'],
        ]);

        // E2-MVP batch 05: the amounts must hold together, in cents, under the parent order's lock (two concurrent
        // creations for the same order are serialized and the second sees the first): total = subtotal + delivery fee,
        // the parent is still open, and its vendor orders still in force never exceed its grand total.
        $vendorOrder = DB::transaction(function () use ($data): VendorOrder {
            $parent = Order::query()->whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            $total = DriverCash::cents($data['total']);

            if (in_array($parent->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['order_id' => t('dashboard.Delivery_Ops_Parent_Closed', 'The parent order is closed, so no vendor order can be added to it.')]);
            }
            if ($total !== DriverCash::cents($data['subtotal']) + DriverCash::cents($data['delivery_fee'] ?? '0')) {
                throw ValidationException::withMessages(['total' => t('dashboard.Delivery_Ops_Total_Mismatch', 'The total must equal the subtotal plus the delivery fee.')]);
            }
            $inForce = VendorOrder::query()->where('order_id', $parent->id)->whereNotIn('status', ['rejected', 'cancelled'])->pluck('total')
                ->sum(fn ($amount) => DriverCash::cents($amount));
            if ($inForce + $total > DriverCash::cents($parent->grand_total)) {
                throw ValidationException::withMessages(['total' => t('dashboard.Delivery_Ops_Total_Exceeds_Order', 'The vendor orders of this order would exceed its grand total.')]);
            }

            return VendorOrder::create([
                'number' => ($data['number'] ?? null) ?: $this->uniqueNumber(),
                'order_id' => $parent->id,
                'vendor_id' => $data['vendor_id'],
                'status' => 'pending',
                'subtotal' => $data['subtotal'],
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'commission_amount' => $data['commission_amount'] ?? 0,
                'total' => $data['total'],
                'vendor_response_due_at' => $data['vendor_response_due_at'] ?? null,
            ]);
        });

        return redirect()
            ->route('dashboard.vendor-orders.show', $vendorOrder)
            ->with('success', t('dashboard.Vendor_order_created_successfully', 'Vendor order created successfully.'));
    }

    public function show(Request $request, VendorOrder $vendorOrder): View
    {
        $this->authorizeAbility($request, 'vendor-orders.view');

        $vendorOrder->load([
            'order.customer',
            'order.address',
            'vendor.profile',
            'items.product',
            'deliveryAssignment.deliveryDriver.profile',
            'deliveryDriver.profile',
        ]);

        $drivers = self::assignableDrivers()
            ->with('profile')
            ->orderBy('name')
            ->get();

        return view('dashboard.vendor-orders.show', [
            'vendorOrder' => $vendorOrder,
            'drivers' => $drivers,
            'statuses' => $this->statuses(),
            'failureReasons' => DeliveryReasons::FAILURE,
            // E2-MVP batch 05: an online order not marked paid cannot be assigned (the form is replaced by a notice).
            'awaitingPayment' => $vendorOrder->order !== null && ! DriverCash::isCashOnDelivery($vendorOrder->order) && ! DriverCash::isPrepaid($vendorOrder->order),
            // E2-MVP batch 04: the driver holding the order can no longer deliver (rejected or blocked).
            'driverNotEligible' => in_array($vendorOrder->status, ['assigned', 'out_for_delivery'], true)
                && $vendorOrder->deliveryDriver !== null
                && ! self::isEligible($vendorOrder->deliveryDriver),
            // E2-MVP batch 04: the admin cancellation, when there is one (reason, admin and time).
            'adminCancellation' => $vendorOrder->status === 'cancelled'
                ? OrderStatusHistory::query()->where('vendor_order_id', $vendorOrder->id)->where('status', 'cancelled_by_admin')->latest('id')->first()
                : null,
        ]);
    }

    /**
     * E2-MVP batch 04: an admin cancels a vendor order before the driver picks the goods up, with a reason. The active
     * attempt (assigned or accepted) ends in the same transaction; a pending order's stock reservations are released
     * (the vendor reject's primitive), while stock deducted at the vendor's accept is not restored (the vendor corrects
     * it by hand); nothing financial is done. Locks: vendor order, then its latest assignment, then the parent order
     * (the sync), as every delivery action. Any other state is refused with 422 and nothing written.
     */
    public function cancel(Request $request, VendorOrder $vendorOrder, StockReservationLifecycle $stock, OrderStatusLifecycle $orders): RedirectResponse
    {
        $this->authorizeAbility($request, 'orders.edit');
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:1000']]);
        $adminId = $request->user('admin')->id;

        DB::transaction(function () use ($vendorOrder, $stock, $orders, $data, $adminId): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            $assignment = DeliveryAssignment::query()->where('vendor_order_id', $lockedOrder->id)->latest('id')->lockForUpdate()->first();
            $activeAttempt = in_array($assignment?->status, self::PRE_PICKUP_ASSIGNMENT_STATUSES, true);

            // Assigned always has its pre-pickup attempt; any goods with a driver (or a state left after pickup) refuse.
            if (! in_array($lockedOrder->status, self::CANCELLABLE_STATUSES, true)
                || ($lockedOrder->status === 'assigned' && ! $activeAttempt)
                || in_array($assignment?->status, self::IN_CUSTODY_ASSIGNMENT_STATUSES, true)) {
                $this->refuseCustodyAction();
            }

            if ($activeAttempt) {
                $assignment->update(['status' => 'cancelled']);
            }
            if ($lockedOrder->status === 'pending') {
                $stock->releaseForVendorOrder($lockedOrder);
            }

            $lockedOrder->update(['status' => 'cancelled', 'delivery_driver_id' => null]);
            $this->recordAdminHistory($lockedOrder, 'cancelled_by_admin', $adminId, trim($data['cancel_reason']));
            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return back()->with('success', t('dashboard.Delivery_Ops_Cancelled_Success', 'The vendor order was cancelled.'));
    }

    /**
     * E2-MVP batch 04: an admin records a failed delivery after pickup (the driver cannot, or cannot be reached), with a
     * reason from the approved list and a note. The goods stay in the driver's custody: the order stays Out for
     * Delivery until the admin confirms the return (confirmReturn). Locks: vendor order, then its latest assignment.
     */
    public function recordFailure(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.record-delivery-failure');
        $data = $request->validate([
            'failure_code' => ['required', 'string', Rule::in(array_keys(DeliveryReasons::FAILURE))],
            'failure_reason' => ['required', 'string', 'max:1000'],
        ]);
        $adminId = $request->user('admin')->id;

        DB::transaction(function () use ($vendorOrder, $data, $adminId): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            $assignment = DeliveryAssignment::query()->where('vendor_order_id', $lockedOrder->id)->latest('id')->lockForUpdate()->first();

            if ($lockedOrder->status !== 'out_for_delivery' || ! in_array($assignment?->status, self::IN_CUSTODY_ASSIGNMENT_STATUSES, true)) {
                $this->refuseCustodyAction();
            }

            $note = trim($data['failure_reason']);
            $assignment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_code' => $data['failure_code'],
                'failure_reason' => $note,
            ]);
            $this->recordAdminHistory($lockedOrder, 'delivery_failed', $adminId,
                $data['failure_code'].' — '.DeliveryReasons::label(DeliveryReasons::FAILURE, $data['failure_code']).': '.$note);
        });

        return back()->with('success', t('dashboard.Delivery_Failure_Recorded', 'The delivery failure was recorded.'));
    }

    public function assignDriver(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.assign-driver');

        $data = $request->validate([
            'delivery_driver_id' => [
                'bail',
                'required',
                Rule::exists('delivery_drivers', 'id')->where('status', 'active'),
                // E2-MVP batch 01: the server applies the same condition as the page's driver list (approved and
                // available), so a driver still under review, rejected or unavailable is never assigned.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! self::assignableDrivers()->whereKey($value)->exists()) {
                        $fail(t('dashboard.Driver_not_assignable', 'The selected driver is not approved or not available for assignment.'));
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $vendorOrder, $data) {
            // VEN-BE-020 (slice 2A): the vendor order is locked and its status re-read before any write, so assignments
            // through this route are serialized per vendor order (a second request waits, then sees the first one's row).
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, self::ASSIGNABLE_STATUSES, true), 422);
            // VEN-BE-020 (slice 2B-1): then its assignment row, the same vendor order → assignment order the driver actions
            // use, so a reassignment and a driver action wait for each other instead of deadlocking.
            $currentAssignment = DeliveryAssignment::query()
                ->where('vendor_order_id', $lockedOrder->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            // E2-MVP batch 01: the driver's eligibility is read again, locked, before anything is written. A rejection,
            // a block or an availability change committed after the validation above is seen here and refuses the
            // assignment; one arriving later waits for this transaction (its UPDATE needs the row lock held here).
            $this->lockEligibleDriver((int) $data['delivery_driver_id']);
            // E2-MVP batch 05: then the parent order (the vendor order → parent order order of the status sync). An online
            // order is assigned only once the admin marked it paid; a cash-on-delivery attempt captures what to collect.
            $parent = Order::query()->whereKey($lockedOrder->order_id)->lockForUpdate()->first(['id', 'payment_method', 'payment_status']);
            $this->refuseUnpaidOnline($parent, 'delivery_driver_id');

            $assignmentData = [
                'delivery_driver_id' => $data['delivery_driver_id'],
                'assigned_by' => $request->user('admin')->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'notes' => $data['notes'] ?? null,
                'cod_amount' => DriverCash::isCashOnDelivery($parent) ? $lockedOrder->total : null,
            ];

            // E2-MVP batch 04: no sham reassignment. While the order is Assigned, the driver who holds its current attempt
            // (not accepted or accepted) cannot be assigned again: nothing would change. A new attempt after a declined,
            // failed or cancelled one stays allowed, and so does an older attempt left on a Ready order (the order then
            // actually becomes Assigned).
            if ($lockedOrder->status === 'assigned'
                && in_array($currentAssignment?->status, self::PRE_PICKUP_ASSIGNMENT_STATUSES, true)
                && (int) $currentAssignment->delivery_driver_id === (int) $data['delivery_driver_id']) {
                throw ValidationException::withMessages([
                    'delivery_driver_id' => t('dashboard.Custody_Reassign_Same_Driver', 'This driver is already assigned to this order.'),
                ]);
            }

            // A task merely assigned (not accepted) can be corrected in place. Once a driver has accepted it,
            // retain that attempt and start a new one instead of silently changing custody history.
            if ($currentAssignment?->status === 'assigned') {
                $currentAssignment->update($assignmentData);
            } else {
                if ($lockedOrder->status === 'assigned' && $currentAssignment?->status === 'accepted') {
                    $currentAssignment->update(['status' => 'cancelled']);
                }

                DeliveryAssignment::query()->create([
                    'vendor_order_id' => $lockedOrder->id,
                    ...$assignmentData,
                ]);
            }

            $lockedOrder->update([
                'delivery_driver_id' => $data['delivery_driver_id'],
                'status' => 'assigned',
            ]);
        });

        return back()->with('success', t('dashboard.Driver_assigned_successfully', 'Delivery driver assigned successfully.'));
    }

    /**
     * E2-MVP custody fix: the admin confirms, after checking with the vendor, that a shipment whose delivery failed after
     * pickup is physically back at the vendor; the driver's declaration is not required. Only this closes the custody:
     * the failed attempt is cancelled with returned_at and return_confirmed_by, the vendor order goes back to Ready for
     * Delivery without a driver, and the parent order is synced. Locks: vendor order, then its latest assignment (the
     * order assignDriver() and the driver actions use). A second confirmation, or one for an order not in that state, is
     * refused with 422 and nothing written.
     */
    public function confirmReturn(Request $request, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.confirm-return');
        $adminId = $request->user('admin')->id;

        DB::transaction(function () use ($vendorOrder, $orders, $adminId): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            $assignment = DeliveryAssignment::query()
                ->where('vendor_order_id', $lockedOrder->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($lockedOrder->status !== 'out_for_delivery' || $assignment?->status !== 'failed') {
                $this->refuseCustodyAction();
            }

            $assignment->update([
                'status' => 'cancelled',
                'returned_at' => now(),
                'return_confirmed_by' => $adminId,
            ]);
            $lockedOrder->update([
                'status' => 'ready_for_delivery',
                'delivery_driver_id' => null,
            ]);
            $this->recordAdminHistory($lockedOrder, 'return_confirmed', $adminId);
            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return back()->with('success', t('dashboard.Return_Confirmed', 'The return of the goods was confirmed.'));
    }

    /** E2-MVP batch 05: an online order not marked paid is neither assigned nor delivered (no gateway records payments). */
    private function refuseUnpaidOnline(?Order $parent, string $field): void
    {
        if ($parent?->payment_method !== 'cod' && ! DriverCash::isPrepaid($parent)) {
            throw ValidationException::withMessages([
                $field => t('dashboard.Delivery_Ops_Awaiting_Payment', 'This online order is not marked paid yet, so it cannot be assigned or delivered.'),
            ]);
        }
    }

    /** 422 for a delivery action the order's current state does not allow; the message is built only when refusing. */
    private function refuseCustodyAction(): never
    {
        abort(422, t('dashboard.Custody_Action_Not_Allowed', 'This action is not available in the current delivery state of the order.'));
    }

    private function recordAdminHistory(VendorOrder $vendorOrder, string $status, int $adminId, ?string $note = null): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $vendorOrder->order_id,
            'vendor_order_id' => $vendorOrder->id,
            'status' => $status,
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'note' => $note,
        ]);
    }

    /** E2-MVP batch 04: an active account approved by the platform (availability aside: a task already held stays valid). */
    public static function isEligible(DeliveryDriver $driver): bool
    {
        return $driver->status === 'active' && $driver->profile?->approval_status === 'approved';
    }

    /** E2-MVP batch 04: the drivers who can no longer deliver (blocked or pending account, profile not approved or missing). */
    public static function ineligibleDrivers($query)
    {
        return $query->where(fn ($driver) => $driver
            ->where('status', '!=', 'active')
            ->orWhereDoesntHave('profile', fn ($profile) => $profile->where('approval_status', 'approved')));
    }

    /**
     * The drivers a vendor order can be assigned to: an active account whose profile is approved by the platform and
     * available. Used by the order page's list and by the server-side check in assignDriver().
     */
    private static function assignableDrivers(): Builder
    {
        return DeliveryDriver::query()
            ->where('status', 'active')
            ->whereHas('profile', function (Builder $query): void {
                $query->where('approval_status', 'approved')
                    ->where('is_available', true);
            });
    }

    /**
     * Inside assignDriver()'s transaction, after the vendor order and assignment locks: locks the driver's account row,
     * then their profile row (two plain locking reads; MySQL does not lock the rows a whereHas() subquery reads), and
     * checks the same conditions as assignableDrivers() on the locked rows. Lock order: vendor order → assignment →
     * driver → profile; the approval, rejection and availability writes lock one row per statement, and the driver's own
     * profile update takes driver → profile, so no cycle is possible.
     *
     * @throws ValidationException on delivery_driver_id when the driver is no longer eligible; nothing is written.
     */
    private function lockEligibleDriver(int $driverId): void
    {
        $driver = DeliveryDriver::query()->whereKey($driverId)->lockForUpdate()->first(['id', 'status']);
        $profile = DeliveryDriverProfile::query()->where('delivery_driver_id', $driverId)->lockForUpdate()->first(['id', 'approval_status', 'is_available']);

        if ($driver?->status !== 'active' || $profile?->approval_status !== 'approved' || ! $profile->is_available) {
            throw ValidationException::withMessages([
                'delivery_driver_id' => t('dashboard.Driver_not_assignable', 'The selected driver is not approved or not available for assignment.'),
            ]);
        }
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }

    private function statuses(): array
    {
        return [
            'pending' => t('dashboard.Pending', 'Pending'),
            'accepted' => t('dashboard.Accepted', 'Accepted'),
            'preparing' => t('dashboard.Preparing', 'Preparing'),
            'ready_for_delivery' => t('dashboard.Ready_for_delivery', 'Ready for delivery'),
            'assigned' => t('dashboard.Assigned', 'Assigned'),
            'out_for_delivery' => t('dashboard.Out_for_delivery', 'Out for delivery'),
            'delivered' => t('dashboard.Delivered', 'Delivered'),
            'completed' => t('dashboard.Completed', 'Completed'),
            'rejected' => t('dashboard.Rejected', 'Rejected'),
            'cancelled' => t('dashboard.Cancelled', 'Cancelled'),
        ];
    }

    private function uniqueNumber(): string
    {
        do {
            $number = 'VO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (VendorOrder::where('number', $number)->exists());

        return $number;
    }
}
