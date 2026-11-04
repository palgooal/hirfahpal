<?php

namespace App\Support\Storefront;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * CART-FIX-02C: one lock per cart owner (a customer, or a guest session), so two writes of the same owner's cart
 * (add, update, remove, guest merge) never interleave: no second active cart, no duplicate line, no lost increment.
 * A row lock cannot do this (the cart may not exist yet), so the lock is the cache store's atomic lock (the project's
 * store is the database: an insert on cache_locks, atomic across processes; no schema change).
 *
 * Rules: a customer lock is always taken before a session lock (a guest write takes only its session lock), so two
 * requests can never wait for each other in a cycle; locks are taken before any database transaction (a lock row
 * inserted inside an open transaction would be invisible to other processes until it commits); the callback must not
 * take the same lock again (the locks are not re-entrant). The session id is hashed in the lock name, so the raw id is
 * never stored.
 */
class CartLock
{
    /** Seconds a lock lives if its holder dies without releasing it. */
    private const LOCK_SECONDS = 10;

    /**
     * Runs $callback holding the locks of this request's cart: the customer's (when signed in) then the session's.
     *
     * @throws LockTimeoutException when another write of the same cart holds them past the wait (config cart.lock_wait)
     */
    public static function forRequest(Request $request, Closure $callback): mixed
    {
        return self::holding(array_filter([
            self::customerKey($request->user('customer')),
            self::sessionKey($request->session()->getId()),
        ]), $callback);
    }

    /**
     * Runs $callback holding the customer's lock then the lock of the guest cart's session (a merge after login or
     * registration, where the guest cart belongs to the session id from before the session was regenerated).
     *
     * @throws LockTimeoutException
     */
    public static function forMerge(Customer $customer, ?string $guestSessionId, Closure $callback): mixed
    {
        return self::holding(array_filter([self::customerKey($customer), self::sessionKey($guestSessionId)]), $callback);
    }

    /** @param list<string> $keys acquired in this order, released in reverse */
    private static function holding(array $keys, Closure $callback): mixed
    {
        if ($keys === []) {
            return $callback();
        }

        $key = array_shift($keys);

        return Cache::lock($key, self::LOCK_SECONDS)
            ->block((int) config('cart.lock_wait', 5), fn () => self::holding($keys, $callback));
    }

    /** The lock name of a customer's cart. */
    public static function customerKey(?Customer $customer): ?string
    {
        return $customer ? 'cart-owner:customer:'.$customer->id : null;
    }

    /** The lock name of a guest session's cart. */
    public static function sessionKey(?string $sessionId): ?string
    {
        return $sessionId ? 'cart-owner:session:'.hash('sha256', $sessionId) : null;
    }
}
