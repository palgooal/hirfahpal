<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Support\Orders\DeliveryDriverAssignmentJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $assignments = DeliveryAssignment::query()
            ->where('delivery_driver_id', $request->user('delivery_driver')->id);

        return response()->json([
            // DRIVER-E1: the driver's own account and operational profile, never their approval records.
            'profile' => DeliveryDriverAssignmentJson::driver($request->user('delivery_driver')->load(['profile.governorate', 'profile.city'])),
            'stats' => [
                'assigned' => (clone $assignments)->where('status', 'assigned')->count(),
                'in_progress' => (clone $assignments)->whereIn('status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
                'delivered_today' => (clone $assignments)->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
                'failed' => (clone $assignments)->where('status', 'failed')->count(),
            ],
            'recent_assignments' => $assignments
                ->with(['vendorOrder.vendor.profile', 'vendorOrder.order.address.governorate', 'vendorOrder.order.address.city'])
                ->latest()
                ->limit(5)
                ->get()
                // ORD-BE-001: the order's delivery address is the checkout snapshot.
                ->each(fn (DeliveryAssignment $assignment) => $assignment->vendorOrder?->order?->exposeDeliveryAddress())
                // DRIVER-E1: only the fields the delivery needs (SRS §2.3).
                ->map(fn (DeliveryAssignment $assignment) => DeliveryDriverAssignmentJson::assignment($assignment)),
        ]);
    }
}
