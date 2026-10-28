<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-B (V1): removing an image from a vendor's product removes the association only. No
 * file is ever deleted by that endpoint, whatever the stored path says: library images, the product's own uploads,
 * and paths with ".." segments that used to reach other vendors' and the admin's files. Ownership checks (404 for
 * another vendor's product or another product's image) are unchanged, and only the requested row is removed.
 */
class VendorProductImageDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Vendor $other;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->other = Vendor::factory()->approved()->create();
        $this->product = $this->product($this->vendor);
    }

    public function test_removing_a_library_image_keeps_the_file_the_media_row_and_other_uses(): void
    {
        $media = $this->libraryImage($this->vendor);
        $second = $this->product($this->vendor);
        $here = $this->attach($this->product, $media->file_path, true);
        $there = $this->attach($second, $media->file_path, true);

        $this->remove($this->product, $here)->assertOk();

        $this->assertModelMissing($here);
        $this->assertModelExists($there);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_removing_an_uploaded_product_image_keeps_its_file(): void
    {
        // A legacy product upload, as the former direct upload endpoint (removed in G7-C2-4) stored it.
        $path = "vendors/products/{$this->vendor->id}/{$this->product->id}/".Str::uuid().'.png';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('jug.png', 40, 40)->getContent());
        $image = $this->attach($this->product, $path, true);

        $this->remove($this->product, $image)->assertOk();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_traversal_paths_never_delete_other_vendors_or_admin_files(): void
    {
        $theirs = $this->libraryImage($this->other);
        $adminPath = 'media/2026/10/'.uniqid('', true).'.png';
        Storage::disk('public')->put($adminPath, 'admin');
        $own = "vendors/products/{$this->vendor->id}/{$this->product->id}";

        foreach ([
            "{$own}/../../../media/{$this->other->id}/".basename($theirs->file_path),
            "{$own}/../../../../{$adminPath}",
            "{$own}/./../../../../{$adminPath}",
            "{$own}/..\\..\\..\\..\\{$adminPath}",
        ] as $path) {
            // Attached the way an attacker could, through the vendor's own API.
            $id = $this->actingAs($this->vendor, 'vendor')
                ->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $path])
                ->json('image.id');
            $image = $id ? ProductImage::query()->findOrFail($id) : $this->attach($this->product, $path);

            $this->remove($this->product, $image)->assertOk();
            $this->assertModelMissing($image);
        }

        Storage::disk('public')->assertExists($theirs->file_path);
        Storage::disk('public')->assertExists($adminPath);
        $this->assertModelExists($theirs);
    }

    public function test_a_traversal_path_given_at_product_creation_deletes_nothing_either(): void
    {
        $theirs = $this->libraryImage($this->other);
        // The id the new product would get, so the path starts with that product's own upload folder.
        $next = (int) Product::query()->max('id') + 1;
        $traversal = "vendors/products/{$this->vendor->id}/{$next}/../../../media/{$this->other->id}/".basename($theirs->file_path);
        $count = Product::query()->count();

        // G7-C2-1 (V2): such a path is no longer accepted at creation.
        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2, 'images' => [['path' => $traversal]],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0.path');
        $this->assertSame($count, Product::query()->count());

        // One stored earlier (legacy data) is still only unlinked.
        $product = $this->product($this->vendor);
        $this->remove($product, $this->attach($product, $traversal, true))->assertOk();

        Storage::disk('public')->assertExists($theirs->file_path);
    }

    public function test_another_vendor_cannot_remove_an_image(): void
    {
        $media = $this->libraryImage($this->vendor);
        $image = $this->attach($this->product, $media->file_path, true);
        $theirs = $this->product($this->other);

        // Another vendor, on this product.
        $this->actingAs($this->other, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $image]))->assertNotFound();
        // This image through another product (the other vendor's).
        $this->actingAs($this->other, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$theirs, $image]))->assertNotFound();
        // The owner, through another of their products the image does not belong to.
        $this->remove($this->product($this->vendor), $image)->assertNotFound();

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_only_the_requested_row_is_removed_and_the_main_image_moves_on(): void
    {
        $paths = array_map(fn () => $this->libraryImage($this->vendor)->file_path, range(1, 3));
        $first = $this->attach($this->product, $paths[0], true, 0);
        $second = $this->attach($this->product, $paths[1], false, 1);
        $third = $this->attach($this->product, $paths[2], false, 2);
        $elsewhere = $this->attach($this->product($this->vendor), $paths[0], true, 0);

        $this->remove($this->product, $first)->assertOk()->assertJsonPath('product.images.0.id', $second->id);

        $this->assertModelMissing($first);
        $this->assertSame([$second->id, $third->id], $this->product->images()->pluck('id')->all());
        $this->assertTrue($second->fresh()->is_primary, 'The next image becomes the main one, as before.');
        $this->assertModelExists($elsewhere);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_the_deletion_endpoint_holds_no_file_deletion(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/VendorDashboard/ProductImageController.php'));

        $this->assertDoesNotMatchRegularExpression('/->delete\(\s*\$path|deleteOwnedUpload|unlink\(|deleteDirectory/', $source);
    }

    // ---------------------------------------------------------------- helpers

    private function remove(Product $product, ProductImage $image): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$product, $image]));
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    private function attach(Product $product, string $path, bool $primary = false, int $order = 0): ProductImage
    {
        return $product->images()->create(['path' => $path, 'alt_text' => 'Jug', 'sort_order' => $order, 'is_primary' => $primary]);
    }

    private function libraryImage(Vendor $vendor): Media
    {
        $path = "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'image');

        return Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);
    }
}
