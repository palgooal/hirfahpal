<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $assignments = DeliveryAssignment::query()
            ->where('delivery_driver_id', $request->user('delivery_driver')->id);

        return response()->json([
            'profile' => $request->user('delivery_driver')->load(['profile.governorate', 'profile.city']),
            'stats' => [
                'assigned' => (clone $assignments)->where('status', 'assigned')->count(),
                'in_progress' => (clone $assignments)->whereIn('status', ['accepted', 'picked_up', 'out_for_delivery'])->count(),
                'delivered_today' => (clone $assignments)->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
                'failed' => (clone $assignments)->where('status', 'failed')->count(),
            ],
            'recent_assignments' => $assignments
                ->with(['vendorOrder.vendor.profile', 'vendorOrder.order.address'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
