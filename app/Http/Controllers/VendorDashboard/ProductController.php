<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductRequest;
use App\Http\Requests\VendorDashboard\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
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
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

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

        try {
            $product = DB::transaction(function () use ($vendor, $data) {
                $images = $data['images'] ?? [];
                unset($data['images']);

                $product = $vendor->products()->create($data);

                // G7-C2-3 (V4): the sent order (sort_order, then the list order) with the first image sent as the main
                // one moved first; without one, the first image is the main one.
                $primaryId = null;
                foreach ($images as $index => $image) {
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
            'product' => $product->load(['category', 'images', 'stockReservations']),
            'available_stock' => $product->availableStockQuantity(),
            'reserved_stock' => $product->reservedStockQuantity(),
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

                $reserved = $lockedProduct->reservedStockQuantity();

                if ((int) $data['stock_quantity'] < $reserved) {
                    throw ValidationException::withMessages([
                        'stock_quantity' => UpdateProductRequest::reservedStockMessage($reserved),
                    ]);
                }

                $lockedProduct->update($data);

                return $lockedProduct;
            });
        } catch (QueryException $exception) {
            $this->failUnlessCategoryVanished($exception, $data);
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(['category', 'images']),
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
        // Status and storefront settings are intentionally not controlled by vendors.
        $data['status'] = $product?->status ?? 'draft';
        $data['is_featured'] = $product?->is_featured ?? false;
        $data['is_limited_edition'] = $product?->is_limited_edition ?? false;
        $data['highlight_tags'] = $product?->highlight_tags ?? [];
        $data['craft_tags'] = $product?->craft_tags ?? [];
        $data['published_at'] = $data['status'] === 'active' ? ($product?->published_at ?? now()) : null;

        return $data;
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
