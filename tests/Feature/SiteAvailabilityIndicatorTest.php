<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 3C (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7.4, DEC-SA-06): the effective-state
 * badge in the admin header (every admin; a link to the settings card for a Super Admin only; server override shown)
 * and the storefront preview bar (only for an admin the gate let through in a closed state, from the gate's request
 * attribute, without a query of its own).
 */
class SiteAvailabilityIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_20_000000_add_site_availability_to_settings_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        config(['site-availability.override' => 'auto']);
    }

    // ---------------------------------------------------------------- header badge

    public function test_every_admin_sees_the_effective_state(): void
    {
        $super = $this->admin(super: true);
        $plain = $this->admin();

        foreach (['open' => 'is-active', 'coming_soon' => 'is-low', 'maintenance' => 'is-suspended'] as $state => $tone) {
            $this->store($state);

            foreach ([$super, $plain] as $admin) {
                $header = $this->header($this->dashboard($admin));
                $this->assertStringContainsString('data-site-availability-badge="'.$state.'"', $header, $state);
                $this->assertMatchesRegularExpression('#class="admin-products-badge '.$tone.'#', $header, $state);
                $this->assertStringNotContainsString('data-site-availability-forced', $header);
            }
        }
    }

    public function test_the_badge_shows_the_state_names(): void
    {
        $admin = $this->admin();

        foreach (['open' => 'Open', 'coming_soon' => 'Coming soon', 'maintenance' => 'Maintenance'] as $state => $label) {
            $this->store($state);
            $this->assertStringContainsString('<span>'.$label.'</span>', $this->header($this->dashboard($admin)), $state);
        }
    }

    public function test_only_a_super_admin_gets_a_link_to_the_settings_card(): void
    {
        $link = route('dashboard.setting.index').'#site-availability';

        $this->assertStringContainsString('href="'.$link.'"', $this->header($this->dashboard($this->admin(super: true))));

        foreach ([[], ['settings.view'], ['settings.view', 'settings.edit'], ['maintenance.bypass']] as $abilities) {
            $header = $this->header($this->dashboard($this->admin(abilities: $abilities)));
            $this->assertStringNotContainsString($link, $header, implode(',', $abilities));
            $this->assertMatchesRegularExpression('#data-site-availability-badge="open"[^>]*>\s*<span class="admin-products-badge#', $header, 'A plain, non-interactive badge.');
        }
    }

    public function test_a_server_override_is_shown_on_the_badge(): void
    {
        $this->store('open');
        config(['site-availability.override' => 'maintenance']);

        foreach ([$this->admin(super: true), $this->admin()] as $admin) {
            $header = $this->header($this->dashboard($admin));
            $this->assertMatchesRegularExpression('#data-site-availability-badge="maintenance"\s+data-site-availability-forced#', $header);
            $this->assertStringContainsString('ph-lock-simple', $header);
            $this->assertStringContainsString('· Server override', $header);
            $this->assertStringContainsString('title="Site availability: Maintenance (Server override)"', $header);
        }
    }

    public function test_without_the_migration_the_badge_shows_open_and_the_dashboard_works(): void
    {
        (require database_path(self::MIGRATION))->down();

        $header = $this->header($this->dashboard($this->admin(super: true)));
        $this->assertStringContainsString('data-site-availability-badge="open"', $header);
    }

    public function test_an_unreadable_settings_table_falls_back_to_open(): void
    {
        $admin = $this->admin();
        Schema::drop('settings');

        $this->assertStringContainsString('data-site-availability-badge="open"', $this->header($this->dashboard($admin)));
    }

    public function test_the_header_reads_the_state_once_per_request(): void
    {
        $this->store('coming_soon');
        $super = $this->admin(super: true);
        $this->dashboard($super);

        $this->assertSame(1, $this->stateReads(fn () => $this->actingAs($super, 'admin')->get(route('dashboard.home'))->assertOk()), 'One memoized state read.');

        // The settings page: the same memoized state read, shared by the header and the card, plus the card's own read
        // of the saved row (mode, date, author). The header adds nothing there.
        $this->assertSame(2, $this->stateReads(fn () => $this->actingAs($super, 'admin')->get(route('dashboard.setting.index'))->assertOk()));
        $plain = $this->admin(abilities: ['settings.view']);
        $this->assertSame(1, $this->stateReads(fn () => $this->actingAs($plain, 'admin')->get(route('dashboard.setting.index'))->assertOk()), 'No card: the header read only.');

        config(['site-availability.override' => 'maintenance']);
        $this->assertSame(0, $this->stateReads(fn () => $this->actingAs($super, 'admin')->get(route('dashboard.home'))->assertOk()), 'Forced: no read.');
    }

    public function test_the_badge_creates_no_translation_row(): void
    {
        $this->dashboard($this->admin(super: true));

        $this->assertSame(0, DB::table('translation_values')->where('key', 'like', 'dashboard.Site_Availability_%')->count());
    }

    // ---------------------------------------------------------------- storefront preview bar

    public function test_a_super_admin_previewing_a_closed_store_sees_the_bar(): void
    {
        $super = $this->admin(super: true);

        $this->store('coming_soon');
        $this->actingAs($super, 'admin')->get('/')->assertOk()
            ->assertSee('data-site-availability-preview="coming_soon"', false)
            ->assertSee('الموقع في وضع «قريبًا» ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.')
            ->assertDontSee('data-site-availability="coming_soon"', false);

        $this->store('maintenance');
        $this->actingAs($super, 'admin')->get(route('contact.create'))->assertOk()
            ->assertSee('data-site-availability-preview="maintenance"', false)
            ->assertSee('الموقع في وضع الصيانة ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.');
    }

    public function test_an_admin_with_maintenance_bypass_sees_the_bar(): void
    {
        $this->store('maintenance');

        $this->actingAs($this->admin(abilities: ['maintenance.bypass']), 'admin')->get('/')->assertOk()
            ->assertSee('data-site-availability-preview="maintenance"', false);
    }

    public function test_the_bar_is_accessible_and_in_the_page_flow(): void
    {
        $this->store('coming_soon');
        $html = $this->actingAs($this->admin(super: true), 'admin')->get('/')->getContent();

        $this->assertMatchesRegularExpression('#<body[^>]*>\s*<div role="note" dir="rtl" lang="ar" data-site-availability-preview="coming_soon" class="[^"]*"#', $html, 'First in <body>, before the header.');
        preg_match('#data-site-availability-preview="coming_soon" class="([^"]*)"#', $html, $class);
        foreach (['fixed', 'sticky', 'absolute', 'z-'] as $positioning) {
            $this->assertStringNotContainsString($positioning, $class[1], 'Not positioned over the page.');
        }
    }

    public function test_no_bar_for_admins_without_bypass_customers_vendors_or_an_open_store(): void
    {
        $this->store('coming_soon');

        $this->actingAs($this->admin(abilities: ['settings.edit']), 'admin')->get('/')
            ->assertSee('data-site-availability="coming_soon"', false)->assertDontSee('data-site-availability-preview', false);

        $this->actingAs($this->admin(super: true, status: 'blocked'), 'admin')->get('/')
            ->assertDontSee('data-site-availability-preview', false);
        auth('admin')->logout();

        $this->actingAs(Customer::factory()->create(), 'customer')->get('/')
            ->assertSee('data-site-availability="coming_soon"', false)->assertDontSee('data-site-availability-preview', false);

        $vendor = Vendor::factory()->approved()->create();
        $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'))->assertOk()->assertDontSee('data-site-availability-preview', false);
        $this->actingAs($vendor, 'vendor')->get('/')->assertDontSee('data-site-availability-preview', false);

        $this->store('open');
        $this->actingAs($this->admin(super: true), 'admin')->get('/')->assertOk()->assertDontSee('data-site-availability-preview', false);
    }

    public function test_the_bar_adds_no_query(): void
    {
        $this->store('coming_soon');
        $super = $this->admin(super: true);
        $this->actingAs($super, 'admin')->get('/');

        $this->assertSame(1, $this->stateReads(fn () => $this->actingAs($super, 'admin')->get('/')->assertSee('data-site-availability-preview', false)), 'Only the gate reads the state.');
    }

    // ---------------------------------------------------------------- helpers

    private function store(string $state): void
    {
        Setting::singleton();
        DB::table('settings')->where('singleton_key', 1)->update(['site_availability_mode' => $state]);
    }

    private function dashboard(Admin $admin): TestResponse
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.home'))->assertOk();
    }

    private function header(TestResponse $response): string
    {
        preg_match('#<header class="pc-header admin-header".*?</header>#s', $response->getContent(), $match);
        $this->assertNotEmpty($match, 'The admin header is rendered.');

        return $match[0];
    }

    private function stateReads(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $reads = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql): bool => str_contains($sql, 'site_availability_mode'));
        DB::disableQueryLog();

        return count($reads);
    }

    private function admin(bool $super = false, array $abilities = [], string $status = 'active'): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => $status,
            'super_admin' => $super,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
