<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * VPM-UX-01A: the vendor products page has its own view (the other five lists keep the shared resources view): one
 * page title, the Refresh icon (the list script's data-resource-retry, before which the editor script adds "Add
 * product"), loading placeholders, an error state with its own Retry, and the unchanged product editor.
 */
class VendorProductsPageTest extends TestCase
{
    use RefreshDatabase;

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
        $this->vendor = Vendor::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_the_products_page_has_its_own_view_and_the_other_lists_keep_the_shared_one(): void
    {
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.products.page'))->assertOk()->assertViewIs('vendor-dashboard.products');

        foreach (self::OTHER_LISTS as $page) {
            $html = $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.'.$page))->assertOk()
                ->assertViewIs('vendor-dashboard.resources')->getContent();
            foreach (['data-product-editor', 'data-products-error', 'vendor-products-page.js', 'vendor-product-editor.js', 'data-moderation-texts'] as $products) {
                $this->assertStringNotContainsString($products, $html, "{$page}: {$products}");
            }
            // The other lists keep their text Refresh button and their loading text.
            $this->assertMatchesRegularExpression('#<button type="button" class="btn btn-light-primary" data-resource-retry>#', $html, $page);
        }
    }

    public function test_one_page_title_and_the_refresh_icon_in_both_languages(): void
    {
        foreach (['ar' => ['المنتجات', 'تحديث قائمة المنتجات'], 'en' => ['Products', 'Refresh product list']] as $locale => [$title, $refresh]) {
            $xpath = $this->xpath($this->page($locale));

            // The layout's h1 is the only "Products" heading; the list card has no heading of its own.
            // VPM-UX-01E-POLISH-01: the only other h1 is the (hidden) editor's title, the page heading while it is open.
            $this->assertSame([$title], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//h1[not(ancestor::*[@data-product-editor])]'))), $locale);
            $this->assertSame(1, $xpath->query('//*[@data-product-editor][@hidden]//h1[@data-product-editor-title]')->length, $locale);
            $this->assertSame(0, $xpath->query('//*[@data-vendor-resource-list]//*[self::h2 or self::h3]')->length, $locale);

            // Refresh: the first data-resource-retry of the list (the list script binds it, the editor script adds
            // "Add product" before it), an icon-only button with its accessible name, not the error Retry.
            $retries = $xpath->query('//*[@data-vendor-resource-list]//*[@data-resource-retry]');
            $this->assertSame(1, $retries->length, $locale);
            $button = $retries->item(0);
            $this->assertSame('button', $button->tagName);
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertSame($refresh, $button->getAttribute('aria-label'), $locale);
            $this->assertSame($refresh, $button->getAttribute('title'), $locale);
            $this->assertSame('', trim($button->textContent), 'Icon only');
            $this->assertStringContainsString('vendor-icon-button', $button->getAttribute('class'));
            $this->assertSame('true', $this->first($button, './/i')->getAttribute('aria-hidden'));
            $this->assertSame('vendor-products-toolbar', $button->parentNode->getAttribute('class'));
        }
    }

    public function test_the_loading_error_and_empty_states(): void
    {
        foreach (['ar' => ['جارٍ التحميل...', 'لا توجد سجلات لعرضها.'], 'en' => ['Loading…', 'No records found.']] as $locale => [$loading, $empty]) {
            $xpath = $this->xpath($this->page($locale));
            $list = $xpath->query('//*[@data-vendor-resource-list]')->item(0);

            // Loading: shown at first, announced by its text, with placeholder rows.
            $block = $this->first($list, './/*[@data-resource-loading]');
            $this->assertFalse($block->hasAttribute('hidden'));
            $this->assertSame('status', $block->getAttribute('role'));
            $this->assertSame($loading, trim($this->first($block, './/span[contains(@class, "sr-only")]')->textContent), $locale);
            $this->assertSame(4, $xpath->query('.//span[contains(@class, "vendor-skeleton-row")][@aria-hidden="true"]', $block)->length);

            // Error: the list script's message stays text only (it rewrites it on each load); Retry lives only here.
            $error = $this->first($list, './/*[@data-products-error]');
            $message = $this->first($error, './/*[@data-resource-error]');
            $this->assertTrue($message->hasAttribute('hidden'));
            $this->assertSame('alert', $message->getAttribute('role'));
            $this->assertSame(0, $message->childElementCount, 'Text only');
            $retryButton = $this->first($error, './/button[@data-products-error-retry]');
            $this->assertSame('button', $retryButton->getAttribute('type'));
            $this->assertFalse($retryButton->hasAttribute('data-resource-retry'), 'Not a second list Refresh hook');
            $this->assertSame(VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Dashboard_Retry'][$locale], trim($retryButton->textContent));

            // Empty: the products page's own text.
            $this->assertSame($empty, trim($this->first($list, './/*[@data-resource-empty]')->textContent), $locale);
        }
        $this->assertSame('إعادة المحاولة', VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Dashboard_Retry']['ar']);
    }

    public function test_the_error_state_is_shown_only_with_the_list_scripts_message_and_retry_reuses_refresh(): void
    {
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));
        $this->assertMatchesRegularExpression('/\.vendor-products-error \{\s*display: none;\s*\}/', $css);
        $this->assertStringContainsString('.vendor-products-error:has([data-resource-error]:not([hidden]))', $css);
        // The loading block sets no display, so the hidden attribute keeps hiding it.
        $this->assertDoesNotMatchRegularExpression('/\.vendor-products-loading \{[^}]*display/', $css);

        $script = file_get_contents(resource_path('js/vendor-products-page.js'));
        $this->assertStringContainsString("querySelector('[data-products-error-retry]')", $script);
        $this->assertStringContainsString("querySelector('[data-resource-retry]')?.click()", $script);
        $this->assertStringContainsString("'resources/js/vendor-products-page.js'", file_get_contents(base_path('vite.config.js')));
    }

    public function test_the_list_hooks_and_the_unchanged_editor(): void
    {
        $html = $this->page('ar');
        $xpath = $this->xpath($html);
        $list = $xpath->query('//*[@data-vendor-resource-list]')->item(0);

        $this->assertSame('products', $list->getAttribute('data-resource'));
        $this->assertSame(route('vendor.dashboard.products.index'), $list->getAttribute('data-endpoint'));
        foreach (['data-resource-table', 'data-resource-head', 'data-resource-body', 'data-resource-empty', 'data-resource-pager', 'data-resource-previous', 'data-resource-next', 'data-resource-page-status'] as $hook) {
            $this->assertSame(1, $xpath->query('//*[@'.$hook.']')->length, $hook);
        }
        $this->assertSame('نشط', json_decode($list->getAttribute('data-list-texts'), true)['statuses']['active']);
        $this->assertSame('مؤرشف', json_decode($list->getAttribute('data-moderation-texts'), true)['archived']);

        // The editor: once, hidden, on the same endpoint, with its form, its ids and the media picker.
        $editors = $xpath->query('//*[@data-product-editor]');
        $this->assertSame(1, $editors->length);
        $this->assertTrue($editors->item(0)->hasAttribute('hidden'));
        $this->assertSame(route('vendor.dashboard.products.index'), $editors->item(0)->getAttribute('data-endpoint'));
        foreach (['vendor-product-name', 'vendor-product-category', 'vendor-product-sku', 'vendor-product-stock', 'vendor-product-status', 'vendor-product-price', 'vendor-product-preparation-days'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
        }
        foreach (['data-product-form', 'data-product-gallery', 'data-product-editor-title', 'data-product-save', 'data-product-media-library'] as $hook) {
            $this->assertGreaterThan(0, $xpath->query('//*[@'.$hook.']')->length, $hook);
        }
        foreach (['resources/js/vendor-resource-list.js', 'resources/js/vendor-product-editor.js', 'resources/js/vendor-products-page.js'] as $script) {
            $this->assertSame(1, substr_count($html, $script), $script);
        }
        // The editor script still adds "Add product" before the list's Refresh hook.
        $this->assertStringContainsString("document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(add)", file_get_contents(resource_path('js/vendor-product-editor.js')));
    }

    private function page(string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
            ->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function first(DOMElement $context, string $expression): DOMElement
    {
        $nodes = (new DOMXPath($context->ownerDocument))->query($expression, $context);
        $this->assertGreaterThan(0, $nodes->length, $expression);

        return $nodes->item(0);
    }
}
