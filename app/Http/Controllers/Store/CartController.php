<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\Storefront\BrowserResponse;
use App\Support\Storefront\GuestCartMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly GuestCartMerger $guestCarts) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage'])
        );
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->sellable()->findOrFail($data['product_id']);
        abort_if($product->availableStockQuantity() < $data['quantity'], 422, 'Requested quantity is not available.');

        $cart = $this->activeCart($request);
        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $nextQuantity = ($item->exists ? $item->quantity : 0) + $data['quantity'];
        abort_if($product->availableStockQuantity() < $nextQuantity, 422, 'Requested quantity is not available.');

        $item->fill([
            'quantity' => $nextQuantity,
            'unit_price' => $product->sellingPrice(),
        ])->save();

        return BrowserResponse::saved($request, $cart->fresh(['items.product.vendor.profile', 'items.product.primaryImage']), 201,
            $request->boolean('checkout') ? 'customer.checkout.show' : null);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->authorizeCartItem($request, $cartItem);
        abort_unless($cartItem->product->isSellable(), 422, 'This product is no longer available for sale.');
        abort_if($cartItem->product->availableStockQuantity() < $data['quantity'], 422, 'Requested quantity is not available.');

        $cartItem->update(['quantity' => $data['quantity']]);

        return BrowserResponse::saved($request, $this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage']));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $this->authorizeCartItem($request, $cartItem);
        $cartItem->delete();

        return BrowserResponse::saved($request, $this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage']));
    }

    public function activeCart(Request $request): Cart
    {
        if ($customer = $request->user('customer')) {
            $customerCart = Cart::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'status' => 'active',
            ]);

            // A guest cart of this same session (the login and registration merge theirs before the session id
            // changes, CUSTOMER-AUTH-A1).
            if ($guestCartId = $this->guestCarts->guestCartId($request->session()->getId())) {
                $this->guestCarts->merge($guestCartId, $customer);
            }

            return $customerCart;
        }

        return Cart::query()->firstOrCreate([
            'session_id' => $request->session()->getId(),
            'status' => 'active',
        ]);
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        $cart = $cartItem->cart;

        if ($customer = $request->user('customer')) {
            abort_unless($cart->customer_id === $customer->id, 403);

            return;
        }

        abort_unless($cart->session_id === $request->session()->getId(), 403);
    }
}
