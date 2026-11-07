<?php

namespace App\Support\Storefront;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Vendor;

/**
 * PREVIEW-01: the storefront store page's data, shared so the page can be rendered for more than one audience (the
 * public page today; the vendor's private preview later). Which store may be shown is the caller's decision (the public
 * page finds it among Vendor::scopeStorefrontVisible()); the products shown are always the ones a customer may see.
 */
final class StorePageData
{
    /**
     * The store page's view data (pages.vendor) for a store loaded with Vendor::scopeWithStorefrontSummary(): its
     * products a customer may see (newest published first), their active categories, the site settings and currency.
     *
     * @return array<string, mixed>
     */
    public static function viewData(Vendor $vendor): array
    {
        $products = $vendor->products()->sellable()
            ->with(['category', 'primaryImage', 'images'])
            ->withCount(['reviews' => fn ($reviews) => $reviews->where('status', 'published')])
            ->withAvg(['reviews' => fn ($reviews) => $reviews->where('status', 'published')], 'rating')
            ->withSum(['stockReservations as reserved_quantity' => fn ($reservations) => $reservations->where('status', 'reserved')], 'quantity')
            ->latest('published_at')->latest('id')->get();
        $setting = Setting::query()->first();

        return [
            'vendor' => $vendor,
            'products' => $products,
            'vendorCategories' => Category::query()->where('is_active', true)
                ->whereIn('id', $products->pluck('category_id')->filter())->orderBy('sort_order')->orderBy('id')->get(),
            'siteSetting' => $setting,
            'currency' => $setting?->default_currency ?: 'ILS',
        ];
    }
}
