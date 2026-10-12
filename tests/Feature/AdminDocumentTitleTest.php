<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHeaderTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AD-UI-01D-6: the admin page title is resolved once in the layout and shared by the
 * header <h1> and the document <title> ("{page title} | {brand}").
 */
class AdminDocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminHeaderTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);

        // A page outside the sidebar, rendered with the real layout (pages may pass $pageTitle as a named slot).
        Route::middleware(['web', 'auth:admin', 'setLocale'])->group(function () {
            Route::get('/__admin-title-probe', fn () => Blade::render('<x-dashboard-layout><x-slot:breadcrumbs><li class="breadcrumb-item">Probe crumb</li></x-slot:breadcrumbs><p>probe</p></x-dashboard-layout>'))->name('dashboard.title-probe');
            Route::get('/__admin-title-custom', fn () => Blade::render('<x-dashboard-layout><x-slot:pageTitle>{{ $custom }}</x-slot:pageTitle><p>probe</p></x-dashboard-layout>', ['custom' => request('t', '')]))->name('dashboard.title-custom');
        });
    }

    public function test_document_title_follows_the_page_in_both_locales(): void
    {
        $admin = $this->admin();

        foreach ([
            ['dashboard.home', 'ar', 'الرئيسية | حِــرْفَة'],
            ['dashboard.categories.index', 'ar', 'التصنيفات | حِــرْفَة'],
            // AD-UI-CAT-01E-3B: the category form names its own page.
            ['dashboard.categories.create', 'ar', 'إضافة تصنيف | حِــرْفَة'],
            ['dashboard.categories.create', 'en', 'Add category | Hirfah'],
            ['dashboard.setting.index', 'ar', 'الإعدادات | حِــرْفَة'],
            ['dashboard.categories.index', 'en', 'Categories | Hirfah'],
            ['dashboard.products.index', 'en', 'Products | Hirfah'],
            ['dashboard.orders.index', 'en', 'Orders | Hirfah'],
        ] as [$route, $locale, $expected]) {
            $this->assertSame($expected, $this->documentTitle($this->page($admin, $route, $locale)), "{$route} {$locale}");
        }
    }

    public function test_header_title_sidebar_entry_and_document_title_agree(): void
    {
        $admin = $this->admin();

        foreach (['dashboard.home', 'dashboard.vendors.index', 'dashboard.categories.index', 'dashboard.products.index', 'dashboard.orders.index', 'dashboard.languages.index', 'dashboard.translation-values.index', 'dashboard.setting.index'] as $route) {
            foreach (['ar', 'en'] as $locale) {
                $html = $this->page($admin, $route, $locale);
                $this->assertSame(1, preg_match('#<h1 class="admin-header-title">([^<]+)</h1>#', $html, $header), $route);
                $this->assertSame(1, preg_match('#<a href="[^"]+" class="pc-link"\s+aria-current="page"\s*>.*?<span class="pc-mtext">([^<]+)</span>#s', $html, $active), $route);

                $this->assertSame($active[1], $header[1], "{$route} {$locale}");
                $this->assertStringStartsWith(html_entity_decode($header[1]).' | ', $this->documentTitle($html), "{$route} {$locale}");
            }
        }
    }

    public function test_the_application_name_is_no_longer_the_title(): void
    {
        $html = $this->page($this->admin(), 'dashboard.home');

        $this->assertStringNotContainsString('Multi Vendor Commerce', $html);
        $this->assertStringNotContainsString('<title>'.config('app.name').'</title>', $html);
    }

    public function test_pages_outside_the_sidebar_fall_back_to_the_brand_subtitle(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->get('/__admin-title-probe')->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="admin-header-title">لوحة الإدارة</h1>', $html);
        $this->assertSame('لوحة الإدارة | حِــرْفَة', $this->documentTitle($html));
    }

    public function test_an_explicit_page_title_wins_and_is_shared(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->get('/__admin-title-custom?t='.urlencode('Monthly report'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="admin-header-title">Monthly report</h1>', $html);
        $this->assertSame('Monthly report | حِــرْفَة', $this->documentTitle($html));
    }

    public function test_an_empty_page_title_is_ignored(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->get('/__admin-title-custom?t='.urlencode('   '))->assertOk()->getContent();

        $this->assertSame('لوحة الإدارة | حِــرْفَة', $this->documentTitle($html));
    }

    public function test_the_brand_is_not_repeated_when_the_title_already_contains_it(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])->get('/__admin-title-custom?t='.urlencode('About Hirfah'))->assertOk()->getContent();

        $this->assertSame('About Hirfah', $this->documentTitle($html));
    }

    public function test_html_in_the_title_is_escaped_in_the_header_and_the_document_title(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->get('/__admin-title-custom?t='.urlencode('<script>alert(1)</script> & <b>x</b>'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('<title>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;x&lt;/b&gt; | حِــرْفَة</title>', $html);
        $this->assertStringContainsString('<h1 class="admin-header-title">&lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;x&lt;/b&gt;</h1>', $html);
    }

    public function test_title_resolution_creates_no_translation_rows(): void
    {
        $admin = $this->admin();
        $before = TranslationValue::query()->count();

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get('/__admin-title-probe')->assertOk();
        }

        $this->assertSame($before, TranslationValue::query()->count());

        // Real pages may auto-create their own body strings, never a layout key (title, brand, sidebar, header).
        $keys = TranslationValue::query()->pluck('key')->all();
        foreach (['dashboard.home', 'dashboard.categories.index', 'dashboard.setting.index'] as $route) {
            $this->page($admin, $route, 'en');
        }
        $created = TranslationValue::query()->whereNotIn('key', $keys)->pluck('key')->unique()->values()->all();

        $this->assertSame([], array_values(array_filter($created, fn (string $key): bool => preg_match('/^dashboard\.(Nav_|Brand_|Header_)/', $key) === 1)));
    }

    public function test_breadcrumbs_stay_in_the_content_area(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->get('/__admin-title-probe')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="pc-content">.*<div class="page-header">\s*<div class="page-block">\s*<ul class="breadcrumb">\s*<li class="breadcrumb-item">Probe crumb</li>\s*</ul>#s', $html);
        $this->assertSame(1, preg_match('#<header class="pc-header admin-header" id="header">.*?</header>#s', $html, $header));
        $this->assertStringNotContainsString('breadcrumb', $header[0]);
    }

    public function test_vendor_and_owner_titles_are_untouched(): void
    {
        foreach (['vendor-dashboard', 'owner-dashboard'] as $shell) {
            $head = file_get_contents(resource_path("views/layouts/partials/{$shell}/head.blade.php"));

            $this->assertStringContainsString('{{ $title }}', str_replace('{{$title}}', '{{ $title }}', $head), $shell);
            $this->assertStringNotContainsString('adminDocumentTitle', $head, $shell);
        }
    }

    private function admin(): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => true,
        ]);
    }

    private function page(Admin $admin, string $route = 'dashboard.home', string $locale = 'ar'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route($route))->assertOk()->getContent();
    }

    private function documentTitle(string $html): string
    {
        $this->assertSame(1, preg_match_all('#<title>(.*?)</title>#s', $html, $matches), 'Exactly one <title> expected.');

        return html_entity_decode(trim($matches[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
