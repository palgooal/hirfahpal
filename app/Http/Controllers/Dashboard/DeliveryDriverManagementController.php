<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverCashSettlement;
use App\Support\Orders\DriverCash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryDriverManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'delivery-drivers.view');

        $drivers = DeliveryDriver::query()
            ->with(['profile.city', 'profile.governorate'])
            ->withCount('deliveryAssignments')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            })
            ->when($request->filled('approval_status'), fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('approval_status', $request->input('approval_status'))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.delivery-drivers.index', compact('drivers'));
    }

    public function show(Request $request, DeliveryDriver $deliveryDriver): View
    {
        $this->authorizeAbility($request, 'delivery-drivers.view');

        $deliveryDriver->load([
            'profile.city',
            'profile.governorate',
            'deliveryAssignments' => fn ($query) => $query->with('vendorOrder.order')->latest()->limit(15),
        ]);

        // E2-MVP batch 04: every task this driver still holds, however old: before pickup (assigned, accepted), with the
        // goods (picked up, out for delivery), or failed after pickup and waiting for the return confirmation. Nothing is
        // unassigned automatically: the admin reassigns or handles the custody from the order page.
        $activeTasks = DeliveryAssignment::query()
            ->with('vendorOrder')
            ->where('delivery_driver_id', $deliveryDriver->id)
            ->where(fn ($query) => $query
                ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])
                ->orWhere(fn ($custody) => $custody->where('status', 'failed')->whereHas('vendorOrder', fn ($order) => $order->where('status', 'out_for_delivery'))))
            ->orderBy('id')
            ->get();

        // E2-MVP batch 05: the driver's cash custody, only for an admin allowed to see it (delivery-drivers.view-cash).
        $admin = $request->user('admin');
        $canViewCash = $admin->isSuperAdmin() || $admin->hasAbility('delivery-drivers.view-cash');

        return view('dashboard.delivery-drivers.show', [
            'deliveryDriver' => $deliveryDriver,
            'activeTasks' => $activeTasks,
            'notEligible' => ! VendorOrderManagementController::isEligible($deliveryDriver),
            'cash' => $canViewCash ? DriverCash::summary($deliveryDriver->id) : null,
            'cashEntries' => $canViewCash
                ? DriverCashSettlement::query()->with(['recorder:id,name', 'reversal:id,reverses_id'])->where('delivery_driver_id', $deliveryDriver->id)->latest('id')->get()
                : collect(),
            'canSettleCash' => $admin->isSuperAdmin() || $admin->hasAbility('delivery-drivers.settle-cash'),
        ]);
    }

    public function approve(Request $request, DeliveryDriver $deliveryDriver): RedirectResponse
    {
        $this->authorizeAbility($request, 'delivery-drivers.edit');

        $deliveryDriver->profile()->updateOrCreate(
            ['delivery_driver_id' => $deliveryDriver->id],
            [
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user('admin')->id,
                'rejection_reason' => null,
                'is_available' => true,
            ]
        );
        $deliveryDriver->update(['status' => 'active']);

        return back()->with('success', t('dashboard.Driver_approved', 'Delivery driver approved successfully.'));
    }

    public function reject(Request $request, DeliveryDriver $deliveryDriver): RedirectResponse
    {
        $this->authorizeAbility($request, 'delivery-drivers.edit');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $deliveryDriver->profile()->updateOrCreate(
            ['delivery_driver_id' => $deliveryDriver->id],
            [
                'approval_status' => 'rejected',
                'approved_at' => null,
                'approved_by' => $request->user('admin')->id,
                'rejection_reason' => $data['rejection_reason'],
                'is_available' => false,
            ]
        );
        // E2-MVP batch 01: a rejection is an approval decision, not an account block. The account status is kept, so the
        // driver can still sign in and read the decision and its reason (FR-DEL-013); the rejected, unavailable profile
        // keeps them out of delivery operations and assignment. Blocking stays an explicit action (updateAvailability).

        return back()->with('success', t('dashboard.Driver_rejected', 'Delivery driver rejected successfully.'));
    }

    public function updateAvailability(Request $request, DeliveryDriver $deliveryDriver): RedirectResponse
    {
        $this->authorizeAbility($request, 'delivery-drivers.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $deliveryDriver->update(['status' => $data['status']]);
        $deliveryDriver->profile()->updateOrCreate(
            ['delivery_driver_id' => $deliveryDriver->id],
            ['is_available' => $request->boolean('is_available')]
        );

        return back()->with('success', t('dashboard.Driver_updated', 'Delivery driver updated successfully.'));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
