<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
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

        return view('dashboard.delivery-drivers.show', compact('deliveryDriver'));
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
        $deliveryDriver->update(['status' => 'blocked']);

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
