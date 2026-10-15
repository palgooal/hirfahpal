<?php

namespace App\Support\Storefront;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;

class StorefrontContext
{
    public static function forRequest(Request $request): array
    {
        if ($request->attributes->has('_storefront_context')) {
            return $request->attributes->get('_storefront_context');
        }

        $setting = Setting::current();
        $customer = $request->user('customer');
        $cart = Cart::query()->where('status', 'active')
            ->when($customer, fn ($query) => $query->where('customer_id', $customer->id),
                fn ($query) => $query->whereNull('customer_id')->where('session_id', $request->session()->getId()))
            ->with('items.product')->first();
        $data = [
            'siteSetting' => $setting,
            'currency' => $setting?->default_currency ?: 'ILS',
            'cartCount' => $cart?->items->sum('quantity') ?? 0,
            'cartTotal' => $cart?->items->sum(fn ($item) => $item->quantity * ($item->product?->price ?? 0)) ?? 0,
            'navigationCategories' => Category::query()->where('is_active', true)->whereNull('parent_id')
                ->with(['imageMedia', 'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                    'products' => fn ($query) => $query->sellable()->with(['primaryImage', 'vendor.profile'])->latest('id')->limit(6),
                    'children.products' => fn ($query) => $query->sellable()->with(['primaryImage', 'vendor.profile'])->latest('id')->limit(3),
                ])
                ->orderBy('sort_order')->orderBy('id')->get(),
        ];
        $request->attributes->set('_storefront_context', $data);

        return $data;
    }
}
