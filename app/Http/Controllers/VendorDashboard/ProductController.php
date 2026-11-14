<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductRequest;
use App\Http\Requests\VendorDashboard\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Rules\VendorLibraryImage;
use App\Support\Products\ProductGallery;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $products = Product::query()
            ->with(['category', 'primaryImage'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            // VENDOR-PRODUCT-LIST-PAGINATION-01B: newest first, the id breaking ties, so products created in the same
            // second keep one order and every page holds its own slice (none twice, none missing).
            ->latest()
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            // VENDOR-PRODUCT-PUBLISH-UI-01E: each row's administrative state, as in show() (UI-01C): two booleans
            // added here, after the product's own serialization; the dates stay hidden (Product::$hidden).
            ->through(fn (Product $product) => $product->toArray() + [
                'moderation' => [
                    'archived' => $product->isArchived(),
                    'suspended' => $product->isSuspended(),
                ],
            ]);

        return response()->json([
            'products' => $products,
            'filters' => [
                'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
                'statuses' => $this->statuses(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $vendor = $request->user('vendor');
        $data = $this->productData($request->validated());
        $data = $this->publication(null, $data['status'] ?? 'draft') + $data;

        try {
            $product = DB::transaction(function () use ($vendor, $data) {
                $images = $data['images'] ?? [];
                unset($data['images']);

                $this->assertStoryImageIsAvailable((int) $vendor->id, $data['story_image_path'] ?? null);

                $product = $vendor->products()->create($data);

                // G7-C2-3 (V4): the sent order (sort_order, then the list order) with the first image sent as the main
                // one moved first; without one, the first image is the main one.
                $primaryId = null;
                $paths = [];
                foreach ($images as $index => $image) {
                    // VENDOR-MEDIA-02C: each library row read again under a shared lock inside this transaction, so a
                    // concurrent library deletion either commits first (refused here) or waits for this commit.
                    if (! VendorLibraryImage::lockedInLibrary((int) $vendor->id, $image['path'])) {
                        throw ValidationException::withMessages(["images.{$index}.path" => VendorLibraryImage::MESSAGE]);
                    }
                    // VPM-01C: each path once (exactly); a repeat rolls the whole product back.
                    if (isset($paths[$image['path']])) {
                        throw ValidationException::withMessages(["images.{$index}.path" => ProductImageController::DUPLICATE_MESSAGE]);
                    }
                    $paths[$image['path']] = true;

                    $created = $product->images()->create([
                        'path' => $image['path'],
                        'alt_text' => $image['alt_text'] ?? $product->name,
                        'sort_order' => $image['sort_order'] ?? $index,
                        'is_primary' => false,
                    ]);
                    $primaryId ??= ! empty($image['is_primary']) ? $created->id : null;
                }

                ProductGallery::normalize($product, $primaryId);

                return $product->fresh(['category', 'images']);
            });
        } catch (QueryException $exception) {
            $this->failUnlessCategoryVanished($exception, $data);
        }

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        return response()->json([
            'product' => $product->load(['category', 'city', 'governorate', 'images', 'stockReservations']),
            'available_stock' => $product->availableStockQuantity(),
            'reserved_stock' => $product->reservedStockQuantity(),
            // VENDOR-PRODUCT-PUBLISH-UI-01C (DETAIL-02, DETAIL-03): the administrative state for the editor, read-only
            // and as two booleans; the dates stay hidden on the product (Product::$hidden), reasons stay with the admin.
            'moderation' => [
                'archived' => $product->isArchived(),
                'suspended' => $product->isSuspended(),
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $data = $this->productData($request->validated(), $product);

        // The FormRequest rule gives early feedback; this is the authoritative check
        // (VEN-BE-019). Product lock first, as in checkout and vendor accept, then the
        // outstanding reserved quantity is re-read under that lock before writing.
        try {
            $product = DB::transaction(function () use ($request, $product, $data): Product {
                $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedProduct->vendor_id === (int) $request->user('vendor')->id, 404);
                // DETAIL-02: an archived product is not editable by its vendor; read under the lock archiving takes too.
                abort_if($lockedProduct->isArchived(), 422, 'This product is archived and cannot be edited.');

                // The status transition and published_at follow the locked row, not the one bound before the lock.
                $data = $this->publication($lockedProduct, $data['status'] ?? $lockedProduct->status) + $data;

                $reserved = $lockedProduct->reservedStockQuantity();

                if ((int) $data['stock_quantity'] < $reserved) {
                    throw ValidationException::withMessages([
                        'stock_quantity' => UpdateProductRequest::reservedStockMessage($reserved),
                    ]);
                }

                if (($data['story_image_path'] ?? null) !== $lockedProduct->story_image_path) {
                    $this->assertStoryImageIsAvailable((int) $request->user('vendor')->id, $data['story_image_path'] ?? null);
                }

                $lockedProduct->update($data);

                return $lockedProduct;
            });
        } catch (QueryException $exception) {
            $this->failUnlessCategoryVanished($exception, $data);
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(['category', 'city', 'governorate', 'images']),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        abort_if($product->stockReservations()->where('status', 'reserved')->exists(), 422, 'Product has reserved stock and cannot be deleted.');

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    private function productData(array $data, ?Product $product = null): array
    {
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $product);
        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 1;
        if (array_key_exists('weight', $data)) {
            $data['weight_unit'] = $data['weight'] === null ? null : ($data['weight_unit'] ?? null);
        }
        // Storefront settings are intentionally not controlled by vendors. The status is (publication() sets it).
        if (($data['status'] ?? null) === null) {
            unset($data['status']);
        }
        $data['is_featured'] = $product?->is_featured ?? false;
        $data['is_limited_edition'] = $product?->is_limited_edition ?? false;
        $data['highlight_tags'] = $product?->highlight_tags ?? [];
        $data['craft_tags'] = $product?->craft_tags ?? [];

        return $data;
    }

    /** The request validates ownership early; lock a newly selected story image again while saving. */
    private function assertStoryImageIsAvailable(int $vendorId, mixed $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (! is_string($path) || ! VendorLibraryImage::lockedInLibrary($vendorId, $path)) {
            throw ValidationException::withMessages(['story_image_path' => VendorLibraryImage::MESSAGE]);
        }
    }

    /**
     * VENDOR-PRODUCT-PUBLISH-DECISION-01 (OPEN-PROD-22, DETAIL-04): the status and published_at written for a move from
     * the stored row ($current, null on create) to $status. Publishing (any other status to active) sets a fresh
     * published_at, so a restored draft's kept date is never reused; staying active keeps it; leaving active clears it.
     * Between two unpublished statuses the stored value stays as it is (null, or the date a restore kept, OPEN-PROD-04B).
     * Suspension and archiving are separate columns and are not touched here.
     *
     * @return array{status: string, published_at: mixed}
     */
    private function publication(?Product $current, string $status): array
    {
        $wasActive = $current?->status === 'active';

        $publishedAt = match (true) {
            $status === 'active' => $wasActive ? ($current->published_at ?? now()) : now(),
            $wasActive => null,
            default => $current?->published_at,
        };

        return ['status' => $status, 'published_at' => $publishedAt];
    }

    /**
     * The write failed after validation, inside the transaction (now rolled back, so no product or image
     * row is left). If it was a foreign key failure and the submitted category no longer exists (deleted
     * in between), report it exactly as the category_id rule would; anything else is re-thrown unchanged.
     */
    private function failUnlessCategoryVanished(QueryException $exception, array $data): never
    {
        // MySQL 1452 (child row references a missing parent) or SQLite's foreign key failure.
        $foreignKeyFailure = ($exception->errorInfo[1] ?? null) === 1452
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');

        if ($foreignKeyFailure && filled($data['category_id'] ?? null)) {
            Validator::make(['category_id' => $data['category_id']], ['category_id' => ['exists:categories,id']])->validate();
        }

        throw $exception;
    }

    /**
     * VENDOR-PRODUCT-LIST-PAGINATION-01B: the list's page size. Missing, not a whole number ("abc", "2.5", an array)
     * or below 1: the default, 15. Above 50: 50. Never unbounded.
     */
    private function perPage(Request $request): int
    {
        $given = $request->query('per_page');
        $value = is_string($given) ? filter_var($given, FILTER_VALIDATE_INT) : false;

        if ($value === false || $value < 1) {
            return 15;
        }

        return min($value, 50);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless((int) $product->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    private function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'out_of_stock' => 'Out of stock',
        ];
    }

    /** @param array<int, string> $tags */
    private function tags(array $tags): array
    {
        return collect($tags)->map(fn (string $tag) => trim($tag))->filter()->unique()->values()->all();
    }

    private function uniqueSlug(string $value, ?Product $product = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $counter = 2;

        while (Product::query()
            ->when($product?->exists, fn ($query) => $query->whereKeyNot($product->id))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
