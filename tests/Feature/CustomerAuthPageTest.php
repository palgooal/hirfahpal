<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_page_contains_real_login_and_register_forms(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertViewIs('pages.customer-login')
            ->assertSee('method="POST" action="'.route('customer.login.store').'"', false)
            ->assertSee('method="POST" action="'.route('customer.register.store').'"', false)
            ->assertSee('name="full_name"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="terms"', false);
    }

    public function test_customer_register_route_opens_the_storefront_register_tab(): void
    {
        $this->get(route('customer.register'))
            ->assertOk()
            ->assertViewIs('pages.customer-login')
            ->assertSee('data-initial-auth-tab="register"', false)
            ->assertSee('id="registerPanel" role="tabpanel" aria-labelledby="registerTabButton" class="mt-6 flex flex-col gap-4"', false);
    }

    public function test_customer_can_register_from_the_storefront_page_and_reach_dashboard(): void
    {
        $this->from(route('customer.login'))
            ->post(route('customer.register.store'), [
                '_auth_panel' => 'register',
                'full_name' => 'Customer One',
                'phone' => '0594999000',
                'email' => 'customer-one@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'terms' => '1',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $customer = Customer::where('email', 'customer-one@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_register_validation_returns_to_register_tab(): void
    {
        $this->from(route('customer.login'))
            ->post(route('customer.register.store'), [
                '_auth_panel' => 'register',
                'full_name' => '',
                'phone' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors(['full_name', 'phone', 'email', 'password', 'terms']);

        $this->get(route('customer.login'))
            ->assertSee('data-initial-auth-tab="register"', false);
    }
}
