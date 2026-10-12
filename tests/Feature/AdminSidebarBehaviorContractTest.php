<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01C-4A: the new sidebar behaviour in resources/js/admin-dashboard.js is opt-in.
 * It must never run alongside Able Pro's script.js, and it keeps no state in storage.
 */
class AdminSidebarBehaviorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_does_not_opt_in_while_the_template_script_drives_it(): void
    {
        $html = $this->dashboardHtml();
        $loadsTemplateScript = str_contains($html, 'assets-dashboard/js/script.js');
        $optedIn = str_contains($html, 'data-admin-sidebar="managed"');

        $this->assertFalse($loadsTemplateScript && $optedIn, 'The sidebar must not opt in while script.js is loaded.');
    }

    public function test_admin_layout_is_cut_over_to_the_admin_sidebar_behaviour(): void
    {
        $html = $this->dashboardHtml();

        // AD-UI-01C-4B: the admin opts in and Able Pro script.js is gone; AD-UI-01C-4C: theme.js too. component.js stays.
        $this->assertStringContainsString('<nav class="pc-sidebar" id="admin-sidebar" data-admin-sidebar="managed"', $html);
        $this->assertStringContainsString('<div class="pc-menu-overlay" data-admin-overlay hidden></div>', $html);
        $this->assertStringNotContainsString('assets-dashboard/js/script.js', $html);
        $this->assertStringNotContainsString('assets-dashboard/js/theme.js', $html);
        $this->assertStringContainsString('assets-dashboard/js/component.js', $html);

        // No pre-loader that a failed script could leave over the page.
        $this->assertStringNotContainsString('loader-bg', $html);
    }

    public function test_header_sidebar_controls_are_labelled_buttons(): void
    {
        $html = $this->dashboardHtml();

        $this->assertMatchesRegularExpression('#<button type="button" class="pc-head-link admin-sidebar-control[^"]*" id="sidebar-hide"\s+aria-controls="admin-sidebar" aria-expanded="true"\s+aria-label="تصغير القائمة"\s+data-label-collapse="تصغير القائمة"\s+data-label-expand="توسيع القائمة">#', $html);
        $this->assertMatchesRegularExpression('#<button type="button" class="pc-head-link admin-sidebar-control[^"]*" id="mobile-collapse"\s+aria-controls="admin-sidebar" aria-expanded="false"\s+aria-label="فتح القائمة"\s+data-label-open="فتح القائمة"\s+data-label-close="إغلاق القائمة">#', $html);
        $this->assertStringNotContainsString('<a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="sidebar-hide">', $html);
    }

    public function test_behaviour_module_is_guarded_and_keeps_no_state_in_storage(): void
    {
        $source = file_get_contents(resource_path('js/admin-dashboard.js'));

        $this->assertStringContainsString("dataset.adminSidebar !== 'managed'", $source);
        $this->assertStringContainsString("typeof win.menu_click === 'function'", $source);
        // Any access (localStorage.getItem, localStorage[...]), not the word in a comment.
        $this->assertDoesNotMatchRegularExpression('/\b(localStorage|sessionStorage)\s*[.\[]/', $source);

        // The helpers component.js used from script.js are provided once script.js is gone.
        foreach (['win.slideUp', 'win.slideDown', 'win.removeClassByPrefix'] as $helper) {
            $this->assertStringContainsString($helper.' =', $source);
        }

        // AD-UI-01D-5: the sidebar account card and its script are gone.
        $this->assertStringNotContainsString('initAccountCard', $source);
        $this->assertStringNotContainsString('admin-user-card', file_get_contents(resource_path('css/admin-dashboard.css')));
    }

    private function dashboardHtml(): string
    {
        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $this->seed(\Database\Seeders\AdminDashboardTranslationSeeder::class);

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
            'super_admin' => true,
        ]);

        return $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk()->getContent();
    }
}
