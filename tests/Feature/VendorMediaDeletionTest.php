<?php

namespace Tests\Feature;

use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\StoreProductRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-MEDIA-02C: deleting an image from the vendor's media library (vendor.dashboard.products.media.destroy) and
 * attaching library images to products are serialised on the media row. The deletion locks it FOR UPDATE and checks
 * the use under that lock; every attachment re-reads it with a shared lock inside its own transaction. Here, a
 * deletion that lands between an attachment's validation and its transaction is simulated by deleting the row right
 * after the form request validated (as ProductCategoryForeignKeyTest does); the real interleaving is checked on MySQL
 * separately (SQLite ignores row locks).
 */
class VendorMediaDeletionTest extends TestCase
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

    // ---------------------------------------------------------------- deletion

    public function test_an_unused_own_image_is_deleted_with_its_file(): void
    {
        $media = $this->library($this->vendor);

        $this->remove($media)->assertOk()->assertExactJson(['message' => 'Media deleted.']);

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    public function test_another_vendors_or_an_unknown_image_is_a_404_and_nothing_changes(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->library($other);
        $admin = $this->media('media/2026/10/admin.png');

        $this->remove($theirs)->assertNotFound();
        $this->remove($admin)->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', 999_999))->assertNotFound();

        foreach ([$theirs, $admin] as $media) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->file_path);
        }
    }

    public function test_an_image_used_by_a_product_is_a_409_and_keeps_its_row_and_file(): void
    {
        $media = $this->library($this->vendor);
        $this->product->images()->create(['path' => $media->file_path, 'sort_order' => 0, 'is_primary' => true]);

        $this->remove($media)->assertStatus(409)->assertJsonPath('message', 'This image is currently used by a product.');

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertSame(1, ProductImage::query()->where('path', $media->file_path)->count());
    }

    public function test_once_unlinked_from_every_product_the_image_can_be_deleted(): void
    {
        $media = $this->library($this->vendor);
        $first = $this->product->images()->create(['path' => $media->file_path, 'sort_order' => 0, 'is_primary' => true]);
        $second = $this->product($this->vendor)->images()->create(['path' => $media->file_path, 'sort_order' => 0, 'is_primary' => true]);

        $this->remove($media)->assertStatus(409);
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $first]))->assertOk();
        $this->remove($media)->assertStatus(409, 'Still used by the second product.');
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$second->product, $second]))->assertOk();

        $this->remove($media)->assertOk();
        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    public function test_legacy_product_images_and_other_library_images_are_left_untouched(): void
    {
        Storage::disk('public')->put('vendors/products/1/9/old.png', 'legacy');
        $legacy = $this->product->images()->create(['path' => 'vendors/products/1/9/old.png', 'sort_order' => 0, 'is_primary' => true]);
        $kept = $this->library($this->vendor);
        $removed = $this->library($this->vendor);

        $this->remove($removed)->assertOk();

        $this->assertModelExists($legacy);
        $this->assertModelExists($kept);
        Storage::disk('public')->assertExists('vendors/products/1/9/old.png');
        Storage::disk('public')->assertExists($kept->file_path);
    }

    public function test_a_rejected_deletion_never_touches_the_disk_or_the_row(): void
    {
        $media = $this->library($this->vendor);
        $this->product->images()->create(['path' => $media->file_path, 'sort_order' => 0, 'is_primary' => true]);
        $before = $media->fresh()->toArray();

        $this->remove($media)->assertStatus(409);
        $this->actingAs(Vendor::factory()->approved()->create(), 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media))->assertNotFound();

        $this->assertSame($before, $media->fresh()->toArray());
        $this->assertSame('image', Storage::disk('public')->get($media->file_path));
    }

    public function test_the_deletion_runs_in_one_transaction_and_locks_the_media_row(): void
    {
        $media = $this->library($this->vendor);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->remove($media)->assertOk();

        $this->assertNotEmpty(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "media"') && str_contains($sql, '"id" = ?')));
        $this->assertNotEmpty(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'delete from "media"')));
        $source = file_get_contents(app_path('Http/Controllers/VendorDashboard/MediaLibraryController.php'));
        $this->assertMatchesRegularExpression('/DB::transaction\(function \(\) use \(\$media, \$prefix\): array \{\s*\$locked = Media::query\(\)->whereKey\(\$media->getKey\(\)\)->lockForUpdate\(\)->first\(\);/', $source);
        $this->assertMatchesRegularExpression('/->sharedLock\(\)->exists\(\), 409/', $source);
    }

    // ---------------------------------------------------------------- attachment after a deletion

    public function test_an_image_deleted_after_validation_is_refused_when_added_to_a_product(): void
    {
        $media = $this->library($this->vendor);
        $this->deleteAfterValidation(StoreProductImageRequest::class, $media);

        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $media->file_path])
            ->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);

        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_an_image_deleted_after_validation_is_refused_as_a_replacement(): void
    {
        $image = $this->product->images()->create(['path' => $this->library($this->vendor)->file_path, 'sort_order' => 0, 'is_primary' => true]);
        $next = $this->library($this->vendor);
        $this->deleteAfterValidation(UpdateProductImageRequest::class, $next);

        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $image]), ['path' => $next->file_path])
            ->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);

        $this->assertNotSame($next->file_path, $image->fresh()->path);
    }

    public function test_an_unchanged_legacy_path_is_not_rechecked_against_the_library(): void
    {
        $legacy = $this->product->images()->create(['path' => 'products/old/jug.png', 'sort_order' => 0, 'is_primary' => true]);

        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $legacy]), ['path' => 'products/old/jug.png', 'alt_text' => 'Old jug'])
            ->assertOk()->assertJsonPath('image.alt_text', 'Old jug');
    }

    public function test_an_image_deleted_after_validation_refuses_the_new_product_and_creates_nothing(): void
    {
        $kept = $this->library($this->vendor);
        $gone = $this->library($this->vendor);
        $products = Product::query()->count();
        $this->deleteAfterValidation(StoreProductRequest::class, $gone);

        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'category_id' => $this->activeCategoryId(), 'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2, 'images' => [['path' => $kept->file_path], ['path' => $gone->file_path]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['images.1.path' => VendorLibraryImage::MESSAGE]);

        $this->assertSame($products, Product::query()->count(), 'The whole creation rolled back.');
        $this->assertSame(0, ProductImage::query()->count());
    }

    // ---------------------------------------------------------------- helpers

    /** Deletes the media row (as a concurrent library deletion would) right after the form request has validated. */
    private function deleteAfterValidation(string $requestClass, Media $media): void
    {
        $this->app->afterResolving($requestClass, function () use ($media) {
            Media::query()->whereKey($media->getKey())->delete();
            Storage::disk('public')->delete($media->file_path);
        });
    }

    private function remove(Media $media): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media));
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    private function library(Vendor $vendor): Media
    {
        return $this->media("vendors/media/{$vendor->id}/".uniqid('', true).'.png', $vendor->id);
    }

    private function media(string $path, ?int $uploader = null): Media
    {
        Storage::disk('public')->put($path, 'image');

        return Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $uploader,
        ]);
    }
}
