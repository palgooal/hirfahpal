<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-PUBLISH-UI-01B (OPEN-PROD-19, 20; DETAIL-01): the editor's publication status field. A labelled select
 * with the vendor's three statuses (draft preselected) and a hint, in Arabic and English; a product stored as
 * out_of_stock gets a "keep" option (value "", text from data-keep-label) so its status is left out of the update.
 * The script's logic is unit tested in tests/js/vendor-product-editor.test.mjs; here: the page, the texts, the wiring,
 * and what the backend does with exactly what the editor sends.
 */
class VendorProductEditorStatusTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private const KEYS = ['vendor.Product_Status', 'vendor.Product_Status_Hint', 'vendor.Product_Status_Keep_Out_Of_Stock'];

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

    // ---------------------------------------------------------------- the field

    public function test_the_field_is_a_labelled_select_of_the_three_vendor_statuses_with_its_hint_in_both_languages(): void
    {
        $texts = [
            'ar' => ['rtl', 'حالة النشر', ['مسودة', 'نشط', 'غير نشط'], 'تظهر المنتجات النشطة في المتجر فقط عندما تستوفي شروطه القائمة. لا تظهر المنتجات المسودة وغير النشطة.', 'نفد من المخزون (إبقاء الحالة الحالية)'],
            'en' => ['ltr', 'Publication status', ['Draft', 'Active', 'Inactive'], 'Active products are shown in the store only when they meet its existing conditions. Draft and inactive products are not shown.', 'Out of stock (keep current status)'],
        ];

        foreach ($texts as $locale => [$dir, $label, $options, $hint, $keep]) {
            $html = $this->page($locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $select = $this->sole($xpath, '//form[@data-product-form]//select[@name="status"]');
            $this->assertSame('vendor-product-status', $select->getAttribute('id'));
            $this->assertSame('vendor-input', $select->getAttribute('class'), 'The same control as the category field (VPM-UX-01E: My Store).');
            $this->assertSame($label, trim($this->sole($xpath, '//label[@for="vendor-product-status"]')->textContent));
            $this->assertSame('vendor-product-status-hint', $select->getAttribute('aria-describedby'));
            $this->assertSame($hint, trim($this->sole($xpath, '//p[@id="vendor-product-status-hint"][@class="vendor-product-hint"]')->textContent));
            $this->assertSame($keep, $select->getAttribute('data-keep-label'));

            $rendered = iterator_to_array($xpath->query('./option', $select));
            $this->assertSame(['draft', 'active', 'inactive'], array_map(fn (DOMElement $option) => $option->getAttribute('value'), $rendered), 'Never out_of_stock.');
            $this->assertSame($options, array_map(fn (DOMElement $option) => trim($option->textContent), $rendered));
            $this->assertSame(['draft'], array_map(fn (DOMElement $option) => $option->getAttribute('value'), array_values(array_filter($rendered, fn (DOMElement $option) => $option->hasAttribute('selected')))), 'A new product is a draft.');

            // VPM-UX-01E: in its own card (the side column, after the images), inside a fieldset read-only disables.
            $this->assertSame(1, $xpath->query('//form[@data-product-form]/fieldset[contains(@class, "is-side")]/section[@data-product-section="status"]//select[@name="status"]')->length);
            $this->assertSame(0, $xpath->query('//*[@data-product-gallery]//select[@name="status"]')->length, 'Not in the gallery.');
        }
    }

    public function test_the_new_texts_are_seeded_once_in_both_languages_and_rendering_creates_no_rows(): void
    {
        foreach (self::KEYS as $key) {
            $values = VendorDashboardTranslationSeeder::TRANSLATIONS[$key];
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], $key);
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all());
        }

        $before = TranslationValue::count();
        $this->page('ar');
        $this->page('en');
        $this->assertSame($before, TranslationValue::count());
    }

    public function test_the_script_sends_the_field_through_status_payload_and_resets_it_on_open_and_close(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString("status: statusPayload(field('status').value), images: editingId ? undefined : images.map(", $script, 'The images payload is unchanged.');
        $this->assertStringContainsString("form.reset(); field('low_stock_threshold').value = '1'; setStatusField(field('status'), null);", $script, 'Closing (and "Add product") resets to draft.');
        // UI-01D: opening a product (and the re-read after an archived refusal) goes through fill(), which sets the status.
        $this->assertMatchesRegularExpression("/const fill = \(\{ product, moderation \}\) => \{.*?setStatusField\(field\('status'\), product\.status\);/s", $script, 'Editing shows the stored status.');
        $this->assertStringContainsString('editingId = id; fill(answer.body);', $script);
        $this->assertDoesNotMatchRegularExpression('/[\'"`]out_of_stock[\'"`]/', $script, 'No status outside the three is ever written by the script.');
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
        $this->assertStringNotContainsString('Publication status', $script);
        $this->assertStringNotContainsString('Out of stock', $script);
    }

    // ---------------------------------------------------------------- what the backend does with it

    public function test_a_new_product_is_saved_with_the_chosen_status(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        foreach (['draft', 'active', 'inactive'] as $status) {
            $id = $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), $this->sent("Jug {$status}", $status, []))->assertCreated()->json('product.id');

            $product = Product::findOrFail($id);
            $this->assertSame($status, $product->status);
            $this->assertSame($status === 'active', $product->published_at !== null);
        }
    }

    public function test_a_new_product_keeps_sending_its_images_with_the_status(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $paths = [$this->library($vendor), $this->library($vendor)];
        $images = [['path' => $paths[0], 'alt_text' => null, 'sort_order' => 0, 'is_primary' => true], ['path' => $paths[1], 'alt_text' => null, 'sort_order' => 1, 'is_primary' => false]];

        $response = $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), $this->sent('Jug', 'active', $images))->assertCreated();

        $this->assertSame('active', $response->json('product.status'));
        $this->assertSame($paths, array_column($response->json('product.images'), 'path'));
        $this->assertSame([true, false], array_column($response->json('product.images'), 'is_primary'));
    }

    public function test_editing_sends_the_chosen_status_for_each_vendor_status(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product($vendor, 'draft');

        foreach (['active', 'inactive', 'draft', 'active'] as $status) {
            $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $this->sent('Jug', $status))->assertOk()->assertJsonPath('product.status', $status);
        }
    }

    public function test_an_out_of_stock_product_left_on_keep_stays_out_of_stock_and_an_explicit_choice_is_saved(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product($vendor, 'out_of_stock');

        // "Keep" selected: statusPayload("") is undefined, so the editor's JSON has no status key at all.
        $kept = $this->sent('Jug, renamed', null);
        $this->assertArrayNotHasKey('status', $kept);
        $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $kept)->assertOk();
        $this->assertSame(['out_of_stock', 'Jug, renamed'], [$product->fresh()->status, $product->fresh()->name]);

        $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $this->sent('Jug', 'active'))->assertOk();
        $this->assertSame('active', $product->fresh()->status);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The body of the editor's payload() as JSON.stringify sends it: every field, status only when it is a vendor status
     * (statusPayload), images only for a new product.
     */
    private function sent(string $name, ?string $status, ?array $images = null): array
    {
        $body = ['name' => $name, 'category_id' => $this->activeCategoryId(), 'price' => 10, 'stock_quantity' => 5, 'sku' => null, 'compare_at_price' => null, 'low_stock_threshold' => 1, 'weight' => null, 'short_description' => null, 'description' => null];

        if ($status !== null) {
            $body['status'] = $status;
        }
        if ($images !== null) {
            $body['images'] = $images;
        }

        return $body;
    }

    private function product(Vendor $vendor, string $status): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 5, 'status' => $status]);
    }

    private function library(Vendor $vendor): string
    {
        $path = "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'image');
        Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        return $path;
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
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function sole(DOMXPath $xpath, string $query): DOMElement
    {
        $nodes = $xpath->query($query);
        $this->assertSame(1, $nodes->length, $query);

        return $nodes->item(0);
    }
}
