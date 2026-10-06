<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Addresses is a static storefront migration: the two approved demo cards are
 * rendered as-is and every address action stays inert until its backend exists.
 */
class CustomerAddressesPageTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Addresses-page-1';

    public function test_guest_is_sent_to_customer_login_and_returns_after_login(): void
    {
        $customer = Customer::factory()->create(['email' => 'addresses@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.addresses'))->assertRedirect(route('customer.login'));
        $this->assertSame(route('customer.addresses'), session('url.intended'));

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.addresses'));
    }

    public function test_active_customer_gets_the_storefront_addresses_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.addresses'))
            ->assertOk()
            ->assertViewIs('pages.customer-addresses')
            ->assertSee('<title>العنوان | حرفة</title>', false)
            ->assertSee('<meta name="description" content="عناوين التوصيل المحفوظة في حسابي على حرفة">', false)
            ->assertSee('<main data-profile-page>', false)
            ->assertSee('storefront-addresses', false);
    }

    public function test_sidebar_marks_addresses_active_and_links_are_real(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.addresses'), '/').'"\s+aria-current="page"/', $page);
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.dashboard'), '/').'"\s+class=/', $page);
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.account-details'), '/').'"\s+class=/', $page);
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.favorites'), '/').'"\s+class=/', $page);

        foreach (['dashboard/orders.html'] as $deferred) {
            $this->assertStringContainsString('data-deferred-navigation="'.$deferred.'"', $page, $deferred);
        }
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $page);
    }

    public function test_dashboard_address_card_links_to_the_addresses_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'))
            ->assertSee('<a href="'.route('customer.addresses').'" class="flex min-h-28', false);
    }

    public function test_the_two_approved_static_address_cards_are_rendered(): void
    {
        $page = $this->page();

        $this->assertSame(2, substr_count($page, 'class="address-list-card'));
        $this->assertMatchesRegularExpression('/data-address-id="home">.*?المنزل.*?افتراضي.*?ليان خليل<br>رام الله، حي الطيرة، قرب دوار الساعة<br><bdi>0599123456<\/bdi>/s', $page);
        $this->assertMatchesRegularExpression('/data-address-id="work">.*?العمل.*?ليان خليل<br>القدس، شارع صلاح الدين، الطابق <bdi>2<\/bdi><br><bdi>0599123456<\/bdi>/s', $page);
        $this->assertSame(1, substr_count($page, '>افتراضي<'));
    }

    public function test_empty_state_and_modal_start_hidden(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/<div data-address-empty-state class="mt-5 hidden [^"]*">/', $page);
        $this->assertStringContainsString('لا يوجد عناوين محفوظة', $page);
        $this->assertMatchesRegularExpression('/<div id="addressModal" class="fixed [^"]*\bhidden\b[^"]*"\s+role="dialog" aria-modal="true" aria-labelledby="addressModalTitle">/', $page);
        $this->assertStringContainsString('<h2 id="addressModalTitle" class="text-lg font-bold leading-7 text-ink">إضافة عنوان جديد</h2>', $page);
        // Header and empty-state add buttons open the modal; each card's edit button reuses it.
        $this->assertSame(2, substr_count($page, 'data-open-address-modal'));
        $this->assertSame(2, substr_count($page, 'data-edit-address'));
    }

    public function test_address_actions_are_inert_with_no_mutation_markup(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/<form id="addressForm" class="[^"]*" novalidate>/', $page);
        $start = strpos($page, '<form id="addressForm"');
        $form = substr($page, $start, strpos($page, '</form>', $start) - $start);
        $this->assertStringNotContainsString('type="submit"', $form);
        $this->assertMatchesRegularExpression('/<button type="button"\s+class="[^"]*">\s*<span>حفظ العنوان<\/span>/', $form);

        $this->assertSame(2, substr_count($page, '<button type="button" data-edit-address'));
        $this->assertSame(2, substr_count($page, '<button type="button" data-remove-address'));

        // Only the sidebar logout form carries CSRF; nothing spoofs PUT/PATCH/DELETE.
        $this->assertSame(1, substr_count($page, 'name="_token"'));
        $this->assertStringNotContainsString('name="_method"', $page);
    }

    public function test_no_address_crud_routes_are_registered(): void
    {
        $addressRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'address'));

        $this->assertSame(['customer/addresses'], $addressRoutes->map->uri()->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $addressRoutes->flatMap->methods()->unique()->values()->all());
    }

    public function test_rendering_does_not_query_address_or_location_tables(): void
    {
        $customer = Customer::factory()->create();

        DB::enableQueryLog();
        $this->actingAs($customer, 'customer')->get(route('customer.addresses'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertDoesNotMatchRegularExpression('/\b(customer_addresses|governorates|cities)\b/', $queries);
    }

    public function test_blocked_customer_is_forced_out(): void
    {
        $customer = Customer::factory()->create(['status' => 'blocked']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.addresses'))
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

    private function page(): string
    {
        return $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.addresses'))
            ->assertOk()
            ->getContent();
    }
}
