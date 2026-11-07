<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\SiteAvailability\SiteAvailability;
use App\Support\SiteAvailability\SiteAvailabilityState;
use App\Support\Storefront\ProductPageData;
use App\Support\Storefront\StorePageData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PREVIEW-02: the vendor's private preview of their own product page, in the storefront's own design (pages.product,
 * with $preview): any status is shown (draft, inactive, out of stock, suspended, archived), but only the signed-in
 * vendor's own products: any other product answers 404. PREVIEW-03: and of their own store page (pages.vendor), as
 * customers will see it. The routes sit in the vendor dashboard group (auth:vendor, vendor.approved), so the site
 * availability gate lets them through in `open` and `coming_soon` only (DEC-SA-01: vendor.dashboard.* is allowlisted
 * in coming_soon; everything vendor is gated in maintenance). Buying, the cart, favourites and related products are
 * left out of the pages; they are never indexed or cached.
 */
class StorefrontPreviewController extends Controller
{
    public const HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'private, no-store',
        'Referrer-Policy' => 'same-origin',
    ];

    public function product(Request $request, Product $product): Response
    {
        abort_unless((int) $product->vendor_id === (int) $request->user('vendor')->id, 404);

        // The storefront's product data without its visibility condition (Product::scopeSellable()).
        $product = ProductPageData::withListingData(Product::query()->whereKey($product->id))->firstOrFail();

        return response()->view('pages.product', ProductPageData::viewData($product, $request, related: false) + [
            'preview' => true,
            'previewStatus' => self::status($product),
        ])->withHeaders(self::HEADERS);
    }

    /**
     * PREVIEW-03: the signed-in vendor's own store page, as customers see it: the same data as the public page
     * (StorePageData: only the products a customer may see), whichever store this is (never one from the address),
     * with the number of the vendor's other products, not shown to visitors (a count only: no status or reason). The
     * products link to their own previews; buying, the cart and favourites are left out.
     */
    public function store(Request $request, SiteAvailability $availability): Response
    {
        $vendor = Vendor::query()->withStorefrontSummary()->whereKey($request->user('vendor')->id)->firstOrFail();
        $data = StorePageData::viewData($vendor);

        return response()->view('pages.vendor', $data + [
            'preview' => true,
            'previewMode' => 'store',
            'previewHiddenCount' => max(0, $vendor->products()->count() - $data['products']->count()),
            'previewSiteClosed' => $availability->current() !== SiteAvailabilityState::Open,
        ])->withHeaders(self::HEADERS);
    }

    /**
     * What a customer would see, in one word for the preview banner: the administration's state first (archived, then
     * suspended: either one hides the product whatever its status), then the vendor's status. No reason or date.
     */
    public static function status(Product $product): string
    {
        return match (true) {
            $product->isArchived() => 'archived',
            $product->isSuspended() => 'suspended',
            in_array($product->status, ['draft', 'inactive', 'out_of_stock'], true) => $product->status,
            default => 'visible',
        };
    }
}
