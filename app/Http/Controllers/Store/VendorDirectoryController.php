<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\Storefront\StorePageData;
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

        // PREVIEW-01: the page's data comes from StorePageData (shared with the vendor's preview later).
        return view('pages.vendor', StorePageData::viewData($vendor));
    }
}
