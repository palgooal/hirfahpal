<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Products\ProductGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A vendor's product images: attach an image of the vendor's own media library (store), edit or choose the main image
 * (update), unlink (destroy). G7-C2-4: there is no upload here; files reach a product only through the vendor's media
 * library (MediaLibraryController@store), so every new image passes the library ownership rule.
 */
class ProductImageController extends Controller
{
    /** G7-C2-2 (V3): the most images one product can have (the vendor's media library itself has no limit). */
    public const MAX_IMAGES = 6;

    public const MAX_IMAGES_MESSAGE = 'A product can have up to 6 images.';

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        // G7-C2-2 (V3): the product row is locked before its images are counted, so concurrent additions are
        // serialised and can never pass six together. A product already at or above six (legacy data included)
        // takes no more; nothing is trimmed.
        // G7-C2-3 (V4): the image is appended (a sent sort_order is not a position), or put first when it is sent as
        // the main image; the gallery is then normalised under the same lock.
        $image = DB::transaction(function () use ($request, $product) {
            $locked = $this->lockProduct($product);

            if ($locked->images()->count() >= self::MAX_IMAGES) {
                throw ValidationException::withMessages(['images' => self::MAX_IMAGES_MESSAGE]);
            }

            $data = $request->validated();
            $image = $locked->images()->create([
                'path' => $data['path'],
                'alt_text' => $data['alt_text'] ?? null,
                'sort_order' => (int) $locked->images()->max('sort_order') + 1,
                'is_primary' => false,
            ]);
            ProductGallery::normalize($locked, $request->boolean('is_primary') ? $image->id : null);

            return $image;
        });

        return response()->json([
            'message' => 'Product image added successfully.',
            'image' => $image->fresh(),
            'product' => $product->fresh('images'),
        ], 201);
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

        // G7-C2-3 (V4): only the sent fields change. is_primary=true makes the image the main one and moves it to
        // position 0; the other images keep their order. A sent sort_order is not a position (no reordering here).
        $productImage = DB::transaction(function () use ($request, $product, $productImage) {
            $locked = $this->lockProduct($product);
            $image = $this->lockedImage($locked, $productImage);
            $data = $request->validated();

            $image->fill(array_intersect_key($data, array_flip(['path', 'alt_text'])));
            if ($request->has('is_primary')) {
                $image->is_primary = $request->boolean('is_primary');
            }
            $image->save();
            ProductGallery::normalize($locked, $request->boolean('is_primary') ? $image->id : null);

            return $image;
        });

        return response()->json([
            'message' => 'Product image updated successfully.',
            'image' => $productImage->fresh(),
            'product' => $product->fresh('images'),
        ]);
    }

    public function destroy(Request $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

        // G7-B (V1): removing an image from a product only removes the association. The file is never deleted
        // here, whatever its stored path: a path is not trusted for filesystem operations (a ".." segment in it
        // used to reach files outside the product's folder). Files are removed from the media library only.
        // G7-C2-3 (V4): under the product lock; the gap closes, and a removed main image hands over to the first
        // remaining one.
        DB::transaction(function () use ($product, $productImage) {
            $locked = $this->lockProduct($product);
            $this->lockedImage($locked, $productImage)->delete();
            ProductGallery::normalize($locked);
        });

        return response()->json([
            'message' => 'Product image deleted successfully.',
            'product' => $product->fresh('images'),
        ]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless((int) $product->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    private function authorizeImage(Request $request, Product $product, ProductImage $productImage): void
    {
        $this->authorizeProduct($request, $product);

        abort_unless((int) $productImage->product_id === (int) $product->id, 404);
    }

    /** G7-C2-3: the product row, locked for the rest of the transaction; every image change on it waits here. */
    private function lockProduct(Product $product): Product
    {
        return Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
    }

    /** The image read again under the lock: one removed meanwhile is a 404, as an unknown one would be. */
    private function lockedImage(Product $locked, ProductImage $productImage): ProductImage
    {
        return ProductImage::query()->whereKey($productImage->getKey())->where('product_id', $locked->getKey())->firstOr(fn () => abort(404));
    }
}
