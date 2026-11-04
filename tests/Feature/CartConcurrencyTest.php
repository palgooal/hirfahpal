<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Storefront\CartLock;
use App\Support\Storefront\GuestCartMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CART-FIX-02C (CART-FIX-02A F2, F3, F6): every cart write (add, update, remove, guest merge) runs holding its owner's
 * cart lock (CartLock), so a concurrent write of the same cart cannot land between its read and its write.
 *
 * SIMULATED races (one PHP process): at the exact interleaving point of CART-FIX-02A (the model event fired between
 * the read and the write), the competing request is played only if it could obtain the owner's lock, as a real one
 * would have to. Before CART-FIX-02C it could, and wrote into the window (two carts, a duplicate-key 500, a lost
 * increment); now it cannot, so it runs after the first request, as a real request waiting for the lock does, and
 * every accepted quantity is counted. Genuine multi-process runs are in the CART-FIX-02C report (scratchpad script).
 */
class CartConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->product = $this->product('clay-jar', 10);
    }

    public function test_two_first_adds_of_one_guest_make_one_cart(): void
    {
        $session = Str::random(40);
        $window = $this->competeWhen(Cart::class, 'creating', CartLock::sessionKey($session), function () use ($session) {
            DB::table('carts')->insert(['session_id' => $session, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->asGuest($session)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        $this->assertSame('excluded', $window(), 'The competing request could not write between the read and the write.');
        $this->asGuest($session)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();

        $this->assertSame(1, Cart::query()->where('session_id', $session)->where('status', 'active')->count());
        $this->assertSame([3], CartItem::query()->pluck('quantity')->all());
    }

    public function test_two_first_adds_of_one_customer_make_one_cart(): void
    {
        $customer = Customer::factory()->create();
        $window = $this->competeWhen(Cart::class, 'creating', CartLock::customerKey($customer), function () use ($customer) {
            DB::table('carts')->insert(['customer_id' => $customer->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        $this->assertSame('excluded', $window());
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();

        $this->assertSame(1, Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->count());
        $this->assertSame([2], CartItem::query()->pluck('quantity')->all(), 'Both adds in the one cart the customer sees.');
        $this->assertSame(2, $this->getJson(route('customer.cart.show'))->json('items.0.quantity'));
    }

    public function test_two_adds_of_the_same_product_make_one_line_without_an_error(): void
    {
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $window = $this->competeWhen(CartItem::class, 'creating', CartLock::customerKey($customer), function () use ($cart) {
            DB::table('cart_items')->insert(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1,
                'unit_price' => 25, 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        $this->assertSame('excluded', $window());
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();

        $this->assertSame([2], CartItem::query()->where('cart_id', $cart->id)->pluck('quantity')->all());
    }

    public function test_concurrent_increments_of_a_line_are_all_counted(): void
    {
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $line = $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 25]);
        // The competing add read 1 too and writes 2 between this request's read (1) and its write (2): a lost increment.
        $window = $this->competeWhen(CartItem::class, 'updating', CartLock::customerKey($customer), function () use ($line) {
            DB::table('cart_items')->where('id', $line->id)->update(['quantity' => 2]);
        });

        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        $this->assertSame('excluded', $window());
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();

        $this->assertSame(3, $line->fresh()->quantity, '1 + 1 + 1: no increment lost.');
    }

    public function test_the_stock_limit_applies_to_the_combined_quantity(): void
    {
        $product = $this->product('small-batch', 3);
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(422)->assertJsonPath('message', 'Requested quantity is not available.');

        $this->assertSame([2], CartItem::query()->pluck('quantity')->all(), 'Never above the stock of 3.');
    }

    public function test_update_and_remove_hold_the_lock_and_act_on_the_line_as_it_is(): void
    {
        $customer = Customer::factory()->create();
        $line = $customer->carts()->create(['status' => 'active'])->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 25]);
        $held = [];
        CartItem::updating(function () use ($customer, &$held) {
            $held[] = $this->lockIsHeld(CartLock::customerKey($customer));
        });
        // The removal deletes by query (no model event): the lock is probed when its DELETE statement runs.
        DB::listen(function ($query) use ($customer, &$held) {
            if (preg_match('/^\s*delete from\s+"?cart_items/i', $query->sql)) {
                $held[] = $this->lockIsHeld(CartLock::customerKey($customer));
            }
        });

        $this->actingAs($customer, 'customer')->patchJson(route('customer.cart.items.update', $line), ['quantity' => 4])->assertOk()->assertJsonPath('items.0.quantity', 4);
        $this->deleteJson(route('customer.cart.items.destroy', $line))->assertOk()->assertJsonPath('items', []);
        $this->assertSame([true, true], $held, 'The update and the removal both held the customer lock.');
        $this->deleteJson(route('customer.cart.items.destroy', $line))->assertNotFound();
    }

    public function test_a_write_waiting_past_the_lock_writes_nothing_and_says_so(): void
    {
        config(['cart.lock_wait' => 0]);
        $session = Str::random(40);
        $lock = Cache::lock(CartLock::sessionKey($session), 10);
        $this->assertTrue($lock->get(), 'Another write of this cart holds the lock.');

        $this->asGuest($session)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertStatus(409)->assertJsonPath('message', 'The cart is being updated by another request. Try again.');
        $this->asGuest($session)->from('/browse')->post(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect('/browse')->assertSessionHasErrors(['cart' => 'السلة قيد التحديث في طلب آخر. حاول مرة أخرى.']);
        $this->assertSame(0, Cart::query()->count());

        $lock->release();
        $this->asGuest($session)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    }

    public function test_a_login_merge_racing_an_add_counts_both(): void
    {
        $customer = Customer::factory()->create(['email' => 'merge@example.test', 'password' => Hash::make('Secret-123456')]);
        $customerCart = $customer->carts()->create(['status' => 'active']);
        $guest = Str::random(40);
        $this->asGuest($guest)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();

        // During the merge the customer's other tab adds the same product into the customer cart.
        $window = $this->competeWhen(CartItem::class, 'creating', CartLock::customerKey($customer), function () use ($customerCart) {
            DB::table('cart_items')->insert(['cart_id' => $customerCart->id, 'product_id' => $this->product->id, 'quantity' => 1,
                'unit_price' => 25, 'created_at' => now(), 'updated_at' => now()]);
        }, onlyCart: $customerCart->id);

        $this->asGuest($guest)->post(route('customer.login.store'), ['login' => 'merge@example.test', 'password' => 'Secret-123456'])->assertRedirect();
        $this->assertSame('excluded', $window(), 'The merge held the customer lock.');
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();

        $this->assertSame([3], CartItem::query()->where('cart_id', $customerCart->id)->pluck('quantity')->all(), 'Guest 2 + other tab 1.');
        $this->assertSame('abandoned', Cart::query()->where('session_id', $guest)->value('status'));
    }

    public function test_the_merge_through_a_cart_write_holds_both_locks_and_runs_once(): void
    {
        $customer = Customer::factory()->create();
        $guest = Str::random(40);
        $this->asGuest($guest)->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $guestCartId = Cart::query()->value('id');
        $held = null;
        Cart::updating(function (Cart $cart) use ($guestCartId, $customer, $guest, &$held) {
            if ($cart->id === $guestCartId) {
                $held = [$this->lockIsHeld(CartLock::customerKey($customer)), $this->lockIsHeld(CartLock::sessionKey($guest))];
            }
        });

        $this->asGuest($guest)->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertCreated()->assertJsonPath('items.0.quantity', 3);

        $this->assertSame([true, true], $held, 'Customer lock and session lock held while the guest cart was merged.');
        $this->assertFalse(app(GuestCartMerger::class)->merge($guestCartId, $customer), 'A repeated merge changes nothing.');
        $this->assertSame([3], CartItem::query()->whereHas('cart', fn ($cart) => $cart->where('customer_id', $customer->id))->pluck('quantity')->all());
    }

    public function test_reads_take_no_lock(): void
    {
        $session = Str::random(40);
        $lock = Cache::lock(CartLock::sessionKey($session), 10);
        $lock->get();

        $this->asGuest($session)->get(route('cart'))->assertOk();
        $this->asGuest($session)->getJson(route('customer.cart.show'))->assertOk()->assertJsonPath('items', []);
        $lock->release();
    }

    /**
     * Plays $competingWrite at the interleaving point (the model event) if the owner lock $key is free, as a request
     * that must hold that lock would; returns a probe: "raced" (it wrote into the window) or "excluded".
     */
    private function competeWhen(string $model, string $event, string $key, \Closure $competingWrite, ?int $onlyCart = null): \Closure
    {
        $result = 'not reached';
        $model::$event(function ($instance) use ($key, $competingWrite, $onlyCart, &$result) {
            if ($result !== 'not reached' || ($onlyCart !== null && $instance->cart_id !== $onlyCart)) {
                return;
            }
            if ($this->lockIsHeld($key)) {
                $result = 'excluded';

                return;
            }
            $result = 'raced';
            $competingWrite();
        });

        return function () use (&$result) {
            return $result;
        };
    }

    private function lockIsHeld(string $key): bool
    {
        $probe = Cache::lock($key, 1);
        if ($probe->get()) {
            $probe->release();

            return false;
        }

        return true;
    }

    private function asGuest(string $session): static
    {
        return $this->withCookie(config('session.cookie'), $session);
    }

    private function product(string $slug, int $stock): Product
    {
        return Product::query()->create(['vendor_id' => Vendor::factory()->approved()->create(['status' => 'active'])->id,
            'category_id' => Category::query()->value('id'), 'name' => 'Product '.$slug, 'slug' => $slug, 'price' => 25,
            'stock_quantity' => $stock, 'status' => 'active', 'published_at' => now()]);
    }
}
