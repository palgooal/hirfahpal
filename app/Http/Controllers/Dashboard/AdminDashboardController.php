<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * AD-UI-DASH-01-FIX-01 (DASH-1a/1b): each figure, list and link belongs to the module whose list it opens,
     * and is loaded only for an admin holding that module's view ability (the same check as the sidebar:
     * super admin, or hasAbility). A figure the admin may not see is null, never 0, and is not queried.
     */
    private const ABILITIES = [
        'vendors.view', 'vendor-orders.view', 'products.view', 'delivery-drivers.view', 'disputes.view',
        'reviews.view', 'return-requests.view', 'orders.view', 'customers.view', 'categories.view',
    ];

    public function index(): View
    {
        $admin = auth('admin')->user();
        $can = collect(self::ABILITIES)
            ->mapWithKeys(fn (string $ability) => [$ability => (bool) ($admin?->isSuperAdmin() || $admin?->hasAbility($ability))])
            ->all();
        $when = fn (string $ability, Closure $query) => $can[$ability] ? $query() : null;

        $stats = [
            'pending_vendors' => $when('vendors.view', fn () => VendorProfile::where('approval_status', 'pending')->count()),
            'active_vendors' => $when('vendors.view', fn () => Vendor::where('status', 'active')->count()),
            'pending_vendor_orders' => $when('vendor-orders.view', fn () => VendorOrder::where('status', 'pending')->count()),
            'orders' => $when('orders.view', fn () => Order::count()),
            'needs_attention' => $when('vendor-orders.view', fn () => VendorOrder::where('status', 'pending')
                ->whereNotNull('vendor_response_due_at')
                ->where('vendor_response_due_at', '<=', now()->addHours(3))
                ->count()),
            // Delivery assignments are managed from the vendor orders (assign-driver).
            'open_assignments' => $when('vendor-orders.view', fn () => DeliveryAssignment::whereIn('status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery'])->count()),
            'active_products' => $when('products.view', fn () => Product::where('status', 'active')->count()),
            'low_stock_products' => $when('products.view', fn () => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count()),
            'pending_drivers' => $when('delivery-drivers.view', fn () => DeliveryDriver::whereHas('profile', fn ($query) => $query->where('approval_status', 'pending'))->count()),
            'pending_reviews' => $when('reviews.view', fn () => Review::where('status', 'pending')->count()),
            'open_disputes' => $when('disputes.view', fn () => Dispute::whereIn('status', ['open', 'under_review'])->count()),
            'open_returns' => $when('return-requests.view', fn () => ReturnRequest::whereIn('status', ['requested', 'under_review'])->count()),
        ];

        $pendingVendors = $when('vendors.view', fn () => Vendor::query()
            ->with(['profile.city'])
            ->whereHas('profile', fn ($query) => $query->where('approval_status', 'pending'))
            ->latest()
            ->limit(6)
            ->get()) ?? new Collection;

        $attentionVendorOrders = $when('vendor-orders.view', fn () => VendorOrder::query()
            ->with(['order', 'vendor.profile', 'deliveryAssignment.deliveryDriver'])
            ->where('status', 'pending')
            ->whereNotNull('vendor_response_due_at')
            ->where('vendor_response_due_at', '<=', now()->addHours(3))
            ->orderBy('vendor_response_due_at')
            ->limit(8)
            ->get()) ?? new Collection;

        return view('dashboard.admin.index', compact('stats', 'pendingVendors', 'attentionVendorOrders', 'can'));
    }
}
