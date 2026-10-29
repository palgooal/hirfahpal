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
use Tests\TestCase;

/**
 * Media Library page (VENDOR-MEDIA-04A). An informational, server-rendered page behind the same gate as the other
 * operational vendor pages, with its sidebar link right after Products. It has no media tools and no page script yet.
 */
class VendorMediaLibraryPageTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('احتفظ بصور متجرك في مكان واحد، جاهزة لإضافتها إلى منتجاتك.')
            ->assertSee('أدوات الوسائط قادمة قريبًا')
            ->assertSee('سنضيف رفع صورك وتصفحها وإدارتها هنا في المرحلة القادمة. حتى ذلك الحين، أضف الصور من محرّر المنتج.')
            ->assertDontSee('Media Library');
    }

    public function test_english_text(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard.media.page').'?change-locale=en')->assertRedirect(route('vendor.dashboard.media.page'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('<h1 class="vendor-page-title">Media Library</h1>', false)
            ->assertSee('Keep your store images in one place, ready to add to your products.')
            ->assertSee('Media tools are on the way')
            ->assertSee('Uploading, browsing and managing your images here will be added in the next phase. Until then, add images from the product editor.')
            ->assertDontSee('مكتبة الوسائط');
    }

    public function test_every_page_string_is_seeded_in_both_languages(): void
    {
        foreach (['vendor.Nav_Media_Library', 'vendor.Media_Library_Description', 'vendor.Media_Library_Next_Title', 'vendor.Media_Library_Next_Text'] as $key) {
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

    public function test_the_page_has_no_media_tools_or_page_script(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertDontSee('type="file"', false)
            ->assertDontSee('<form method="POST" action="'.route('vendor.dashboard.products.media.store').'"', false)
            ->assertDontSee(route('vendor.dashboard.products.media.library'), false)
            ->assertDontSee('vendor-product-editor.js', false)
            ->assertDontSee('vendor-my-store.js', false)
            ->assertDontSee('type="search"', false);
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create();
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.dashboard.media.page'));
    }
}
