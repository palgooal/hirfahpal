<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'vendor.profile', 'primaryImage'])
            ->where('status', 'active')
            ->whereHas('vendor', fn ($query) => $query->where('status', 'active'))
            ->when($request->filled('category'), function ($query) use ($request): void {
                $query->whereHas('category', fn ($category) => $category->whereIn('slug', (array) $request->input('category')));
            })
            ->when($request->filled('vendor'), function ($query) use ($request): void {
                $query->whereHas('vendor.profile', fn ($profile) => $profile->whereIn('slug', (array) $request->input('vendor')));
            })
            ->when($request->filled('city'), function ($query) use ($request): void {
                $query->whereHas('vendor.profile.city', fn ($city) => $city->whereIn('slug', (array) $request->input('city')));
            })
            ->when($request->filled('price_min'), fn ($query) => $query->where('price', '>=', $request->decimal('price_min')))
            ->when($request->filled('price_max'), fn ($query) => $query->where('price', '<=', $request->decimal('price_max')));

        match ($request->input('sort')) {
            'price-asc' => $products->orderBy('price'),
            'price-desc' => $products->orderByDesc('price'),
            'newest' => $products->latest('published_at'),
            default => $products->latest(),
        };

        return response()->json($products->paginate($request->integer('per_page', 12)));
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->status === 'active', 404);

        return response()->json($product->load(['category', 'vendor.profile.city', 'images']));
    }

    public function categories(): JsonResponse
    {
        return response()->json(
            Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function vendors(): JsonResponse
    {
        return response()->json(
            Vendor::query()
                ->with('profile.city')
                ->where('status', 'active')
                ->whereHas('profile', fn ($profile) => $profile->where('approval_status', 'approved'))
                ->get()
        );
    }
}
