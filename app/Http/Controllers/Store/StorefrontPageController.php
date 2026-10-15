<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StorefrontPageController extends Controller
{
    public function categories()
    {
        return view('pages.categories', ['categories' => Category::query()->where('is_active', true)
            ->with('imageMedia')->withCount(['children' => fn ($query) => $query->where('is_active', true),
                'products' => fn ($query) => $query->sellable()])->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function browse(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'sale' => ['nullable', 'boolean']]);

        return view('pages.browse', [
            'products' => $this->products()->when($request->boolean('sale'), fn ($query) => $query->whereColumn('compare_at_price', '>', 'price'))
                ->when($request->filled('q'), function (Builder $query) use ($request) {
                    $term = '%'.addcslashes($request->string('q')->trim()->toString(), '%_\\').'%';
                    $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('short_description', 'like', $term)
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term))
                        ->orWhereHas('vendor.profile', fn ($profile) => $profile->where('store_name', 'like', $term)));
                })->latest('published_at')->latest('id')->get(),
            'filterCategories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'filterVendors' => Vendor::query()->storefrontVisible()->with('profile')->orderBy('id')->get(),
            'filterCities' => City::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function product(Request $request)
    {
        $request->validate(['slug' => ['nullable', 'string', 'max:255'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:3']]);
        $product = $this->products()->when($request->filled('slug'), fn ($query) => $query->where('slug', $request->input('slug')))->latest('id')->first();
        if (! $product && ! $request->filled('slug')) {
            return redirect()->route('browse');
        }
        abort_unless($product, 404);
        $product->setRelation('vendor', Vendor::query()->withStorefrontSummary()->findOrFail($product->vendor_id));
        $reviews = $product->reviews()->where('status', 'published')->with('customer')->latest()->get();

        return view('pages.product', [
            'product' => $product,
            'selectedQuantity' => min($request->integer('quantity', 1), max(1, min(3, $product->availableStockQuantity()))),
            'productImages' => $product->images->isNotEmpty() ? $product->images->sortByDesc('is_primary')->values() : collect([new ProductImage(['alt_text' => $product->name])]),
            'productReviews' => $reviews,
            'reviewDistribution' => collect(range(1, 5))->mapWithKeys(fn ($rating) => [$rating => $reviews->isEmpty() ? 0 : (int) round($reviews->where('rating', $rating)->count() * 100 / $reviews->count())])->all(),
            'relatedProducts' => $this->products()->whereKeyNot($product->id)->where('category_id', $product->category_id)->latest('id')->limit(4)->get(),
        ]);
    }

    public function cart(Request $request, CartController $carts)
    {
        return view('pages.cart', $this->cartData($request, $carts));
    }

    public function checkout(Request $request, CartController $carts)
    {
        $data = $this->cartData($request, $carts);
        if ($data['cartItems']->isEmpty()) {
            return redirect()->route('cart')->withErrors(['cart' => 'السلة فارغة.']);
        }

        return view('pages.checkout', $data + [
            'addresses' => $request->user('customer')->addresses()->with(['city', 'governorate'])->orderByDesc('is_default')->orderBy('id')->get(),
            'governorates' => Governorate::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    private function products(): Builder
    {
        return Product::query()->sellable()->with(['category', 'vendor.profile.city', 'vendor.profile.governorate', 'primaryImage', 'images'])
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->withSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity');
    }

    private function cartData(Request $request, CartController $carts): array
    {
        $cart = $carts->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage']);
        $items = $cart->items->filter(fn ($item) => $item->product !== null);

        return [
            'cart' => $cart,
            'cartItems' => $items,
            'cartGroups' => $items->groupBy(fn ($item) => $item->product->vendor_id),
            'subtotal' => $items->sum(fn ($item) => $item->quantity * (float) $item->product->price),
            'deliveryTotal' => $items->pluck('product.vendor_id')->unique()->count() * max(0, (float) config('marketplace.delivery_fee', 0)),
        ];
    }
}
