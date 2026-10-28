<?php

namespace App\Support\Products;

use App\Models\Product;
use App\Models\ProductImage;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-3 (V4): puts a vendor product's images back into their one consistent shape after
 * a change. Call it inside the transaction that holds the product row lock.
 *
 * The gallery order is sort_order, then id (deterministic for equal or legacy values). The main image is, in that
 * order of preference: the image just chosen ($primaryId), the image already marked main (the first one if several
 * are), else the first image. It moves to position 0; every other image keeps its relative order. sort_order then
 * becomes 0..n-1 and only position 0 is marked main. Nothing is added, removed or re-pathed, so legacy images and a
 * product above six (from before the limit) keep every association. An empty gallery has no main image.
 */
final class ProductGallery
{
    public static function normalize(Product $product, ?int $primaryId = null): void
    {
        $images = ProductImage::query()->where('product_id', $product->getKey())->orderBy('sort_order')->orderBy('id')->get();

        if ($images->isEmpty()) {
            return;
        }

        $primary = ($primaryId !== null ? $images->firstWhere('id', $primaryId) : null)
            ?? $images->first(fn (ProductImage $image) => $image->is_primary)
            ?? $images->first();

        $images->reject(fn (ProductImage $image) => $image->is($primary))
            ->prepend($primary)
            ->values()
            ->each(function (ProductImage $image, int $position) {
                if ((int) $image->sort_order !== $position || $image->is_primary !== ($position === 0)) {
                    $image->forceFill(['sort_order' => $position, 'is_primary' => $position === 0])->save();
                }
            });
    }
}
