<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class StorefrontCartPageTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_guest_cart_is_session_scoped_and_uses_current_prices(): void
    {
        $product = $this->storefrontProduct();
        $this->get(route('cart'))->assertOk()->assertSee('سلتك فارغة حالياً');
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $product->update(['price' => 30]);
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('cart'))->assertOk()
            ->assertViewHas('subtotal', 60.0)->assertSee('60.00 ILS');
    }

    public function test_cart_groups_real_items_by_vendor_and_has_native_quantity_forms(): void
    {
        $customer = Customer::factory()->create();
        $first = $this->storefrontProduct('first-product');
        $second = $this->storefrontProduct('second-product', Vendor::factory()->approved()->create());
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $first->id, 'quantity' => 2])->assertCreated();
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $second->id, 'quantity' => 1])->assertCreated();
        $this->get(route('cart'))->assertOk()->assertViewHas('cartGroups', fn ($groups) => $groups->count() === 2)
            ->assertSee('75.00 ILS')->assertSee('cart-plus-')->assertSee('name="_method" value="PATCH"', false);
    }

    public function test_customer_cannot_modify_another_customers_cart(): void
    {
        $owner = Customer::factory()->create();
        $product = $this->storefrontProduct();
        $cart = $owner->carts()->create(['status' => 'active']);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 25]);
        $this->actingAs(Customer::factory()->create(), 'customer')->patchJson(route('customer.cart.items.update', $item), ['quantity' => 3])->assertForbidden();
        $this->deleteJson(route('customer.cart.items.destroy', $item))->assertForbidden();
        $this->get(route('cart'))->assertOk()->assertViewHas('cartItems', fn ($items) => $items->isEmpty());
    }

    public function test_rendered_quantity_form_contains_valid_method_spoofing_and_submits(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->storefrontProduct();
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $item = $customer->carts()->first()->items()->first();
        $html = $this->get(route('cart'))->assertOk()->getContent();
        preg_match('/<form hidden id="cart-plus-'.$item->id.'"[^>]*>(.*?)<\/form>/s', $html, $form);
        $this->assertStringContainsString('name="_method" value="PATCH"', $form[1]);
        $this->assertStringNotContainsString('@method', $form[1]);
        $this->from(route('cart'))->post(route('customer.cart.items.update', $item), ['_method' => 'PATCH', 'quantity' => 2])->assertRedirect(route('cart'));
        $this->assertSame(2, $item->fresh()->quantity);
    }
}
