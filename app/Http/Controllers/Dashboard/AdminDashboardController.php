<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'pending_vendors' => VendorProfile::where('approval_status', 'pending')->count(),
            'active_vendors' => Vendor::where('status', 'active')->count(),
            'pending_vendor_orders' => VendorOrder::where('status', 'pending')->count(),
            'orders' => Order::count(),
            'needs_attention' => VendorOrder::where('status', 'pending')
                ->whereNotNull('vendor_response_due_at')
                ->where('vendor_response_due_at', '<=', now()->addHours(3))
                ->count(),
            'open_assignments' => DeliveryAssignment::whereIn('status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])->count(),
        ];

        $pendingVendors = Vendor::query()
            ->with(['profile.city'])
            ->whereHas('profile', fn ($query) => $query->where('approval_status', 'pending'))
            ->latest()
            ->limit(6)
            ->get();

        $attentionVendorOrders = VendorOrder::query()
            ->with(['order', 'vendor.profile', 'deliveryAssignment.deliveryDriver'])
            ->where('status', 'pending')
            ->whereNotNull('vendor_response_due_at')
            ->where('vendor_response_due_at', '<=', now()->addHours(3))
            ->orderBy('vendor_response_due_at')
            ->limit(8)
            ->get();

        return view('dashboard.admin.index', compact('stats', 'pendingVendors', 'attentionVendorOrders'));
    }
}
