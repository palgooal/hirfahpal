<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The vendor dashboard browser shell (VUI-01B) on the canonical vendor.dashboard
 * route (VUI-01C / VEN-BE-010), and the overview JSON that moved, unchanged, to
 * vendor.dashboard.overview.
 */
class VendorDashboardShellTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    private const OVERVIEW_STATS = [
        'products_total',
        'products_active',
        'products_low_stock',
        'orders_pending',
        'orders_in_progress',
        'orders_completed',
        'sales_total',
        'commission_pending',
        'average_rating',
    ];

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // UI strings come from seeded translation_values; the default locale is Arabic (RTL).
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);

        // A hot file makes @vite print the entry names without needing a build.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---- Routes ----

    public function test_dashboard_route_is_the_gated_browser_page(): void
    {
        $route = Route::getRoutes()->getByName('vendor.dashboard');

        $this->assertSame('vendor/dashboard', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());

        $middleware = $route->gatherMiddleware();
        foreach (['auth:vendor', 'vendor.approved', 'setLocale', 'disableTranslationAutoCreate'] as $expected) {
            $this->assertContains($expected, $middleware);
        }
    }

    public function test_overview_json_has_its_own_gated_route(): void
    {
        $route = Route::getRoutes()->getByName('vendor.dashboard.overview');

        $this->assertSame('vendor/dashboard/overview', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('App\Http\Controllers\VendorDashboard\OverviewController', $route->getActionName());
        $this->assertContains('auth:vendor', $route->gatherMiddleware());
        $this->assertContains('vendor.approved', $route->gatherMiddleware());
    }

    public function test_temporary_panel_route_no_longer_exists(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('vendor.panel.home'));

        $this->as('approved')->get('/vendor/panel')->assertNotFound();
    }

    // ---- Canonical page ----

    public function test_approved_vendor_gets_the_html_shell_not_json(): void
    {
        $response = $this->as('approved')
            ->page()
            ->assertOk()
            ->assertViewIs('vendor-dashboard.home')
            ->assertSee('id="vendor-sidebar"', false)
            ->assertSee('class="pc-header"', false)
            ->assertSee('class="pc-container"', false)
            ->assertSee('Craft Corner');

        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
        $this->assertNull(json_decode($response->getContent()));
    }

    public function test_pending_vendor_cannot_access_the_dashboard(): void
    {
        $this->as('pending')->page()->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.dashboard'))->assertForbidden()->assertJsonPath('approval_status', 'pending');
        $this->getJson(route('vendor.dashboard.overview'))->assertForbidden()->assertJsonPath('approval_status', 'pending');
    }

    public function test_rejected_vendor_cannot_access_the_dashboard(): void
    {
        $this->as('rejected', 'Reason.')->page()->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.dashboard.overview'))->assertForbidden()->assertJsonPath('approval_status', 'rejected');
    }

    public function test_guest_is_sent_to_the_vendor_login(): void
    {
        $this->page()->assertRedirect(route('vendor.login'));
    }

    public function test_inactive_account_keeps_the_existing_account_status_behavior(): void
    {
        $vendor = Vendor::factory()->approved()->create(['status' => 'blocked']);

        $this->actingAs($vendor, 'vendor')->page()->assertRedirect();
        $this->assertGuest('vendor');
    }

    // ---- Overview JSON (moved, unchanged) ----

    public function test_overview_endpoint_keeps_the_existing_payload_contract(): void
    {
        $vendor = $this->approvedVendor();

        $response = $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.overview'))
            ->assertOk()
            ->assertJsonPath('vendor.id', $vendor->id)
            ->assertJsonStructure(['vendor' => ['id', 'profile'], 'stats', 'recent_orders', 'low_stock_products']);

        $this->assertSame(['vendor', 'stats', 'recent_orders', 'low_stock_products'], array_keys($response->json()));
        $this->assertSame(self::OVERVIEW_STATS, array_keys($response->json('stats')));
    }

    // ---- Auth flow ----

    public function test_approved_login_redirects_to_the_dashboard_and_renders_the_shell(): void
    {
        $vendor = $this->approvedVendor();

        $redirect = $this->login($vendor)->assertRedirect(route('vendor.dashboard'));

        $this->get($redirect->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('vendor-dashboard.home')
            ->assertSee('id="vendor-sidebar"', false);
    }

    public function test_pending_login_ends_on_the_approval_status_page(): void
    {
        $vendor = Vendor::factory()->withProfile('pending')->create(['password' => self::PASSWORD]);

        $this->from(route('vendor.login'))
            ->login($vendor)
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');
    }

    public function test_rejected_login_ends_on_the_rejected_status_with_reason(): void
    {
        $vendor = Vendor::factory()->withProfile('rejected', 'Store details are incomplete.')->create(['password' => self::PASSWORD]);

        $this->from(route('vendor.login'))
            ->login($vendor)
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');
    }

    public function test_registration_creates_a_pending_store_and_returns_to_login(): void
    {
        $response = $this->followingRedirects()
            ->post(route('vendor.register.store'), [
                'full_name' => 'Store Owner',
                'phone' => '0591000009',
                'email' => 'new-vendor@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'terms' => '1',
            ]);

        $response->assertOk()
            ->assertViewIs('auth.vendor.login')
            ->assertDontSee('id="vendor-sidebar"', false);

        $vendor = Vendor::with('profile')->sole();
        $this->assertSame('active', $vendor->status);
        $this->assertSame('pending', $vendor->profile->approval_status);
        $this->assertGuest('vendor');
    }

    public function test_approved_vendor_on_the_status_page_is_sent_to_the_dashboard(): void
    {
        $this->as('approved');

        $this->get(route('vendor.approval-status'))->assertRedirect(route('vendor.dashboard'));
        $this->getJson(route('vendor.approval-status'))->assertOk()->assertJsonPath('dashboard_url', route('vendor.dashboard'));
    }

    // ---- Locale ----

    public function test_arabic_renders_rtl_from_the_server(): void
    {
        $this->as('approved')
            ->page()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('data-pc-direction="rtl"', false)
            ->assertSee('لوحة التحكم')
            ->assertSee('مرحبًا بك في متجرك')
            ->assertSee('ملخص المتجر')
            ->assertSee('بوابة البائعين');
    }

    public function test_switching_languages_on_the_dashboard(): void
    {
        $this->as('approved');

        $this->get(route('vendor.dashboard').'?change-locale=en')->assertRedirect(route('vendor.dashboard'));
        $this->page()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('data-pc-direction="ltr"', false)
            ->assertSee('Welcome to your store')
            ->assertSee('Store overview')
            ->assertSee('Sign out');

        $this->get(route('vendor.dashboard').'?change-locale=ar')->assertRedirect(route('vendor.dashboard'));
        $this->page()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('مرحبًا بك في متجرك');
    }

    public function test_language_switcher_keeps_the_vendor_page(): void
    {
        $this->as('approved')
            ->page()
            ->assertSee('aria-label="اختيار اللغة"', false)
            ->assertSee('href="'.route('vendor.dashboard').'?change-locale=en"', false)
            ->assertDontSee('/admin/home', false);
    }

    public function test_light_vertical_theme_is_fixed_by_the_server(): void
    {
        $this->as('approved')
            ->page()
            ->assertSee('data-pc-theme="light"', false)
            ->assertSee('data-pc-layout="vertical"', false);
    }

    // ---- Shell content ----

    public function test_no_admin_routes_json_endpoints_or_authorization_ui(): void
    {
        $response = $this->as('approved')->page();

        // Pages may fetch JSON endpoints (VUI-02B, VUI-03B), but no link or form targets one.
        foreach ([
            route('vendor.dashboard.overview'), route('vendor.dashboard.profile.show'), route('vendor.dashboard.products.index'),
            route('vendor.dashboard.orders.index'), route('vendor.dashboard.reviews.index'), route('vendor.dashboard.returns.index'),
            route('vendor.dashboard.disputes.index'), route('vendor.dashboard.commissions.index'),
        ] as $endpoint) {
            $response->assertDontSee('href="'.$endpoint, false)->assertDontSee('action="'.$endpoint, false);
        }

        $response
            ->assertDontSee(route('admin.logout'), false)
            ->assertDontSee(url('/admin'), false)
            ->assertDontSee(url('/dashboard'), false)
            ->assertDontSee('/vendor/panel', false)
            ->assertDontSee('Permissions')
            ->assertDontSee('Admins')
            ->assertDontSee('إعدادات الموقع');
    }

    public function test_vendor_logout_is_a_post_form(): void
    {
        $response = $this->as('approved')
            ->page()
            ->assertSee('method="POST" action="'.route('vendor.logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('تسجيل الخروج');

        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('vendor.logout').'"'));
    }

    public function test_dashboard_link_points_to_the_canonical_route_and_is_the_only_active_link(): void
    {
        $html = $this->as('approved')->page()->getContent();

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('vendor.dashboard'), '/').'" class="pc-link"\s+aria-current="page"/', $html);
        // Dashboard and My Store (VUI-03B) are the navigable items; only Dashboard is current here.
        $this->assertSame(2, preg_match_all('/data-nav-state="link"/', $html));
        $this->assertSame(1, preg_match_all('/<li class="pc-item active"/', $html));
        $this->assertSame(2, preg_match_all('/aria-current="page"/', $html), 'Only the Dashboard nav link and its breadcrumb item are current.');
    }

    public function test_disabled_items_have_no_link(): void
    {
        $html = $this->as('approved')->page()->getContent();

        preg_match_all('/<li class="pc-item vendor-nav-disabled" data-nav-state="disabled">(.*?)<\/li>/s', $html, $matches);

        // My Store became a link in VUI-03B; the other six sections stay inactive placeholders.
        $this->assertCount(6, $matches[1]);
        foreach ($matches[1] as $item) {
            $this->assertStringNotContainsString('href', $item);
            $this->assertStringContainsString('aria-disabled="true"', $item);
            // No visible "Soon" badge; the status is announced to screen readers only.
            $this->assertStringContainsString('<span class="sr-only">(قريبًا)</span>', $item);
        }
        $this->assertStringNotContainsString('vendor-soon-badge', $html);

        foreach (['المنتجات', 'الطلبات', 'التقييمات', 'المرتجعات', 'النزاعات', 'المالية'] as $label) {
            $this->assertStringContainsString($label, implode('', $matches[1]));
        }
    }

    public function test_reports_is_omitted(): void
    {
        $this->as('approved')->page()->assertDontSee('التقارير');

        $this->get(route('vendor.dashboard').'?change-locale=en');
        $this->page()->assertDontSee('Reports');
    }

    public function test_no_metrics_financial_data_or_charts(): void
    {
        $this->as('approved')
            ->page()
            ->assertDontSee('sales_total')
            ->assertDontSee('commission_pending')
            ->assertDontSee('orders_completed')
            ->assertDontSee('average_rating')
            ->assertDontSee('low_stock_products')
            ->assertDontSee('apexcharts')
            ->assertDontSee('chart');
    }

    public function test_local_hirfah_branding_and_no_external_avatar(): void
    {
        $this->as('approved')
            ->page()
            ->assertSee(asset('images/brand/hirfah-logo.png'), false)
            ->assertSee('حِــرْفَة')
            ->assertDontSee('marina.jpg')
            ->assertDontSee('ui-avatars.com');
    }

    public function test_no_admin_theme_scripts_or_customizer(): void
    {
        $this->as('approved')
            ->page()
            ->assertSee('resources/css/vendor-dashboard.css', false)
            ->assertSee('resources/js/vendor-dashboard.js', false)
            ->assertSee('assets-dashboard/css/style.css', false)
            ->assertDontSee('assets-dashboard/js', false)
            ->assertDontSee('theme.js')
            ->assertDontSee('script.js')
            ->assertDontSee('flatpickr')
            ->assertDontSee('offcanvas_pc_layout')
            ->assertDontSee('layout_change')
            ->assertDontSee('Buy Now')
            ->assertDontSee('resources/css/app.css', false);
    }

    public function test_store_name_is_escaped(): void
    {
        $this->as('approved', storeName: '<b>Craft</b> Corner')
            ->page()
            ->assertSee('&lt;b&gt;Craft&lt;/b&gt; Corner', false)
            ->assertDontSee('<b>Craft</b>', false);
    }

    public function test_store_name_is_bidi_isolated_inside_page_direction_layouts(): void
    {
        // Arabic store name under the English (LTR) interface: user data is never translated.
        $this->as('approved', storeName: 'متجر حرفة');
        $this->get(route('vendor.dashboard').'?change-locale=en');
        $html = $this->page()->assertSee('<html lang="en" dir="ltr"', false)->getContent();

        // Block containers keep the page direction (no dir attribute), so their alignment follows the
        // interface; <bdi> lets the name itself take its natural direction.
        $this->assertStringContainsString('<h2 id="vendor-welcome-title" class="vendor-welcome-name"><bdi>متجر حرفة</bdi></h2>', $html);
        $this->assertStringContainsString('<span class="vendor-store-card-name"><bdi>متجر حرفة</bdi></span>', $html);
        $this->assertStringContainsString('<span class="vendor-dropdown-name"><bdi>متجر حرفة</bdi></span>', $html);
        // The single-line, truncated header name keeps dir="auto" so the ellipsis follows the name's own direction.
        $this->assertStringContainsString('<span class="vendor-user-trigger-name" dir="auto">متجر حرفة</span>', $html);
    }

    public function test_english_store_name_is_bidi_isolated_in_the_arabic_interface(): void
    {
        $html = $this->as('approved', storeName: 'Craft Corner')->page()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->getContent();

        $this->assertStringContainsString('<h2 id="vendor-welcome-title" class="vendor-welcome-name"><bdi>Craft Corner</bdi></h2>', $html);
    }

    public function test_blank_store_name_falls_back_to_the_vendor_name(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'Samir Haddad']);
        $vendor->profile()->create(['store_name' => '', 'slug' => 'blank-'.$vendor->id, 'approval_status' => 'approved']);

        $this->actingAs($vendor, 'vendor')
            ->page()
            ->assertSee('Samir Haddad')
            ->assertSee('<span class="vendor-monogram" aria-hidden="true">S</span>', false);
    }

    public function test_visiting_the_dashboard_never_auto_creates_translation_rows(): void
    {
        $before = TranslationValue::count();

        $this->as('approved')->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    private function as(string $approval, ?string $reason = null, string $storeName = 'Craft Corner'): static
    {
        $vendor = Vendor::factory()->create();
        $vendor->profile()->create([
            'store_name' => $storeName,
            'slug' => 'craft-corner-'.$vendor->id,
            'approval_status' => $approval,
            'rejection_reason' => $reason,
        ]);

        return $this->actingAs($vendor, 'vendor');
    }

    private function approvedVendor(): Vendor
    {
        return Vendor::factory()->approved()->create(['password' => self::PASSWORD]);
    }

    private function login(Vendor $vendor): TestResponse
    {
        Auth::forgetGuards();

        return $this->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => self::PASSWORD]);
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.dashboard'));
    }
}
