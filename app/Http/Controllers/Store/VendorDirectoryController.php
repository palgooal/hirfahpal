<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\View\View;

class VendorDirectoryController extends Controller
{
    public function index(): View
    {
        return view('pages.vendors', [
            'vendors' => Vendor::query()->storefrontVisible()->withStorefrontSummary()
                ->orderBy(VendorProfile::query()->select('store_name')->whereColumn('vendor_id', 'vendors.id')->limit(1))
                ->orderBy('vendors.id')->get(),
            'siteSetting' => Setting::query()->first(),
        ]);
    }

    public function show(string $storeSlug): View
    {
        $vendor = Vendor::query()->storefrontVisible()->withStorefrontSummary()
            ->whereHas('profile', fn ($profile) => $profile->where('slug', $storeSlug))->firstOrFail();
        $products = $vendor->products()->sellable()
            ->with(['category', 'primaryImage', 'images'])
            ->withCount(['reviews' => fn ($reviews) => $reviews->where('status', 'published')])
            ->withAvg(['reviews' => fn ($reviews) => $reviews->where('status', 'published')], 'rating')
            ->withSum(['stockReservations as reserved_quantity' => fn ($reservations) => $reservations->where('status', 'reserved')], 'quantity')
            ->latest('published_at')->latest('id')->get();
        $setting = Setting::query()->first();

        return view('pages.vendor', [
            'vendor' => $vendor,
            'products' => $products,
            'vendorCategories' => Category::query()->where('is_active', true)
                ->whereIn('id', $products->pluck('category_id')->filter())->orderBy('sort_order')->orderBy('id')->get(),
            'siteSetting' => $setting,
            'currency' => $setting?->default_currency ?: 'ILS',
        ]);
    }
}
