<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-5: the vendor product editor's gallery. The page gives the script its texts in the
 * page language (seeded, read without creating rows); the script replaces an image with one PATCH (never POST then
 * DELETE), uses no product upload, and redraws from the server's answer. The requests below are exactly the ones the
 * script sends. Its logic runs in tests/js/vendor-product-editor.test.mjs, its behaviour in a real browser (QA).
 */
class VendorProductEditorGalleryTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private string $hotFile = '';

    private Vendor $vendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
        Storage::fake('public');

        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = $this->product($this->vendor);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the page

    public function test_the_page_gives_the_script_its_gallery_texts_in_arabic_and_english(): void
    {
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);

        $expected = [
            'ar' => ['limit' => 'يمكن أن يحتوي المنتج على 6 صور كحد أقصى. أزل صورة أو استبدلها لإضافة أخرى.', 'library' => 'هذه الصورة ليست في مكتبة الوسائط الخاصة بك أو أُزيلت منها. اختر صورة أخرى.', 'gone' => 'لم تعد هذه الصورة أو هذا المنتج متاحًا. أعد تحميل الصفحة وحاول مجددًا.', 'session' => 'انتهت جلستك. سجّل الدخول مجددًا للمتابعة.', 'forbidden' => 'ليست لديك صلاحية للقيام بذلك.', 'network' => 'تعذّر الاتصال. تحقق من الشبكة وحاول مجددًا.', 'failed' => 'حدث خطأ ما. حاول مجددًا.', 'replaced' => 'استُبدلت الصورة.', 'count' => ':count من :max صور'],
            'en' => ['limit' => 'A product can have up to 6 images. Remove or replace an image to add another.', 'library' => 'This image is not in your media library, or it was removed. Choose another image.', 'gone' => 'This image or product is no longer available. Reload the page and try again.', 'session' => 'Your session has ended. Sign in again to continue.', 'forbidden' => 'You do not have permission to do this.', 'network' => 'The connection failed. Check the network and try again.', 'failed' => 'Something went wrong. Try again.', 'replaced' => 'Image replaced.', 'count' => ':count of :max images'],
        ];

        foreach ($expected as $locale => $texts) {
            $html = $this->editorPage($locale);
            $messages = $this->messages($html);

            $this->assertMatchesRegularExpression('#<html[^>]* dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"#', $html);
            foreach ($texts as $name => $text) {
                $this->assertSame($text, $messages[$name], "{$locale}.{$name}");
            }
            $this->assertCount(30, $messages);
            $this->assertStringContainsString('data-product-gallery-status', $html);
            $this->assertMatchesRegularExpression('#role="alert" data-product-gallery-error hidden#', $html);
            $this->assertStringContainsString('data-product-gallery-open data-limited="true"', $html);
        }
    }

    public function test_every_script_text_is_seeded_in_both_languages(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $messages = $this->messages($this->editorPage('en'));

        preg_match_all("/\\\$galleryText\\('([A-Za-z_]+)', '((?:[^'\\\\]|\\\\.)*)'\\)/", file_get_contents(resource_path('views/vendor-dashboard/resources.blade.php')), $calls, PREG_SET_ORDER);
        $this->assertCount(30, $calls);

        foreach ($calls as [, $key, $default]) {
            $values = VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Product_Gallery_'.$key] ?? null;
            $this->assertNotNull($values, $key);
            $this->assertSame(stripslashes($default), $values['en'], "{$key}: the English value is the default in the view.");
            $this->assertNotSame('', $values['ar']);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
        }

        // Every message the script reads is given by the page.
        preg_match_all('/messages\.([a-zA-Z]+)/', file_get_contents(base_path(self::SCRIPT)), $used);
        foreach (array_unique($used[1]) as $name) {
            $this->assertArrayHasKey($name, $messages, "messages.{$name}");
        }
    }

    public function test_the_page_never_creates_translation_rows(): void
    {
        $before = TranslationValue::query()->where('key', 'like', 'vendor.Product_Gallery_%')->count();

        $this->editorPage('ar');
        $this->editorPage('en');

        $this->assertSame($before, TranslationValue::query()->where('key', 'like', 'vendor.Product_Gallery_%')->count());
    }

    // ---------------------------------------------------------------- the script

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-product-editor.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_replaces_with_one_patch_and_uses_no_product_upload(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringNotContainsString('upload-image', $source);
        $this->assertStringNotContainsString('images.upload', $source);
        $this->assertMatchesRegularExpression("#plan\.action === 'replace'\s*//[^\n]*\n\s*\? await mutate\(`\\\$\{endpoint\}/\\\$\{editingId\}/images/\\\$\{plan\.target\.id\}`, 'PATCH', \{ path: medium\.path#", $source);
        $this->assertStringNotContainsString('old image could not be removed', $source, 'The former POST-then-DELETE replacement is gone.');
        $this->assertSame(1, substr_count($source, "'DELETE', null"), 'DELETE only for removing an image.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source);
        $this->assertStringNotContainsString('body.message', $source, 'Server messages are never shown as they are.');
        $this->assertStringContainsString('editor.dataset.mediaStoreUrl', $source, 'Uploads go to the media library.');
    }

    // ---------------------------------------------------------------- the requests the script sends

    public function test_replacing_with_six_images_keeps_the_count_the_place_and_the_main_image(): void
    {
        $ids = $this->fill(6);
        $next = $this->library();

        // The script's replacement: PATCH {path, alt_text} on the replaced image.
        $response = $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[3]]), ['path' => $next, 'alt_text' => 'New'], $this->as())->assertOk();

        $this->assertSame($ids, array_column($response->json('product.images'), 'id'), 'Same images, same order.');
        $this->assertSame($next, $response->json('product.images.3.path'));
        $this->assertSame([true, false, false, false, false, false], array_column($response->json('product.images'), 'is_primary'));
        $this->assertSame(6, $this->product->images()->count());
    }

    public function test_replacing_the_main_image_keeps_it_main_and_first(): void
    {
        $ids = $this->fill(3);
        $next = $this->library();

        $response = $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[0]]), ['path' => $next, 'alt_text' => null], $this->as())->assertOk();

        $this->assertSame([$ids[0], $ids[1], $ids[2]], array_column($response->json('product.images'), 'id'));
        $this->assertSame($next, $response->json('product.images.0.path'));
        $this->assertTrue($response->json('product.images.0.is_primary'));
    }

    public function test_a_replacement_with_another_vendors_image_is_refused_and_the_gallery_kept(): void
    {
        $ids = $this->fill(6);
        $before = ProductImage::query()->orderBy('id')->get()->toArray();
        $theirs = $this->library(Vendor::factory()->approved()->create());

        $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[2]]), ['path' => $theirs, 'alt_text' => null], $this->as())
            ->assertUnprocessable()->assertJsonValidationErrors('path')->assertJsonMissingValidationErrors('images');

        $this->assertSame($before, ProductImage::query()->orderBy('id')->get()->toArray());
    }

    public function test_adding_up_to_six_then_a_seventh_is_refused_as_the_limit(): void
    {
        foreach (range(1, 6) as $i) {
            $response = $this->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $this->library(), 'alt_text' => null, 'is_primary' => $i === 1], $this->as())->assertCreated();
            $this->assertCount($i, $response->json('product.images'));
            $this->assertTrue($response->json('product.images.0.is_primary'));
        }

        $this->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $this->library(), 'alt_text' => null, 'is_primary' => false], $this->as())
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame(6, $this->product->images()->count());
    }

    public function test_set_main_and_remove_answer_with_the_new_order(): void
    {
        $ids = $this->fill(4);

        $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[2]]), ['is_primary' => true], $this->as())
            ->assertOk()->assertJsonPath('product.images.0.id', $ids[2])->assertJsonPath('product.images.1.id', $ids[0]);
        // Removing the main image promotes the next; removing another keeps the main image.
        $this->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[2]]), [], $this->as())
            ->assertOk()->assertJsonPath('product.images.0.id', $ids[0])->assertJsonPath('product.images.0.is_primary', true);
        $response = $this->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[3]]), [], $this->as())->assertOk();
        $this->assertSame([$ids[0], $ids[1]], array_column($response->json('product.images'), 'id'));
    }

    public function test_one_library_image_is_reused_across_two_products(): void
    {
        $path = $this->library();
        $second = $this->product($this->vendor);

        foreach ([$this->product, $second] as $product) {
            $this->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path, 'alt_text' => null, 'is_primary' => false], $this->as())->assertCreated();
        }

        $this->assertSame(2, ProductImage::query()->where('path', $path)->count());
    }

    // ---------------------------------------------------------------- helpers

    private function as(): array
    {
        $this->actingAs($this->vendor, 'vendor');

        return [];
    }

    private function editorPage(string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
            ->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
    }

    private function messages(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-product-gallery-messages>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<int> */
    private function fill(int $count): array
    {
        return array_map(fn ($i) => $this->product->images()->create(['path' => $this->library(), 'sort_order' => $i, 'is_primary' => $i === 0])->id, range(0, $count - 1));
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    private function library(?Vendor $vendor = null): string
    {
        $vendor ??= $this->vendor;
        $path = "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'image');
        Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        return $path;
    }
}
