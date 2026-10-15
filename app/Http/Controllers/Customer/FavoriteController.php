<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerFavoriteRequest;
use App\Models\CustomerFavorite;
use App\Models\Product;
use App\Support\Storefront\BrowserResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.customer-favorites', [
            'favorites' => $request->user('customer')
                ->favorites()
                ->whereHas('product', fn ($product) => $product->sellable())
                ->with(['product.vendor.profile', 'product.primaryImage', 'product.category',
                    'product' => fn ($product) => $product->withCount(['reviews' => fn ($reviews) => $reviews->where('status', 'published')])
                        ->withAvg(['reviews' => fn ($reviews) => $reviews->where('status', 'published')], 'rating'),
                ])
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreCustomerFavoriteRequest $request): JsonResponse|RedirectResponse
    {
        $product = Product::query()->sellable()->findOrFail($request->integer('product_id'));

        $favorite = CustomerFavorite::query()->firstOrCreate([
            'customer_id' => $request->user('customer')->id,
            'product_id' => $product->id,
        ]);

        return BrowserResponse::saved($request, $favorite->load(['product.vendor.profile', 'product.primaryImage']), $favorite->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, CustomerFavorite $favorite): JsonResponse|RedirectResponse
    {
        abort_unless((int) $favorite->customer_id === (int) $request->user('customer')->id, 403);

        $favorite->delete();

        return BrowserResponse::saved($request, ['message' => 'Favorite removed.']);
    }
}
