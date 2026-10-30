<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VPM-01B: the vendor product editor's image picker, a native dialog in the admin product gallery chooser's look. It
 * reads the vendor's own library one page of 24 at a time through the existing listing endpoint and uploads through the
 * existing upload endpoint; the endpoints, the image requests and their checks are unchanged. Its behaviour runs in
 * tests/js/vendor-product-picker.test.mjs; this covers the page, the texts, the script's guarantees and the server
 * answers the script relies on.
 */
class VendorProductPickerTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private string $hotFile = '';

    /** @var list<string> */
    private array $temporary = [];

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
        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);
        array_map('unlink', array_filter($this->temporary, 'is_file'));

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the dialog

    public function test_the_picker_is_a_native_dialog_after_the_product_form(): void
    {
        $html = $this->page('en');

        $this->assertSame(1, preg_match('#<form data-product-form novalidate>(.*?)</form>\s*<dialog class="vendor-media-dialog vendor-product-picker" aria-labelledby="vendor-product-picker-title" aria-describedby="vendor-product-picker-intro" data-product-media-library>#s', $html, $form), 'The dialog follows the product form.');
        $this->assertStringNotContainsString('data-product-media-', $form[1], 'Nothing of the picker inside the product form: its search is never submitted with the product.');
        $this->assertStringNotContainsString('<form data-product-form', $this->dialog($html), 'The product form is not nested in the dialog.');
    }

    public function test_the_dialog_has_the_admin_chooser_layout_and_every_script_hook(): void
    {
        $dialog = $this->dialog($this->page('en'));

        $this->assertMatchesRegularExpression('#<div class="vendor-media-dialog-header">\s*<h2 class="vendor-media-dialog-title" id="vendor-product-picker-title" data-product-media-title>Choose an image from media</h2>\s*<button type="button" class="vendor-media-icon-button" aria-label="Close" data-product-media-close>#', $dialog);
        $this->assertMatchesRegularExpression('#<form class="vendor-media-filters vendor-product-picker-filters" role="search" data-product-media-search-form novalidate>\s*<label class="form-label sr-only" for="vendor-product-picker-search">Search images</label>#', $dialog);
        // VPM-01F-FIX-01 (F-2): no dir="auto"; the field takes the page direction.
        $this->assertMatchesRegularExpression('#<input type="search" class="form-control" id="vendor-product-picker-search" maxlength="100" autocomplete="off" placeholder="Name or alt text" data-product-media-search>#', $dialog);
        foreach (['summary', 'status', 'alert', 'items', 'loading', 'empty', 'no-results', 'error', 'error-text', 'retry', 'pager', 'previous', 'next', 'page-status', 'upload', 'file'] as $hook) {
            $this->assertStringContainsString("data-product-media-{$hook}", $dialog, $hook);
        }
        $this->assertMatchesRegularExpression('#<ul class="vendor-media-grid" aria-label="Media library images" aria-busy="false" data-product-media-items hidden></ul>#', $dialog);
        $this->assertMatchesRegularExpression('#class="vendor-media-state" role="status" data-product-media-loading hidden>\s*<span class="vendor-media-spinner"#', $dialog);
        $this->assertMatchesRegularExpression('#class="vendor-media-state vendor-media-state-error" role="alert" data-product-media-error hidden>#', $dialog);
        $this->assertMatchesRegularExpression('#role="status" aria-live="polite" data-product-media-status#', $dialog);
        $this->assertMatchesRegularExpression('#class="vendor-product-error" role="alert" data-product-media-alert hidden#', $dialog);

        // Upload from inside the dialog: a button that names its limits, the file input out of sight and out of the tab order.
        $this->assertMatchesRegularExpression('#<button class="btn btn-primary" type="button" data-product-media-upload aria-describedby="vendor-product-upload-hint">#', $dialog);
        $this->assertStringContainsString('up to 10 at a time. The first uploaded image is added to the product; the others stay in your media library.', $dialog);
        $this->assertStringContainsString('<input class="vendor-product-media-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-product-media-file multiple tabindex="-1" aria-hidden="true">', $dialog);

        // The pager arrows mirror in RTL.
        // VPM-01F-FIX-01 (F-1): English (LTR): Previous points left, Next right; chosen on the server, no CSS flip.
        $this->assertMatchesRegularExpression('#data-product-media-previous disabled>\s*<i class="ph-duotone ph-caret-left" aria-hidden="true"></i>#', $dialog);
        $this->assertMatchesRegularExpression('#data-product-media-next disabled>\s*<span>Next</span>\s*<i class="ph-duotone ph-caret-right" aria-hidden="true"></i>#', $dialog);
        $this->assertStringNotContainsString('rtl:-scale-x-100', $dialog);
    }

    public function test_the_picker_reads_in_arabic_and_english(): void
    {
        $ar = $this->page('ar');
        $this->assertMatchesRegularExpression('/<html lang="ar" dir="rtl"/', $ar);
        // VPM-01F-FIX-01 (F-1): Arabic (RTL): Previous points right, Next left.
        $this->assertMatchesRegularExpression('#data-product-media-previous disabled>\s*<i class="ph-duotone ph-caret-right" aria-hidden="true"></i>#', $this->dialog($ar));
        $this->assertMatchesRegularExpression('#data-product-media-next disabled>\s*<span>[^<]+</span>\s*<i class="ph-duotone ph-caret-left" aria-hidden="true"></i>#', $this->dialog($ar));
        foreach (['اختر صورة من الوسائط', 'aria-label="إغلاق"', 'تظهر هنا صور مكتبة الوسائط الخاصة بك فقط.', 'ابحث في الصور', 'placeholder="الاسم أو النص البديل"', 'ارفع صورة جديدة', 'جارٍ تحميل الصور…', 'لا توجد صور تطابق بحثك.', 'إعادة المحاولة', 'aria-label="صفحات مكتبة الوسائط"', 'السابق', 'التالي'] as $text) {
            $this->assertStringContainsString($text, $this->dialog($ar), $text);
        }
        $messages = $this->messages($ar);
        $this->assertSame('عدد الصور: :count', $messages['libraryCount']);
        $this->assertSame('الصفحة :page من :last', $messages['pageStatus']);
        $this->assertSame('اختر :name', $messages['choose']);
        $this->assertSame('مضافة إلى هذا المنتج', $messages['attached']);
        $this->assertSame('يمكنك رفع 10 صور كحد أقصى في المرة الواحدة.', $messages['uploadMax']);
        $this->assertStringContainsString('أُضيفت أول صورة إلى المنتج', $messages['uploaded']);

        $en = $this->page('en');
        $this->assertMatchesRegularExpression('/<html lang="en" dir="ltr"/', $en);
        $messages = $this->messages($en);
        $this->assertSame('Already in this product', $messages['attached']);
        $this->assertSame('You can upload up to 10 images at a time.', $messages['uploadMax']);
        $this->assertSame('Images uploaded: :count. The first one was added to the product; the others are in your media library.', $messages['uploaded']);
        foreach (['titleMain', 'titleReplace', 'titleGallery', 'libraryError', 'session', 'forbidden', 'invalid', 'network', 'mediaAlt', 'uploadInvalid', 'limit'] as $kept) {
            $this->assertArrayHasKey($kept, $messages, $kept);
        }
        $this->assertStringNotContainsString('مكتبة', $this->dialog($en));
    }

    // ---------------------------------------------------------------- the script

    public function test_the_picker_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-product-picker.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_pages_the_library_and_keeps_the_endpoints_and_rules(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringNotContainsString('fetchJson(editor.dataset.mediaLibraryUrl', $source, 'The whole library is never asked for.');
        $this->assertStringContainsString('request(listUrl(url, { page, search })', $source, 'One page at a time (page, per_page=24, search).');
        $this->assertStringContainsString("import { cleanSearch, createGallery, listUrl, safeUrl, viewState } from './vendor-media-library.js';", $source);
        $this->assertStringContainsString('url: editor.dataset.mediaLibraryUrl', $source);
        $this->assertStringContainsString("fetchJson(editor.dataset.mediaStoreUrl, { method: 'POST'", $source, 'Uploads go to the vendor library, as before.');
        $this->assertStringContainsString("data.append('files[]', file)", $source);
        $this->assertStringContainsString('export const MAX_UPLOAD_FILES = 10;', $source);
        $this->assertStringContainsString("if (!target && images.length >= MAX_IMAGES) { galleryFail('limit'); return; }", $source, 'The six-image limit before the picker opens.');
        $this->assertDoesNotMatchRegularExpression('#/dashboard/products/vendor-media|dashboard\.products\.vendor-media|admin-product-gallery|vendor_id=#', $source, 'No admin endpoint or script, no vendor chosen by the page.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source);
    }

    public function test_the_picker_styles_are_vendor_scoped_and_logical(): void
    {
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));
        $block = substr($css, strpos($css, '/* ---- Product image picker (VPM-01B)'));

        preg_match_all('/^([^\s@}\/*][^{]*)\{/m', $block, $selectors);
        $this->assertNotEmpty($selectors[1]);
        foreach ($selectors[1] as $selector) {
            foreach (array_map('trim', explode(',', $selector)) as $one) {
                $this->assertStringStartsWith('html.vendor-shell .vendor-product-picker', $one);
            }
        }
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w])(left|right|margin-left|margin-right|padding-left|padding-right)\s*:/', $block);
        $this->assertStringContainsString('width: min(56rem, calc(100vw - 2rem));', $block, 'The admin chooser width.');
        $this->assertStringContainsString('@media (max-width: 575.98px)', $block);
        $this->assertStringNotContainsString('vendor-product-media-panel', $css, 'The inline chooser styles are gone.');
    }

    // ---------------------------------------------------------------- the server answers the picker relies on

    public function test_the_paged_listing_the_picker_reads_is_the_vendors_own_library(): void
    {
        $own = $this->media("vendors/media/{$this->vendor->id}/".uniqid().'.png');
        $other = Vendor::factory()->approved()->create();
        $this->media("vendors/media/{$other->id}/".uniqid().'.png');
        $this->media('media/2026/10/admin.png');

        $this->actingAs($this->vendor, 'vendor')
            ->getJson(route('vendor.dashboard.products.media.library', ['page' => 1, 'per_page' => 24]))
            ->assertOk()
            ->assertExactJson([
                'images' => [['id' => $own->id, 'path' => $own->file_path, 'alt_text' => 'jug.png', 'url' => $own->url]],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 24, 'total' => 1],
            ]);
    }

    public function test_an_upload_of_eleven_is_refused_on_files_and_nothing_is_stored(): void
    {
        $files = array_map(fn (int $i) => $this->png("photo-{$i}.png"), range(1, 11));

        $this->actingAs($this->vendor, 'vendor')
            ->post(route('vendor.dashboard.products.media.store'), ['files' => $files], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_another_vendors_image_is_refused_on_path_and_the_gallery_is_kept(): void
    {
        $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->media("vendors/media/{$other->id}/".uniqid().'.png');

        $this->actingAs($this->vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $theirs->file_path, 'alt_text' => null, 'is_primary' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        $this->assertSame(0, ProductImage::query()->count());
    }

    // ---------------------------------------------------------------- helpers

    private function page(string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
            ->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
    }

    private function dialog(string $html): string
    {
        $this->assertSame(1, preg_match('#<dialog class="vendor-media-dialog vendor-product-picker".*?</dialog>#s', $html, $match));

        return $match[0];
    }

    /** @return array<string, string> */
    private function messages(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-product-gallery-messages>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function media(string $path): Media
    {
        Storage::disk('public')->put($path, 'image');

        return Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public',
        ]);
    }

    /** A real PNG in a temporary file, so the upload checks see real image bytes. */
    private function png(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(8, 6);
        ob_start();
        imagepng($image);
        $path = tempnam(sys_get_temp_dir(), 'vpm01b');
        file_put_contents($path, (string) ob_get_clean());
        $this->temporary[] = $path;

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
