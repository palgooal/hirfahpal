<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront shell's account controls always point at the customer dashboard;
 * ARCH-04 sends guests through customer login and back.
 */
class StorefrontAccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Account-nav-1';

    public function test_header_account_icon_points_to_the_customer_dashboard(): void
    {
        $header = $this->section($this->get(route('home'))->getContent(), '<header', '</header>');

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('customer.dashboard'), '/').'"[^>]*aria-label="الحساب"/',
            $header
        );
    }

    public function test_footer_account_link_points_to_the_customer_dashboard(): void
    {
        $footer = $this->section($this->get(route('home'))->getContent(), '<footer', '</footer>');

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('customer.dashboard'), '/').'"[^>]*>\s*<i[^>]*><\/i>\s*<span>حسابي<\/span>/',
            $footer
        );
    }

    public function test_account_controls_no_longer_use_the_static_placeholder(): void
    {
        foreach (['home', 'vendors', 'customer.login'] as $page) {
            $html = $this->get(route($page))->getContent();

            $this->assertStringNotContainsString('data-deferred-navigation="profile.html"', $html, $page);
            $this->assertStringNotContainsString('dashboard/index.html', $html, $page);
        }
    }

    public function test_guest_following_the_account_link_logs_in_and_returns_to_the_dashboard(): void
    {
        $customer = Customer::factory()->create(['email' => 'account-nav@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->assertSame(route('customer.dashboard'), session('url.intended'));

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_authenticated_customer_reaches_the_dashboard_directly(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertViewIs('pages.customer-dashboard');
    }

    private function section(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, $open);

        return substr($html, $start, strpos($html, $close, $start) - $start);
    }
}
