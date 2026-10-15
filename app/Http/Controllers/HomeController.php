<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $siteSetting = Setting::current();
        $products = Product::query()->sellable()
            ->with(['category', 'vendor.profile.city', 'primaryImage', 'images'])
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn ($query) => $query->whereHas('vendorOrder', fn ($order) => $order->whereIn('status', ['delivered', 'completed']))], 'quantity')
            ->withSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity');

        $vendors = Vendor::query()->where('status', 'active')
            ->whereHas('profile', fn ($query) => $query->where('approval_status', 'approved'));
        $customer = $request->user('customer');
        $cart = Cart::query()->where('status', 'active')
            ->when($customer, fn ($query) => $query->where('customer_id', $customer->id),
                fn ($query) => $query->whereNull('customer_id')->where('session_id', $request->session()->getId()))
            ->with('items.product')->first();

        return view('pages.home', [
            'siteSetting' => $siteSetting,
            'homeContent' => array_replace(config('homepage', []), $siteSetting?->home_content ?? []),
            'currency' => $siteSetting?->default_currency ?: 'ILS',
            'popularProducts' => (clone $products)->orderByDesc('sold_quantity')->orderByDesc('reviews_count')->latest('id')->limit(4)->get(),
            'newProducts' => (clone $products)->orderByDesc('published_at')->latest('id')->limit(4)->get(),
            'seasonProducts' => (clone $products)->where('is_featured', true)->latest('id')->limit(4)->get(),
            'homeCategories' => Category::query()->where('is_active', true)->with('imageMedia')
                ->withCount(['products' => fn ($query) => $query->sellable()])->orderBy('sort_order')->orderBy('id')->limit(4)->get(),
            'filterCategories' => Category::query()->where('is_active', true)->whereHas('products', fn ($query) => $query->sellable())->orderBy('sort_order')->get(),
            'artisans' => (clone $vendors)->with(['profile.city', 'profile.governorate'])
                ->withCount(['products' => fn ($query) => $query->sellable()])->latest('id')->limit(3)->get(),
            'artisanCount' => $vendors->count(),
            'favoriteIds' => $customer?->favorites()->pluck('id', 'product_id')->all() ?? [],
            'cartCount' => $cart?->items->sum('quantity') ?? 0,
            'cartTotal' => $cart?->items->sum(fn ($item) => $item->quantity * ($item->product?->price ?? 0)) ?? 0,
        ]);
    }
}
