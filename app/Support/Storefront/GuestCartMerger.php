<?php

namespace App\Support\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * CUSTOMER-AUTH-A1 (F-1): moves the guest cart of a session into a customer's active cart, with the cart's
 * existing merge rules (quantities are added per product, the guest line's unit price is kept; availability and
 * stock are checked later by the cart and checkout, as before).
 *
 * Login and registration regenerate the session id, so the guest cart must be found by the id of the session
 * before it is regenerated (guestCartId()), then merged once the customer is authenticated (merge()). The id comes
 * from the server-side session only, never from the request.
 */
class GuestCartMerger
{
    /** The active guest cart of this session id, if any. */
    public function guestCartId(string $sessionId): ?int
    {
        return Cart::query()
            ->whereNull('customer_id')
            ->where('session_id', $sessionId)
            ->where('status', 'active')
            ->value('id');
    }

    /**
     * Merges the guest cart into the customer's active cart, in one transaction. The guest cart is read again
     * locked FOR UPDATE and must still be an active guest cart, so a second call (a repeated request, a parallel
     * login of the same session) finds it abandoned and changes nothing. An empty guest cart creates no customer
     * cart. The guest cart is marked abandoned, never deleted, in the same transaction as the merged lines.
     *
     * @return bool whether anything was merged
     */
    public function merge(int $guestCartId, Customer $customer): bool
    {
        return DB::transaction(function () use ($guestCartId, $customer): bool {
            $guestCart = Cart::query()
                ->whereKey($guestCartId)
                ->whereNull('customer_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $guestCart) {
                return false;
            }

            $guestItems = $guestCart->items()->get();

            if ($guestItems->isEmpty()) {
                return false;
            }

            $customerCart = Cart::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'status' => 'active',
            ]);

            foreach ($guestItems as $guestItem) {
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

            return true;
        });
    }

    /**
     * merge() right after a successful customer login or registration. The authentication has already succeeded
     * and stays valid: a failed merge is rolled back as a whole (the guest cart keeps its lines and stays active)
     * and reported, not turned into an error page for a signed-in customer.
     */
    public function mergeAfterAuthentication(?int $guestCartId, Customer $customer): void
    {
        if ($guestCartId === null) {
            return;
        }

        try {
            $this->merge($guestCartId, $customer);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
