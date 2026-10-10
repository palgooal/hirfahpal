<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontVendorSellabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_catalog_only_exposes_products_from_approved_active_vendors(): void
    {
        $approved = $this->product($this->vendor('approved'), 'approved-product');
        $pending = $this->product($this->vendor('pending'), 'pending-product');
        $rejected = $this->product($this->vendor('rejected'), 'rejected-product');
        $blocked = $this->product($this->vendor('approved', accountStatus: 'blocked'), 'blocked-product');

        $response = $this->getJson(route('shop.products.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($approved->id));
        $this->assertFalse($ids->contains($pending->id));
        $this->assertFalse($ids->contains($rejected->id));
        $this->assertFalse($ids->contains($blocked->id));

        $this->getJson(route('shop.products.show', $approved))->assertOk();
        $this->getJson(route('shop.products.show', $pending))->assertNotFound();
        $this->getJson(route('shop.products.show', $rejected))->assertNotFound();
        $this->getJson(route('shop.products.show', $blocked))->assertNotFound();
    }

    public function test_cart_refuses_products_from_unapproved_vendors(): void
    {
        $pending = $this->product($this->vendor('pending'), 'pending-product');

        $this->postJson(route('customer.cart.items.store'), [
            'product_id' => $pending->id,
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertSame(0, Cart::count());
    }

    public function test_checkout_rechecks_vendor_approval_before_creating_an_order(): void
    {
        $vendor = $this->vendor('approved');
        $product = $this->product($vendor, 'approved-then-pending');
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
        ]);

        $vendor->profile()->update(['approval_status' => 'pending']);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422);

        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockReservation::count());
    }

    public function test_checkout_uses_the_current_product_price_instead_of_stale_cart_price(): void
    {
        $product = $this->product($this->vendor('approved'), 'current-price', price: 25);
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
        ]);

        $product->update(['price' => 30]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertCreated()
            ->assertJsonPath('subtotal', '60.00')
            ->assertJsonPath('vendor_orders.0.subtotal', '60.00')
            ->assertJsonPath('vendor_orders.0.items.0.unit_price', '30.00')
            ->assertJsonPath('vendor_orders.0.items.0.line_total', '60.00');
    }

    private function vendor(string $approval, string $accountStatus = 'active'): Vendor
    {
        return Vendor::factory()
            ->withProfile($approval)
            ->create(['status' => $accountStatus]);
    }

    private function product(Vendor $vendor, string $slug, int $stock = 10, int $price = 10): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => str_replace('-', ' ', $slug),
            'slug' => $slug,
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
        ]);
    }
}
