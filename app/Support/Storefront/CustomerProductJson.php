<?php

namespace App\Support\Storefront;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Support\Arr;

/**
 * JSON-EXPOSURE-01B: the product and store data that customer JSON responses (favorites, orders) may carry, built
 * field by field under the keys those responses already used ("vendor", "profile", "primary_image"): only what the
 * storefront shows any visitor. Never the vendor account (email, phone, login data), the store's commercial,
 * moderation or address records, or the product's SKU, stock, status, flags or tags.
 */
class CustomerProductJson
{
    /** @return array<string, mixed> a purchased product as an order item links to it */
    public static function reference(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ] + self::loaded($product);
    }

    /** @return array<string, mixed> a product as the favorite store response summarizes it */
    public static function summary(Product $product): array
    {
        // price and compare_at_price as the product serialized them before (decimal casts), plus the selling price.
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ] + Arr::only($product->attributesToArray(), ['price', 'compare_at_price']) + [
            'selling_price' => number_format($product->sellingPrice(), 2, '.', ''),
            'has_discount' => $product->hasDiscount(),
        ] + self::loaded($product);
    }

    /** @return array<string, mixed>|null the public store identity, under the existing vendor.profile keys */
    public static function vendor(?Vendor $vendor): ?array
    {
        if ($vendor === null) {
            return null;
        }

        return ['id' => $vendor->id] + ($vendor->relationLoaded('profile') ? ['profile' => $vendor->profile === null ? null : [
            'store_name' => $vendor->profile->store_name,
            'slug' => $vendor->profile->slug,
        ]] : []);
    }

    /** @return array<string, mixed> the product's loaded public relations: its main image and its store */
    private static function loaded(Product $product): array
    {
        $data = [];
        if ($product->relationLoaded('primaryImage')) {
            $data['primary_image'] = self::image($product->primaryImage);
        }
        if ($product->relationLoaded('vendor')) {
            $data['vendor'] = self::vendor($product->vendor);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private static function image(?ProductImage $image): ?array
    {
        return $image === null ? null : [
            'id' => $image->id,
            'url' => HomepageMedia::url($image->path),
            'path' => $image->path,
            'alt_text' => $image->alt_text,
        ];
    }
}
