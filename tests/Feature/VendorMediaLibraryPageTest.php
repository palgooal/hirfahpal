<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * Media Library page (VENDOR-MEDIA-04A), behind the same gate as the other operational vendor pages, with its sidebar
 * link right after Products. VENDOR-MEDIA-04B: a read-only gallery. The page renders labels, states and an empty grid;
 * resources/js/vendor-media-library.js reads the vendor's own listing one page of 24 at a time (its logic runs in
 * tests/js/vendor-media-library.test.mjs). No upload, edit or delete.
 */
class VendorMediaLibraryPageTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-media-library.js';

    private const KEYS = [
        'vendor.Nav_Media_Library', 'vendor.Media_Library_Description', 'vendor.Media_Library_Images', 'vendor.Media_Library_Search_Label',
        'vendor.Media_Library_Search_Placeholder', 'vendor.Media_Library_Search_Clear', 'vendor.Media_Library_Count', 'vendor.Media_Library_Empty',
        'vendor.Media_Library_No_Results', 'vendor.Media_Library_Invalid', 'vendor.Media_Library_Untitled', 'vendor.Media_Library_Open',
        'vendor.Media_Library_Pagination', 'vendor.Media_Library_Previous', 'vendor.Media_Library_Next', 'vendor.Media_Library_Page_Status',
        'vendor.Media_Library_Preview', 'vendor.Media_Library_Close', 'vendor.Media_Library_Open_Original', 'vendor.Media_Library_Field_Name',
    ];

    private string $hotFile;

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

    // ---- Route and access ----

    public function test_route_is_a_gated_browser_page(): void
    {
        $route = Route::getRoutes()->getByName('vendor.dashboard.media.page');

        $this->assertSame('vendor/dashboard/media', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth:vendor', 'vendor.approved', 'setLocale', 'disableTranslationAutoCreate'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_guests_and_admins_are_sent_to_the_vendor_login(): void
    {
        $this->get(route('vendor.dashboard.media.page'))->assertRedirect(route('vendor.login'));
        $this->getJson(route('vendor.dashboard.media.page'))->assertUnauthorized();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('vendor.dashboard.media.page'))->assertRedirect(route('vendor.login'));
    }

    public function test_pending_rejected_and_profileless_vendors_are_gated(): void
    {
        $vendors = [
            Vendor::factory()->withProfile('pending')->create(),
            Vendor::factory()->withProfile('rejected', 'Reason.')->create(),
            Vendor::factory()->create(),
        ];

        foreach ($vendors as $vendor) {
            $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard.media.page'))->assertRedirect(route('vendor.approval-status'));
            $this->getJson(route('vendor.dashboard.media.page'))->assertForbidden();
        }
    }

    public function test_an_approved_vendor_sees_the_page(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertOk()
            ->assertViewIs('vendor-dashboard.media')
            ->assertSee('data-vendor-media-page', false)
            ->assertSee('ph-duotone ph-images', false);
    }

    // ---- Navigation ----

    public function test_the_media_link_follows_products_in_the_sidebar(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->get(route('vendor.dashboard'))->getContent();

        preg_match_all('/<li class="pc-item[^"]*" data-nav-state="link">\s*<a href="([^"]+)"/', $html, $links);
        $products = array_search(route('vendor.dashboard.products.page'), $links[1], true);

        $this->assertNotFalse($products);
        $this->assertSame(route('vendor.dashboard.media.page'), $links[1][$products + 1] ?? null);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('vendor.dashboard.media.page'), '/').'" class="pc-link"\s*>\s*<span class="pc-micon"><i class="ph-duotone ph-images" aria-hidden="true"><\/i><\/span>/', $html);
    }

    public function test_media_is_the_current_nav_item_only_on_its_page(): void
    {
        $this->actingAs($this->vendor(), 'vendor');

        $media = $this->page()->getContent();
        $this->assertMatchesRegularExpression('/<li class="pc-item active" data-nav-state="link">\s*<a href="'.preg_quote(route('vendor.dashboard.media.page'), '/').'" class="pc-link"\s+aria-current="page"/', $media);
        $this->assertSame(1, preg_match_all('/<li class="pc-item active"/', $media));
        $this->assertSame(2, preg_match_all('/aria-current="page"/', $media), 'The nav link and its breadcrumb item.');
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendor.dashboard.products.page'), '/').'" class="pc-link"\s*>/', $media);

        foreach (['vendor.dashboard', 'vendor.dashboard.products.page'] as $other) {
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendor.dashboard.media.page'), '/').'" class="pc-link"\s*>/', $this->get(route($other))->getContent(), $other);
        }
    }

    // ---- Content ----

    public function test_arabic_text(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('<h1 class="vendor-page-title">مكتبة الوسائط</h1>', false)
            ->assertSeeInOrder(['لوحة التحكم', 'مكتبة الوسائط'])
            ->assertSeeInOrder(['احتفظ بصور متجرك في مكان واحد، جاهزة لإضافتها إلى منتجاتك.', 'ابحث في الصور', 'aria-label="صورك"'], false)
            ->assertSee('<dt>الاسم</dt>', false)
            ->assertSee('placeholder="الاسم أو النص البديل"', false)
            ->assertSee('مسح البحث')
            ->assertSee('جارٍ تحميل الصور…')
            ->assertSee('لا توجد صور في مكتبة الوسائط بعد. تظهر هنا الصور التي ترفعها من محرّر المنتج.')
            ->assertSee('لا توجد صور تطابق بحثك.')
            ->assertSee('إعادة المحاولة')
            ->assertSee('aria-label="صفحات مكتبة الوسائط"', false)
            ->assertSeeInOrder(['السابق', 'التالي'])
            ->assertSeeInOrder(['معاينة الصورة', 'aria-label="إغلاق"', 'فتح الصورة الأصلية'], false)
            ->assertDontSee('Media Library')
            ->assertDontSee('Search images');
    }

    public function test_english_text(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard.media.page').'?change-locale=en')->assertRedirect(route('vendor.dashboard.media.page'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('<h1 class="vendor-page-title">Media Library</h1>', false)
            ->assertSeeInOrder(['Keep your store images in one place, ready to add to your products.', 'Search images', 'aria-label="Your images"'], false)
            ->assertSee('<dt>Name</dt>', false)
            ->assertSee('placeholder="Name or alt text"', false)
            ->assertSee('Clear search')
            ->assertSee('Loading images…')
            ->assertSee('There are no images in your media library yet. Images you upload from the product editor appear here.')
            ->assertSee('No images match your search.')
            ->assertSee('Retry')
            ->assertSee('aria-label="Media library pages"', false)
            ->assertSeeInOrder(['Previous', 'Next'])
            ->assertSeeInOrder(['Image preview', 'aria-label="Close"', 'Open the original image'], false)
            ->assertDontSee('مكتبة الوسائط');
    }

    public function test_the_pager_arrows_mirror_in_rtl(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        $this->assertStringContainsString('<i class="ph-duotone ph-caret-left rtl:-scale-x-100" aria-hidden="true"></i>', $html);
        $this->assertStringContainsString('<i class="ph-duotone ph-caret-right rtl:-scale-x-100" aria-hidden="true"></i>', $html);
        $this->assertStringContainsString('dir="auto"', $html, 'A search in either script is typed in its own direction.');
    }

    public function test_the_script_strings_are_translated_in_both_languages(): void
    {
        $this->actingAs($this->vendor(), 'vendor');

        $ar = $this->i18n($this->page());
        $this->assertSame('عدد الصور: :count', $ar['count']);
        $this->assertSame('الصفحة :page من :last', $ar['pageStatus']);
        $this->assertSame('معاينة :name', $ar['open']);
        $this->assertSame('تعذّر تحميل مكتبة الوسائط.', $ar['loadError']);

        $this->get(route('vendor.dashboard.media.page').'?change-locale=en');
        $en = $this->i18n($this->page());
        $this->assertSame(['count', 'pageStatus', 'open', 'untitled', 'loadError', 'invalid', 'session', 'forbidden', 'network'], array_keys($en));
        $this->assertSame('Images: :count', $en['count']);
        $this->assertSame('Untitled image', $en['untitled']);
        $this->assertSame('The search could not be run. Change it and try again.', $en['invalid']);
    }

    public function test_every_page_string_is_seeded_in_both_languages(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertSame(['ar', 'en'], array_keys(VendorDashboardTranslationSeeder::TRANSLATIONS[$key]), $key);
            $this->assertSame(2, TranslationValue::where('key', $key)->count(), $key);
        }
    }

    public function test_rendering_never_creates_translation_rows(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->get(route('vendor.dashboard.media.page').'?change-locale=en');
        $this->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    // ---- Gallery ----

    public function test_the_page_renders_the_gallery_shell_for_the_script(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertSee('data-vendor-media-library', false)
            ->assertSee('data-state="loading"', false)
            ->assertSee('data-list-url="'.route('vendor.dashboard.products.media.library').'"', false)
            ->assertSee(self::SCRIPT, false)
            ->assertSee('role="search"', false)
            ->assertSee('type="search"', false)
            ->assertSee('maxlength="100"', false)
            ->assertSee('<label class="form-label sr-only" for="vendor-media-search">', false)
            ->assertSee('aria-busy="true" data-media-grid hidden></ul>', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('<dialog class="vendor-media-dialog" aria-labelledby="vendor-media-dialog-title" data-media-dialog>', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<img[^>]*\ssrc=""/', $html, 'No empty image address (it would request the page again).');
    }

    /** VENDOR-MEDIA-04B-UI: the admin media library's structure, under vendor-media-* names, without admin assets. */
    public function test_the_page_follows_the_admin_media_library_layout_without_admin_assets(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        $this->assertMatchesRegularExpression('/class="col-span-12 vendor-media".*class="vendor-media-head".*class="card vendor-media-panel".*class="vendor-media-filters" role="search".*class="card-body".*class="vendor-media-grid"/s', $html);
        $this->assertMatchesRegularExpression('/<div class="vendor-media-search">\s*<i class="ph-duotone ph-magnifying-glass" aria-hidden="true"><\/i>\s*<input\s+type="search"\s+class="form-control"/', $html);
        $this->assertStringContainsString('<button type="button" class="btn btn-light-secondary" data-media-clear hidden>', $html);
        $this->assertMatchesRegularExpression('/<div class="vendor-media-state" role="status" data-media-loading>\s*<span class="vendor-media-spinner" aria-hidden="true"><\/span>/', $html);
        $this->assertMatchesRegularExpression('/<div class="vendor-media-state vendor-media-state-error" role="alert" data-media-error hidden>\s*<i class="ph-duotone ph-warning"/', $html);
        $this->assertStringContainsString('<button type="button" class="btn btn-sm btn-light-secondary" data-media-retry>', $html);
        $this->assertMatchesRegularExpression('/class="vendor-media-dialog-header">\s*<h2 class="vendor-media-dialog-title"[^>]*>[^<]+<\/h2>\s*<button type="button" class="vendor-media-icon-button"/', $html);
        $this->assertMatchesRegularExpression('/class="vendor-media-dialog-preview">\s*<div class="vendor-media-dialog-image">.*<dl class="vendor-media-facts">/s', $html);

        // The vendor shell and its own assets only: no admin stylesheet, script, class prefix or write control.
        $this->assertStringContainsString('class="vendor-shell"', $html);
        $this->assertStringContainsString('resources/css/vendor-dashboard.css', $html);
        foreach (['admin-dashboard.css', 'admin-media-library.js', 'admin-shell', 'class="admin-', 'btn-primary', 'btn-light-danger', 'type="checkbox"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent);
        }
    }

    public function test_the_vendor_styles_are_scoped_and_leave_the_product_editor_classes_alone(): void
    {
        $css = file_get_contents(base_path('resources/css/vendor-dashboard.css'));
        $block = substr($css, strpos($css, '/* ---- Media Library (VENDOR-MEDIA-04B'));

        preg_match_all('/^([^\s@}\/*][^{]*)\{/m', $block, $selectors);
        $this->assertNotEmpty($selectors[1]);
        foreach ($selectors[1] as $selector) {
            foreach (array_map('trim', explode(',', $selector)) as $one) {
                if ($one === 'to') {
                    continue;
                }
                $this->assertMatchesRegularExpression('/^html\.vendor-shell(\[dir="(rtl|ltr)"\])? /', $one, 'Scoped to the vendor shell.');
                $this->assertStringNotContainsString('.admin-', $one, 'The admin-media* classes of the product editor are untouched.');
            }
        }
        $this->assertStringNotContainsString(':root', $block, 'No shared design token changes.');
        $this->assertStringNotContainsString('@theme', $block);
    }

    public function test_the_page_never_embeds_media_and_has_no_write_tools(): void
    {
        $vendor = $this->vendor();
        Media::create([
            'file_name' => 'x.png', 'file_original_name' => 'secret-original.png', 'file_path' => "vendors/media/{$vendor->id}/x.png",
            'file_extension' => 'png', 'mime_type' => 'image/png', 'size' => 4, 'file_type' => 'image', 'disk' => 'public',
        ]);

        $this->actingAs($vendor, 'vendor')
            ->page()
            ->assertDontSee('secret-original.png')
            ->assertDontSee("vendors/media/{$vendor->id}/x.png")
            ->assertDontSee('type="file"', false)
            // The upload shares the listing's URL (POST); nothing here posts to it.
            ->assertDontSee('data-media-store-url', false)
            ->assertDontSee('multipart/form-data', false)
            ->assertDontSee('vendor-product-editor.js', false)
            ->assertDontSee('vendor-my-store.js', false)
            ->assertDontSee('admin-', false)
            ->assertDontSee(url('dashboard').'/', false);
    }

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/vendor-media-library.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output, 'The JS tests must actually run.');
    }

    public function test_the_script_holds_no_wording_no_html_injection_no_writes_and_no_admin_routes(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Every text comes from the translated page config.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML|document\.write|eval\(|new Function/', $source, 'DOM APIs only.');
        $this->assertDoesNotMatchRegularExpression("/method:\\s*'(?:POST|PUT|PATCH|DELETE)'/i", $source, 'The page only reads.');
        $this->assertStringNotContainsString('body.message', $source, 'A server message is never shown as is.');
        $this->assertStringNotContainsString('localStorage', $source);
        $this->assertDoesNotMatchRegularExpression('#/dashboard/|admin-media-library|admin-vendor-media|vendor_id#', $source, 'No admin routes, no vendor chosen by the page.');
        $this->assertStringContainsString("import { format, request } from './admin-media/shared.js';", $source, 'Only the shared pure helpers are reused.');
        $this->assertStringContainsString('export const PER_PAGE = 24;', $source);
        $this->assertStringContainsString("Accept: 'application/json'", $source);
    }

    public function test_the_entry_is_built_by_vite(): void
    {
        $this->assertStringContainsString("'".self::SCRIPT."'", file_get_contents(base_path('vite.config.js')));
    }

    // ---- Product editor compatibility ----

    public function test_the_product_editor_keeps_its_library_and_script(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->get(route('vendor.dashboard.products.page'))
            ->assertOk()
            ->assertSee('data-media-library-url="'.route('vendor.dashboard.products.media.library').'"', false)
            ->assertSee('resources/js/vendor-product-editor.js', false)
            ->assertDontSee(self::SCRIPT, false);

        // Without page, per_page or search the editor still gets the whole library as {images}.
        $this->getJson(route('vendor.dashboard.products.media.library'))->assertOk()->assertExactJson(['images' => []]);
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create();
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.dashboard.media.page'));
    }

    /** @return array<string, string> */
    private function i18n(TestResponse $response): array
    {
        preg_match('#<script type="application/json" data-media-i18n>(.*?)</script>#s', $response->getContent(), $match);

        return json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
    }

    private function node(): string
    {
        foreach (['node', 'node.exe'] as $candidate) {
            $found = trim((string) shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ').$candidate.' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));

            if ($found !== '') {
                return strtok($found, "\r\n");
            }
        }

        $this->markTestSkipped('Node.js is not available.');
    }
}
