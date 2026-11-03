<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Storefront\GuestCartMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CART-SEC-01 (CART-AUDIT-01 S1, S2): every cart JSON response (customer.cart.show, customer.cart.items.store /
 * update / destroy) publishes the PublicCart allow-list only: the cart's id, owner and status, each line's id,
 * product, quantity and stored unit price, the product as PublicCatalog shows it. No session id, no vendor account
 * (email, phone, login), no store records (commission rate, approval, rejection, street address), no internal
 * product data, at any depth. The cart's behaviour (pricing, stock, ownership, HTML forms, guest merge) is unchanged.
 */
class CartJsonSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const CART_KEYS = ['id', 'customer_id', 'status', 'items'];

    private const ITEM_KEYS = ['id', 'product_id', 'quantity', 'unit_price', 'product'];

    private const PRODUCT_KEYS = ['id', 'name', 'slug', 'short_description', 'price', 'selling_price', 'has_discount', 'available_stock', 'image_url', 'category', 'store'];

    private const STORE_KEYS = ['slug', 'name', 'short_description', 'city', 'governorate', 'preparation_days'];

    private const SECRETS = ['private-cart-vendor@example.com', '0599888777', 'PRIVATE STREET 9', '9.99', 'PRIVATE REJECTION', 'PRIVATE-SKU'];

    /** Private keys that must not appear at any depth ("status" is only the cart's own, checked separately). */
    private const FORBIDDEN_KEYS = ['session_id', 'email', 'phone', 'password', 'remember_token', 'last_login_at', 'email_verified_at',
        'phone_verified_at', 'commission_rate', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'address_line',
        'vendor', 'profile', 'vendor_id', 'city_id', 'governorate_id', 'cart_id', 'sku', 'stock_quantity', 'low_stock_threshold',
        'is_featured', 'published_at', 'suspended_at', 'archived_at', 'created_at', 'updated_at'];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // JSON test requests send cookies only with credentials, as a browser's same-origin fetch does.
        $this->withCredentials();

        $vendor = Vendor::factory()->approved()->create(['status' => 'active', 'email' => 'private-cart-vendor@example.com', 'phone' => '0599888777']);
        $vendor->profile()->update(['address_line' => 'PRIVATE STREET 9', 'commission_rate' => 9.99, 'rejection_reason' => 'PRIVATE REJECTION']);
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->product = Product::query()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Clay jar',
            'slug' => 'clay-jar', 'sku' => 'PRIVATE-SKU', 'price' => 45, 'compare_at_price' => 39.90, 'stock_quantity' => 10,
            'status' => 'active', 'published_at' => now()]);
    }

    public function test_guest_cart_json_publishes_only_the_allow_list(): void
    {
        // One guest session for every request (as CustomerGuestCartMergeTest does); its id must not be published.
        $session = Str::random(40);
        $store = $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $this->assertPublicCart($store, 'guest store', $session);
        $this->assertNull($store->json('customer_id'));

        $show = $this->withCookie(config('session.cookie'), $session)->getJson(route('customer.cart.show'))->assertOk();
        $this->assertPublicCart($show, 'guest show', $session);
        $this->assertSame(2, $show->json('items.0.quantity'));

        $line = $show->json('items.0.id');
        $update = $this->withCookie(config('session.cookie'), $session)->patchJson(route('customer.cart.items.update', $line), ['quantity' => 3])->assertOk();
        $this->assertPublicCart($update, 'guest update', $session);
        $this->assertSame(3, $update->json('items.0.quantity'));
        $destroy = $this->withCookie(config('session.cookie'), $session)->deleteJson(route('customer.cart.items.destroy', $line))->assertOk();
        $this->assertPublicCart($destroy, 'guest destroy', $session);
        $this->assertSame([], $destroy->json('items'));
    }

    public function test_customer_cart_json_publishes_only_the_allow_list(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $store = $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
        $show = $this->getJson(route('customer.cart.show'))->assertOk();
        $update = $this->patchJson(route('customer.cart.items.update', $show->json('items.0.id')), ['quantity' => 4])->assertOk();
        $destroy = $this->deleteJson(route('customer.cart.items.destroy', $show->json('items.0.id')))->assertOk();

        foreach (['store' => $store, 'show' => $show, 'update' => $update, 'destroy' => $destroy] as $label => $response) {
            $this->assertPublicCart($response, "customer {$label}");
            $this->assertSame($customer->id, $response->json('customer_id'));
        }
        $this->assertSame(4, $update->json('items.0.quantity'));
    }

    public function test_the_line_carries_the_public_product_and_the_selling_price_the_header_sums(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $response = $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();

        $this->assertSame(['id' => $response->json('items.0.id'), 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '39.90'],
            array_intersect_key($response->json('items.0'), array_flip(['id', 'product_id', 'quantity', 'unit_price'])));
        $this->assertSame(['45.00', '39.90', true, 10], [$response->json('items.0.product.price'), $response->json('items.0.product.selling_price'),
            $response->json('items.0.product.has_discount'), $response->json('items.0.product.available_stock')]);
        // storefront/home-actions.blade.php sums quantity x unit_price for the header after Add to cart.
        $this->assertEqualsWithDelta(79.80, array_sum(array_map(fn (array $item) => $item['quantity'] * (float) $item['unit_price'], $response->json('items'))), 0.001);
        $this->get(route('home'))->assertViewHas('cartTotal', fn ($total) => abs($total - 79.80) < 0.001);
    }

    public function test_ownership_stock_and_html_forms_are_unchanged(): void
    {
        $owner = Customer::factory()->create();
        $line = $this->actingAs($owner, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertCreated()->json('items.0.id');

        $this->actingAs(Customer::factory()->create(), 'customer');
        $this->patchJson(route('customer.cart.items.update', $line), ['quantity' => 2])->assertForbidden();
        $this->deleteJson(route('customer.cart.items.destroy', $line))->assertForbidden();

        $this->actingAs($owner, 'customer');
        $this->patchJson(route('customer.cart.items.update', $line), ['quantity' => 11])->assertStatus(422);
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 10])->assertStatus(422);
        $this->from(route('cart'))->patch(route('customer.cart.items.update', $line), ['quantity' => 2])
            ->assertRedirect(route('cart'))->assertSessionHas('success');
        $productUrl = route('product', ['slug' => $this->product->slug]);
        $this->from($productUrl)->post(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect($productUrl)->assertSessionHas('success');
        $this->from($productUrl)->post(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 50])
            ->assertRedirect($productUrl)->assertSessionHasErrors('cart');
    }

    public function test_the_merged_cart_json_publishes_only_the_allow_list(): void
    {
        $guestSession = Str::random(40);
        $this->withCookie(config('session.cookie'), $guestSession)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
        $customer = Customer::factory()->create();

        app(GuestCartMerger::class)->merge(app(GuestCartMerger::class)->guestCartId($guestSession), $customer);
        $show = $this->actingAs($customer, 'customer')->getJson(route('customer.cart.show'))->assertOk();

        $this->assertPublicCart($show, 'merged show', $guestSession);
        $this->assertSame([$customer->id, 2], [$show->json('customer_id'), $show->json('items.0.quantity')]);
    }

    private function assertPublicCart(TestResponse $response, string $label, ?string $sessionId = null): void
    {
        if ($sessionId !== null) {
            $this->assertStringNotContainsString($sessionId, $response->getContent(), "{$label}: the guest session id");
        }
        $this->assertSame(self::CART_KEYS, array_keys($response->json()), $label);
        $this->assertSame('active', $response->json('status'), $label);
        foreach ($response->json('items') as $item) {
            $this->assertSame(self::ITEM_KEYS, array_keys($item), $label);
            $this->assertSame(self::PRODUCT_KEYS, array_keys($item['product']), $label);
            $this->assertSame(self::STORE_KEYS, array_keys($item['product']['store']), $label);
            $this->assertSame(['name', 'slug'], array_keys($item['product']['category']), $label);
            $this->assertArrayNotHasKey('status', $item['product'], $label);
        }

        $json = $response->getContent();
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $json, "{$label}: private value {$secret}");
        }
        $this->assertStringNotContainsString(session()->getId(), $json, "{$label}: the session id");
        $keys = $this->keysAtEveryDepth($response->json());
        foreach (self::FORBIDDEN_KEYS as $key) {
            $this->assertNotContains($key, $keys, "{$label}: private key {$key}");
        }
    }

    /** @return list<string> */
    private function keysAtEveryDepth(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $key => $child) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            array_push($keys, ...$this->keysAtEveryDepth($child));
        }

        return $keys;
    }
}
