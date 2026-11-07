<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * PREVIEW-04: the ways into the vendor's private previews: "View" for every product in the products list (a new tab,
 * before Edit and Delete), "Preview my store" on My Store (a new tab), and from a product's preview to the store's.
 * Edit, Delete and the five other lists are unchanged.
 */
class VendorPreviewNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-resource-list.js';

    private const OTHER_LISTS = ['orders.page', 'reviews.page', 'returns.page', 'disputes.page', 'commissions.page'];

    private string $hotFile = '';

    private Vendor $vendor;

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

        $this->vendor = Vendor::factory()->approved()->create(['status' => 'active']);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the products list

    public function test_the_products_list_gives_its_script_the_preview_address_and_the_view_texts(): void
    {
        foreach (['ar' => ['مشاهدة', 'مشاهدة المنتج: :name (يفتح في تبويب جديد)'], 'en' => ['View', 'View product: :name (opens in a new tab)']] as $locale => [$view, $named]) {
            $xpath = $this->xpath($this->page('vendor.dashboard.products.page', $locale));
            $list = $xpath->query('//*[@data-vendor-resource-list]')->item(0);

            $this->assertSame(route('vendor.dashboard.preview.product', ['product' => '__ID__']), $list->getAttribute('data-preview-url'));
            $texts = json_decode($list->getAttribute('data-list-texts'), true);
            $this->assertSame([$view, $named], [$texts['view'], $texts['viewNamed']], $locale);
            // Edit and Delete keep their texts.
            $this->assertSame($locale === 'ar' ? ['تعديل', 'حذف'] : ['Edit', 'Delete'], [$texts['edit'], $texts['delete']]);
        }

        // The address the script builds opens the owner's preview of any product, whatever its status.
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        foreach (['active', 'draft', 'inactive', 'out_of_stock'] as $status) {
            $product = Product::query()->create(['vendor_id' => $this->vendor->id, 'category_id' => $category->id, 'name' => "Jug {$status}", 'slug' => "jug-{$status}", 'price' => 10, 'stock_quantity' => 2, 'status' => $status]);
            $url = str_replace('__ID__', (string) $product->id, route('vendor.dashboard.preview.product', ['product' => '__ID__']));
            $this->actingAs($this->vendor, 'vendor')->get($url)->assertOk();
        }
    }

    public function test_the_script_adds_view_before_edit_and_delete_as_a_new_tab_link(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $view = strpos($script, 'const previewUrl = productPreviewUrl(root.dataset.previewUrl, item.id);');
        $buttons = strpos($script, "[[texts.edit, 'edit', texts.editNamed], [texts.delete, 'delete', texts.deleteNamed]].forEach(");
        $this->assertNotFalse($view);
        $this->assertNotFalse($buttons);
        $this->assertLessThan($buttons, $view, 'View comes first.');
        $this->assertStringContainsString("view.href = previewUrl; view.target = '_blank'; view.rel = 'noopener noreferrer';", $script);
        $this->assertStringContainsString("eye.className = 'ph-duotone ph-eye'; eye.setAttribute('aria-hidden', 'true');", $script);
        $this->assertStringContainsString('view.dataset.productPreview = String(item.id);', $script);
        // Not a data-product-action button: the editor's click handler (Edit / Delete) never sees it.
        $this->assertStringNotContainsString('view.dataset.productAction', $script);
        $this->assertStringContainsString('button.dataset.productAction = action; button.dataset.productId = item.id;', $script);
    }

    public function test_the_five_other_lists_have_no_preview(): void
    {
        foreach (self::OTHER_LISTS as $page) {
            $html = $this->page('vendor.dashboard.'.$page, 'ar');
            $this->assertStringNotContainsString('data-preview-url', $html, $page);
            $this->assertStringNotContainsString('/vendor/dashboard/preview/', $html, $page);
        }
    }

    // ---------------------------------------------------------------- My Store and the previews

    public function test_my_store_links_to_the_store_preview_in_a_new_tab(): void
    {
        foreach (['ar' => ['معاينة متجري', 'معاينة متجري (يفتح في تبويب جديد)'], 'en' => ['Preview my store', 'Preview my store (opens in a new tab)']] as $locale => [$text, $label]) {
            $xpath = $this->xpath($this->page('vendor.dashboard.my-store', $locale));
            $links = $xpath->query('//a[@data-store-preview]');

            $this->assertSame(1, $links->length, $locale);
            $link = $links->item(0);
            $this->assertSame(route('vendor.dashboard.preview.store'), $link->getAttribute('href'));
            $this->assertSame('_blank', $link->getAttribute('target'));
            $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
            $this->assertSame($label, $link->getAttribute('aria-label'));
            $this->assertSame($text, trim($link->textContent));
            $this->assertSame('true', $xpath->query('.//i', $link)->item(0)->getAttribute('aria-hidden'));
            // Outside the form (so it works whatever the profile read gives).
            $this->assertSame(0, $xpath->query('//form[@data-store-form]//a[@data-store-preview]')->length);
        }
    }

    public function test_the_previews_lead_to_each_other(): void
    {
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $jar = Product::query()->create(['vendor_id' => $this->vendor->id, 'category_id' => $category->id, 'name' => 'Jar', 'slug' => 'jar', 'price' => 10, 'stock_quantity' => 2, 'status' => 'active', 'published_at' => now()]);

        foreach (['ar' => 'معاينة متجري', 'en' => 'Preview my store'] as $locale => $text) {
            // Product preview: back to the products list, and on to the store preview.
            $product = $this->xpath($this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.preview.product', $jar))->assertOk()->getContent());
            $this->assertSame(route('vendor.dashboard.preview.store'), $product->query('//a[@data-preview-store-link]')->item(0)->getAttribute('href'));
            $this->assertSame($text, trim($product->query('//a[@data-preview-store-link]')->item(0)->textContent));
            $this->assertSame(1, $product->query('//a[@href="'.route('vendor.dashboard.products.page').'"]')->length);
            $this->assertSame(0, $product->query('//form')->length, 'Still no buying.');

            // Store preview: back to My Store, and its products to their previews (never their public pages).
            $store = $this->xpath($this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.preview.store'))->assertOk()->getContent());
            $this->assertSame(1, $store->query('//a[@href="'.route('vendor.dashboard.my-store').'"]')->length);
            $this->assertSame(1, $store->query('//a[@href="'.route('vendor.dashboard.preview.product', $jar).'"]')->length);
            $this->assertSame(0, $store->query('//a[@href="'.route('product', ['slug' => 'jar']).'"]')->length);
            $this->assertSame(0, $store->query('//a[@data-preview-store-link]')->length, 'No link to itself.');
        }

        // In maintenance the dashboard (and so every way in) is closed; in coming_soon the links work.
        config(['site-availability.override' => 'coming_soon']);
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.my-store'))->assertOk()->assertSee('data-store-preview', false);
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.preview.store'))->assertOk();
        config(['site-availability.override' => 'maintenance']);
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.my-store'))->assertStatus(503);
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.preview.store'))->assertStatus(503);
    }

    // ---------------------------------------------------------------- helpers

    private function page(string $route, string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route($route))->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
