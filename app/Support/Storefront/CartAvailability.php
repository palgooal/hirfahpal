<?php

namespace App\Support\Storefront;

use App\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * CART-FIX-01: what keeps a cart line from checkout, read from the same rules the cart and checkout enforce
 * (Product::isSellable(), Product::availableStockQuantity()): a product no longer for sale, a product out of stock,
 * or a quantity above the stock available now. Only describes; the checkout's own checks stay authoritative.
 */
class CartAvailability
{
    public const UNAVAILABLE = 'unavailable';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const EXCEEDS_STOCK = 'exceeds_stock';

    public const CHECKOUT_BLOCKED = 'لا يمكن إتمام الطلب حتى تعالج المنتجات المشار إليها في السلة: خفّض كميتها إلى المتوفر أو أزلها.';

    public const CHANGED_DURING_CHECKOUT = 'تغيّر توفر بعض المنتجات أثناء إتمام الطلب. راجع سلتك ثم أعد المحاولة.';

    /** @return array{type: string, available: int, message: string}|null the line's issue, null when it can be ordered */
    public static function issue(CartItem $item): ?array
    {
        $product = $item->product;

        if ($product === null || ! $product->isSellable()) {
            return ['type' => self::UNAVAILABLE, 'available' => 0,
                'message' => 'هذا المنتج لم يعد متاحاً للشراء. أزله من السلة لإتمام الطلب.'];
        }

        $available = $product->availableStockQuantity();

        if ($available < 1) {
            return ['type' => self::OUT_OF_STOCK, 'available' => 0,
                'message' => 'نفد مخزون هذا المنتج حالياً. أزله من السلة لإتمام الطلب.'];
        }

        if ($item->quantity > $available) {
            return ['type' => self::EXCEEDS_STOCK, 'available' => $available,
                'message' => 'الكمية في سلتك ('.$item->quantity.') أكبر من المتوفر حالياً ('.$available.'). خفّض الكمية أو أزل المنتج.'];
        }

        return null;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array{type: string, available: int, message: string}> the issues by cart line id
     */
    public static function issues(Collection $items): array
    {
        return $items->mapWithKeys(fn (CartItem $item) => [$item->id => self::issue($item)])->filter()->all();
    }
}
