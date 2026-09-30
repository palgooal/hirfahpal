<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_customer_gets_the_storefront_dashboard(): void
    {
        $customer = Customer::factory()->create(['name' => 'سلمى عبد الرحمن الخطيب']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertViewIs('pages.customer-dashboard')
            ->assertSee('data-dashboard-page', false)
            ->assertSee('<title>لوحة التحكم | حرفة</title>', false);
    }

    public function test_dashboard_greets_the_authenticated_customer_by_full_name_only(): void
    {
        $customer = Customer::factory()->create(['name' => 'سلمى عبد الرحمن الخطيب']);
        Customer::factory()->create(['name' => 'زبون آخر مختلف']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertSeeText('مرحباً بعودتك، سلمى عبد الرحمن الخطيب')
            ->assertDontSeeText('زبون آخر مختلف')
            ->assertDontSeeText('ليان');
    }

    public function test_customer_name_is_escaped(): void
    {
        $customer = Customer::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_reference_demo_data_is_not_rendered(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'));

        $response->assertDontSee('HF-2026-0814')
            ->assertDontSee('₪1,066');

        $main = $this->mainContent($response->getContent());
        $this->assertStringNotContainsString('>آخر طلب<', $main);
        $this->assertStringNotContainsString('latestOrderTitle', $main);
        $this->assertStringNotContainsString('قطع محفوظة', $main);
        $this->assertStringNotContainsString('دار الكرمة', $main);
        $this->assertDoesNotMatchRegularExpression('/>\s*5\s*</', $main);
    }

    public function test_dashboard_navigation_and_deferred_sections(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'));

        $main = $this->mainContent($response->getContent());

        $this->assertStringContainsString('href="'.route('customer.dashboard').'"', $main);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('customer.dashboard'), '/').'"\s+aria-current="page"/', $main);
        $this->assertStringContainsString('href="'.route('home').'"', $main);

        foreach (['الطلبات', 'العنوان', 'تفاصيل الحساب', 'المفضلة'] as $label) {
            $this->assertStringContainsString('<span>'.$label.'</span>', $main, $label);
        }

        // Deferred sections never emit a link; only home and dashboard hrefs exist inside <main>.
        preg_match_all('/href="([^"]*)"/', $main, $hrefs);
        $this->assertSame([], array_values(array_diff(array_unique($hrefs[1]), [route('home'), route('customer.dashboard')])));
        $this->assertStringNotContainsString('href="#"', $main);
        $this->assertSame(6, substr_count($main, 'aria-disabled="true"'));
    }

    public function test_logout_is_a_post_form_with_csrf(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'));

        $main = $this->mainContent($response->getContent());

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="'.preg_quote(route('customer.logout'), '/').'">\s*<input type="hidden" name="_token" value="[^"]+"/',
            $main
        );
        $this->assertStringContainsString('<button type="submit"', $main);
    }

    public function test_customer_can_log_out_from_the_dashboard_form(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    public function test_vendor_dashboard_still_uses_the_shared_account_view(): void
    {
        $this->actingAs(Vendor::factory()->create(), 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertViewIs('accounts.dashboard')
            ->assertDontSee('data-dashboard-page', false);
    }

    public function test_delivery_driver_dashboard_still_uses_the_shared_account_view(): void
    {
        $this->actingAs(DeliveryDriver::factory()->create(), 'delivery_driver')
            ->get(route('delivery-driver.dashboard'))
            ->assertOk()
            ->assertViewIs('accounts.dashboard')
            ->assertDontSee('data-dashboard-page', false);
    }

    public function test_guest_is_redirected_to_customer_login(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }

    public function test_blocked_customer_is_forced_out_to_customer_login(): void
    {
        $customer = Customer::factory()->create(['status' => 'blocked']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main data-dashboard-page>');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) - $start);
    }
}
