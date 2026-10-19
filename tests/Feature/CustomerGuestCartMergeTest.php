<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Storefront\GuestCartMerger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

/**
 * CUSTOMER-AUTH-A1 (F-1): a guest cart survives the customer login and registration, which regenerate the session
 * id. Every request sends the session cookie it would carry in a browser: the guest session before, the regenerated
 * one after.
 */
class CustomerGuestCartMergeTest extends TestCase
{
    use InteractsWithStorefront;
    use RefreshDatabase;

    private const PASSWORD = 'Secret-123456';

    protected function setUp(): void
    {
        parent::setUp();

        // JSON test requests send cookies only with credentials, as a browser's same-origin fetch does.
        $this->withCredentials();
    }

    // ---------------------------------------------------------------- T1, T2

    public function test_t1_a_guest_cart_moves_to_the_customer_on_login(): void
    {
        $customer = $this->customer();
        [$lamp, $rug] = [$this->storefrontProduct('lamp'), $this->storefrontProduct('rug')];
        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 2);
        $this->addAsGuest($guest, $rug, 1);
        $guestCartId = $this->guestCartId($guest);

        $this->login($guest)->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->assertSame([$lamp->id => 2, $rug->id => 1], $this->lines($customer));
        $this->assertSame('abandoned', Cart::find($guestCartId)->status, 'Kept, not deleted.');

        // What the customer then sees, on the regenerated session.
        $this->withCookie(config('session.cookie'), session()->getId())->getJson(route('customer.cart.show'))->assertOk()
            ->assertJsonCount(2, 'items')->assertJsonPath('customer_id', $customer->id);
    }

    public function test_t2_a_guest_cart_moves_to_the_new_customer_on_registration(): void
    {
        $lamp = $this->storefrontProduct('lamp');
        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 3);
        $guestCartId = $this->guestCartId($guest);

        $this->withCookie(config('session.cookie'), $guest)->post(route('customer.register.store'), [
            'full_name' => 'New Customer', 'phone' => '0599111222', 'email' => 'new@example.test',
            'password' => 'Secret-123456!', 'password_confirmation' => 'Secret-123456!', 'terms' => '1',
        ])->assertRedirect(route('customer.dashboard'));

        $customer = Customer::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame([$lamp->id => 3], $this->lines($customer));
        $this->assertSame('abandoned', Cart::find($guestCartId)->status);
    }

    // ---------------------------------------------------------------- T3

    public function test_t3_an_existing_customer_cart_and_a_guest_cart_are_merged_without_loss(): void
    {
        $customer = $this->customer();
        [$lamp, $rug, $vase] = [$this->storefrontProduct('lamp'), $this->storefrontProduct('rug'), $this->storefrontProduct('vase')];
        $saved = $customer->carts()->create(['status' => 'active']);
        $saved->items()->create(['product_id' => $lamp->id, 'quantity' => 1, 'unit_price' => 25]);
        $saved->items()->create(['product_id' => $rug->id, 'quantity' => 1, 'unit_price' => 25]);

        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 2);
        $this->addAsGuest($guest, $vase, 1);

        $this->login($guest)->assertRedirect();

        $this->assertSame([$lamp->id => 3, $rug->id => 1, $vase->id => 1], $this->lines($customer));
        $this->assertSame(1, Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->count(), 'One active cart.');
        $this->assertSame($saved->id, Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->value('id'));
    }

    // ---------------------------------------------------------------- T4, T5

    public function test_t4_a_failed_login_leaves_the_guest_cart_as_it_was(): void
    {
        $customer = $this->customer();
        $lamp = $this->storefrontProduct('lamp');
        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 2);
        $guestCartId = $this->guestCartId($guest);

        $this->withCookie(config('session.cookie'), $guest)
            ->post(route('customer.login.store'), ['login' => $customer->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest('customer');
        $guestCart = Cart::find($guestCartId);
        $this->assertSame('active', $guestCart->status);
        $this->assertNull($guestCart->customer_id);
        $this->assertSame([$lamp->id => 2], $guestCart->items()->pluck('quantity', 'product_id')->all());
        $this->assertSame(0, Cart::query()->whereNotNull('customer_id')->count(), 'Nothing moved to any account.');
    }

    public function test_t5_a_login_without_a_guest_cart_creates_no_cart(): void
    {
        $customer = $this->customer();

        $this->login($this->newSession())->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_t5_an_empty_guest_cart_creates_no_customer_cart(): void
    {
        $customer = $this->customer();
        $guest = $this->newSession();
        // Opening the cart page as a guest creates an empty guest cart.
        $this->withCookie(config('session.cookie'), $guest)->getJson(route('customer.cart.show'))->assertOk();
        $guestCartId = $this->guestCartId($guest);

        $this->login($guest)->assertRedirect();

        $this->assertSame(0, Cart::query()->where('customer_id', $customer->id)->count());
        $this->assertSame('active', Cart::find($guestCartId)->status, 'An empty guest cart is left as it is.');
    }

    // ---------------------------------------------------------------- T6

    public function test_t6_the_session_is_still_regenerated_on_login_and_registration(): void
    {
        $this->customer();
        $guest = $this->newSession();
        $this->addAsGuest($guest, $this->storefrontProduct('lamp'), 1);

        $this->login($guest)->assertRedirect();
        $afterLogin = session()->getId();

        $this->assertNotSame($guest, $afterLogin);
        $this->assertArrayNotHasKey(Auth::guard('customer')->getName(), $this->storedSession($guest), 'The old session id is not signed in.');
        $this->assertArrayHasKey(Auth::guard('customer')->getName(), $this->storedSession($afterLogin));

        Auth::guard('customer')->logout();
        $other = $this->newSession();
        $this->addAsGuest($other, $this->storefrontProduct('rug'), 1);
        $this->withCookie(config('session.cookie'), $other)->post(route('customer.register.store'), [
            'full_name' => 'New Customer', 'phone' => '0599111333', 'email' => 'other@example.test',
            'password' => 'Secret-123456!', 'password_confirmation' => 'Secret-123456!', 'terms' => '1',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertNotSame($other, session()->getId());
        $this->assertArrayNotHasKey(Auth::guard('customer')->getName(), $this->storedSession($other));
    }

    // ---------------------------------------------------------------- T7

    public function test_t7_only_the_carts_own_guest_session_is_merged_whatever_the_request_says(): void
    {
        $customer = $this->customer();
        $someoneElse = Customer::factory()->create();
        [$lamp, $rug, $vase] = [$this->storefrontProduct('lamp'), $this->storefrontProduct('rug'), $this->storefrontProduct('vase')];

        // Another visitor's guest cart, and another customer's cart.
        $stranger = $this->newSession();
        $this->addAsGuest($stranger, $rug, 4);
        $strangerCartId = $this->guestCartId($stranger);
        $foreign = $someoneElse->carts()->create(['status' => 'active']);
        $foreign->items()->create(['product_id' => $vase->id, 'quantity' => 5, 'unit_price' => 25]);

        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 1);
        // A cart of another customer that still carries this browser's session id is not a guest cart.
        $claimed = $someoneElse->carts()->create(['status' => 'active', 'session_id' => $guest]);

        $this->withCookie(config('session.cookie'), $guest)->post(route('customer.login.store'), [
            'login' => $customer->email, 'password' => self::PASSWORD,
            // Untrusted references in the request are ignored.
            'cart_id' => $strangerCartId, 'guest_cart_id' => $strangerCartId, 'session_id' => $stranger,
        ])->assertRedirect();

        $this->assertSame([$lamp->id => 1], $this->lines($customer));
        $strangerCart = Cart::find($strangerCartId);
        $this->assertSame('active', $strangerCart->status);
        $this->assertNull($strangerCart->customer_id);
        $this->assertSame([$rug->id => 4], $strangerCart->items()->pluck('quantity', 'product_id')->all());
        $this->assertSame([$vase->id => 5], $foreign->fresh()->items()->pluck('quantity', 'product_id')->all());
        $this->assertSame($someoneElse->id, $foreign->fresh()->customer_id);
        $this->assertSame($someoneElse->id, $claimed->fresh()->customer_id);
        $this->assertSame('active', $claimed->fresh()->status);
    }

    public function test_t7_a_vendor_or_driver_login_does_not_take_or_change_the_guest_cart(): void
    {
        $vendor = Vendor::factory()->approved()->create(['password' => Hash::make(self::PASSWORD)]);
        $guest = $this->newSession();
        $this->addAsGuest($guest, $this->storefrontProduct('lamp'), 2);
        $guestCartId = $this->guestCartId($guest);

        $this->withCookie(config('session.cookie'), $guest)
            ->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => self::PASSWORD])->assertRedirect();

        $this->assertAuthenticatedAs($vendor, 'vendor');
        $this->assertGuest('customer');
        $this->assertSame('active', Cart::find($guestCartId)->status);
        $this->assertNull(Cart::find($guestCartId)->customer_id);
        $this->assertSame(1, Cart::query()->count());
    }

    // ---------------------------------------------------------------- T8

    public function test_t8_the_merge_happens_once(): void
    {
        $customer = $this->customer();
        $lamp = $this->storefrontProduct('lamp');
        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 2);
        $guestCartId = $this->guestCartId($guest);

        $this->login($guest)->assertRedirect();
        $session = session()->getId();

        foreach (range(1, 3) as $visit) {
            $this->withCookie(config('session.cookie'), $session)->getJson(route('customer.cart.show'))->assertOk();
        }
        // A repeated login request from the signed-in session is turned away by guest:customer.
        $this->withCookie(config('session.cookie'), $session)
            ->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])->assertRedirect(route('home'));
        // And a second merge of the same guest cart changes nothing.
        $this->assertFalse(app(GuestCartMerger::class)->merge($guestCartId, $customer));

        $this->assertSame([$lamp->id => 2], $this->lines($customer));
    }

    // ---------------------------------------------------------------- T9

    public function test_t9_the_cart_rules_still_apply_after_the_merge(): void
    {
        $customer = $this->customer();
        $lamp = $this->storefrontProduct('lamp', null, ['stock_quantity' => 10]);
        $rug = $this->storefrontProduct('rug');
        $saved = $customer->carts()->create(['status' => 'active']);
        $saved->items()->create(['product_id' => $lamp->id, 'quantity' => 8, 'unit_price' => 25]);

        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 5);
        $this->addAsGuest($guest, $rug, 1);
        $this->login($guest)->assertRedirect();
        $session = session()->getId();

        // The merge keeps the existing rule: quantities are added; stock is checked by the cart and the checkout.
        $this->assertSame([$lamp->id => 13, $rug->id => 1], $this->lines($customer));

        $lampLine = $saved->items()->where('product_id', $lamp->id)->first();
        $this->withCookie(config('session.cookie'), $session)
            ->patchJson(route('customer.cart.items.update', $lampLine), ['quantity' => 11])->assertStatus(422);
        $this->withCookie(config('session.cookie'), $session)
            ->patchJson(route('customer.cart.items.update', $lampLine), ['quantity' => 10])->assertOk();
        $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $lamp->id, 'quantity' => 1])->assertStatus(422);

        // A product that is no longer for sale cannot be changed in the cart.
        $rug->forceFill(['suspended_at' => now()])->save();
        $rugLine = $saved->items()->where('product_id', $rug->id)->first();
        $this->withCookie(config('session.cookie'), $session)
            ->patchJson(route('customer.cart.items.update', $rugLine), ['quantity' => 2])->assertStatus(422);
    }

    // ---------------------------------------------------------------- failure

    public function test_a_failed_merge_is_rolled_back_reported_and_the_login_stands(): void
    {
        Exceptions::fake();
        $customer = $this->customer();
        $lamp = $this->storefrontProduct('lamp');
        $guest = $this->newSession();
        $this->addAsGuest($guest, $lamp, 2);
        $guestCartId = $this->guestCartId($guest);

        // The last write of the merge fails: the merged lines and the customer cart must not stay.
        DB::statement("CREATE TRIGGER guest_cart_merge_fails BEFORE UPDATE OF status ON carts WHEN NEW.status = 'abandoned' BEGIN SELECT RAISE(ABORT, 'forced merge failure'); END");

        $this->login($guest)->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
        Exceptions::assertReported(QueryException::class);
        $this->assertSame(0, Cart::query()->where('customer_id', $customer->id)->count());
        $guestCart = Cart::find($guestCartId);
        $this->assertSame('active', $guestCart->status);
        $this->assertSame([$lamp->id => 2], $guestCart->items()->pluck('quantity', 'product_id')->all());
    }

    // ---------------------------------------------------------------- helpers

    private function customer(): Customer
    {
        return Customer::factory()->create(['email' => 'customer@example.test', 'password' => Hash::make(self::PASSWORD)]);
    }

    /** A session id in the format Laravel accepts, sent as the browser's session cookie. */
    private function newSession(): string
    {
        return Str::random(40);
    }

    private function addAsGuest(string $session, Product $product, int $quantity): void
    {
        $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertCreated();
    }

    private function login(string $session)
    {
        return $this->withCookie(config('session.cookie'), $session)
            ->post(route('customer.login.store'), ['login' => 'customer@example.test', 'password' => self::PASSWORD]);
    }

    private function guestCartId(string $session): int
    {
        return Cart::query()->whereNull('customer_id')->where('session_id', $session)->where('status', 'active')->valueOrFail('id');
    }

    /** @return array<int, int> product id => quantity of the customer's active cart */
    private function lines(Customer $customer): array
    {
        $cart = Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->first();

        return $cart ? $cart->items()->orderBy('id')->pluck('quantity', 'product_id')->all() : [];
    }

    /** @return array<string, mixed> the data stored for a session id */
    private function storedSession(string $id): array
    {
        $data = app('session')->driver()->getHandler()->read($id);

        return $data === '' ? [] : (array) unserialize($data);
    }
}
