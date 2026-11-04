<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\Storefront\BrowserResponse;
use App\Support\Storefront\CartLock;
use App\Support\Storefront\GuestCartMerger;
use App\Support\Storefront\PublicCart;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly GuestCartMerger $guestCarts) {}

    public function show(Request $request): JsonResponse
    {
        // CART-SEC-01: every cart JSON response is the PublicCart allow-list, never the raw models.
        // CART-FIX-02B: reading the cart creates and merges nothing; with no cart yet, an empty one with a null id.
        return response()->json(PublicCart::cart($this->findActiveCart($request), $request->user('customer')?->id));
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

        // CART-FIX-02C: the cart is found or created, and the line read, checked against the stock and written, while
        // holding this owner's cart lock: two adds of the same cart never interleave (one active cart, one line, every
        // accepted quantity counted, the stock limit applied to the combined quantity).
        return $this->whileLocked($request, function () use ($request, $product, $data) {
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
        });
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ], $request->expectsJson() ? [] : self::FORM_MESSAGES);

        $this->authorizeCartItem($request, $cartItem);

        // CART-FIX-02C: applied while holding the owner's cart lock, to the line as it is then (re-read: a concurrent
        // write may have changed or removed it while this request waited).
        return $this->whileLocked($request, function () use ($request, $cartItem, $data) {
            $cartItem = CartItem::query()->findOrFail($cartItem->id);

            // CART-FIX-01: a browser form that cannot apply the change goes back to the cart with an Arabic message (as
            // store() does since PRODUCT-FIX-02); JSON keeps its status codes and messages. Same checks, same order.
            if (! $request->expectsJson() && ! $cartItem->product->isSellable()) {
                return $this->refuseForm($request, self::FORM_MESSAGES['product_id.*']);
            }
            abort_unless($cartItem->product->isSellable(), 422, 'This product is no longer available for sale.');

            $available = $cartItem->product->availableStockQuantity();
            if ($available < $data['quantity'] && ! $request->expectsJson()) {
                return $this->refuseForm($request, $available < 1
                    ? 'هذا المنتج غير متوفر حالياً.'
                    : 'الكمية المطلوبة غير متوفرة. المتوفر حالياً: '.$available.'.');
            }
            abort_if($available < $data['quantity'], 422, 'Requested quantity is not available.');

            $cartItem->update(['quantity' => $data['quantity']]);

            return BrowserResponse::saved($request, PublicCart::cart($this->activeCart($request)));
        });
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        // CART-FIX-02C: while holding the owner's cart lock; a line already removed meanwhile is simply gone.
        return $this->whileLocked($request, function () use ($request, $cartItem) {
            CartItem::query()->whereKey($cartItem->id)->delete();

            return BrowserResponse::saved($request, PublicCart::cart($this->activeCart($request)));
        });
    }

    /**
     * CART-FIX-02C: runs a cart write holding this request's cart-owner locks (CartLock). When another write of the
     * same cart holds them past the wait, nothing is written: a browser form goes back with an Arabic message, JSON
     * gets 409 (the request can simply be sent again).
     */
    private function whileLocked(Request $request, Closure $write): JsonResponse|RedirectResponse
    {
        try {
            return CartLock::forRequest($request, $write);
        } catch (LockTimeoutException) {
            return $request->expectsJson()
                ? response()->json(['message' => 'The cart is being updated by another request. Try again.'], 409)
                : back()->withErrors(['cart' => 'السلة قيد التحديث في طلب آخر. حاول مرة أخرى.'])->withInput($request->only('quantity'));
        }
    }

    /** PRODUCT-FIX-02: back to the form's page with the message (shown by the storefront layout) and the quantity sent. */
    private function refuseForm(Request $request, string $message): RedirectResponse
    {
        return back()->withErrors(['cart' => $message])->withInput($request->only('quantity'));
    }

    /**
     * CART-FIX-02B: the active cart of this customer or guest session, if there is one, for requests that only read
     * it (the cart and checkout pages, the cart JSON): the same match as activeCart(), but nothing is created and no
     * guest cart is merged. Writes (add, update, remove), login and registration still use activeCart() / the merger.
     */
    public function findActiveCart(Request $request): ?Cart
    {
        if ($customer = $request->user('customer')) {
            return Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->first();
        }

        return Cart::query()->where('session_id', $request->session()->getId())->where('status', 'active')->first();
    }

    /**
     * The active cart of this customer or guest session, created if there is none; for a customer, the guest cart of
     * this session is merged into it. CART-FIX-02C: a write; call it only while holding CartLock::forRequest().
     */
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
