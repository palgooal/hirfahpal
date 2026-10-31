<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\MoveProductImageRequest;
use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Rules\VendorLibraryImage;
use App\Support\Products\ProductGallery;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    /** VPM-01C: a path the product already has (another of its images) is not attached again. */
    public const DUPLICATE_MESSAGE = 'This image is already in this product.';

    /** DETAIL-02: the same message as an archived product's refused update (ProductController). */
    public const ARCHIVED_MESSAGE = 'This product is archived and cannot be edited.';

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
            // VENDOR-MEDIA-02C: the library row read again under a shared lock (after the product lock), so a concurrent
            // library deletion cannot slip between the validation and this insert.
            $this->ensureStillInLibrary($locked, $data['path'], 'path');
            // VPM-01C: under the same product lock, so a concurrent attachment of this path has committed (and is seen) or waits.
            $this->ensureNotAttached($locked, $data['path']);

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

            // VENDOR-MEDIA-02C: a new path is re-checked under the lock; the unchanged (possibly legacy) path is not.
            if (array_key_exists('path', $data) && $data['path'] !== $image->path) {
                $this->ensureStillInLibrary($locked, $data['path'], 'path');
                // VPM-01C: another image of the product must not have it; keeping the slot's own path skips this check.
                $this->ensureNotAttached($locked, $data['path'], $image->id);
            }

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

    /**
     * VPM-01D: moves one image one place up or down. Under the product lock (as every image change), the gallery is read
     * again in its order (sort_order, then id, the main image first as ProductGallery keeps it), the image swaps places
     * with its neighbour, and the gallery is written back in that order and normalised, so the first image is the main
     * one (the same rule as "set as main"). The move is relative to the order at that moment, so concurrent moves apply
     * one after the other and none is lost. A move past either end changes nothing. Nothing is added, removed, re-pathed
     * or limited: galleries above six and historical duplicates move like any other.
     */
    public function move(MoveProductImageRequest $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

        $productImage = DB::transaction(function () use ($request, $product, $productImage) {
            $locked = $this->lockProduct($product);
            $image = $this->lockedImage($locked, $productImage);

            // The order as shown and as ProductGallery::normalize() keeps it: sort_order, then id, with the main image
            // (the first one marked main, else the first) moved first; legacy data that is not normalised yet included.
            $rows = ProductImage::query()->where('product_id', $locked->getKey())->orderBy('sort_order')->orderBy('id')->get(['id', 'is_primary']);
            $main = $rows->first(fn (ProductImage $row) => $row->is_primary) ?? $rows->first();
            $order = $rows->reject(fn (ProductImage $row) => $row->is($main))->prepend($main)->pluck('id')->all();
            $from = array_search($image->id, $order, true);
            $to = $from + ($request->validated('direction') === 'up' ? -1 : 1);

            if ($to >= 0 && $to < count($order)) {
                [$order[$from], $order[$to]] = [$order[$to], $order[$from]];

                foreach ($order as $position => $id) {
                    ProductImage::query()->whereKey($id)->update(['sort_order' => $position, 'is_primary' => $position === 0]);
                }

                ProductGallery::normalize($locked, $order[0]);
            }

            return $image;
        });

        return response()->json([
            'message' => 'Product image moved successfully.',
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

    /**
     * VENDOR-MEDIA-02C: inside the attaching transaction, after the product lock. The media row is read with a shared
     * lock (lock order: product, then media; a library deletion takes only the media row, FOR UPDATE). A row deleted
     * meanwhile is refused with the same message as any image outside the library.
     */
    private function ensureStillInLibrary(Product $locked, string $path, string $field): void
    {
        if (! VendorLibraryImage::lockedInLibrary((int) $locked->vendor_id, $path)) {
            throw ValidationException::withMessages([$field => VendorLibraryImage::MESSAGE]);
        }
    }

    /**
     * VPM-01C: refuses (409) a path that another image of the product already has. Called inside the attaching
     * transaction after the product lock, which every image change of the product takes first, so the check and the
     * write cannot be interleaved with another attachment. Paths are compared exactly (the database comparison may
     * ignore case or trailing spaces). $except is the image being replaced. Historical duplicates are left as they are:
     * only a new duplicate is refused, nothing is removed or reordered.
     */
    private function ensureNotAttached(Product $locked, string $path, ?int $except = null): void
    {
        $taken = ProductImage::query()
            ->where('product_id', $locked->getKey())
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->pluck('path')
            ->contains(fn ($stored) => $stored === $path);

        if ($taken) {
            // Thrown inside the transaction, so it rolls back: nothing is written.
            throw new HttpResponseException(response()->json(['message' => self::DUPLICATE_MESSAGE, 'errors' => ['path' => [self::DUPLICATE_MESSAGE]]], 409));
        }
    }

    /**
     * G7-C2-3: the product row, locked for the rest of the transaction; every image change on it waits here.
     * VENDOR-PRODUCT-PUBLISH-DETAIL-02: an archived product's images are not the vendor's to change. Read on the locked
     * row (archiving locks it too), before anything is written, so a concurrent archive is either seen or waits.
     */
    private function lockProduct(Product $product): Product
    {
        $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

        abort_if($locked->isArchived(), 422, self::ARCHIVED_MESSAGE);

        return $locked;
    }

    /** The image read again under the lock: one removed meanwhile is a 404, as an unknown one would be. */
    private function lockedImage(Product $locked, ProductImage $productImage): ProductImage
    {
        return ProductImage::query()->whereKey($productImage->getKey())->where('product_id', $locked->getKey())->firstOr(fn () => abort(404));
    }
}
