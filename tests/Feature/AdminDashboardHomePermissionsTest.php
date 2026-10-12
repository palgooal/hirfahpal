<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AD-UI-DASH-01-FIX-01: the admin dashboard home shows each figure, list and link only to admins holding the
 * view ability of its module (DASH-1a), does not even query the others (DASH-1b), and shows the flashed
 * success / danger results (DASH-1c). Super admins keep seeing everything.
 */
class AdminDashboardHomePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** Tables holding module data; the page may read them only for an admin allowed to see that module. */
    private const MODULE_TABLES = [
        'vendors' => 'vendors.view', 'vendor_profiles' => 'vendors.view',
        'vendor_orders' => 'vendor-orders.view', 'delivery_assignments' => 'vendor-orders.view',
        'orders' => 'orders.view', 'products' => 'products.view',
        'delivery_drivers' => 'delivery-drivers.view', 'delivery_driver_profiles' => 'delivery-drivers.view',
        'reviews' => 'reviews.view', 'disputes' => 'disputes.view', 'return_requests' => 'return-requests.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Vendor::factory()->withProfile('pending')->create(['name' => 'Pending Potter']);
    }

    public function test_a_super_admin_sees_every_section_and_every_link_opens(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true]);
        $html = $this->home($admin);

        foreach (['Pending Vendors', 'Active Vendors', 'Pending Vendor Orders', 'Needs attention', 'Active Products', 'Low Stock', 'Pending Drivers', 'Open Disputes', 'Admin Quick Actions', 'Vendors Waiting Review', 'Response Window Alerts', 'Pending Potter'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }

        $links = $this->contentLinks($html);
        $this->assertCount(18, $links);
        $this->assertEveryLinkOpens($admin, $links);
    }

    public function test_a_limited_admin_sees_only_the_allowed_modules(): void
    {
        $admin = $this->admin(['products.view', 'orders.view']);
        $tables = $this->tablesReadWhile(fn () => $html = $this->home($admin));
        $html = $this->home($admin);

        // Products: real figures, even when 0.
        $this->assertMatchesRegularExpression('#Active Products</p>\s*<h3 class="mb-0">0</h3>#', $html);
        $this->assertStringContainsString('Low Stock', $html);
        // Not allowed: no card, no figure, no list, no name.
        foreach (['Pending Vendors', 'Active Vendors', 'Pending Vendor Orders', 'Needs attention', 'Pending Drivers', 'Open Disputes', 'Vendors Waiting Review', 'Response Window Alerts', 'Pending Potter', 'Pending Reviews', 'Open Returns', '>Categories<', '>Customers<'] as $text) {
            $this->assertStringNotContainsString($text, $html, $text);
        }

        $links = $this->contentLinks($html);
        $this->assertSame([route('dashboard.products.index', ['status' => 'active']), route('dashboard.products.index'), route('dashboard.products.index'), route('dashboard.orders.index')], $links);
        $this->assertEveryLinkOpens($admin, $links);

        // Only the allowed modules' tables were read.
        $this->assertSame([], array_values(array_diff($tables, ['products', 'orders'])), json_encode($tables));
    }

    public function test_a_delete_only_category_admin_sees_no_module_data_and_nothing_protected_is_queried(): void
    {
        $admin = $this->admin(['categories.delete']);
        $tables = $this->tablesReadWhile(fn () => $this->home($admin));
        $html = $this->home($admin);

        $this->assertSame([], $tables, 'No module data is queried.');
        $this->assertSame([], $this->contentLinks($html), 'No module link.');
        foreach (['Pending Vendors', 'Pending Potter', 'Admin Quick Actions', 'Vendors Waiting Review', 'Response Window Alerts', 'Active Products'] as $text) {
            $this->assertStringNotContainsString($text, $html, $text);
        }
        // The page itself still opens (the header card).
        $this->assertStringContainsString('HIRFAH Operations', $html);
    }

    public function test_an_admin_without_module_abilities_sees_no_vendor_order_or_figure(): void
    {
        $admin = $this->admin([]);
        $tables = $this->tablesReadWhile(fn () => $this->home($admin));
        $html = $this->home($admin);

        $this->assertSame([], $tables);
        $this->assertSame([], $this->contentLinks($html));
        $this->assertDoesNotMatchRegularExpression('#<h3 class="mb-0">\d+</h3>#', $html, 'No figure at all.');
        $this->assertStringNotContainsString('Pending Potter', $html);
    }

    public function test_each_module_is_tied_to_its_own_ability(): void
    {
        $cases = [
            'vendors.view' => [['vendors', 'vendor_profiles'], ['Pending Vendors', 'Active Vendors', 'Vendors Waiting Review', 'Pending Potter']],
            'vendor-orders.view' => [['vendor_orders', 'delivery_assignments'], ['Pending Vendor Orders', 'Needs attention', 'Response Window Alerts']],
            'delivery-drivers.view' => [['delivery_driver_profiles', 'delivery_drivers'], ['Pending Drivers', 'Delivery Drivers']],
            'disputes.view' => [['disputes'], ['Open Disputes']],
            'reviews.view' => [['reviews'], ['Pending Reviews: 0']],
            'return-requests.view' => [['return_requests'], ['Open Returns: 0']],
            'customers.view' => [[], ['>Customers<']],
            'categories.view' => [[], ['>Categories<']],
        ];

        foreach ($cases as $ability => [$expectedTables, $texts]) {
            $admin = $this->admin([$ability]);
            $tables = $this->tablesReadWhile(fn () => $this->home($admin));
            $html = $this->home($admin);

            $this->assertSame([], array_values(array_diff($tables, $expectedTables)), "{$ability}: ".json_encode($tables));
            foreach ($texts as $text) {
                $this->assertStringContainsString($text, $html, "{$ability}: {$text}");
            }
            $this->assertEveryLinkOpens($admin, $this->contentLinks($html));
        }
    }

    public function test_flashed_results_are_shown_with_their_role_and_escaped(): void
    {
        $admin = $this->admin(['categories.delete']);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en', 'success' => 'Category deleted successfully.'])->get(route('dashboard.home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-success" role="status" data-dashboard-flash="success">.*?<p>Category deleted successfully\.</p>#s', $html);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en', 'danger' => 'Refused <script>alert(1)</script>'])->get(route('dashboard.home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-error" role="alert" data-dashboard-flash="danger">.*?<p>Refused &lt;script&gt;alert\(1\)&lt;/script&gt;</p>#s', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        // Arabic message on the Arabic page.
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar', 'success' => 'تم حذف التصنيف بنجاح.'])->get(route('dashboard.home'))->getContent();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('<p>تم حذف التصنيف بنجاح.</p>', $html);

        // Nothing flashed: no alert; a non-string value is ignored. (withSession data is not a one-request flash.)
        $this->flushSession();
        $this->assertStringNotContainsString('data-dashboard-flash', $this->home($admin));
        $html = $this->actingAs($admin, 'admin')->withSession(['success' => ['x']])->get(route('dashboard.home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-dashboard-flash', $html);
    }

    public function test_the_button_cards_carry_the_home_colour_scope(): void
    {
        // DASH-3: admin-dashboard.css gives .admin-home buttons the HIRFAH colours; every button card is inside it.
        $html = $this->home(Admin::factory()->create(['super_admin' => true]));
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($doc);
        $buttons = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " pc-content ")]//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]');
        $scoped = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " admin-home ")]//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]');

        $this->assertSame(10, $buttons->length);
        $this->assertSame($buttons->length, $scoped->length, 'Every button of the page is inside .admin-home.');
        $this->assertSame(3, substr_count($html, ' admin-home"'));
    }

    /** @return list<string> the module tables read while running $action */
    private function tablesReadWhile(callable $action): array
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables) {
            preg_match_all('/(?:from|join)\s+"(\w+)"/i', $query->sql, $matches);
            foreach ($matches[1] as $table) {
                if (array_key_exists($table, self::MODULE_TABLES)) {
                    $tables[$table] = true;
                }
            }
        });
        $action();
        DB::flushQueryLog();
        $read = array_keys($tables);
        $tables = [];

        sort($read);

        return $read;
    }

    private function home(Admin $admin): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.home'))->assertOk()->getContent();
    }

    /** @return list<string> the hrefs in the page content (not the sidebar or header) */
    private function contentLinks(string $html): array
    {
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $links = [];
        foreach ((new \DOMXPath($doc))->query('//div[contains(concat(" ", normalize-space(@class), " "), " pc-content ")]//a[@href]') as $a) {
            $links[] = $a->getAttribute('href');
        }

        return $links;
    }

    private function assertEveryLinkOpens(Admin $admin, array $links): void
    {
        foreach ($links as $href) {
            $this->actingAs($admin, 'admin')->get($href)->assertOk();
        }
    }

    private function admin(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);
        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
