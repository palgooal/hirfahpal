<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account details is a read-only storefront migration: the customer's own values
 * are shown, and both update actions stay inert until their backend exists.
 */
class CustomerAccountDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Account-details-1';

    public function test_guest_is_sent_to_customer_login_and_returns_after_login(): void
    {
        $customer = Customer::factory()->create(['email' => 'details@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.account-details'))->assertRedirect(route('customer.login'));
        $this->assertSame(route('customer.account-details'), session('url.intended'));

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.account-details'));
    }

    public function test_active_customer_gets_the_storefront_account_details_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.account-details'))
            ->assertOk()
            ->assertViewIs('pages.customer-account-details')
            ->assertSee('<title>تفاصيل الحساب | حرفة</title>', false)
            ->assertSee('<main data-profile-page>', false);
    }

    public function test_inputs_show_only_the_authenticated_customers_values(): void
    {
        $customer = Customer::factory()->create(['name' => 'سلمى الخطيب', 'email' => 'salma@example.com', 'phone' => '0591234567']);
        Customer::factory()->create(['name' => 'زبون آخر', 'email' => 'other@example.com', 'phone' => '0597654321']);

        $main = $this->main($customer);

        $this->assertMatchesRegularExpression('/name="fullName" value="سلمى الخطيب"/', $main);
        $this->assertMatchesRegularExpression('/name="email" value="salma@example\.com"/', $main);
        $this->assertMatchesRegularExpression('/name="phone" value="0591234567"/', $main);

        foreach (['زبون آخر', 'other@example.com', '0597654321', 'ليان خليل', 'layan.khalil@example.com', '0599123456'] as $foreign) {
            $this->assertStringNotContainsString($foreign, $main, $foreign);
        }
    }

    public function test_input_values_are_escaped(): void
    {
        $customer = Customer::factory()->create(['name' => '"><script>alert(1)</script>']);

        $main = $this->main($customer);

        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $main);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $main);
    }

    public function test_null_email_renders_an_empty_value(): void
    {
        $this->assertMatchesRegularExpression('/name="email" value=""/', $this->main(Customer::factory()->create(['email' => null])));
    }

    public function test_password_fields_never_carry_a_value(): void
    {
        $customer = Customer::factory()->create();
        $main = $this->main($customer);

        foreach (['currentPassword', 'newPassword', 'confirmPassword'] as $field) {
            $this->assertMatchesRegularExpression('/<input type="password" name="'.$field.'" required autocomplete="[a-z-]+"\s+class="/', $main, $field);
        }
        $this->assertStringNotContainsString($customer->password, $main);
    }

    public function test_sidebar_marks_account_details_active_with_real_links(): void
    {
        $main = $this->main(Customer::factory()->create());

        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.account-details'), '/').'"\s+aria-current="page"/', $main);
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.dashboard'), '/').'"\s+class=/', $main);

        foreach (['dashboard/orders.html', 'dashboard/addresses.html', 'dashboard/favorites.html'] as $deferred) {
            $this->assertStringContainsString('data-deferred-navigation="'.$deferred.'"', $main, $deferred);
        }
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $main);
    }

    public function test_update_actions_are_inert_with_no_backend_endpoint(): void
    {
        $main = $this->main(Customer::factory()->create());

        foreach (['personalInfoForm', 'passwordForm'] as $form) {
            // No action/method attributes: the form tag carries only id, class and novalidate.
            $this->assertMatchesRegularExpression('/<form id="'.$form.'" class="[^"]*" novalidate>/', $main, $form);

            $start = strpos($main, '<form id="'.$form.'"');
            $markup = substr($main, $start, strpos($main, '</form>', $start) - $start);
            $this->assertSame(1, substr_count($markup, '<button type="button"'), $form);
            $this->assertStringNotContainsString('type="submit"', $markup, $form);
        }

        // Only the sidebar logout form carries CSRF; no PUT/PATCH spoofing anywhere.
        $this->assertSame(1, substr_count($main, 'name="_token"'));
        $this->assertStringNotContainsString('name="_method"', $main);

        // The reference password error slot is kept, hidden and empty.
        $this->assertMatchesRegularExpression('/<p data-password-error class="hidden [^"]*"\s+role="alert"><\/p>/', $main);
    }

    public function test_no_update_route_is_registered(): void
    {
        $methods = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'customer/account-details')
            ->flatMap(fn ($route) => $route->methods())
            ->unique()->values()->all();

        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $methods);
    }

    public function test_blocked_customer_is_forced_out(): void
    {
        $customer = Customer::factory()->create(['status' => 'blocked']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.account-details'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    public function test_vendor_and_driver_dashboards_are_unaffected(): void
    {
        $this->actingAs(Vendor::factory()->create(), 'vendor')
            ->get(route('vendor.dashboard'))->assertOk()->assertViewIs('accounts.dashboard');

        $this->actingAs(DeliveryDriver::factory()->create(), 'delivery_driver')
            ->get(route('delivery-driver.dashboard'))->assertOk()->assertViewIs('accounts.dashboard');
    }

    private function main(Customer $customer): string
    {
        $html = $this->actingAs($customer, 'customer')->get(route('customer.account-details'))->assertOk()->getContent();
        $start = strpos($html, '<main data-profile-page>');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) - $start);
    }
}
