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
        abort_if($product->stock_quantity < $data['quantity'], 422, 'Requested quantity is not available.');

        $cart = $this->activeCart($request);
        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $nextQuantity = ($item->exists ? $item->quantity : 0) + $data['quantity'];
        abort_if($product->stock_quantity < $nextQuantity, 422, 'Requested quantity is not available.');

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
        abort_if($cartItem->product->stock_quantity < $data['quantity'], 422, 'Requested quantity is not available.');

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
        return Cart::query()->firstOrCreate([
            'customer_id' => $request->user('customer')->id,
            'status' => 'active',
        ]);
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->cart->customer_id === $request->user('customer')->id, 403);
    }
}
