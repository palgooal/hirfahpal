<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Review;
use App\Models\VendorOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $orders = VendorOrder::query()->where('vendor_id', $vendor->id);
        $products = Product::query()->where('vendor_id', $vendor->id);

        return response()->json([
            'vendor' => $vendor->load(['profile.governorate', 'profile.city']),
            'stats' => [
                'products_total' => (clone $products)->count(),
                'products_active' => (clone $products)->where('status', 'active')->count(),
                'products_low_stock' => (clone $products)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->count(),
                'orders_pending' => (clone $orders)->where('status', 'pending')->count(),
                'orders_in_progress' => (clone $orders)
                    ->whereIn('status', ['accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery'])
                    ->count(),
                'orders_completed' => (clone $orders)->where('status', 'completed')->count(),
                'sales_total' => (float) (clone $orders)
                    ->whereIn('status', ['delivered', 'completed'])
                    ->sum('total'),
                'commission_pending' => (float) Commission::query()
                    ->where('vendor_id', $vendor->id)
                    ->where('status', 'pending')
                    ->sum('amount'),
                'average_rating' => round((float) Review::query()
                    ->where('vendor_id', $vendor->id)
                    ->where('status', 'published')
                    ->avg('rating'), 2),
            ],
            'recent_orders' => VendorOrder::query()
                ->with(['order.customer', 'items'])
                ->where('vendor_id', $vendor->id)
                ->latest()
                ->limit(8)
                ->get(),
            'low_stock_products' => Product::query()
                ->with(['category', 'primaryImage'])
                ->where('vendor_id', $vendor->id)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->orderBy('stock_quantity')
                ->limit(8)
                ->get(),
        ]);
    }
}
