<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Support\Orders\DeliveryDriverAssignmentCard;
use App\Support\Orders\DriverCash;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $driver = $request->user('delivery_driver');
        $assignments = DeliveryAssignment::query()
            ->with([
                'vendorOrder.order.customer',
                // ORD-BE-001: legacy orders without a checkout snapshot fall back to the saved address.
                'vendorOrder.order.address.governorate',
                'vendorOrder.order.address.city',
                'vendorOrder.vendor.profile.governorate',
                'vendorOrder.vendor.profile.city',
                'vendorOrder.items',
            ])
            ->where('delivery_driver_id', $driver->id)
            ->latest()
            ->get();

        return view('delivery-driver.dashboard', [
            'driver' => $driver->load('profile'),
            'assignments' => $assignments,
            // E2-MVP batch 02: what each task card shows, field by field (DeliveryDriverAssignmentCard).
            'cards' => $assignments->mapWithKeys(fn (DeliveryAssignment $assignment) => [$assignment->id => DeliveryDriverAssignmentCard::for($assignment)]),
            'assignedCount' => $assignments->where('status', 'assigned')->count(),
            'activeCount' => $assignments->whereIn('status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
            'deliveredCount' => $assignments->where('status', 'delivered')->count(),
            // E2-MVP batch 05: the driver's own cash custody.
            'cashBalance' => DriverCash::format(DriverCash::summary($driver->id)['balance']),
        ]);
    }
}
