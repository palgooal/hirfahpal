<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\NewsletterSubscription;
use App\Models\RoleUser;
use App\Models\Setting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 1 (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §4, §5): the gate. Statuses and
 * headers per state, method and format; exempt paths; blocked mutations never reaching their controllers; the gate
 * running before route authentication and model binding; sessions kept; CSRF order; admin bypass; a forced state
 * before the migrations; no behaviour change when open.
 */
class SiteAvailabilityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- open

    public function test_open_changes_nothing(): void
    {
        $this->state('open');

        $this->get('/')->assertOk()->assertDontSee('data-site-availability="', false);
        $this->get(route('contact.create'))->assertOk()->assertDontSee('data-site-availability="', false);
        $this->get(route('customer.login'))->assertOk();
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->get('/shop/products/does-not-exist')->assertNotFound();
    }

    // ---------------------------------------------------------------- statuses and headers

    public function test_coming_soon_answers_safe_requests_with_200_and_mutations_with_503(): void
    {
        $this->state('coming_soon');

        foreach (['/', route('product'), route('vendors'), route('categories'), route('contact.create'), route('customer.login'), route('customer.register'), route('delivery-driver.login'), route('login')] as $url) {
            $this->assertComingSoon($this->get($url), 200, $url);
        }

        foreach ([['POST', route('contact.store')], ['POST', route('newsletter.store')], ['POST', route('customer.login.store')], ['POST', route('delivery-driver.login.store')], ['POST', route('login.store')], ['POST', route('customer.cart.items.store')]] as [$method, $url]) {
            $this->assertComingSoon($this->call($method, $url), 503, $url);
        }

        $head = $this->call('HEAD', '/');
        $head->assertOk();
        $this->assertSame('', $head->getContent());
    }

    public function test_maintenance_answers_everything_with_503_and_retry_after(): void
    {
        $this->state('maintenance');
        config(['site-availability.retry_after' => 1800]);

        foreach ([['GET', '/'], ['GET', route('customer.login')], ['GET', route('delivery-driver.login')], ['POST', route('contact.store')], ['DELETE', route('customer.cart.items.destroy', 1)]] as [$method, $url]) {
            $response = $this->call($method, $url);
            $response->assertStatus(503)->assertHeader('Retry-After', '1800')->assertSee('data-site-availability="maintenance"', false);
            $this->assertNoStoreHeaders($response);
        }
    }

    public function test_json_requests_get_a_structured_body(): void
    {
        $this->state('coming_soon');
        $this->getJson('/')->assertOk()->assertExactJson(['state' => 'coming_soon', 'message' => 'متجر حِرفة قريبًا بين أيديكم.']);
        $this->postJson(route('newsletter.store'), ['email' => 'a@example.com'])->assertStatus(503)->assertJsonPath('state', 'coming_soon')->assertJsonMissingPath('retry_after');

        $this->state('maintenance');
        $this->getJson('/')->assertStatus(503)->assertExactJson(['state' => 'maintenance', 'message' => 'الموقع تحت الصيانة حاليًا، وسنعود قريبًا.', 'retry_after' => 3600]);
    }

    // ---------------------------------------------------------------- blocked requests never reach their routes

    public function test_blocked_mutations_never_reach_their_controllers(): void
    {
        foreach (['coming_soon', 'maintenance'] as $state) {
            $this->state($state);

            $this->post(route('contact.store'), ['name' => 'N', 'email' => 'n@example.com', 'message' => 'Hello'])->assertStatus(503);
            $this->post(route('newsletter.store'), ['email' => 'n@example.com'])->assertStatus(503);
            $this->postJson(route('customer.cart.items.store'), ['product_id' => 1, 'quantity' => 1])->assertStatus(503);
            $customer = Customer::factory()->create(['email' => $state.'@example.com', 'password' => 'password']);
            $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => 'password'])->assertStatus(503);
            $this->assertGuest('customer');
        }

        $this->assertSame(0, ContactMessage::query()->count());
        $this->assertSame(0, NewsletterSubscription::query()->count());
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_the_gate_runs_before_route_authentication_and_model_binding(): void
    {
        $this->state('coming_soon');

        // Not the auth redirect to the customer login, and not the 404 of the slug binding.
        $this->assertComingSoon($this->get(route('customer.dashboard')), 200, 'customer.dashboard');
        $this->assertComingSoon($this->get(route('delivery-driver.dashboard')), 200, 'delivery-driver.dashboard');
        $this->assertComingSoon($this->get('/shop/products/does-not-exist'), 200, 'shop.products.show');
    }

    public function test_csrf_is_still_checked_before_the_gate_and_a_valid_token_still_saves_nothing(): void
    {
        $this->state('coming_soon');
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->post(route('contact.store'), ['name' => 'N', 'email' => 'n@example.com', 'message' => 'Hello'])->assertStatus(419);
        $this->withSession(['_token' => 'known-token'])
            ->post(route('contact.store'), ['_token' => 'known-token', 'name' => 'N', 'email' => 'n@example.com', 'message' => 'Hello'])
            ->assertStatus(503);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_signed_in_customers_and_drivers_see_the_page_and_keep_their_sessions(): void
    {
        $customer = Customer::factory()->create();
        $driver = DeliveryDriver::factory()->create();

        foreach (['coming_soon' => 200, 'maintenance' => 503] as $state => $status) {
            $this->state($state);

            $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertStatus($status)->assertSee('data-site-availability', false);
            $this->assertAuthenticatedAs($customer, 'customer');

            $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertStatus($status)->assertSee('data-site-availability', false);
            $this->assertAuthenticatedAs($driver, 'delivery_driver');
        }
    }

    // ---------------------------------------------------------------- exempt paths

    public function test_admin_authentication_dashboard_health_and_locale_stay_reachable(): void
    {
        foreach (['coming_soon', 'maintenance'] as $state) {
            $this->state($state);

            $this->get(route('admin.login'))->assertOk()->assertDontSee('data-site-availability="', false);
            $this->get(route('admin.password.request'))->assertOk()->assertDontSee('data-site-availability="', false);
            $this->get(route('dashboard.home'))->assertRedirect(route('admin.login'));
            $this->get('/up')->assertOk();
            $this->from('/')->get('/change-locale/en')->assertRedirect();
        }

        // The locale switch only redirects: the target is still gated.
        $this->state('coming_soon');
        $this->followingRedirects()->from('/')->get('/change-locale/en')->assertOk()->assertSee('data-site-availability="coming_soon"', false);
    }

    public function test_admins_without_bypass_keep_the_dashboard_but_see_the_page_on_the_storefront(): void
    {
        $admin = $this->admin(abilities: ['customers.view']);

        foreach (['coming_soon' => 200, 'maintenance' => 503] as $state => $status) {
            $this->state($state);

            $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk()->assertDontSee('data-site-availability="', false);
            $this->actingAs($admin, 'admin')->get(route('dashboard.customers.index'))->assertOk();
            $this->actingAs($admin, 'admin')->get('/')->assertStatus($status)->assertSee('data-site-availability', false);
        }
    }

    // ---------------------------------------------------------------- bypass

    public function test_super_admins_and_admins_with_maintenance_bypass_preview_the_storefront(): void
    {
        foreach ([$this->admin(super: true), $this->admin(abilities: ['maintenance.bypass'])] as $admin) {
            foreach (['coming_soon', 'maintenance'] as $state) {
                $this->state($state);

                $this->actingAs($admin, 'admin')->get('/')->assertOk()->assertDontSee('data-site-availability="', false);
                $this->actingAs($admin, 'admin')->get(route('contact.create'))->assertOk()->assertDontSee('data-site-availability="', false);
            }
        }
    }

    public function test_bypass_grants_no_other_permission(): void
    {
        $admin = $this->admin(abilities: ['maintenance.bypass']);
        $this->state('maintenance');

        // Not a customer: the customer area keeps its own guard.
        $this->actingAs($admin, 'admin')->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        // No dashboard ability gained.
        $this->actingAs($admin, 'admin')->get(route('dashboard.customers.index'))->assertForbidden();
    }

    public function test_a_blocked_or_pending_admin_never_bypasses(): void
    {
        foreach (['pending', 'blocked'] as $status) {
            $admin = $this->admin(super: true, abilities: ['maintenance.bypass'], status: $status);
            $this->state('coming_soon');

            $this->actingAs($admin, 'admin')->get('/')->assertOk()->assertSee('data-site-availability="coming_soon"', false);
        }
    }

    // ---------------------------------------------------------------- environment override and database independence

    public function test_a_forced_state_works_before_the_migrations_without_any_query(): void
    {
        Schema::drop('settings');
        config(['site-availability.override' => 'coming_soon']);
        DB::enableQueryLog();

        $this->assertComingSoon($this->get('/'), 200, '/');
        $this->assertComingSoon($this->post(route('contact.store')), 503, 'contact.store');

        $this->assertSame([], DB::getQueryLog(), 'Neither the state nor the page reads the database.');
    }

    public function test_the_override_wins_over_the_stored_state_in_both_directions(): void
    {
        $this->state('open');
        config(['site-availability.override' => 'maintenance']);
        $this->get('/')->assertStatus(503);

        $this->state('maintenance');
        config(['site-availability.override' => 'open']);
        $this->get('/')->assertOk()->assertDontSee('data-site-availability="', false);
    }

    public function test_the_blocked_response_reads_only_the_setting(): void
    {
        $this->state('coming_soon');
        DB::enableQueryLog();

        $this->get('/')->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');
        $this->assertCount(1, $queries, implode(' | ', $queries));
        $this->assertStringContainsString('"settings"', $queries[0]);
    }

    public function test_exempt_paths_never_read_the_setting(): void
    {
        $this->state('maintenance');
        DB::enableQueryLog();

        $this->get(route('admin.login'))->assertOk();
        $this->get('/up')->assertOk();

        $settingsReads = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $query) => str_contains($query, 'site_availability_mode'));
        $this->assertSame([], array_values($settingsReads));
    }

    public function test_laravel_maintenance_mode_is_separate(): void
    {
        $this->state('maintenance');
        $this->get('/')->assertStatus(503);

        $this->assertFalse($this->app->isDownForMaintenance());
    }

    // ---------------------------------------------------------------- helpers

    private function state(string $mode): void
    {
        config(['site-availability.override' => 'auto']);
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => $mode]);
    }

    private function assertComingSoon(TestResponse $response, int $status, string $label): void
    {
        $this->assertSame($status, $response->status(), $label);
        $response->assertSee('data-site-availability="coming_soon"', false);
        $response->assertHeaderMissing('Retry-After');
        $this->assertNoStoreHeaders($response);
    }

    private function assertNoStoreHeaders(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    private function admin(bool $super = false, array $abilities = [], string $status = 'active'): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com', 'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password', 'status' => $status, 'super_admin' => $super,
        ]);
        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
