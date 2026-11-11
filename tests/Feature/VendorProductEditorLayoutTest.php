<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
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
 * VPM-UX-01E: the product editor in the approved prototype's layout: main and side columns, My Store's fields with a
 * message beside each one, weight units labelled in the page language (stored values unchanged), and the save bar
 * (Save waits for a change, an unsaved-changes line, a leave warning). Requests, locking and moderation are covered
 * by the other VendorProductEditor* tests.
 */
class VendorProductEditorLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private const NEW_KEYS = [
        'vendor.Product_Section_Basic', 'vendor.Product_Section_Inventory', 'vendor.Product_Section_Shipping', 'vendor.Product_Back_To_List',
        'vendor.Product_Notice_Archived_Title', 'vendor.Product_Notice_Suspended_Title', 'vendor.Product_Weight_Unit_Kg', 'vendor.Product_Weight_Unit_G',
        'vendor.Product_Weight_Unit_T', 'vendor.Product_Unit_Cm', 'vendor.Product_Preparation_Days_Hint', 'vendor.Product_Media_Hint_Add',
        'vendor.Product_Media_Hint_Edit', 'vendor.Product_Save', 'vendor.Product_Discard_Confirm',
    ];

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

    public function test_the_cards_sit_in_the_main_and_side_columns_in_order(): void
    {
        $xpath = $this->xpath($this->page('ar'));
        $sections = fn (string $column) => array_map(fn ($node) => $node->getAttribute('data-product-section'), iterator_to_array($xpath->query("//form[@data-product-form]/fieldset[contains(@class, '{$column}')]/section[@data-product-section]")));

        $this->assertSame(['basic', 'pricing', 'shipping', 'craft'], $sections('is-main'));
        $this->assertSame(['images', 'status'], $sections('is-side'));
        // VPM-UX-01E-POLISH-01: the editor title is an h1 (the page heading while the editor is open; the script hides
        // the layout's "Products" h1 meanwhile), the cards are h2, the image groups h3; Back sits on the title's row.
        $this->assertSame(['h1' => 1, 'h2' => 6, 'h3' => 2, 'h4' => 0], [
            'h1' => $xpath->query('//*[@data-product-editor]/div[@class="vendor-product-editor-head"]/h1[@data-product-editor-title]')->length,
            'h2' => $xpath->query('//form[@data-product-form]//h2[@class="vendor-section-title"]')->length,
            'h3' => $xpath->query('//*[@data-product-gallery]//h3')->length,
            'h4' => $xpath->query('//*[@data-product-editor]//h4')->length,
        ]);
        $this->assertSame(1, $xpath->query('//div[@class="vendor-product-editor-head"]/h1/following-sibling::*[1][self::button][@data-product-editor-close]')->length);
        // The heading's own wording ("إضافة منتج" / "تعديل منتج"); the list's "Add product" button keeps Product_New.
        $this->assertSame('إضافة منتج', trim($xpath->query('//h1[@data-product-editor-title]')->item(0)->textContent));
        $this->assertSame(['ar' => 'تعديل منتج', 'en' => 'Edit Product'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Product_Heading_Edit']);
        $this->assertSame(['ar' => 'إضافة منتج', 'en' => 'Add Product'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Product_Heading_New']);
        $script = file_get_contents(base_path(self::SCRIPT));
        $this->assertStringContainsString('title.textContent = texts.headingEdit;', $script);
        $this->assertStringContainsString('title.textContent = texts.headingAdd;', $script);
        $this->assertStringContainsString('add.textContent = texts.add;', $script);
        // The main image's buttons are grouped (Able Pro's global [data-action] auto margins are cancelled).
        $this->assertStringContainsString('html.vendor-shell .vendor-product-gallery-actions [data-action] { margin: 0; }', file_get_contents(resource_path('css/vendor-dashboard.css')));
        // Back to the list (the editor's close hook) in the header; Save and Cancel in the save bar.
        $this->assertSame('العودة إلى المنتجات', trim($xpath->query('//*[@data-product-editor]/div[@class="vendor-product-editor-head"]/button[@data-product-editor-close]')->item(0)->textContent));
        $bar = $xpath->query('//form[@data-product-form]/div[contains(@class, "vendor-product-save-bar")]')->item(0);
        $this->assertSame('حفظ المنتج', trim($xpath->query('.//button[@type="submit"][@data-product-save]', $bar)->item(0)->textContent));
        $this->assertSame('إلغاء', trim($xpath->query('.//button[@data-product-editor-close][@data-product-cancel]', $bar)->item(0)->textContent));
        $this->assertSame('status', $xpath->query('.//p[@data-product-unsaved]', $bar)->item(0)->getAttribute('role'));
        $this->assertTrue($xpath->query('.//p[@data-product-readonly]', $bar)->item(0)->hasAttribute('hidden'));
    }

    public function test_every_field_has_its_message_beside_it_and_announced_with_it(): void
    {
        $xpath = $this->xpath($this->page('en'));
        $boxes = iterator_to_array($xpath->query('//form[@data-product-form]//p[@data-error-for]'));

        $this->assertCount(21, $boxes, 'Every field the vendor types or chooses, except the publication status (a fixed list).');
        // The product's city and governorate have their own messages too.
        $names = array_map(fn ($box) => $box->getAttribute('data-error-for'), $boxes);
        $this->assertContains('city_id', $names);
        $this->assertContains('governorate_id', $names);
        foreach ($boxes as $box) {
            $name = $box->getAttribute('data-error-for');
            $this->assertTrue($box->hasAttribute('hidden'), $name);
            $this->assertSame('vendor-field-error', $box->getAttribute('class'), $name);
            $control = $xpath->query('//form[@data-product-form]//*[@name="'.$name.'"]')->item(0);
            $this->assertNotNull($control, $name);
            $this->assertContains($box->getAttribute('id'), explode(' ', $control->getAttribute('aria-describedby')), "{$name} is described by its message.");
        }
        // Required fields keep "required" and the asterisk; the legend explains it.
        foreach (['name', 'category_id', 'price', 'stock_quantity'] as $name) {
            $this->assertTrue($xpath->query('//*[@name="'.$name.'"]')->item(0)->hasAttribute('required'), $name);
        }
        $this->assertSame(4, $xpath->query('//form[@data-product-form]//span[@class="vendor-required"][@aria-hidden="true"]')->length, 'Name, category (VPM-CATEGORY-REQUIRED-01), price, stock.');
        $this->assertSame('Fields marked * are required.', trim($xpath->query('//p[@data-product-required-legend]')->item(0)->textContent));
    }

    public function test_weight_units_keep_their_values_and_read_in_the_page_language(): void
    {
        foreach (['ar' => ['الوحدة', 'كغ', 'غ', 'طن', 'سم'], 'en' => ['Unit', 'kg', 'g', 'ton', 'cm']] as $locale => [$select, $kg, $g, $t, $cm]) {
            $xpath = $this->xpath($this->page($locale));
            $options = iterator_to_array($xpath->query('//select[@name="weight_unit"]/option'));

            $this->assertSame(['', 'kg', 'g', 't'], array_map(fn ($option) => $option->getAttribute('value'), $options), $locale);
            $this->assertSame([$select, $kg, $g, $t], array_map(fn ($option) => trim($option->textContent), $options), $locale);
            $this->assertSame(3, $xpath->query('//div[@class="vendor-product-suffix"]/span[@aria-hidden="true"][normalize-space()="'.$cm.'"]')->length, $locale);
        }
    }

    public function test_the_save_bar_waits_for_a_change_and_warns_before_leaving(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        // Unsaved: the payload as opened (fields, and a new product's images) against the payload now.
        $this->assertStringContainsString('const snapshot = () => JSON.stringify(payload());', $script);
        $this->assertStringContainsString('const isDirty = () => baseline !== null && !readOnly && snapshot() !== baseline;', $script);
        $this->assertStringContainsString('if (!readOnly) saveButton.disabled = saveDisabled({ readOnly, saving, dirty });', $script);
        $this->assertStringContainsString('showHeader(product, moderation); clearFieldErrors(); updateCounter(); markClean();', $script, 'Opening a product is the baseline.');
        $this->assertStringContainsString('showHeader(null); clearFieldErrors(); updateCounter(); markClean(); };', $script, 'Closing (after a save, too) clears it.');
        // One save at a time; the request and its ticket check are unchanged.
        $this->assertMatchesRegularExpression('/if \\(locked\\(\\)\\) return;\\s*\\/\\/[^\\n]*\\n\\s*if \\(saving\\) return;/', $script);
        $this->assertStringContainsString("setSaving(true);\n        const answer = await send(editingId ? `\${endpoint}/\${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload());", $script);
        $this->assertStringContainsString('const begin = () => { session = tickets.next(); busy = false; setSaving(false); return session; };', $script);
        // Leaving: Cancel and Back ask first; the browser warns while the open editor has unsaved changes.
        $this->assertStringContainsString("editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.onclick = leave);", $script);
        $this->assertStringContainsString('const leave = () => { if (isDirty() && !window.confirm(texts.discard)) return; close(); };', $script);
        $this->assertMatchesRegularExpression("/window\\.addEventListener\\('beforeunload', \\(event\\) => \\{\\s*if \\(!editor\\.hidden && isDirty\\(\\)\\) \\{\\s*event\\.preventDefault\\(\\);/", $script);
        // VPM-UX-01E-POLISH-01: the layout's "Products" h1 is hidden while the editor is open, and back with the list.
        $this->assertStringContainsString("const pageTitle = document.querySelector('.vendor-page-title');", $script);
        $this->assertStringContainsString('editor.hidden = false; if (pageTitle) pageTitle.hidden = true;', $script);
        $this->assertStringContainsString('list.hidden = false; if (pageTitle) pageTitle.hidden = false;', $script);
        // Field messages: beside their fields, the bar's line asks to review them (or keeps the full text).
        $this->assertStringContainsString('fail(shown.first && !shown.unmatched ? texts.fixErrors : saveErrorText(answer.status, answer.body, texts, messages));', $script);
    }

    public function test_the_new_texts_are_seeded_once_in_both_languages_and_rendering_creates_no_rows(): void
    {
        foreach (self::NEW_KEYS as $key) {
            $values = VendorDashboardTranslationSeeder::TRANSLATIONS[$key] ?? null;
            $this->assertNotNull($values, $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], $key);
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all());
        }

        $before = TranslationValue::count();
        $this->page('ar');
        $this->page('en');
        $this->assertSame($before, TranslationValue::count());
    }

    private function page(string $locale): string
    {
        return $this->actingAs(Vendor::factory()->approved()->create(), 'vendor')->withSession(['locale' => $locale])
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
}
