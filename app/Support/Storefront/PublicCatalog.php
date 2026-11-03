<?php

namespace App\Support\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VendorProfile;

/**
 * SEC-01: the public catalog JSON (shop.products.index, shop.products.show, shop.vendors.index) as explicit
 * allow-lists: only what the storefront pages already show a visitor. Never the vendor account (email, phone, login
 * data), the store's commercial or administrative data (commission rate, approval, rejection reason, street address,
 * internal ids) or internal product data (SKU, raw stock, low stock threshold, status, moderation columns, timestamps).
 * Every nested value is built here field by field, so a model attribute added later is not published by default.
 */
class PublicCatalog
{
    /** @return array<string, mixed> a product in a list (shop.products.index) */
    public static function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'price' => self::money($product->price),
            'selling_price' => self::money($product->sellingPrice()),
            'has_discount' => $product->hasDiscount(),
            'available_stock' => self::availableStock($product),
            'image_url' => $product->primaryImage ? HomepageMedia::url($product->primaryImage->path) : null,
            'category' => self::category($product->category),
            'store' => self::store($product->vendor?->profile),
        ];
    }

    /** @return array<string, mixed> one product's public details (shop.products.show) */
    public static function productDetails(Product $product): array
    {
        return self::productSummary($product) + [
            'description' => $product->description,
            'material' => $product->material,
            'place_of_manufacture' => $product->place_of_manufacture,
            'preparation_days' => $product->preparation_days,
            'care_instructions' => $product->care_instructions,
            'shipping_details' => $product->shipping_details,
            'weight' => $product->weight,
            'weight_unit' => $product->weight === null ? null : $product->weight_unit,
            'dimensions' => collect($product->dimensions ?? [])->only(['length', 'width', 'height'])->all(),
            'is_limited_edition' => (bool) $product->is_limited_edition,
            'highlight_tags' => array_values($product->highlight_tags ?? []),
            'craft_tags' => array_values($product->craft_tags ?? []),
            'images' => $product->images->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])->values()
                ->map(fn (ProductImage $image) => [
                    'url' => HomepageMedia::url($image->path),
                    'alt_text' => $image->alt_text,
                    'is_primary' => (bool) $image->is_primary,
                ])->all(),
        ];
    }

    /** @return array<string, mixed>|null a store as the storefront shows it (no account or administrative data) */
    public static function store(?VendorProfile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        return [
            'slug' => $profile->slug,
            'name' => $profile->store_name,
            'short_description' => $profile->short_description,
            'city' => $profile->city?->name,
            'governorate' => $profile->governorate?->name,
            'preparation_days' => $profile->preparation_days,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function category(?Category $category): ?array
    {
        return $category === null ? null : ['name' => $category->name, 'slug' => $category->slug];
    }

    /** Physical stock minus outstanding reservations; the preloaded sum (withSum) when the query has it. */
    private static function availableStock(Product $product): int
    {
        $reserved = array_key_exists('reserved_quantity', $product->getAttributes())
            ? (int) $product->getAttribute('reserved_quantity')
            : $product->reservedStockQuantity();

        return max(0, (int) $product->stock_quantity - $reserved);
    }

    private static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
