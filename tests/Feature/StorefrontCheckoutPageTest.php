<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class StorefrontCheckoutPageTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_checkout_requires_customer_and_a_nonempty_cart(): void
    {
        $this->get(route('customer.checkout.show'))->assertRedirect(route('customer.login'));
        $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.checkout.show'))->assertRedirect(route('cart'));
    }

    public function test_checkout_only_renders_owned_addresses_and_current_cart_items(): void
    {
        $customer = Customer::factory()->create();
        $this->storefrontAddress($customer);
        $this->storefrontAddress(Customer::factory()->create(), ['address_line' => 'Foreign private street']);
        $product = $this->storefrontProduct();
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->get(route('customer.checkout.show'))->assertOk()->assertSee('Real street')->assertDontSee('Foreign private street')
            ->assertSee('test-product')->assertSee('50.00 ILS')->assertSee('id="checkoutForm"', false)
            ->assertSee('id="finalConfirmButton" type="submit"', false);
    }

    public function test_new_address_checkout_persists_address_order_and_stock_reservations(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->storefrontProduct();
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->post(route('customer.checkout.store'), ['addressChoice' => 'new', 'paymentMethod' => 'cod',
            'recipient_name' => 'Recipient', 'phone' => '0500000000', 'address_line' => 'New real street'])->assertRedirect(route('customer.dashboard'));
        $order = Order::query()->firstOrFail();
        $this->assertSame('50.00', $order->grand_total);
        $this->assertDatabaseHas('customer_addresses', ['customer_id' => $customer->id, 'address_line' => 'New real street']);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2, 'status' => 'reserved']);
    }

    public function test_checkout_refuses_an_address_owned_by_someone_else(): void
    {
        $address = $this->storefrontAddress(Customer::factory()->create());
        $this->actingAs(Customer::factory()->create(), 'customer')->postJson(route('customer.checkout.store'), [
            'payment_method' => 'cod', 'customer_address_id' => $address->id])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }
}
