<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\Storefront\BrowserResponse;
use App\Support\Storefront\GuestCartMerger;
use App\Support\Storefront\PublicCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly GuestCartMerger $guestCarts) {}

    public function show(Request $request): JsonResponse
    {
        // CART-SEC-01: every cart JSON response is the PublicCart allow-list, never the raw models.
        return response()->json(PublicCart::cart($this->activeCart($request)));
    }

    /** PRODUCT-FIX-02: the Arabic messages of a browser form's refused add-to-cart (JSON keeps the default ones). */
    private const FORM_MESSAGES = [
        'product_id.*' => 'هذا المنتج غير متاح للشراء حالياً.',
        'quantity.*' => 'يجب أن تكون الكمية رقماً صحيحاً لا يقل عن 1.',
    ];

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // PRODUCT-FIX-02: a browser form (not JSON) that cannot be added goes back to its page with an Arabic message
        // and the quantity it sent, instead of a bare error page; JSON requests keep their status codes and messages.
        // Every check still runs before anything is written.
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], $request->expectsJson() ? [] : self::FORM_MESSAGES);

        if (! $request->expectsJson() && ! Product::query()->sellable()->whereKey($data['product_id'])->exists()) {
            return $this->refuseForm($request, self::FORM_MESSAGES['product_id.*']);
        }
        $product = Product::query()->sellable()->findOrFail($data['product_id']);

        $available = $product->availableStockQuantity();
        if ($available < $data['quantity'] && ! $request->expectsJson()) {
            return $this->refuseForm($request, $available < 1
                ? 'هذا المنتج غير متوفر حالياً.'
                : 'الكمية المطلوبة غير متوفرة. المتوفر حالياً: '.$available.'.');
        }
        abort_if($available < $data['quantity'], 422, 'Requested quantity is not available.');

        $cart = $this->activeCart($request);
        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $nextQuantity = ($item->exists ? $item->quantity : 0) + $data['quantity'];
        $available = $product->availableStockQuantity();
        if ($available < $nextQuantity && ! $request->expectsJson()) {
            return $this->refuseForm($request, 'لا يمكن إضافة هذه الكمية: في سلتك '.$item->quantity.' من هذا المنتج، والمتوفر حالياً: '.$available.'.');
        }
        abort_if($available < $nextQuantity, 422, 'Requested quantity is not available.');

        $item->fill([
            'quantity' => $nextQuantity,
            'unit_price' => $product->sellingPrice(),
        ])->save();

        return BrowserResponse::saved($request, PublicCart::cart($cart->fresh()), 201,
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

        return BrowserResponse::saved($request, PublicCart::cart($this->activeCart($request)));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $this->authorizeCartItem($request, $cartItem);
        $cartItem->delete();

        return BrowserResponse::saved($request, PublicCart::cart($this->activeCart($request)));
    }

    /** PRODUCT-FIX-02: back to the form's page with the message (shown by the storefront layout) and the quantity sent. */
    private function refuseForm(Request $request, string $message): RedirectResponse
    {
        return back()->withErrors(['cart' => $message])->withInput($request->only('quantity'));
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
