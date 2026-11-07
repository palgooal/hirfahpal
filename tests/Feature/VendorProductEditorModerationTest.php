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
 * VENDOR-PRODUCT-PUBLISH-UI-01D (DETAIL-02, DETAIL-03): the editor's archived (read-only) and suspended notices, in
 * Arabic and English, and the script's wiring: one read-only state (applyMode) that disables every section and hides
 * Save, every image action and the save behind locked(), late answers dropped by ticket, and a 422 naming no field
 * re-read before it is taken as the archived refusal. The logic is unit tested in tests/js/vendor-product-editor.test.mjs
 * and checked in a browser; here, also, the server answers that logic relies on.
 */
class VendorProductEditorModerationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private const CSS = 'resources/css/vendor-dashboard.css';

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

    // ---------------------------------------------------------------- the notices

    public function test_the_two_notices_are_hidden_notes_at_the_top_of_the_form_in_both_languages(): void
    {
        $texts = [
            'ar' => ['rtl', 'هذا المنتج مؤرشف من الإدارة ولا يمكن تعديله. يرجى التواصل مع الإدارة لاستعادته.', 'هذا المنتج معلّق من الإدارة، ولن يظهر في المتجر أو يكون متاحًا للبيع حتى يُرفع التعليق، مهما كانت حالة نشره. يمكنك الاستمرار في تعديل بياناته.', 'تمت أرشفة هذا المنتج، ولم يعد بإمكانك تعديله.'],
            'en' => ['ltr', 'This product is archived by the administration and cannot be edited. Contact the administration to restore it.', 'This product is suspended by the administration. It is not shown in the store or available for sale until the suspension is lifted, whatever its publication status. You can still edit it.', 'This product was archived and can no longer be edited.'],
        ];

        foreach ($texts as $locale => [$dir, $archived, $suspended, $refused]) {
            $html = $this->page($locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $notes = iterator_to_array($xpath->query('//form[@data-product-form]/div[@data-product-notice]'));
            $this->assertSame(['archived', 'suspended'], array_map(fn (DOMElement $note) => $note->getAttribute('data-product-notice'), $notes));
            foreach ($notes as $note) {
                $this->assertSame('note', $note->getAttribute('role'));
                $this->assertTrue($note->hasAttribute('hidden'), 'Shown only by the script, from the flags.');
                $this->assertSame('true', $xpath->query('.//i', $note)->item(0)->getAttribute('aria-hidden'));
                // VPM-UX-01E: a short title, then the existing text.
                $this->assertSame(1, $xpath->query('.//p[@class="vendor-product-notice-title"]', $note)->length);
            }
            [$archivedNote, $suspendedNote] = $notes;
            $this->assertSame($archived, trim($xpath->query('.//p[@class="vendor-product-notice-text"]', $archivedNote)->item(0)->textContent));
            $this->assertSame($suspended, trim($xpath->query('.//p[@class="vendor-product-notice-text"]', $suspendedNote)->item(0)->textContent));
            $this->assertSame($refused, $archivedNote->getAttribute('data-refused'));
            $this->assertSame('-1', $archivedNote->getAttribute('tabindex'), 'Focus can move to it after a late refusal.');

            // Before the first section, so it is read first; the save error region (role=alert) is outside every section.
            $this->assertSame(0, $xpath->query('//form[@data-product-form]/fieldset[1]/preceding-sibling::div[@data-product-notice]/preceding-sibling::fieldset')->length);
            // VPM-UX-01E: the save outcome is the save bar's line; the bar holds Save, outside both columns.
            $this->assertSame(1, $xpath->query('//form[@data-product-form]/div[contains(@class, "vendor-product-save-bar")]/p[@data-product-error][@role="alert"]')->length);
            $this->assertSame(2, $xpath->query('//form[@data-product-form]/fieldset')->length, 'The main and side columns: what read-only disables.');
            $this->assertSame(1, $xpath->query('//form[@data-product-form]/fieldset[contains(@class, "is-main")]//input[@name="name"]')->length);
            $this->assertSame(1, $xpath->query('//form[@data-product-form]/fieldset[contains(@class, "is-side")]//*[@data-product-gallery]')->length);
            $this->assertSame(0, $xpath->query('//form[@data-product-form]//*[self::input or self::select or self::textarea][@name][not(@type="hidden")][not(ancestor::fieldset)]')->length, 'Every field is in a column.');
            $this->assertSame(1, $xpath->query('//form[@data-product-form]/div[contains(@class, "vendor-product-save-bar")]/button[@data-product-save]')->length);
        }
    }

    public function test_the_texts_are_seeded_in_both_languages_and_rendering_creates_no_rows(): void
    {
        foreach (['vendor.Product_Notice_Archived', 'vendor.Product_Notice_Suspended', 'vendor.Product_Archived_Refused'] as $key) {
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

    public function test_the_notice_styles_are_vendor_scoped_logical_and_hide_when_hidden(): void
    {
        $css = file_get_contents(base_path(self::CSS));
        preg_match_all('/^.*vendor-product-notice.*$/m', $css, $lines);

        $this->assertNotEmpty($lines[0]);
        foreach ($lines[0] as $line) {
            // A rule, or a rule inside a media query, scoped to the vendor shell's product form.
            $this->assertStringStartsWith('html.vendor-shell .vendor-product-form .vendor-product-notice', preg_replace('/^@media \([^)]*\) \{ /', '', $line));
            $this->assertDoesNotMatchRegularExpression('/\b(margin|padding|border)-(left|right)\b|\b(left|right)\s*:/', $line, 'Logical properties only (RTL and LTR).');
        }
        $this->assertStringContainsString('html.vendor-shell .vendor-product-form .vendor-product-notice[hidden] { display: none; }', $css);
    }

    // ---------------------------------------------------------------- the script's wiring

    public function test_one_read_only_state_drives_the_sections_save_picker_and_every_action(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        // The one place the mode is set.
        $editor = substr($script, strpos($script, 'function initEditor('));
        $this->assertSame(1, substr_count($editor, 'readOnly = next === \'archived\';'));
        preg_match_all('/\breadOnly = /', $editor, $assignments);
        $this->assertCount(2, $assignments[0], 'readOnly is declared (false) and set in applyMode() only.');
        $this->assertStringContainsString("form.querySelectorAll(':scope > fieldset').forEach((section) => { section.disabled = readOnly; });", $script);
        $this->assertStringContainsString('saveButton.hidden = readOnly;', $script);
        $this->assertStringContainsString('if (readOnly) picker?.close();', $script);
        $this->assertStringContainsString('galleryButtonDisabled({ readOnly, busy: state,', $script, 'Busy cleanup recomputes with read-only.');
        $this->assertStringContainsString('picker?.setBusy(state || readOnly);', $script);

        // Every action asks locked() first; mutate refuses on its own too.
        foreach (['const setPrimary = async (image) => {', 'const moveImage = async (image, direction) => {', 'const removeImage = async (image) => {', 'const choose = async (medium) => {', 'const openMedia = (nextMode, image = null, from = null) => {', 'const upload = async (files) => {'] as $handler) {
            $this->assertMatchesRegularExpression('/'.preg_quote($handler, '/').'\s*\n\s*if \(locked\(\)/', $script, $handler);
        }
        $this->assertMatchesRegularExpression('/form\.addEventListener\(\'submit\'.*?if \(locked\(\)\) return;/s', $script);
        $this->assertMatchesRegularExpression('/const mutate = async \(url, method, body, done\) => \{\s*if \(readOnly\) return false;/', $script);
        $this->assertStringNotContainsString('if (busy) return', $script, 'No action checks busy alone any more.');

        // Opening, closing and late answers.
        $this->assertStringContainsString('const close = () => { begin();', $script);
        $this->assertStringContainsString("applyMode('editable'); clearGalleryError();", $script);
        $this->assertStringContainsString('applyMode(editorMode(moderation));', $script);
        $this->assertGreaterThanOrEqual(5, substr_count($script, 'tickets.isCurrent(ticket)'), 'Load, save, image change, upload and the re-read.');

        // The re-read: only a 422 naming no field, and only the fresh flags decide.
        $this->assertStringContainsString('mayBeArchivedRefusal(answer.status, answer.body)', $script);
        $this->assertStringContainsString('const outcome = refusalOutcome(fresh);', $script);
        $this->assertStringContainsString('fail(notices.archived.dataset.refused);', $script);
        $this->assertStringContainsString('notices.archived.focus();', $script);

        // Deleting from the list is unchanged (OPEN-PROD-08).
        $this->assertStringContainsString("if (button.dataset.productAction === 'delete') { if (!window.confirm(texts.confirmDelete)) return; const response = await request(`\${endpoint}/\${id}`, 'DELETE');", $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
        $this->assertStringNotContainsString('archived by the administration', $script);
    }

    // ---------------------------------------------------------------- the server answers the script relies on

    public function test_every_archived_refusal_names_no_field_and_every_other_422_names_one(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $product = Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $path = $this->library($vendor);
        $image = $product->images()->create(['path' => $this->library($vendor), 'sort_order' => 0, 'is_primary' => true]);
        $second = $product->images()->create(['path' => $this->library($vendor), 'sort_order' => 1, 'is_primary' => false]);
        $as = $this->actingAs($vendor, 'vendor');

        // Not archived: each refusal the editor can meet names a field (the editor shows it as before).
        $this->assertNotEmpty($as->putJson(route('vendor.dashboard.products.update', $product), ['price' => 10, 'stock_quantity' => 2])->assertUnprocessable()->json('errors'));
        $this->assertNotEmpty($as->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => 'vendors/media/999999/x.png'])->assertUnprocessable()->json('errors'));
        $this->assertSame(['archived' => false, 'suspended' => false], $as->getJson(route('vendor.dashboard.products.show', $product))->json('moderation'));

        // Archived: the save and every image route answer 422 with a message only; the re-read says archived.
        $product->forceFill(['archived_at' => now()])->save();
        $refusals = [
            $as->putJson(route('vendor.dashboard.products.update', $product), ['category_id' => $this->activeCategoryId(), 'name' => 'Jug', 'price' => 10, 'stock_quantity' => 2]),
            $as->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path]),
            $as->patchJson(route('vendor.dashboard.products.images.update', [$product, $second]), ['path' => $path]),
            $as->patchJson(route('vendor.dashboard.products.images.update', [$product, $second]), ['is_primary' => true]),
            $as->patchJson(route('vendor.dashboard.products.images.move', [$product, $second]), ['direction' => 'up']),
            $as->deleteJson(route('vendor.dashboard.products.images.destroy', [$product, $image])),
        ];
        foreach ($refusals as $response) {
            $response->assertUnprocessable()->assertJsonMissingPath('errors');
        }
        $this->assertSame(['archived' => true, 'suspended' => false], $as->getJson(route('vendor.dashboard.products.show', $product))->assertOk()->json('moderation'));

        // A validation error on an archived product still names its field (checked before the archive guard).
        $this->assertNotEmpty($as->putJson(route('vendor.dashboard.products.update', $product), ['price' => 10, 'stock_quantity' => 2])->assertUnprocessable()->json('errors'));
    }

    // ---------------------------------------------------------------- helpers

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
}
