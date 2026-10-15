<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicStorefrontPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_categories_and_product_pages_render_database_records(): void
    {
        $product = $this->product();
        $this->get(route('categories'))->assertOk()->assertSee('Real category')
            ->assertViewHas('categories', fn ($categories) => $categories->first()->products_count === 1);
        $this->get(route('browse'))->assertOk()->assertSee('Real product')->assertSee('data-price="25.00"', false)
            ->assertSee('value="real-category"', false)->assertSee('browse-cart-'.$product->id);
        $this->get(route('product', ['slug' => $product->slug]))->assertOk()->assertSee('Real product')
            ->assertSee('25.00 ILS')->assertSee('main-cart-'.$product->id)->assertDontSee('سارة القواسمي');
        $this->get(route('product', ['slug' => 'missing']))->assertNotFound();
    }

    public function test_cart_browser_forms_persist_quantity_and_removal(): void
    {
        $product = $this->product();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->from(route('product', ['slug' => $product->slug]))
            ->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $item = $customer->carts()->first()->items()->first();
        $this->get(route('cart'))->assertOk()->assertSee('Real product')->assertSee('50.00 ILS')
            ->assertSee('cart-plus-'.$item->id)->assertDontSee('₪1,011');
        $this->from(route('cart'))->patch(route('customer.cart.items.update', $item), ['quantity' => 3])->assertRedirect(route('cart'));
        $this->assertSame(3, $item->fresh()->quantity);
        $this->from(route('cart'))->delete(route('customer.cart.items.destroy', $item))->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertOk()->assertSee('سلتك فارغة حالياً');
    }

    public function test_account_dashboard_addresses_and_favorites_render_only_customer_data(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product();
        $customer->favorites()->create(['product_id' => $product->id]);
        $customer->addresses()->create(['label' => 'My address', 'recipient_name' => 'My recipient', 'phone' => '0500000000', 'address_line' => 'My street']);
        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertOk()->assertSee($customer->name)->assertDontSee('HF-2026-0814');
        $this->get(route('customer.favorites'))->assertOk()->assertSee('Real product')->assertSee('favorite-remove-');
        $this->get(route('customer.addresses'))->assertOk()->assertSee('My street')->assertSee('My recipient')->assertDontSee('ليان خليل');
        $this->get(route('customer.account-details'))->assertOk()->assertSee(route('customer.account-details.update'), false);
    }

    public function test_checkout_renders_actual_cart_and_creates_order_with_server_totals(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1]);
        $address = $customer->addresses()->create(['label' => 'My address', 'recipient_name' => 'My recipient', 'phone' => '0500000000', 'address_line' => 'My street']);
        $this->actingAs($customer, 'customer')->get(route('customer.checkout.show'))->assertOk()->assertSee('Real product')->assertSee('My street')->assertSee('50.00 ILS');
        $this->post(route('customer.checkout.store'), ['paymentMethod' => 'cod', 'addressChoice' => (string) $address->id,
            'delivery_fees' => [$product->vendor_id => 9999]])->assertRedirect(route('customer.dashboard'));
        $order = Order::query()->firstOrFail();
        $this->assertSame('50.00', $order->grand_total);
        $this->assertSame('0.00', $order->delivery_total);
        $this->assertSame('ordered', $cart->fresh()->status);
        $this->get(route('customer.dashboard'))->assertOk()->assertSee($order->number)->assertSee('50.00 ILS');
    }

    private function product(): Product
    {
        $category = Category::query()->create(['name' => 'Real category', 'slug' => 'real-category', 'is_active' => true]);
        $vendor = Vendor::factory()->approved()->create();
        $vendor->profile()->update(['store_name' => 'Real store', 'short_description' => 'Real store description']);

        return Product::query()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Real product',
            'slug' => 'real-product', 'price' => 25, 'stock_quantity' => 10, 'status' => 'active', 'description' => 'Real long description']);
    }
}
