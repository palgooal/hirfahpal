<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01C-3: the admin sidebar follows AD-UI-01 v0.5 section 3.1, shows only the
 * items the admin may open, marks the current page on the server and uses the
 * seeded dashboard.Nav_* strings.
 */
class AdminDashboardSidebarTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ITEMS = [
        'dashboard.home', 'dashboard.vendors.index', 'dashboard.customers.index', 'dashboard.delivery-drivers.index',
        'dashboard.admins.index', 'dashboard.categories.index', 'dashboard.products.index', 'dashboard.orders.index',
        'dashboard.vendor-orders.index', 'dashboard.reviews.index', 'dashboard.disputes.index', 'dashboard.return-requests.index',
        'dashboard.contact-messages.index', // CONTACT-ADMIN-IMPLEMENTATION-01, Quality group, contact-messages.view.
        'dashboard.languages.index', 'dashboard.translation-values.index', 'dashboard.setting.index',
        'media-library.page', // AD-UI-CAT-M3, System group, media.view.
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminDashboardTranslationSeeder::class);
    }

    public function test_super_admin_sees_every_approved_item_in_four_groups(): void
    {
        $nav = $this->sidebar($this->admin(superAdmin: true));

        foreach (self::ALL_ITEMS as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $nav, "{$route} should be listed.");
        }

        $this->assertSame(count(self::ALL_ITEMS), substr_count($nav, 'class="pc-link"'));
        $this->assertSame(4, substr_count($nav, 'class="admin-nav-group-title"'));

        foreach (['إدارة المستخدمين', 'إدارة المتجر', 'الجودة والمتابعة', 'النظام'] as $title) {
            $this->assertStringContainsString('>'.$title.'</span>', $nav);
        }
    }

    public function test_partial_admin_sees_only_permitted_items_and_their_groups(): void
    {
        $nav = $this->sidebar($this->admin(abilities: ['categories.view', 'settings.view']));

        $this->assertSame([
            route('dashboard.home'),
            route('dashboard.categories.index'),
            route('dashboard.setting.index'),
        ], $this->links($nav));

        $this->assertStringContainsString('>إدارة المتجر</span>', $nav);
        $this->assertStringContainsString('>النظام</span>', $nav);
        $this->assertStringNotContainsString('>إدارة المستخدمين</span>', $nav);
        $this->assertStringNotContainsString('>الجودة والمتابعة</span>', $nav);
    }

    public function test_policy_based_items_follow_their_abilities(): void
    {
        $nav = $this->sidebar($this->admin(abilities: ['admins.view', 'languages.view', 'translation-values.view']));

        $this->assertSame([
            route('dashboard.home'),
            route('dashboard.admins.index'),
            route('dashboard.languages.index'),
            route('dashboard.translation-values.index'),
        ], $this->links($nav));
    }

    public function test_admin_without_abilities_sees_only_home_and_no_group(): void
    {
        $nav = $this->sidebar($this->admin());

        $this->assertSame([route('dashboard.home')], $this->links($nav));
        $this->assertStringNotContainsString('admin-nav-group-title', $nav);
    }

    public function test_every_listed_link_is_reachable_for_that_admin(): void
    {
        foreach ([['categories.view', 'settings.view'], ['reviews.view', 'admins.view'], []] as $abilities) {
            $admin = $this->admin(abilities: $abilities);

            foreach ($this->links($this->sidebar($admin)) as $url) {
                $this->actingAs($admin, 'admin')->get($url)->assertOk();
            }
        }
    }

    public function test_add_links_placeholders_and_deferred_sections_are_not_rendered(): void
    {
        $nav = $this->sidebar($this->admin(superAdmin: true));

        foreach (['dashboard.admins.create', 'dashboard.vendors.create', 'dashboard.vendor-orders.create'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $nav);
        }

        foreach (['javascript:void(0)', 'Area', 'مقدمة الصفحة الرئيسية', 'My_account', 'admin.', 'إدارة التوصيل', 'المالية والتقارير', 'Delivery management'] as $text) {
            $this->assertStringNotContainsString($text, $nav);
        }
    }

    public function test_home_is_current_on_the_dashboard(): void
    {
        $nav = $this->sidebar($this->admin(superAdmin: true));

        $this->assertSame([route('dashboard.home')], $this->currentLinks($nav));
    }

    public function test_item_stays_current_on_its_sub_routes(): void
    {
        $admin = $this->admin(superAdmin: true);

        foreach (['dashboard.categories.index', 'dashboard.categories.create'] as $route) {
            $nav = $this->sidebar($admin, $route);

            $this->assertSame([route('dashboard.categories.index')], $this->currentLinks($nav), $route);
        }
    }

    public function test_icons_are_phosphor_duotone_and_brand_uses_the_approved_logo(): void
    {
        $nav = $this->sidebar($this->admin(superAdmin: true));

        $this->assertSame(count(self::ALL_ITEMS), substr_count($nav, '<span class="pc-micon"><i class="ph-duotone ph-'));
        $this->assertStringNotContainsString('fa-', $nav);
        $this->assertStringNotContainsString('data-feather', $nav);
        $this->assertStringContainsString(asset('images/brand/hirfah-logo.png'), $nav);
        $this->assertStringNotContainsString('marina.jpg', $nav);
        $this->assertStringNotContainsString('ui-avatars.com', $nav);
    }

    public function test_labels_come_from_the_seeded_nav_keys_in_each_locale(): void
    {
        $admin = $this->admin(superAdmin: true);

        $ar = $this->sidebar($admin);
        $this->assertStringContainsString('aria-label="القائمة الرئيسية"', $ar);
        $this->assertStringContainsString('>البائعون</span>', $ar);
        $this->assertStringContainsString('>لوحة الإدارة</span>', $ar);

        $en = $this->sidebar($admin, locale: 'en');
        $this->assertStringContainsString('aria-label="Main menu"', $en);
        $this->assertStringContainsString('>Vendors</span>', $en);
        $this->assertStringContainsString('>Store management</span>', $en);

        // Every sidebar string is seeded: rendering never auto-creates a dashboard.Nav_* row.
        $this->assertSame(
            count(AdminDashboardTranslationSeeder::TRANSLATIONS) * 2,
            TranslationValue::where('key', 'like', 'dashboard.Nav_%')->get()->filter(fn ($row) => str_starts_with($row->key, 'dashboard.Nav_'))->count(),
        );
    }

    public function test_sidebar_has_no_account_card_or_sign_out(): void
    {
        // AD-UI-01D-5: name, role and sign out moved to the header account menu.
        $nav = $this->sidebar($this->admin(superAdmin: true));

        foreach (['admin-user-card', 'pc_sidebar_userlink', 'admin-monogram', 'data-pc-toggle', '<form', 'logout', 'تسجيل الخروج', 'Admin 1'] as $text) {
            $this->assertStringNotContainsString($text, $nav);
        }
    }

    public function test_the_layout_has_one_sign_out_form_in_the_header(): void
    {
        $admin = $this->admin(superAdmin: true);
        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk()->getContent();
        $logoutForm = 'action="'.route('admin.logout').'" method="post"';

        $this->assertSame(1, substr_count($html, $logoutForm));
        $this->assertSame(1, preg_match('#<header class="pc-header admin-header" id="header">.*?</header>#s', $html, $header));
        $this->assertMatchesRegularExpression('#'.preg_quote($logoutForm, '#').'>\s*<input type="hidden" name="_token"#', $header[0]);

        $this->actingAs($admin, 'admin')->post(route('admin.logout'))->assertRedirect();
        $this->assertGuest('admin');
    }

    private function admin(bool $superAdmin = false, array $abilities = []): Admin
    {
        $number = Admin::query()->count() + 1;

        $admin = Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
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

    private function sidebar(Admin $admin, string $route = 'dashboard.home', string $locale = 'ar'): string
    {
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route($route))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<nav class="pc-sidebar" id="admin-sidebar".*?</nav>#s', $html, $match), 'Sidebar not rendered.');

        return $match[0];
    }

    /** @return list<string> */
    private function currentLinks(string $nav): array
    {
        preg_match_all('#<a href="([^"]+)" class="pc-link"\s+aria-current="page"\s*>#', $nav, $matches);

        $this->assertSame(substr_count($nav, 'aria-current="page"'), count($matches[1]), 'aria-current must sit on sidebar links only.');

        return $matches[1];
    }

    /** @return list<string> */
    private function links(string $nav): array
    {
        preg_match_all('#<a href="([^"]+)" class="pc-link"#', $nav, $matches);

        return $matches[1];
    }
}
