<?php

namespace App\Support\Storefront;

use App\Models\Cart;
use App\Models\CartItem;

/**
 * CART-SEC-01: a cart as its JSON responses publish it (customer.cart.show and the item store / update / destroy),
 * built field by field: the cart's id, owner and status, and each line's id, product, quantity and stored unit price,
 * with the product as PublicCatalog shows it to any visitor. Never the session id, the vendor account or the store's
 * private records, so a model attribute or relation added later is not published by default.
 */
class PublicCart
{
    /** The relations cart() reads, to eager load them in one go. */
    public const RELATIONS = [
        'items.product.category',
        'items.product.vendor.profile.city',
        'items.product.vendor.profile.governorate',
        'items.product.primaryImage',
    ];

    /**
     * CART-FIX-02B: with no cart (nothing created on a read), the same keys: a null id, the requester's customer id
     * (null for a guest), the active status and no items.
     *
     * @return array<string, mixed>
     */
    public static function cart(?Cart $cart, ?int $customerId = null): array
    {
        if ($cart === null) {
            return ['id' => null, 'customer_id' => $customerId, 'status' => 'active', 'items' => []];
        }

        $cart->loadMissing(self::RELATIONS);

        return [
            'id' => $cart->id,
            'customer_id' => $cart->customer_id,
            'status' => $cart->status,
            'items' => $cart->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'product' => $item->product ? PublicCatalog::productSummary($item->product) : null,
            ])->values()->all(),
        ];
    }
}
