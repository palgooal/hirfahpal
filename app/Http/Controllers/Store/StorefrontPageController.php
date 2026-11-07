<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Vendor;
use App\Support\Storefront\CartAvailability;
use App\Support\Storefront\ProductPageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'sale' => ['nullable', 'boolean'],
            'priceMin' => ['nullable', 'numeric', 'min:0'],
            'priceMax' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(['newest', 'price-asc', 'price-desc', 'rating-desc'])],
        ]);

        $categories = $this->filterValues($request, 'category');
        $vendors = $this->filterValues($request, 'vendor');
        $cities = $this->filterValues($request, 'city');

        $products = $this->products()
            ->when($request->boolean('sale'), fn (Builder $query) => $query->whereColumn('compare_at_price', '<', 'price'))
            ->when($categories !== [], fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->whereIn('slug', $categories)))
            ->when($vendors !== [], fn (Builder $query) => $query->whereHas('vendor.profile', fn (Builder $profile) => $profile->whereIn('slug', $vendors)))
            ->when($cities !== [], fn (Builder $query) => $query->whereHas('vendor.profile.city', fn (Builder $city) => $city->whereIn('slug', $cities)))
            ->when($request->filled('priceMin'), fn (Builder $query) => $query->where('price', '>=', $request->input('priceMin')))
            ->when($request->filled('priceMax'), fn (Builder $query) => $query->where('price', '<=', $request->input('priceMax')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = '%'.addcslashes($request->string('q')->trim()->toString(), '%_\\').'%';

                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('short_description', 'like', $term)
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $term))
                    ->orWhereHas('vendor.profile', fn (Builder $profile) => $profile->where('store_name', 'like', $term)));
            });

        match ($request->input('sort', 'newest')) {
            'price-asc' => $products->orderBy('price')->orderBy('id'),
            'price-desc' => $products->orderByDesc('price')->orderByDesc('id'),
            'rating-desc' => $products->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->latest('published_at')->latest('id'),
            default => $products->latest('published_at')->latest('id'),
        };

        return view('pages.browse', [
            'products' => $products->get(),
            'filterCategories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'filterVendors' => Vendor::query()->storefrontVisible()->with('profile')->orderBy('id')->get(),
            'filterCities' => City::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function product(Request $request)
    {
        // PRODUCT-FIX-02: a product page needs its slug: a non-empty string of at most 255 characters (empty and
        // whitespace-only values arrive as null), otherwise 404; it never falls back to another product.
        $slug = $request->query('slug');
        abort_unless(is_string($slug) && $slug !== '' && mb_strlen($slug) <= 255, 404);
        $product = $this->products()->where('slug', $slug)->first();
        abort_unless($product, 404);

        // PREVIEW-01: the page's data comes from ProductPageData (shared with the vendor's preview later).
        return view('pages.product', ProductPageData::viewData($product, $request));
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
        // CART-FIX-01: no checkout page (and no payable total) while a cart line cannot be ordered.
        if (! $data['checkoutReady']) {
            return redirect()->route('cart')->withErrors(['cart' => CartAvailability::CHECKOUT_BLOCKED]);
        }

        return view('pages.checkout', $data + [
            'addresses' => $request->user('customer')->addresses()->with(['city', 'governorate'])->orderByDesc('is_default')->orderBy('id')->get(),
            'governorates' => Governorate::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /** The products a customer may see, with the storefront's product data (PREVIEW-01: ProductPageData). */
    private function products(): Builder
    {
        return ProductPageData::sellable();
    }

    private function cartData(Request $request, CartController $carts): array
    {
        // CART-FIX-02B: the cart and checkout pages only read the cart: no cart is created and no guest cart merged on
        // a GET; without a cart they show the empty state (the checkout page sends an empty cart back to the cart).
        $cart = $carts->findActiveCart($request)?->load(['items.product.vendor.profile', 'items.product.primaryImage']);
        $items = ($cart?->items ?? new EloquentCollection)->filter(fn ($item) => $item->product !== null);
        // CART-FIX-01: each line that cannot be ordered as it is, and whether the cart can go to checkout.
        $issues = CartAvailability::issues($items);

        return [
            'cart' => $cart,
            'cartItems' => $items,
            'cartIssues' => $issues,
            'checkoutReady' => $items->isNotEmpty() && $issues === [],
            'cartGroups' => $items->groupBy(fn ($item) => $item->product->vendor_id),
            'subtotal' => $items->sum(fn ($item) => $item->quantity * $item->product->sellingPrice()),
            'deliveryTotal' => $items->pluck('product.vendor_id')->unique()->count() * max(0, (float) config('marketplace.delivery_fee', 0)),
        ];
    }

    /**
     * Accept both the single-value links emitted by storefront cards and the
     * repeated values submitted by the browse filter checkboxes.
     *
     * @return list<string>
     */
    private function filterValues(Request $request, string $key): array
    {
        $values = $request->input($key, []);

        return collect(is_array($values) ? $values : [$values])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }
}
