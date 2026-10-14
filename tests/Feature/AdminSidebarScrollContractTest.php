<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01C-4E: regression guard for the admin sidebar scroll (AD-UI-01C-4D).
 *
 * Contract test only: it checks the CSS rules and markup the scrolling depends on. It does not
 * drive a browser, so it cannot prove that wheel, touch or keyboard scrolling works; the project has
 * no browser-test framework. The repeatable browser check is documented in the AD-UI-01C-4E report.
 */
class AdminSidebarScrollContractTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{selector: string, declarations: array<string, string>}> */
    private array $rules = [];

    protected function setUp(): void
    {
        parent::setUp();

        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/admin-dashboard.css')));

        // Every style rule, including those nested in @media blocks.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $selector, $body]) {
            $declarations = [];

            foreach (array_filter(array_map('trim', explode(';', $body))) as $declaration) {
                [$property, $value] = array_map('trim', explode(':', $declaration, 2)) + [1 => ''];
                $declarations[strtolower($property)] = $value;
            }

            $this->rules[] = ['selector' => preg_replace('/\s+/', ' ', trim($selector)), 'declarations' => $declarations];
        }
    }

    public function test_the_list_container_scrolls_vertically_and_keeps_the_scroll_inside(): void
    {
        $rule = $this->rule('html.admin-shell .pc-sidebar .admin-navbar-content');

        $this->assertSame('auto', $rule['overflow-y'] ?? null, 'The sidebar list must scroll (overflow-y: auto).');
        $this->assertSame('contain', $rule['overscroll-behavior'] ?? null, 'Scrolling past the list edge must not move the page.');
        $this->assertSame('calc(100vh - 74px)', $rule['height'] ?? null, 'The list needs a bounded height to scroll.');
    }

    public function test_no_admin_rule_disables_scrolling_of_the_list_in_any_state(): void
    {
        $checked = 0;

        foreach ($this->rules as ['selector' => $selector, 'declarations' => $declarations]) {
            if (! str_contains($selector, 'admin-navbar-content') && ! str_contains($selector, '.navbar-content')) {
                continue;
            }

            foreach (['overflow', 'overflow-y'] as $property) {
                if (isset($declarations[$property])) {
                    $this->assertSame('auto', $declarations[$property], "{$selector} must not set {$property} to anything but auto.");
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No overflow rule on the sidebar list: it would not scroll.');
    }

    public function test_the_sidebar_itself_is_not_made_scrollable(): void
    {
        // Tooltips and the drawer rely on .pc-sidebar keeping Able Pro's overflow:hidden.
        foreach ($this->rules as ['selector' => $selector, 'declarations' => $declarations]) {
            foreach (explode(',', $selector) as $part) {
                if (preg_match('/\.pc-sidebar(\[[^\]]*\]|\.[\w-]+)*\s*$/', trim($part))) {
                    $this->assertArrayNotHasKey('overflow', $declarations, "{$part} must not change overflow.");
                    $this->assertArrayNotHasKey('overflow-y', $declarations, "{$part} must not change overflow-y.");
                }
            }
        }
    }

    public function test_the_collapsed_rail_keeps_its_content_inside_and_hides_only_the_scrollbar(): void
    {
        $this->assertSame('76px', $this->rule('html.admin-shell .pc-sidebar[data-admin-sidebar="managed"].admin-sidebar-collapsed, html.admin-shell .pc-sidebar[data-admin-sidebar="managed"].admin-sidebar-collapsed .navbar-wrapper')['width'] ?? null);
        $this->assertSame('none', $this->rule('html.admin-shell .admin-sidebar-collapsed[data-admin-sidebar="managed"] .admin-navbar-content')['scrollbar-width'] ?? null);
        $this->assertSame('none', $this->rule('html.admin-shell .admin-sidebar-collapsed[data-admin-sidebar="managed"] .admin-navbar-content::-webkit-scrollbar')['display'] ?? null);
    }

    public function test_the_tooltip_clears_the_rail_edge_and_stays_hoverable(): void
    {
        $tooltip = $this->rule('html.admin-shell .admin-sidebar-tooltip');
        $bridge = $this->rule('html.admin-shell .admin-sidebar-tooltip::before');

        // The script places it 8px from the link; the link sits 10px inside the rail.
        $this->assertSame('8px', $tooltip['margin-inline-start'] ?? null);
        $this->assertSame('16px', $bridge['width'] ?? null, 'The hover bridge must span the whole gap back to the link.');
    }

    public function test_the_rendered_sidebar_wraps_its_list_in_the_scroll_container_without_simplebar(): void
    {
        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $this->seed(AdminDashboardTranslationSeeder::class);

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
            'super_admin' => true,
        ]);

        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="navbar-content admin-navbar-content">.*?<ul class="pc-navbar admin-navbar">#s', $html);
        // SimpleBar used to provide the scrolling through script.js; neither may come back on the list.
        $this->assertStringNotContainsString('assets-dashboard/js/script.js', $html);
        $this->assertStringNotContainsString('data-simplebar', $html);
    }

    /** @return array<string, string> */
    private function rule(string $selector): array
    {
        foreach ($this->rules as $rule) {
            if ($rule['selector'] === $selector) {
                return $rule['declarations'];
            }
        }

        $this->fail("CSS rule not found: {$selector}");
    }
}
