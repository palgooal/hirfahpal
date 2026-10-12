<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * AD-UI-01B-3: the admin layout renders lang, dir and data-pc-direction on the
 * server from the active Language row, before any JavaScript runs.
 */
class AdminDashboardLayoutDirectionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->language('ar', 'العربية', rtl: true);
        $this->language('en', 'English', rtl: false);

        $this->admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    public function test_default_arabic_renders_rtl_from_the_server(): void
    {
        $this->assertHtmlTag($this->dashboard(), 'ar', 'rtl');
    }

    public function test_english_renders_ltr_from_the_server(): void
    {
        $this->assertHtmlTag($this->dashboard('en'), 'en', 'ltr');
    }

    public function test_third_rtl_language_renders_rtl_without_hardcoding(): void
    {
        $this->language('ur', 'اردو', rtl: true);

        $this->assertHtmlTag($this->dashboard('ur'), 'ur', 'rtl');
    }

    public function test_third_ltr_language_renders_ltr(): void
    {
        $this->language('fr', 'Français', rtl: false);

        $this->assertHtmlTag($this->dashboard('fr'), 'fr', 'ltr');
    }

    public function test_direction_is_read_from_the_database_not_from_the_locale_code(): void
    {
        Language::where('code', 'ar')->update(['is_rtl' => false]);

        $this->assertHtmlTag($this->dashboard(), 'ar', 'ltr');
    }

    public function test_inactive_language_in_session_falls_back_to_the_default_locale(): void
    {
        $this->language('fr', 'Français', rtl: false, active: false);

        $this->assertHtmlTag($this->dashboard('fr'), 'ar', 'rtl');
    }

    public function test_html_tag_no_longer_hardcodes_english_ltr(): void
    {
        $this->dashboard()->assertDontSee('<html lang="en"', false)->assertDontSee('data-pc-direction="ltr" dir="ltr"', false);
    }

    public function test_html_never_calls_the_template_direction_switcher(): void
    {
        $this->language('ur', 'اردو', rtl: true);

        // The admin HTML never calls the template direction switcher (theme.js is no longer loaded, AD-UI-01C-4C).
        foreach ([null, 'en', 'ur'] as $locale) {
            $html = $this->dashboard($locale)->getContent();

            $this->assertStringNotContainsString('layout_rtl_change(', $html);
            $this->assertStringNotContainsString('assets-dashboard/js/theme.js', $html);
        }
    }

    public function test_html_keeps_the_server_classes_and_never_calls_the_preset_switcher(): void
    {
        $response = $this->dashboard();
        $this->assertMatches('/<html\b[^>]*>/', $response->getContent(), $tag);

        $this->assertStringContainsString('class="preset-1 admin-shell"', $tag);
        // preset_change() stays defined in the external theme.js; an inline call would overwrite the <html> classes.
        $this->assertStringNotContainsString('preset_change(', $response->getContent());
    }

    public function test_template_theme_script_is_not_loaded_and_never_called(): void
    {
        $html = $this->dashboard()->getContent();

        // AD-UI-01C-4C: theme.js (and script.js since 01C-4B) are not loaded in the admin.
        $this->assertStringNotContainsString('assets-dashboard/js/theme.js', $html);
        $this->assertStringNotContainsString('assets-dashboard/js/script.js', $html);

        foreach (['layout_change(', 'layout_change_default(', 'layout_theme_contrast_change(', 'change_box_container(', 'layout_caption_change(', 'main_layout_change(', 'preset_change(', 'layout_rtl_change('] as $call) {
            $this->assertStringNotContainsString($call, $html, "{$call} must not be called from the admin HTML.");
        }
    }

    public function test_dark_theme_switcher_is_not_rendered(): void
    {
        $this->dashboard()
            ->assertDontSee('custom-moon', false)
            ->assertDontSee('custom-setting-2', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('data-pc-theme="dark"', false);
    }

    public function test_server_renders_the_light_vertical_admin_appearance(): void
    {
        $this->assertMatches('/<html\b[^>]*>/', $this->dashboard()->getContent(), $tag);

        $this->assertStringContainsString('class="preset-1 admin-shell"', $tag);
        $this->assertStringContainsString('data-pc-theme="light"', $tag);
        $this->assertStringContainsString('data-pc-layout="vertical"', $tag);
        $this->assertStringContainsString('data-pc-sidebar-caption="true"', $tag);
        $this->assertStringContainsString('dir="rtl"', $tag);
    }

    public function test_cairo_is_loaded_for_the_admin_shell(): void
    {
        $this->dashboard()->assertSee('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap', false);
    }

    public function test_template_customizer_and_buy_now_are_not_rendered(): void
    {
        $this->dashboard()
            ->assertDontSee('offcanvas_pc_layout', false)
            ->assertDontSee('pct-c-btn', false)
            ->assertDontSee('pct-offcanvas', false)
            ->assertDontSee('layoutreset', false)
            ->assertDontSee('buynowlinks', false)
            ->assertDontSee('Buy Now', false);
    }

    private function dashboard(?string $locale = null): TestResponse
    {
        $request = $this->actingAs($this->admin, 'admin');

        if ($locale) {
            $request = $request->withSession(['locale' => $locale]);
        }

        return $request->get(route('dashboard.home'))->assertOk();
    }

    private function assertHtmlTag(TestResponse $response, string $lang, string $dir): void
    {
        $this->assertMatches('/<html\b[^>]*>/', $response->getContent(), $tag);

        $this->assertStringContainsString('lang="'.$lang.'"', $tag);
        $this->assertStringContainsString('dir="'.$dir.'"', $tag);
        $this->assertStringContainsString('data-pc-direction="'.$dir.'"', $tag);
    }

    private function assertMatches(string $pattern, string $subject, ?string &$match): void
    {
        $this->assertSame(1, preg_match($pattern, $subject, $matches), 'No <html> tag rendered.');
        $match = $matches[0];
    }

    private function language(string $code, string $native, bool $rtl, bool $active = true): void
    {
        Language::create([
            'name' => $native,
            'native' => $native,
            'code' => $code,
            'is_rtl' => $rtl,
            'is_active' => $active,
        ]);
    }
}
