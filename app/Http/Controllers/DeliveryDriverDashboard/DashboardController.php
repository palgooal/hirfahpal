<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $driver = $request->user('delivery_driver');
        $assignments = DeliveryAssignment::query()
            ->with(['vendorOrder.order.customer', 'vendorOrder.vendor.profile', 'vendorOrder.items'])
            ->where('delivery_driver_id', $driver->id)
            ->latest()
            ->get();

        return view('delivery-driver.dashboard', [
            'driver' => $driver->load('profile'),
            'assignments' => $assignments,
            'assignedCount' => $assignments->where('status', 'assigned')->count(),
            'activeCount' => $assignments->whereIn('status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
            'deliveredCount' => $assignments->where('status', 'delivered')->count(),
        ]);
    }
}
