<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHeaderTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01D-3: admin header markup — page title from the active sidebar entry, language switcher
 * with every active language, and an account menu with name, role and sign out only.
 */
class AdminDashboardHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true, 'flag' => 'flags/ar.png']);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true, 'flag' => 'flags/en.png']);
        // dashboard.Choose_Language comes from the admin auth strings.
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminHeaderTranslationSeeder::class);
    }

    public function test_no_external_avatar_and_no_email_in_the_header(): void
    {
        $admin = $this->admin(superAdmin: true, name: 'Samira', email: 'owner@example.com');
        $html = $this->page($admin);
        $header = $this->header($html);

        $this->assertStringNotContainsString('ui-avatars.com', $html);
        $this->assertStringNotContainsString('owner@example.com', $header);
        $this->assertStringContainsString('<span class="admin-monogram admin-monogram-sm" aria-hidden="true">S</span>', $header);
    }

    public function test_name_and_role_are_shown_for_a_super_admin_and_an_admin(): void
    {
        $super = $this->header($this->page($this->admin(superAdmin: true, name: 'Samira Haddad')));
        $this->assertStringContainsString('<span class="admin-account-name"><bdi>Samira Haddad</bdi></span>', $super);
        $this->assertStringContainsString('<span class="admin-account-role">مشرف عام</span>', $super);
        $this->assertStringContainsString('aria-label="قائمة الحساب: Samira Haddad"', $super);

        $plain = $this->header($this->page($this->admin(name: 'Omar Nasser'), locale: 'en'));
        $this->assertStringContainsString('<span class="admin-account-role">Admin</span>', $plain);
    }

    public function test_header_has_no_placeholder_links_or_unfinished_widgets(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        $this->assertStringNotContainsString('href="#"', $header);
        $this->assertStringNotContainsString('href="#!"', $header);
        $this->assertStringNotContainsString('javascript:void', $header);
        $this->assertStringNotContainsString('notification', strtolower($header));
        $this->assertStringNotContainsString('form-search', $header);
        $this->assertStringNotContainsString('#custom-', $header);
    }

    public function test_sign_out_form_keeps_post_and_csrf(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        $this->assertMatchesRegularExpression('#<form action="'.preg_quote(route('admin.logout'), '#').'" method="post">\s*<input type="hidden" name="_token"#', $header);
        $this->assertStringContainsString('<span>تسجيل الخروج</span>', $header);
    }

    public function test_every_active_language_is_listed_by_native_name_with_lang_and_dir(): void
    {
        Language::create(['name' => 'Urdu', 'native' => 'اردو', 'code' => 'ur', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'French', 'native' => 'Français', 'code' => 'fr', 'is_rtl' => false, 'is_active' => false]);

        $header = $this->header($this->page($this->admin(superAdmin: true)));

        // Current language: shown as selected, not a link.
        $this->assertMatchesRegularExpression('#<span class="dropdown-item admin-header-menu-item admin-lang-item is-current" aria-current="true" lang="ar" dir="rtl">#', $header);
        $this->assertStringContainsString('href="'.route('dashboard.home').'?change-locale=en" hreflang="en" lang="en" dir="ltr"', $header);
        $this->assertStringContainsString('href="'.route('dashboard.home').'?change-locale=ur" hreflang="ur" lang="ur" dir="rtl"', $header);
        $this->assertStringContainsString('<span>اردو</span>', $header);
        $this->assertStringNotContainsString('Français', $header);
        $this->assertStringNotContainsString('change-locale=fr', $header);
        $this->assertStringContainsString('aria-label="اختيار اللغة: العربية"', $header);
        $this->assertStringNotContainsString('/admin/home', $header);
    }

    public function test_missing_flag_files_are_not_rendered_but_existing_ones_are(): void
    {
        // flags/ar.png and flags/en.png do not exist in public/.
        $header = $this->header($this->page($this->admin(superAdmin: true)));
        $this->assertStringNotContainsString('flags/ar.png', $header);
        $this->assertStringNotContainsString('flags/en.png', $header);
        $this->assertStringContainsString('<span>English</span>', $header);

        // A flag path that exists is shown.
        Language::where('code', 'en')->update(['flag' => 'images/brand/hirfah-logo.png']);
        $header = $this->header($this->page($this->admin(superAdmin: true)));
        $this->assertStringContainsString('src="'.asset('images/brand/hirfah-logo.png').'" alt="" class="admin-lang-flag"', $header);
    }

    public function test_page_title_matches_the_current_sidebar_entry_on_every_sidebar_page(): void
    {
        $admin = $this->admin(superAdmin: true);

        foreach (['dashboard.home', 'dashboard.vendors.index', 'dashboard.categories.index', 'dashboard.languages.index', 'dashboard.translation-values.index', 'dashboard.setting.index'] as $route) {
            $html = $this->page($admin, $route);
            $this->assertSame(1, preg_match('#<a href="[^"]+" class="pc-link"\s+aria-current="page"\s*>.*?<span class="pc-mtext">([^<]+)</span>#s', $html, $current), $route);
            $this->assertStringContainsString('<h1 class="admin-header-title">'.$current[1].'</h1>', $this->header($html), $route);
        }

        // AD-UI-CAT-01E-3B: the category form keeps Categories current in the sidebar but names its own page.
        $this->seed(AdminCategoryTranslationSeeder::class);
        $html = $this->page($admin, 'dashboard.categories.create');
        $this->assertSame(1, preg_match('#<a href="[^"]+" class="pc-link"\s+aria-current="page"\s*>.*?<span class="pc-mtext">([^<]+)</span>#s', $html, $current));
        $this->assertSame('التصنيفات', trim($current[1]));
        $this->assertStringContainsString('<h1 class="admin-header-title">إضافة تصنيف</h1>', $this->header($html));
    }

    public function test_page_title_follows_the_locale(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true), 'dashboard.categories.index', 'en'));

        $this->assertStringContainsString('<h1 class="admin-header-title">Categories</h1>', $header);
    }

    public function test_alignment_does_not_depend_on_the_locale_code(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        $this->assertStringNotContainsString('me-auto', $header);
        $this->assertStringNotContainsString('class="ml-0"', $header);
        $this->assertStringNotContainsString('pc-mob-drp mr-0', $header);
    }

    public function test_sidebar_controls_keep_their_ids_and_behaviour_attributes(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        $this->assertStringContainsString('id="sidebar-hide"', $header);
        $this->assertStringContainsString('id="mobile-collapse"', $header);
        $this->assertSame(2, substr_count($header, 'aria-controls="admin-sidebar"'));
        $this->assertStringContainsString('data-label-expand="توسيع القائمة"', $header);
        $this->assertStringContainsString('data-label-close="إغلاق القائمة"', $header);
    }

    public function test_header_menus_are_owned_by_the_admin_script_not_component_js(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        // AD-UI-01D-4: component.js binds [data-pc-toggle]; no header element may carry it.
        $this->assertStringNotContainsString('data-pc-toggle', $header);
        $this->assertSame(2, substr_count($header, 'data-admin-dropdown>'));
        $this->assertSame(2, substr_count($header, 'data-admin-dropdown-toggle'));
    }

    public function test_header_menu_triggers_are_disclosure_buttons_with_matching_panels(): void
    {
        $header = $this->header($this->page($this->admin(superAdmin: true)));

        foreach (['admin-lang-menu', 'admin-account-menu'] as $panelId) {
            $this->assertMatchesRegularExpression('#<button type="button" class="[^"]*" data-admin-dropdown-toggle\s+aria-haspopup="true" aria-expanded="false" aria-controls="'.$panelId.'"\s+aria-label="[^"]+">#', $header, $panelId);
            $this->assertSame(1, substr_count($header, 'id="'.$panelId.'"'), $panelId);
        }

        // Disclosure pattern: plain links and a form, no ARIA menu roles.
        $this->assertStringNotContainsString('role="menu"', $header);
        $this->assertStringNotContainsString('role="menuitem"', $header);
    }

    public function test_the_admin_script_drives_the_header_menus_with_aria_state_and_keyboard_support(): void
    {
        $source = file_get_contents(resource_path('js/admin-dashboard.js'));

        // Contract only: browser behaviour is verified separately (AD-UI-01D-4 report).
        $this->assertStringContainsString('export function initHeaderDropdowns', $source);
        $this->assertStringContainsString('[data-admin-dropdown]', $source);
        $this->assertStringContainsString("setAttribute('aria-expanded', 'true')", $source);
        $this->assertStringContainsString("setAttribute('aria-expanded', 'false')", $source);
        $this->assertStringContainsString("event.key === 'Escape'", $source);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $source);
        $this->assertStringContainsString("addEventListener('focusout'", $source);
        $this->assertStringNotContainsString('data-pc-toggle="dropdown"', $source);
    }

    private function admin(bool $superAdmin = false, string $name = 'Admin', ?string $email = null, array $abilities = []): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => $name,
            'email' => $email ?? 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, string $route = 'dashboard.home', string $locale = 'ar'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route($route))->assertOk()->getContent();
    }

    private function header(string $html): string
    {
        $this->assertSame(1, preg_match('#<header class="pc-header admin-header" id="header">.*?</header>#s', $html, $match), 'Header not rendered.');

        return $match[0];
    }
}
