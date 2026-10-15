<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class CustomerDashboardPageTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_dashboard_requires_customer_and_greets_the_current_user(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $customer = Customer::factory()->create(['name' => 'سلمى الخطيب']);
        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertOk()->assertViewIs('pages.customer-dashboard')
            ->assertSee('سلمى الخطيب')->assertSee('لا توجد طلبات بعد')->assertDontSee('HF-2026-0814');
    }

    public function test_dashboard_uses_only_own_latest_order_and_favorite_counts(): void
    {
        $customer = Customer::factory()->create();
        $own = $this->storefrontOrder($customer, 'OWN-ORDER');
        $this->storefrontOrder(Customer::factory()->create(), 'FOREIGN-ORDER');
        $product = $this->storefrontProduct();
        $customer->favorites()->create(['product_id' => $product->id]);
        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertOk()->assertSee('OWN-ORDER')->assertDontSee('FOREIGN-ORDER')
            ->assertViewHas('favoriteCount', 1)->assertViewHas('latestOrder', fn ($order) => $order->id === $own->id);
        $this->get(route('customer.orders.index'))->assertOk()->assertSee('OWN-ORDER')->assertDontSee('FOREIGN-ORDER');
        $this->get(route('customer.orders.show', $own))->assertOk()->assertSee('OWN-ORDER');
    }

    public function test_customer_cannot_open_another_customers_order_in_html_or_json(): void
    {
        $foreign = $this->storefrontOrder(Customer::factory()->create());
        $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.orders.show', $foreign))->assertForbidden();
        $this->getJson(route('customer.orders.show', $foreign))->assertForbidden();
    }
}
