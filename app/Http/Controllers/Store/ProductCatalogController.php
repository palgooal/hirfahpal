<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Storefront\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    /** SEC-01: the largest page a visitor may request. */
    public const MAX_PER_PAGE = 48;

    public function index(Request $request): JsonResponse
    {
        // SEC-01: a bounded page size (and a valid page), refused with 422 otherwise.
        $paging = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = Product::query()
            ->with(['category', 'vendor.profile.city', 'vendor.profile.governorate', 'primaryImage'])
            ->withSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity')
            ->sellable()
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

        // SEC-01: public fields only (PublicCatalog), in the same paginator envelope; its links keep the filters.
        return response()->json(
            $products->paginate((int) ($paging['per_page'] ?? 12))
                ->withQueryString()
                ->through(fn (Product $product) => PublicCatalog::productSummary($product))
        );
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->isSellable(), 404);

        // SEC-01: public fields only, never the raw model with its vendor account and store records.
        return response()->json(PublicCatalog::productDetails(
            $product->load(['category', 'vendor.profile.city', 'vendor.profile.governorate', 'primaryImage', 'images'])
        ));
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
        // SEC-01: each approved, active store's public identity only (no account, contact or administrative data).
        return response()->json(
            Vendor::query()
                ->with(['profile.city', 'profile.governorate'])
                ->where('status', 'active')
                ->whereHas('profile', fn ($profile) => $profile->where('approval_status', 'approved'))
                ->get()
                ->map(fn (Vendor $vendor) => PublicCatalog::store($vendor->profile))
                ->values()
        );
    }
}
