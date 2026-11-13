<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Database\Seeders\AdminProductTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * PRODUCT-STORY-MEDIA (7B-4F-2A): the story image field of the admin product form and its script
 * (resources/js/admin-product-story-image.js, tested with Node's test runner in tests/js/admin-product-story-image.test.mjs,
 * skipped when Node is not available). The chooser helpers live in one shared module
 * (resources/js/admin-media/vendor-image-chooser.js) with no start-up, which the gallery re-exports; the story script
 * never imports the gallery script, so the gallery starts once. The field renders in both languages, posts two
 * hidden values (never both), and brings back the choice or removal of a refused save, from the vendor's library only.
 */
class AdminProductStoryImageScriptTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/admin-product-story-image.js';

    private const CHOOSER = 'resources/js/admin-media/vendor-image-chooser.js';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);

        foreach ([1, 10] as $id) {
            Vendor::factory()->approved()->create(['id' => $id]);
        }
    }

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-product-story-image.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_story_script_uses_the_shared_chooser_never_the_gallery_script(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString("from './admin-media/vendor-image-chooser.js'", $source);
        $this->assertDoesNotMatchRegularExpression('/from\s+[\'"][^\'"]*admin-product-gallery(\.js)?[\'"]/', $source, 'Importing the gallery entry would copy its start-up into this script.');
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Texts come from the page.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source, 'DOM APIs only.');
        $this->assertDoesNotMatchRegularExpression('/type\s*=\s*[\'"]file|FormData|\.upload/', $source, 'Nothing is uploaded.');
    }

    public function test_the_shared_chooser_has_no_start_up(): void
    {
        $source = file_get_contents(base_path(self::CHOOSER));

        $this->assertDoesNotMatchRegularExpression('/\bdocument\b|\bwindow\b|DOMContentLoaded|readyState|\bboot\b/', $source);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source);
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source);
        $this->assertStringContainsString("from './admin-media/vendor-image-chooser.js'", file_get_contents(resource_path('js/admin-product-gallery.js')));
    }

    public function test_only_the_product_form_loads_the_script(): void
    {
        $this->assertStringContainsString("@vite('resources/js/admin-product-story-image.js')", file_get_contents(resource_path('views/dashboard/products/form.blade.php')));
        $this->assertStringContainsString("'resources/js/admin-product-story-image.js'", file_get_contents(base_path('vite.config.js')));
    }

    public function test_the_field_renders_in_each_language_with_no_upload(): void
    {
        $this->seed(AdminProductTranslationSeeder::class);
        $product = $this->product(['story_image_path' => 'vendors/media/1/old.png']);
        $this->media('vendors/media/1/old.png', 'Old jug');

        foreach ([
            'ar' => ['rtl', 'صورة القصة', 'اختر صورة من مكتبة وسائط هذا البائع.', 'استبدال الصورة', 'إزالة الصورة', 'القصة'],
            'en' => ['ltr', 'Story image', 'Choose an image from this vendor\'s media library.', 'Replace image', 'Remove image', 'Story'],
        ] as $locale => [$direction, $label, $hint, $replace, $remove, $legend]) {
            $html = $this->page(route('dashboard.products.edit', $product), $locale);
            $xpath = $this->xpath($html);

            $this->assertMatchesRegularExpression('/<html[^>]* dir="'.$direction.'"/', $html);
            $this->assertStringContainsString($label, $xpath->query('//*[@id="product-story-image-label"]')->item(0)->textContent);
            $this->assertSame($hint, trim($xpath->query('//*[@id="product-story-image-hint"]')->item(0)->textContent));
            $this->assertSame($replace, trim($xpath->query('//*[@id="product-story-image-choose"]')->item(0)->textContent));
            $this->assertSame($remove, trim($xpath->query('//*[@id="product-story-image-remove"]')->item(0)->textContent));
            $this->assertSame(1, $xpath->query('//form//fieldset[contains(normalize-space(legend), "'.$legend.'")]//*[@id="product-story-image"]')->length);
            $this->assertSame('Old jug', trim($xpath->query('//*[@id="product-story-image-name"]')->item(0)->textContent));

            $this->assertSame(0, $xpath->query('//input[@type="file"]')->length);
            $this->assertStringNotContainsString('data-media-picker', $html);
            $this->assertSame(1, $xpath->query('//form//input[@type="hidden"][@name="story_image_media_id"][@value=""]')->length);
            $this->assertSame(1, $xpath->query('//form//input[@type="hidden"][@name="remove_story_image"][@value=""]')->length);
            $this->assertSame(0, $xpath->query('//form//*[@id="product-story-dialog"]')->length, 'The chooser is outside the form.');
            $this->assertSame(1, $xpath->query('//dialog[@id="product-story-dialog"]')->length);
            $this->assertSame(1, $xpath->query('//dialog[@id="product-gallery-dialog"]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="product-story-image-actions"][@hidden]')->length, 'Revealed by the script.');
        }
    }

    public function test_a_new_product_without_a_story_image_says_so(): void
    {
        $this->seed(AdminProductTranslationSeeder::class);
        $xpath = $this->xpath($this->page(route('dashboard.products.create'), 'ar'));

        $this->assertSame(1, $xpath->query('//*[@id="product-story-image-preview"][@hidden]')->length);
        $this->assertSame('لا توجد صورة للقصة.', trim($xpath->query('//*[@id="product-story-image-empty"]')->item(0)->textContent));
        $this->assertSame('اختيار صورة', trim($xpath->query('//*[@id="product-story-image-choose"]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//*[@id="product-story-image-remove"][@hidden]')->length);
    }

    public function test_a_refused_save_brings_back_the_choice_from_the_vendors_library_only(): void
    {
        $own = $this->media('vendors/media/1/new.png', 'New jug');
        $foreign = $this->media('vendors/media/10/other.png', 'Other jug');
        $product = $this->product(['story_image_path' => 'vendors/media/1/old.png']);

        // An empty name refuses the save; the chosen image comes back with it.
        $html = $this->refusedUpdate($product, ['story_image_media_id' => (string) $own->id]);
        $xpath = $this->xpath($html);
        $this->assertSame((string) $own->id, $xpath->query('//input[@name="story_image_media_id"]')->item(0)->getAttribute('value'));
        $this->assertSame('', $xpath->query('//input[@name="remove_story_image"]')->item(0)->getAttribute('value'));
        $this->assertSame('New jug', trim($xpath->query('//*[@id="product-story-image-name"]')->item(0)->textContent));

        // Another vendor's image never comes back (the stored image is shown again, nothing chosen).
        $xpath = $this->xpath($this->refusedUpdate($product, ['story_image_media_id' => (string) $foreign->id]));
        $this->assertSame('', $xpath->query('//input[@name="story_image_media_id"]')->item(0)->getAttribute('value'));
        $this->assertStringNotContainsString('Other jug', $xpath->query('//*[@id="product-story-image"]')->item(0)->textContent);

        // A removal comes back as a removal.
        $xpath = $this->xpath($this->refusedUpdate($product, ['remove_story_image' => '1']));
        $this->assertSame('1', $xpath->query('//input[@name="remove_story_image"]')->item(0)->getAttribute('value'));
        $this->assertSame('', $xpath->query('//input[@name="story_image_media_id"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//*[@id="product-story-image-preview"][@hidden]')->length);

        $this->assertSame('vendors/media/1/old.png', $product->fresh()->story_image_path, 'A refused save changes nothing.');
    }

    public function test_a_story_image_error_is_shown_at_the_field_and_linked_from_the_summary(): void
    {
        $foreign = $this->media('vendors/media/10/other.png', 'Other jug');
        $product = $this->product();

        $html = $this->actingAs($this->superAdmin(), 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.products.edit', $product))->followingRedirects()
            ->put(route('dashboard.products.update', $product), $this->fields($product, ['story_image_media_id' => (string) $foreign->id]))
            ->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame('Each selected image must come from this vendor\'s media library.', trim($xpath->query('//*[@id="product-story-image-error"]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//a[@href="#product-story-image"]')->length, 'The error summary links to the field.');
    }

    private function refusedUpdate(Product $product, array $fields): string
    {
        return $this->actingAs($this->superAdmin(), 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.products.edit', $product))->followingRedirects()
            ->put(route('dashboard.products.update', $product), $this->fields($product, $fields + ['name' => '']))
            ->assertOk()->getContent();
    }

    private function fields(Product $product, array $overrides): array
    {
        $fresh = $product->fresh();

        return $overrides + [
            'vendor_id' => (string) $fresh->vendor_id, 'category_id' => (string) $fresh->category_id, 'name' => $fresh->name, 'slug' => $fresh->slug,
            'price' => '10', 'stock_quantity' => (string) $fresh->stock_quantity, 'status' => 'draft',
            'stock_quantity_original' => (string) $fresh->stock_quantity,
            'stock_version' => ProductManagementController::stockVersion($fresh),
        ];
    }

    private function page(string $url, string $locale): string
    {
        return $this->actingAs($this->superAdmin(), 'admin')->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'fixture-category'], ['name' => 'Pottery', 'is_active' => true]);

        return Product::create($attributes + [
            'vendor_id' => 1, 'category_id' => $category->id, 'name' => 'Clay jug', 'slug' => 'clay-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 4, 'status' => 'draft',
        ]);
    }

    private function media(string $path, string $name): Media
    {
        Storage::disk('public')->put($path, 'file');

        return Media::create([
            'file_name' => basename($path), 'file_original_name' => $name, 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 4, 'file_type' => 'image', 'disk' => 'public',
        ]);
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
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
