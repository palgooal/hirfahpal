<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-4: the legacy direct upload to a product (POST products/{product}/upload-image,
 * vendor.dashboard.products.images.upload) is gone. A vendor uploads to their own media library and attaches library
 * images to their products; the earlier protections (library ownership, six images, one first main image, unlink
 * only) apply to that one path. Every other vendor product and media route is still registered.
 */
class VendorProductDirectUploadRemovedTest extends TestCase
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

    public function test_the_direct_upload_route_and_its_code_are_gone(): void
    {
        $this->assertFalse(Route::has('vendor.dashboard.products.images.upload'));
        $this->assertEmpty(collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'upload-image')));
        $this->assertFalse(method_exists(ProductImageController::class, 'upload'));
        $this->assertFalse(method_exists(ProductImageController::class, 'library'));
        $this->assertFalse(class_exists('App\Http\Requests\VendorDashboard\UploadProductImageRequest'));
    }

    public function test_the_former_url_uploads_and_attaches_nothing(): void
    {
        $other = Vendor::factory()->approved()->create();
        $url = "/vendor/dashboard/products/{$this->product->id}/upload-image";

        foreach ([$this->vendor, $other] as $vendor) {
            $this->actingAs($vendor, 'vendor')
                ->post($url, ['file' => UploadedFile::fake()->image('jug.png', 40, 40)], ['Accept' => 'application/json'])
                ->assertNotFound();
            $this->actingAs($vendor, 'vendor')->postJson($url, ['path' => 'vendors/products/1/x.png'])->assertNotFound();
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_the_other_vendor_product_and_media_routes_are_unchanged(): void
    {
        $expected = [
            'vendor.dashboard.products.index' => ['GET|HEAD', 'vendor/dashboard/products'],
            'vendor.dashboard.products.store' => ['POST', 'vendor/dashboard/products'],
            'vendor.dashboard.products.page' => ['GET|HEAD', 'vendor/dashboard/products/manage'],
            'vendor.dashboard.products.media.library' => ['GET|HEAD', 'vendor/dashboard/products/media/library'],
            'vendor.dashboard.products.media.store' => ['POST', 'vendor/dashboard/products/media/library'],
            'vendor.dashboard.products.media.destroy' => ['DELETE', 'vendor/dashboard/products/media/library/{media}'],
            'vendor.dashboard.products.show' => ['GET|HEAD', 'vendor/dashboard/products/{product}'],
            'vendor.dashboard.products.update' => ['PUT|PATCH', 'vendor/dashboard/products/{product}'],
            'vendor.dashboard.products.destroy' => ['DELETE', 'vendor/dashboard/products/{product}'],
            'vendor.dashboard.products.images.store' => ['POST', 'vendor/dashboard/products/{product}/images'],
            'vendor.dashboard.products.images.update' => ['PATCH', 'vendor/dashboard/products/{product}/images/{productImage}'],
            'vendor.dashboard.products.images.destroy' => ['DELETE', 'vendor/dashboard/products/{product}/images/{productImage}'],
        ];

        $actual = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'vendor.dashboard.products.'))
            ->mapWithKeys(fn ($route) => [$route->getName() => [implode('|', $route->methods()), $route->uri()]])
            ->sortKeys()->all();

        ksort($expected);
        $this->assertSame($expected, $actual);

        foreach (array_keys($expected) as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('auth:vendor', $route->gatherMiddleware(), $name);
            $this->assertContains('vendor.approved', $route->gatherMiddleware(), $name);
        }
    }

    public function test_library_upload_attach_limit_main_image_and_unlink_still_work_end_to_end(): void
    {
        // Upload eight images to the library: the library has no six-image limit.
        $paths = $this->actingAs($this->vendor, 'vendor')->post(route('vendor.dashboard.products.media.store'), [
            'files' => array_map(fn ($i) => UploadedFile::fake()->image("jug-{$i}.png", 30, 30), range(1, 8)),
        ], ['Accept' => 'application/json'])->assertCreated()->json('images.*.path');
        $this->assertCount(8, $paths);

        // Attach six; the first is the main one, the fourth is chosen as main and moves first.
        $ids = [];
        foreach (array_slice($paths, 0, 6) as $i => $path) {
            $ids[] = $this->actingAs($this->vendor, 'vendor')
                ->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $path, 'is_primary' => $i === 3])
                ->assertCreated()->json('image.id');
        }
        $this->assertSame([$ids[3], $ids[0], $ids[1], $ids[2], $ids[4], $ids[5]], $this->product->images()->pluck('id')->all());
        $this->assertSame([true, false, false, false, false, false], $this->product->images()->pluck('is_primary')->all());

        // A seventh is refused; the same library image reused on another product of the vendor is accepted.
        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $paths[6]])
            ->assertUnprocessable()->assertJsonValidationErrors(['images' => ProductImageController::MAX_IMAGES_MESSAGE]);
        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product($this->vendor)), ['path' => $paths[0]])
            ->assertCreated();

        // Removing the main image unlinks it only, and the next one becomes the main image.
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[3]]))
            ->assertOk()->assertJsonPath('product.images.0.id', $ids[0]);
        Storage::disk('public')->assertExists($paths[3]);
        $this->assertSame(8, Media::query()->count());
    }

    public function test_another_vendors_library_image_is_still_refused_and_404s_are_unchanged(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->actingAs($other, 'vendor')->post(route('vendor.dashboard.products.media.store'), [
            'files' => [UploadedFile::fake()->image('theirs.png', 30, 30)],
        ], ['Accept' => 'application/json'])->assertCreated()->json('images.0.path');

        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $theirs])
            ->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);
        $this->actingAs($other, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $theirs])
            ->assertNotFound();

        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_a_legacy_direct_upload_stays_visible_and_manageable(): void
    {
        $legacy = "vendors/products/{$this->vendor->id}/{$this->product->id}/old.png";
        Storage::disk('public')->put($legacy, 'legacy');
        $image = $this->product->images()->create(['path' => $legacy, 'alt_text' => 'Old', 'sort_order' => 0, 'is_primary' => true]);

        $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.show', $this->product))
            ->assertOk()->assertJsonPath('product.images.0.path', $legacy);
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $image]), ['path' => $legacy, 'alt_text' => 'Old jug'])
            ->assertOk()->assertJsonPath('image.alt_text', 'Old jug');
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $image]))->assertOk();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertExists($legacy);
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }
}
