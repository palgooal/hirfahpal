<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VPM-01E: images from before the media library and galleries from before the six-image limit. The editor shows every
 * image at the address the storefront uses, marks images outside the vendor's library folder, shows a placeholder for a
 * file that is gone, and one note at or above the limit; nothing is migrated, copied, renamed, removed or truncated.
 * The server already keeps such images manageable (unchanged paths accepted, never trimmed, no seventh added); this
 * covers the page, its texts and those guarantees together on one historical product.
 */
class VendorProductLegacyGalleryTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Old jug', 'slug' => 'old-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    protected function tearDown(): void
    {
        if ($this->hotFile !== '') {
            @unlink($this->hotFile);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the page

    public function test_the_editor_knows_the_vendors_library_folder_and_has_one_limit_note(): void
    {
        $html = $this->page('en');

        $this->assertStringContainsString('data-library-prefix="vendors/media/'.$this->vendor->id.'/"', $html);
        $this->assertSame(1, substr_count($html, 'data-product-gallery-limit'), 'One note, not one per section.');
        $this->assertStringContainsString('<p class="vendor-product-gallery-note" id="vendor-product-gallery-limit" role="status" aria-live="polite" data-product-gallery-limit hidden></p>', $html);
        $this->assertStringContainsString('data-product-gallery-open data-limited="true" aria-describedby="vendor-product-gallery-hint vendor-product-gallery-limit"', $html, 'The disabled add button says why.');
    }

    public function test_the_legacy_missing_and_limit_texts_in_arabic_and_english(): void
    {
        $ar = $this->messages($this->page('ar'));
        $this->assertSame('ليست في مكتبة الوسائط الخاصة بك', $ar['legacy']);
        $this->assertSame('ملف الصورة غير موجود', $ar['missing']);
        $this->assertStringContainsString(':max', $ar['full']);
        $this->assertStringContainsString(':count', $ar['overMax']);
        $this->assertStringContainsString('لا تُزال أي صورة تلقائيًا', $ar['overMax']);

        $en = $this->messages($this->page('en'));
        $this->assertSame('Not in your media library', $en['legacy']);
        $this->assertSame('Image file not found', $en['missing']);
        $this->assertSame('This product has the maximum of :max images. Remove or replace one to add another.', $en['full']);
        $this->assertSame('This product has :count images, more than the current maximum of :max. Nothing can be added. You can still remove, replace, reorder and set the main image; no image is removed automatically.', $en['overMax']);
    }

    public function test_the_script_shows_images_at_the_storefront_address_and_marks_legacy_ones(): void
    {
        $source = file_get_contents(resource_path('js/vendor-product-editor.js'));

        $this->assertStringContainsString('const imageUrl = productImageUrl;', $source);
        $this->assertStringNotContainsString('`/storage/${image.path}`', $source, 'No more /storage/ for every path.');
        $this->assertStringContainsString('legacy: isLegacyImage(image, libraryPrefix)', $source);
        $this->assertStringContainsString('pic.onerror = unavailable;', $source, 'A missing file becomes a placeholder.');
    }

    // ---------------------------------------------------------------- a historical product

    public function test_a_historical_gallery_above_six_with_legacy_and_missing_files_is_kept_and_manageable(): void
    {
        $ids = $this->historicalGallery();
        $snapshot = $this->rows();

        // Readable as it is: all eight, in order, legacy paths and missing files included.
        $shown = $this->as()->getJson(route('vendor.dashboard.products.show', $this->product))->assertOk()->json('product.images');
        $this->assertSame($ids, array_column($shown, 'id'));
        $this->assertSame(array_column($snapshot, 'path'), array_column($shown, 'path'));

        // Unrelated product fields save; the images are untouched.
        $this->as()->putJson(route('vendor.dashboard.products.update', $this->product), ['category_id' => $this->activeCategoryId(), 'name' => 'Old jug, renamed', 'price' => 12, 'stock_quantity' => 3])->assertOk();
        $this->assertSame($snapshot, $this->rows());

        // Nothing can be added above the limit.
        $this->as()->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $this->library(), 'alt_text' => null, 'is_primary' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame($snapshot, $this->rows());

        // A legacy path cannot be attached as a new image, nor put on another slot.
        $this->as()->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[2]]), ['path' => 'products/2023/other.png'])
            ->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);

        // Every action still works on legacy and missing-file images: alt text, main image, moves, keeping its own path.
        $this->as()->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[1]]), ['alt_text' => 'Side view'])->assertOk();
        $this->as()->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[1]]), ['path' => 'products/2023/front.png', 'alt_text' => 'Side view'])->assertOk();
        $this->assertSame('products/2023/front.png', ProductImage::find($ids[1])->path, 'Its legacy path is kept as it is.');
        $this->as()->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[3]]), ['is_primary' => true])->assertOk()->assertJsonPath('product.images.0.id', $ids[3]);
        $this->as()->patchJson(route('vendor.dashboard.products.images.move', [$this->product, $ids[7]]), ['direction' => 'up'])->assertOk();
        $this->assertSame(8, $this->product->images()->count());

        // Replacing one with a library image keeps the count; removing brings it down, one at a time.
        $this->as()->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[5]]), ['path' => $this->library(), 'alt_text' => null])->assertOk();
        $this->assertSame(8, $this->product->images()->count());
        $this->as()->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[0]]))->assertOk();
        $this->as()->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[4]]))->assertOk();
        $this->assertSame(6, $this->product->images()->count());

        // Removing only unlinks: the legacy files are where they were.
        Storage::disk('public')->assertExists('products/2023/front.png');
        Storage::disk('public')->assertExists('vendors/products/'.$this->vendor->id.'/old.png');
        // At six, a seventh is refused again; the order stays 0..n-1 with one main image.
        $this->as()->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $this->library(), 'alt_text' => null, 'is_primary' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $rows = $this->product->images()->orderBy('sort_order')->get();
        $this->assertSame(range(0, 5), $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(1, $rows->where('is_primary', true)->count());
    }

    public function test_new_products_stay_limited_to_six(): void
    {
        $images = array_map(fn () => ['path' => $this->library()], range(1, 7));
        $products = Product::query()->count();

        $this->as()->postJson(route('vendor.dashboard.products.store'), ['category_id' => $this->activeCategoryId(), 'name' => 'New jug', 'price' => 10, 'stock_quantity' => 1, 'images' => $images])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame($products, Product::query()->count());

        $this->as()->postJson(route('vendor.dashboard.products.store'), ['category_id' => $this->activeCategoryId(), 'name' => 'New jug', 'price' => 10, 'stock_quantity' => 1, 'images' => array_slice($images, 0, 6)])
            ->assertCreated()->assertJsonCount(6, 'product.images');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Eight images from before the limit and the library: library images, legacy paths (an old product folder, the
     * old vendor product folder, an external URL, a public asset), and files that are gone (no file on the disk).
     *
     * @return list<int>
     */
    private function historicalGallery(): array
    {
        Storage::disk('public')->put('products/2023/front.png', 'old');
        Storage::disk('public')->put('vendors/products/'.$this->vendor->id.'/old.png', 'old');

        $paths = [
            $this->library(),
            'products/2023/front.png',
            'vendors/products/'.$this->vendor->id.'/old.png',
            'https://cdn.example.test/jug.png',
            'assets/images/jug.png',
            'products/2023/gone.png',
            $this->library(),
            'products/2023/also-gone.png',
        ];

        return array_map(fn ($position) => $this->product->images()->create([
            'path' => $paths[$position], 'alt_text' => "Image {$position}", 'sort_order' => $position, 'is_primary' => $position === 0,
        ])->id, array_keys($paths));
    }

    private function rows(): array
    {
        return ProductImage::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'path', 'alt_text', 'sort_order', 'is_primary'])->toArray();
    }

    private function as(): static
    {
        return $this->actingAs($this->vendor, 'vendor');
    }

    private function library(): string
    {
        $path = "vendors/media/{$this->vendor->id}/".uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'image');
        Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $this->vendor->id,
        ]);

        return $path;
    }

    private function page(string $locale): string
    {
        if ($this->hotFile === '') {
            Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
            Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
            $this->seed(AdminAuthTranslationSeeder::class);
            $this->seed(VendorAuthTranslationSeeder::class);
            $this->seed(VendorDashboardTranslationSeeder::class);
            $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
            file_put_contents($this->hotFile, 'http://localhost:5173');
            Vite::useHotFile($this->hotFile);
        }

        return $this->as()->withSession(['locale' => $locale])->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
    }

    /** @return array<string, string> */
    private function messages(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-product-gallery-messages>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
