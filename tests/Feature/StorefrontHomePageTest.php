<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_without_catalog_data_and_does_not_create_cart_or_settings(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('لا توجد منتجات متاحة حالياً.')
            ->assertViewHas('cartCount', 0)
            ->assertViewHas('artisanCount', 0);

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_home_only_shows_sellable_products_and_active_categories(): void
    {
        $category = Category::query()->create(['name' => 'Handmade ceramics', 'slug' => 'ceramics', 'is_active' => true]);
        Category::query()->create(['name' => 'Hidden category', 'slug' => 'hidden', 'is_active' => false]);
        $approved = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $visible = $this->product($approved, 'visible-product', ['category_id' => $category->id, 'is_featured' => true]);
        $pending = Vendor::factory()->withProfile('pending')->create();
        $this->product($pending, 'pending-product');
        $this->product($approved, 'draft-product', ['status' => 'draft']);
        $suspended = $this->product($approved, 'suspended-product');
        $suspended->forceFill(['suspended_at' => now()])->save();

        $this->get(route('home'))->assertOk()
            ->assertSee($visible->name)->assertSee('Handmade ceramics')
            ->assertDontSee('pending-product')->assertDontSee('draft-product')
            ->assertDontSee('suspended-product')->assertDontSee('Hidden category')
            ->assertViewHas('artisanCount', 1)
            ->assertViewHas('homeCategories', fn ($categories) => $categories->first()->products_count === 1)
            ->assertViewHas('seasonProducts', fn ($products) => $products->pluck('id')->all() === [$visible->id]);
    }

    public function test_popular_products_use_delivered_sales_and_only_published_reviews(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $bestseller = $this->product($vendor, 'bestseller');
        $newest = $this->product($vendor, 'newest');
        $customer = Customer::factory()->create();
        $order = Order::query()->create(['number' => 'HOME-1', 'customer_id' => $customer->id, 'payment_method' => 'cod']);
        $vendorOrder = $order->vendorOrders()->create(['number' => 'HOME-V-1', 'vendor_id' => $vendor->id, 'status' => 'delivered']);
        $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $bestseller->id,
            'product_name' => $bestseller->name, 'quantity' => 3, 'unit_price' => 25, 'line_total' => 75]);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'product_id' => $bestseller->id,
            'reviewable_type' => 'product', 'rating' => 4, 'status' => 'published']);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'product_id' => $bestseller->id,
            'reviewable_type' => 'product', 'rating' => 1, 'status' => 'hidden']);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('popularProducts', fn ($products) => $products->first()->id === $bestseller->id
                && $products->first()->reviews_count === 1 && (float) $products->first()->reviews_avg_rating === 4.0)
            ->assertViewHas('newProducts', fn ($products) => $products->first()->id === $newest->id);
    }

    public function test_home_uses_stored_content_and_escapes_html(): void
    {
        Setting::singleton()->update(['site_name' => 'My craft shop', 'default_currency' => 'JOD',
            'home_content' => ['hero_text_1' => '<script>alert(1)</script>']]);

        $this->get(route('home'))->assertOk()->assertSee('My craft shop')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertViewHas('currency', 'JOD');
    }

    public function test_home_renders_customer_cart_favorites_and_reserved_stock(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $product = $this->product($vendor, 'reserved-product', ['stock_quantity' => 2]);
        $customer = Customer::factory()->create();
        $favorite = $customer->favorites()->create(['product_id' => $product->id]);
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]);
        $order = Order::query()->create(['number' => 'RESERVED-1', 'customer_id' => $customer->id, 'payment_method' => 'cod']);
        $product->stockReservations()->create(['order_id' => $order->id, 'quantity' => 2, 'status' => 'reserved']);

        $this->actingAs($customer, 'customer')->get(route('home'))->assertOk()
            ->assertViewHas('cartCount', 2)->assertViewHas('cartTotal', 50)
            ->assertViewHas('favoriteIds', [$product->id => $favorite->id])
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('data-price="25.00" disabled', false);

        $this->actingAs(Customer::factory()->create(), 'customer')->get(route('home'))->assertOk()
            ->assertViewHas('cartCount', 0)->assertViewHas('favoriteIds', []);
    }

    public function test_newsletter_validates_normalizes_and_deduplicates_email(): void
    {
        $this->postJson(route('newsletter.store'), ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('newsletter.store'), ['email' => ' Person@Example.com '])->assertOk();
        $this->postJson(route('newsletter.store'), ['email' => 'person@example.com'])->assertOk();
        $this->assertDatabaseCount('newsletter_subscriptions', 1);
        $this->assertDatabaseHas('newsletter_subscriptions', ['email' => 'person@example.com']);
    }

    public function test_newsletter_supports_standard_forms_and_limits_requests(): void
    {
        $this->post(route('newsletter.store'), ['email' => 'form@example.com'])
            ->assertRedirect(route('home').'#newsletter')->assertSessionHas('newsletter_status');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson(route('newsletter.store'), ['email' => 'form@example.com'])->assertOk();
        }
        $this->postJson(route('newsletter.store'), ['email' => 'form@example.com'])->assertStatus(429);
    }

    public function test_home_content_endpoint_requires_admin_and_merges_valid_content(): void
    {
        $this->patchJson(route('dashboard.setting.home-content.update'), ['home_content' => ['hero_text_1' => 'Changed']])->assertUnauthorized();
        $this->actingAs(Admin::factory()->create(), 'admin')->patchJson(route('dashboard.setting.home-content.update'), [
            'home_content' => ['hero_text_1' => 'Unauthorized'],
        ])->assertForbidden();
        $admin = Admin::factory()->create(['super_admin' => true]);
        $this->actingAs($admin, 'admin')->patchJson(route('dashboard.setting.home-content.update'), [
            'home_content' => ['hero_text_1' => 'Changed'],
        ])->assertOk();
        $this->actingAs($admin, 'admin')->patchJson(route('dashboard.setting.home-content.update'), [
            'home_content' => ['trust_text_1' => 'Authentic'],
        ])->assertOk();
        $this->assertSame('Changed', Setting::query()->first()->home_content['hero_text_1']);
        $this->actingAs($admin, 'admin')->patchJson(route('dashboard.setting.home-content.update'), [
            'home_content' => ['unknown' => 'Invalid'],
        ])->assertUnprocessable();
    }

    private function product(Vendor $vendor, string $name, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $vendor->id, 'name' => $name, 'slug' => $name,
            'price' => 25, 'stock_quantity' => 10, 'status' => 'active',
        ], $attributes));
    }
}
