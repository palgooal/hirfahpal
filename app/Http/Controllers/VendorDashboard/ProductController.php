<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductRequest;
use App\Http\Requests\VendorDashboard\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $product = DB::transaction(function () use ($vendor, $data) {
            $images = $data['images'] ?? [];
            unset($data['images']);

            $product = $vendor->products()->create($data);

            foreach ($images as $index => $image) {
                $product->images()->create([
                    'path' => $image['path'],
                    'alt_text' => $image['alt_text'] ?? $product->name,
                    'sort_order' => $image['sort_order'] ?? $index,
                    'is_primary' => (bool) ($image['is_primary'] ?? $index === 0),
                ]);
            }

            $this->normalizePrimaryImage($product);

            return $product->fresh(['category', 'images']);
        });

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

        $product->update($this->productData($request->validated(), $product));

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
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['published_at'] = $data['status'] === 'active' ? ($product?->published_at ?? now()) : null;

        return $data;
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless((int) $product->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    private function normalizePrimaryImage(Product $product): void
    {
        $primary = $product->images()->where('is_primary', true)->orderBy('sort_order')->first();

        if (! $primary) {
            $first = $product->images()->orderBy('sort_order')->first();
            $first?->update(['is_primary' => true]);

            return;
        }

        $product->images()
            ->whereKeyNot($primary->id)
            ->update(['is_primary' => false]);
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
