<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a signed-in account lands on a guest login page. Only the vendor login
 * has its own destination (VendorLoginPageTest); every other guard keeps
 * Laravel's default, which is the "home" route in this application.
 */
class AuthenticatedLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_admin_keeps_the_default_destination(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('home'));
    }

    public function test_signed_in_customer_keeps_the_default_destination(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.login'))
            ->assertRedirect(route('home'));
    }

    public function test_signed_in_delivery_driver_keeps_the_default_destination(): void
    {
        $this->actingAs(DeliveryDriver::factory()->create(), 'delivery_driver')
            ->get(route('delivery-driver.login'))
            ->assertRedirect(route('home'));
    }

    public function test_signed_in_web_user_keeps_the_default_destination(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_a_vendor_session_does_not_change_another_guards_login_destination(): void
    {
        $this->actingAs(Vendor::factory()->approved()->create(), 'vendor')
            ->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('home'));
    }
}
