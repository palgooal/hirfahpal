<?php

namespace App\Support\Storefront;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * PREVIEW-01: the storefront product page's data, shared so the page can be rendered for more than one audience (the
 * public page today; the vendor's private preview later) from the same query and the same view data. Which products
 * may be shown is the caller's decision: the public page passes the sellable query (Product::scopeSellable()).
 */
final class ProductPageData
{
    /** The storefront's product data (category, store, images, published reviews, reserved stock) on any product query. */
    public static function withListingData(Builder $query): Builder
    {
        return $query->with(['category', 'vendor.profile.city', 'vendor.profile.governorate', 'primaryImage', 'images'])
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->withSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity');
    }

    /** The products a customer may see (Product::scopeSellable()), with the storefront's product data. */
    public static function sellable(): Builder
    {
        return self::withListingData(Product::query()->sellable());
    }

    /**
     * The product page's view data (pages.product) for a product loaded with withListingData(): its store's summary,
     * the quantity to show, its images, its published reviews and their distribution, and up to four related products
     * a customer may see (same category, newest first). PREVIEW-02: $related false leaves the related products out (an
     * empty list; the vendor's private preview does not show them).
     *
     * @return array<string, mixed>
     */
    public static function viewData(Product $product, Request $request, bool $related = true): array
    {
        $product->setRelation('vendor', Vendor::query()->withStorefrontSummary()->findOrFail($product->vendor_id));
        $reviews = $product->reviews()->where('status', 'published')->with('customer')->latest()->get();

        return [
            'product' => $product,
            // PRODUCT-FIX-02: the quantity a refused add-to-cart sent (old input), else ?quantity=; anything that is not
            // a whole number of at least 1 counts as 1 (no redirect); never above the available stock.
            'selectedQuantity' => min(self::requestedQuantity($request), max(1, $product->availableStockQuantity())),
            'productImages' => $product->images->isNotEmpty() ? $product->images->sortByDesc('is_primary')->values() : collect([new ProductImage(['alt_text' => $product->name])]),
            'productReviews' => $reviews,
            'reviewDistribution' => collect(range(1, 5))->mapWithKeys(fn ($rating) => [$rating => $reviews->isEmpty() ? 0 : (int) round($reviews->where('rating', $rating)->count() * 100 / $reviews->count())])->all(),
            'relatedProducts' => $related ? self::sellable()->whereKeyNot($product->id)->where('category_id', $product->category_id)->latest('id')->limit(4)->get() : $product->newCollection(),
        ];
    }

    private static function requestedQuantity(Request $request): int
    {
        $quantity = $request->old('quantity', $request->query('quantity'));
        $quantity = is_int($quantity) ? (string) $quantity : $quantity;

        return is_string($quantity) && ctype_digit($quantity) && (int) $quantity >= 1 ? (int) $quantity : 1;
    }
}
