<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ARCH-04: guests of each account area land on that area's own login, and the
 * legacy Fortify GET /login now points at the canonical customer login.
 */
class CustomerLoginCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Canonical-login-1';

    public function test_guest_customer_protected_routes_redirect_to_customer_login(): void
    {
        foreach (['customer.dashboard', 'customer.orders.index', 'customer.account-details', 'customer.addresses'] as $protected) {
            $this->get(route($protected))->assertRedirect(route('customer.login'));
            $this->assertSame(route($protected), session('url.intended'), $protected);
            $this->flushSession();
        }
    }

    public function test_guest_can_access_customer_cart_without_login(): void
    {
        $this->get(route('customer.cart.show'))->assertOk();

        $this->assertGuest('customer');
    }

    public function test_customer_intended_url_survives_successful_login(): void
    {
        $customer = Customer::factory()->create(['email' => 'customer@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.orders.index'))->assertRedirect(route('customer.login'));
        $this->get(route('customer.login'))->assertOk()->assertViewIs('pages.customer-login');

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.orders.index'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_guest_vendor_route_redirects_to_vendor_login_and_returns_after_login(): void
    {
        $vendor = Vendor::factory()->approved()->create(['email' => 'vendor@example.com', 'password' => self::PASSWORD]);

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));
        $this->assertSame(route('vendor.dashboard'), session('url.intended'));

        $this->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_guest_delivery_driver_route_redirects_to_its_login_and_returns_after_login(): void
    {
        $driver = DeliveryDriver::factory()->create(['email' => 'driver@example.com', 'password' => self::PASSWORD]);

        $this->get(route('delivery-driver.dashboard'))->assertRedirect(route('delivery-driver.login'));
        $this->assertSame(route('delivery-driver.dashboard'), session('url.intended'));

        $this->post(route('delivery-driver.login.store'), ['login' => $driver->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('delivery-driver.dashboard'));
        $this->assertAuthenticatedAs($driver, 'delivery_driver');
    }

    public function test_guest_post_to_customer_route_redirects_without_storing_its_url(): void
    {
        $this->post(route('customer.checkout.store'))->assertRedirect(route('customer.login'));

        $this->assertNotSame(route('customer.checkout.store'), session('url.intended'));
    }

    public function test_customer_json_request_returns_401_instead_of_redirecting(): void
    {
        $this->getJson(route('customer.dashboard'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_admin_protected_route_still_redirects_to_admin_login(): void
    {
        $this->get(route('media-library.page'))->assertRedirect(route('admin.login'));
        $this->get(route('dashboard.home'))->assertRedirect(route('admin.login'));
    }

    public function test_public_vendors_page_is_not_treated_as_the_vendor_account_area(): void
    {
        $this->get(route('vendors'))->assertOk();
    }

    public function test_login_page_redirects_to_customer_login(): void
    {
        $this->get('/login')->assertRedirect(route('customer.login'));
    }

    public function test_authenticated_customer_visiting_login_does_not_loop(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->get('/login')->assertRedirect(route('customer.login'));
        $this->get(route('customer.login'))->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();
    }

    public function test_login_route_name_still_resolves_to_login_path(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertSame(url('/login'), route('login'));
    }

    public function test_fortify_login_post_route_remains_registered(): void
    {
        $route = Route::getRoutes()->getByName('login.store');

        $this->assertNotNull($route);
        $this->assertSame('login', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertCount(1, collect(Route::getRoutes()->getRoutes())->filter(
            fn ($candidate) => $candidate->uri() === 'login' && in_array('GET', $candidate->methods(), true)
        ));
    }
}
