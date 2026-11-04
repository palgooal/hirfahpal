<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CART-FIX-02B (CART-FIX-02A F1): GET /cart, GET /customer/cart and GET /customer/checkout only read the cart: they
 * create no cart, merge no guest cart and write nothing. Without a cart, the pages show the empty state and the JSON
 * keeps its keys with a null id. Adding to the cart still creates it, and the add, login and registration flows still
 * merge the session's guest cart.
 */
class CartReadOnlyRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private const CART_KEYS = ['id', 'customer_id', 'status', 'items'];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->product = Product::query()->create(['vendor_id' => Vendor::factory()->approved()->create(['status' => 'active'])->id,
            'category_id' => $category->id, 'name' => 'Clay jar', 'slug' => 'clay-jar', 'price' => 45, 'compare_at_price' => 39.90,
            'stock_quantity' => 10, 'status' => 'active', 'published_at' => now()]);
    }

    public function test_repeated_cookie_less_guest_gets_write_nothing(): void
    {
        $writes = $this->writesDuring(function () {
            foreach (range(1, 3) as $request) {
                $this->get(route('cart'))->assertOk()->assertSee('سلتك فارغة حالياً');
                $this->getJson(route('customer.cart.show'))->assertOk()->assertExactJson(['id' => null, 'customer_id' => null, 'status' => 'active', 'items' => []]);
            }
        });

        $this->assertSame([], $writes);
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_a_guest_with_a_cart_reads_it_without_writes(): void
    {
        $session = Str::random(40);
        $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $cart = Cart::query()->sole();

        $writes = $this->writesDuring(function () use ($session, $cart) {
            $this->withCookie(config('session.cookie'), $session)->get(route('cart'))->assertOk()->assertSee('Clay jar')->assertSee('79.80');
            $show = $this->withCookie(config('session.cookie'), $session)->getJson(route('customer.cart.show'))->assertOk();
            $this->assertSame(self::CART_KEYS, array_keys($show->json()));
            $this->assertSame([$cart->id, null, 2], [$show->json('id'), $show->json('customer_id'), $show->json('items.0.quantity')]);
        });

        $this->assertSame([], $writes);
    }

    public function test_a_customer_without_a_cart_gets_the_empty_cart_and_no_checkout_without_writes(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $writes = $this->writesDuring(function () use ($customer) {
            $this->get(route('cart'))->assertOk()->assertSee('سلتك فارغة حالياً');
            $this->getJson(route('customer.cart.show'))->assertOk()
                ->assertExactJson(['id' => null, 'customer_id' => $customer->id, 'status' => 'active', 'items' => []]);
            $this->get(route('customer.checkout.show'))->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => 'السلة فارغة.']);
        });

        $this->assertSame([], $writes);
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_a_customer_with_a_cart_reads_it_and_checks_out_unchanged(): void
    {
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 39.90]);
        $this->actingAs($customer, 'customer');

        $writes = $this->writesDuring(function () use ($cart) {
            $this->get(route('cart'))->assertOk()->assertSee('Clay jar')->assertSee('79.80');
            $this->assertSame($cart->id, $this->getJson(route('customer.cart.show'))->assertOk()->json('id'));
            $this->get(route('customer.checkout.show'))->assertOk()->assertSee('79.80');
        });
        $this->assertSame([], $writes);

        $address = $customer->addresses()->create(['label' => 'H', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'S']);
        $this->post(route('customer.checkout.store'), ['paymentMethod' => 'cod', 'addressChoice' => (string) $address->id])
            ->assertRedirect(route('customer.dashboard'));
        $this->assertSame('ordered', $cart->fresh()->status);
    }

    public function test_reading_merges_nothing_and_the_next_write_merges_the_guest_cart(): void
    {
        $session = Str::random(40);
        $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $guestCart = Cart::query()->sole();
        $customer = Customer::factory()->create();

        // Signed in on the same session without a login merge (e.g. the merge failed and was only reported).
        $writes = $this->writesDuring(function () use ($session, $customer) {
            $this->withCookie(config('session.cookie'), $session)->actingAs($customer, 'customer')->get(route('cart'))->assertOk();
            $this->withCookie(config('session.cookie'), $session)->actingAs($customer, 'customer')->getJson(route('customer.cart.show'))->assertOk();
        });
        $this->assertSame([], $writes);
        $this->assertSame('active', $guestCart->fresh()->status, 'Not merged by a read.');

        $this->withCookie(config('session.cookie'), $session)->actingAs($customer, 'customer')
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated()
            ->assertJsonPath('items.0.quantity', 3);
        $this->assertSame('abandoned', $guestCart->fresh()->status, 'Merged by the next write, as before.');
    }

    public function test_adding_to_the_cart_still_creates_it(): void
    {
        $this->assertSame(0, Cart::query()->count());

        $response = $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(Cart::query()->value('id'), $response->json('id'));
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_login_and_registration_still_merge_the_guest_cart(): void
    {
        $customer = Customer::factory()->create(['email' => 'login@example.test', 'password' => Hash::make('Secret-123456')]);
        $guest = Str::random(40);
        $this->withCookie(config('session.cookie'), $guest)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();

        $this->withCookie(config('session.cookie'), $guest)
            ->post(route('customer.login.store'), ['login' => 'login@example.test', 'password' => 'Secret-123456'])->assertRedirect();

        $this->assertSame([2], CartItem::query()->whereHas('cart', fn ($cart) => $cart->where('customer_id', $customer->id))->pluck('quantity')->all());
    }

    /** @return list<string> the INSERT, UPDATE and DELETE statements run by $requests */
    private function writesDuring(callable $requests): array
    {
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $requests();

        return $writes;
    }
}
