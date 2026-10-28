<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-2 (V3): a vendor product has at most six images, on creation (images[]) and when
 * an image is added (images.store, counted under a lock on the product row). A refusal is a standard 422 and changes
 * nothing; removing frees a slot, replacing a path keeps the count; a legacy product above six is kept as it is and
 * takes no more. The vendor's media library itself has no limit. Concurrency is checked on MySQL separately: SQLite,
 * used here, ignores lockForUpdate().
 */
class VendorProductImageLimitTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = $this->product($this->vendor);
    }

    // ---------------------------------------------------------------- product creation

    public function test_a_product_can_be_created_with_none_one_or_six_images(): void
    {
        foreach ([0, 1, 6] as $count) {
            $paths = $this->libraryPaths($count);
            $response = $this->createProduct(array_map(fn ($path) => ['path' => $path], $paths))->assertCreated();

            $this->assertSame($paths, Product::query()->findOrFail($response->json('product.id'))->images()->pluck('path')->all(), "{$count} images");
        }
    }

    public function test_a_product_with_seven_images_is_refused_and_not_created(): void
    {
        $products = Product::query()->count();
        $images = array_map(fn ($path) => ['path' => $path], $this->libraryPaths(7));

        $this->createProduct($images)->assertUnprocessable()->assertJsonValidationErrors(['images' => ProductImageController::MAX_IMAGES_MESSAGE]);

        $this->assertSame($products, Product::query()->count());
        $this->assertSame(0, ProductImage::query()->count());
    }

    // ---------------------------------------------------------------- adding images

    public function test_the_sixth_image_is_added_and_the_seventh_refused_with_nothing_changed(): void
    {
        foreach ($this->libraryPaths(5) as $path) {
            $this->addImage($this->product, $path)->assertCreated();
        }

        $this->addImage($this->product, $this->libraryPaths(1)[0])
            ->assertCreated()
            ->assertJsonStructure(['message', 'image' => ['id', 'path'], 'product' => ['id', 'images']])
            ->assertJsonCount(6, 'product.images');

        $before = $this->rows($this->product);
        $this->addImage($this->product, $this->libraryPaths(1)[0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images' => ProductImageController::MAX_IMAGES_MESSAGE]);

        $this->assertSame($before, $this->rows($this->product), 'The six associations are untouched.');
    }

    public function test_removing_an_image_frees_a_slot_and_keeps_its_file(): void
    {
        $images = $this->fill($this->product, 6);
        $this->addImage($this->product, $this->libraryPaths(1)[0])->assertUnprocessable();

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $images[2]]))->assertOk();
        Storage::disk('public')->assertExists($images[2]->path);

        $this->addImage($this->product, $this->libraryPaths(1)[0])->assertCreated();
        $this->assertSame(6, $this->product->images()->count());
    }

    public function test_replacing_an_image_path_at_six_keeps_working(): void
    {
        $images = $this->fill($this->product, 6);
        $next = $this->libraryPaths(1)[0];

        $this->actingAs($this->vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $images[0]]), ['path' => $next])
            ->assertOk()->assertJsonPath('image.path', $next);

        $this->assertSame(6, $this->product->images()->count());
    }

    public function test_a_legacy_product_with_seven_images_is_kept_and_takes_no_eighth(): void
    {
        // Seven associations from before the limit, one of them outside the library.
        $images = $this->fill($this->product, 6);
        $images[] = $this->product->images()->create(['path' => 'products/old/jug.png', 'sort_order' => 6, 'is_primary' => false]);
        $before = $this->rows($this->product);

        $this->addImage($this->product, $this->libraryPaths(1)[0])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame($before, $this->rows($this->product), 'Nothing is trimmed or added.');

        // Still managed: an image can be updated, and one removed (7 -> 6) is still full.
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $images[6]]), ['alt_text' => 'Old jug'])->assertOk();
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $images[6]]))->assertOk();
        $this->addImage($this->product, $this->libraryPaths(1)[0])->assertUnprocessable();

        // Below six, an image can be added again.
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $images[5]]))->assertOk();
        $this->addImage($this->product, $this->libraryPaths(1)[0])->assertCreated();
        $this->assertSame(6, $this->product->images()->count());
    }

    // ---------------------------------------------------------------- what the limit does not touch

    public function test_one_library_image_is_reused_across_products_and_each_product_counts_its_own(): void
    {
        $shared = $this->libraryPaths(1)[0];
        $full = $this->product($this->vendor);
        $this->fill($full, 6);

        $this->addImage($this->product, $shared)->assertCreated();
        $this->addImage($this->product($this->vendor), $shared)->assertCreated();
        $this->addImage($full, $shared)->assertUnprocessable()->assertJsonValidationErrors('images');

        $this->assertSame(2, ProductImage::query()->where('path', $shared)->count());
    }

    public function test_the_media_library_itself_has_no_six_image_limit(): void
    {
        $files = array_map(fn ($i) => UploadedFile::fake()->image("jug-{$i}.png", 20, 20), range(1, 8));

        $this->actingAs($this->vendor, 'vendor')
            ->post(route('vendor.dashboard.products.media.store'), ['files' => $files], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonCount(8, 'images');
        $this->libraryPaths(4);

        $this->assertSame(12, Media::query()->where('file_path', 'like', "vendors/media/{$this->vendor->id}/%")->count());
        $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.media.library'))->assertOk();
    }

    public function test_ownership_is_still_checked_before_the_limit(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->libraryPaths(1, $other)[0];

        $this->addImage($this->product, $theirs)->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);
        $this->createProduct([['path' => $theirs]])->assertUnprocessable()->assertJsonValidationErrors(['images.0.path' => VendorLibraryImage::MESSAGE]);
        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_the_404_checks_are_unchanged(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirProduct = $this->product($other);
        $this->fill($theirProduct, 6);
        $before = $this->rows($theirProduct);

        // Another vendor's product: 404, whether it is full or not, never the limit error.
        $this->addImage($theirProduct, $this->libraryPaths(1)[0])->assertNotFound();
        $this->addImage($this->product($other), $this->libraryPaths(1)[0])->assertNotFound();
        $this->assertSame($before, $this->rows($theirProduct));
    }

    // ---------------------------------------------------------------- helpers

    private function addImage(Product $product, string $path): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path]);
    }

    private function createProduct(array $images): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2, 'images' => $images,
        ]);
    }

    /** @return list<ProductImage> */
    private function fill(Product $product, int $count): array
    {
        return array_map(
            fn ($path, $i) => $product->images()->create(['path' => $path, 'alt_text' => 'Jug', 'sort_order' => $i, 'is_primary' => $i === 0]),
            $this->libraryPaths($count, $product->vendor),
            range(0, $count - 1)
        );
    }

    /** @return list<array<string, mixed>> */
    private function rows(Product $product): array
    {
        return $product->images()->orderBy('id')->get()->toArray();
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    /** @return list<string> */
    private function libraryPaths(int $count, ?Vendor $vendor = null): array
    {
        $vendor ??= $this->vendor;

        return array_map(function () use ($vendor) {
            $path = "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
            Storage::disk('public')->put($path, 'image');
            Media::create([
                'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
                'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
            ]);

            return $path;
        }, $count > 0 ? range(1, $count) : []);
    }
}
