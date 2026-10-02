<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->where('status', 'active')->findOrFail($data['product_id']);
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
            'unit_price' => $product->price,
        ])->save();

        return response()->json($cart->fresh(['items.product.vendor.profile', 'items.product.primaryImage']), 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->authorizeCartItem($request, $cartItem);
        abort_if($cartItem->product->availableStockQuantity() < $data['quantity'], 422, 'Requested quantity is not available.');

        $cartItem->update(['quantity' => $data['quantity']]);

        return response()->json($this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage']));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);
        $cartItem->delete();

        return response()->json($this->activeCart($request)->load(['items.product.vendor.profile', 'items.product.primaryImage']));
    }

    private function activeCart(Request $request): Cart
    {
        if ($customer = $request->user('customer')) {
            $customerCart = Cart::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'status' => 'active',
            ]);

            $guestCart = Cart::query()
                ->with('items')
                ->where('session_id', $request->session()->getId())
                ->whereNull('customer_id')
                ->where('status', 'active')
                ->first();

            if ($guestCart) {
                $this->mergeGuestCart($guestCart, $customerCart);
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

    private function mergeGuestCart(Cart $guestCart, Cart $customerCart): void
    {
        foreach ($guestCart->items as $guestItem) {
            $customerItem = CartItem::query()->firstOrNew([
                'cart_id' => $customerCart->id,
                'product_id' => $guestItem->product_id,
            ]);

            $customerItem->fill([
                'quantity' => ($customerItem->exists ? $customerItem->quantity : 0) + $guestItem->quantity,
                'unit_price' => $guestItem->unit_price,
            ])->save();
        }

        $guestCart->update(['status' => 'abandoned']);
    }
}
