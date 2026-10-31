<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * VENDOR-UI-CLEANUP-01C: the vendor dashboard's two sign-out icons (sidebar, header account menu) point along the
 * reading direction. Tailwind's rtl:-scale-x-100 never applied in the vendor shell (Able Pro's unlayered
 * "--tw-scale-x: 1" reset overrides the layered utility), so the icons mirror through one vendor-scoped, unlayered rule
 * under html.vendor-shell[dir="rtl"], and no longer carry the utility (which would flip them twice if it ever applied).
 * The sign-out forms, route and labels are unchanged.
 */
class VendorSignOutIconTest extends TestCase
{
    use RefreshDatabase;

    private const ICON = '<i class="ph-duotone ph-sign-out" aria-hidden="true"></i>';

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_both_sign_out_controls_keep_their_form_label_and_plain_icon_in_both_languages(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        foreach (['ar' => ['rtl', 'تسجيل الخروج'], 'en' => ['ltr', 'Sign out']] as $locale => [$dir, $label]) {
            $html = $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard'))->assertOk()->getContent();

            $this->assertStringContainsString("dir=\"{$dir}\"", $html);
            $this->assertSame(2, substr_count($html, 'action="'.route('vendor.logout').'"'), 'Sidebar and header, as before.');
            $this->assertMatchesRegularExpression('#<button type="submit" class="vendor-nav-logout">\s*'.preg_quote(self::ICON, '#').'\s*<span>'.preg_quote($label, '#').'</span>#u', $html);
            $this->assertMatchesRegularExpression('#<button type="submit" class="vendor-dropdown-item">\s*'.preg_quote(self::ICON, '#').'\s*<span>'.preg_quote($label, '#').'</span>#u', $html);
            $this->assertStringNotContainsString('ph-sign-out rtl:-scale-x-100', $html, 'No utility that could flip the icon a second time.');
        }
    }

    public function test_the_mirroring_rule_is_vendor_scoped_rtl_only_and_targets_just_these_icons(): void
    {
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));

        $this->assertSame(1, preg_match('#html\.vendor-shell\[dir="rtl"\] \.vendor-nav-logout i\.ph-sign-out,\s*html\.vendor-shell\[dir="rtl"\] \.vendor-dropdown-item i\.ph-sign-out \{\s*transform: scaleX\(-1\);\s*\}#', $css));
        $this->assertSame(1, substr_count($css, 'transform: scaleX(-1)'), 'One mirroring rule, nothing broader.');

        // Unlayered (outside any @layer block), so Able Pro's unlayered reset cannot outrank it by layer order.
        $position = strpos($css, 'html.vendor-shell[dir="rtl"] .vendor-nav-logout i.ph-sign-out');
        $before = substr($css, 0, $position);
        $this->assertSame(substr_count($before, '{'), substr_count($before, '}'), 'The rule is at the top level of the sheet.');
        $this->assertStringNotContainsString('@layer', substr($before, strrpos($before, "\n\n") ?: 0));
    }

    public function test_signing_out_still_works_through_the_same_route(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.logout'))->assertRedirect(route('vendor.login'));
        $this->assertGuest('vendor');
        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));
    }
}
