<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-1 (V2): a vendor attaches only images of their own media library, on product
 * creation (images[].path), on adding an image and on changing an image's path. The path must be exactly the
 * file_path of an image media row in the canonical vendors/media/{vendorId}/ folder; ownership never comes from
 * uploader_id or from the request. An unchanged legacy path stays accepted, removing never deletes a file, the 404
 * ownership checks come first, and a refused request changes nothing.
 */
class VendorProductImageOwnershipTest extends TestCase
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

    // ---------------------------------------------------------------- accepted

    public function test_a_vendor_can_attach_an_image_from_their_own_library(): void
    {
        $media = $this->libraryImage($this->vendor);

        $this->addImage($this->product, $media->file_path)->assertCreated()->assertJsonPath('image.path', $media->file_path);

        $this->assertSame([$media->file_path], $this->product->images()->pluck('path')->all());
    }

    public function test_the_same_library_image_can_be_reused_across_the_vendors_products(): void
    {
        $media = $this->libraryImage($this->vendor);
        $second = $this->product($this->vendor);

        $this->addImage($this->product, $media->file_path)->assertCreated();
        $this->addImage($second, $media->file_path)->assertCreated();
        $created = $this->createProduct([['path' => $media->file_path, 'is_primary' => true]])->assertCreated();

        $this->assertSame(3, ProductImage::query()->where('path', $media->file_path)->count());
        $this->assertSame($media->file_path, $created->json('product.images.0.path'));
    }

    public function test_surrounding_whitespace_is_trimmed_and_only_the_exact_library_path_is_stored(): void
    {
        // TrimStrings removes it (spaces, tabs, a trailing NUL) before validation; what is checked is what is stored.
        $media = $this->libraryImage($this->vendor);

        foreach (["  {$media->file_path}  ", "{$media->file_path}\0", "\t{$media->file_path}\n"] as $padded) {
            $this->addImage($this->product, $padded)->assertCreated()->assertJsonPath('image.path', $media->file_path);
        }

        $this->assertSame(array_fill(0, 3, $media->file_path), $this->product->images()->pluck('path')->all());
    }

    public function test_product_creation_accepts_several_own_library_images(): void
    {
        $paths = array_map(fn () => $this->libraryImage($this->vendor)->file_path, range(1, 3));

        $response = $this->createProduct(array_map(fn ($path) => ['path' => $path], $paths))->assertCreated();

        $this->assertSame($paths, Product::query()->findOrFail($response->json('product.id'))->images()->pluck('path')->all());
    }

    // ---------------------------------------------------------------- refused

    public function test_paths_outside_the_vendors_library_are_refused_everywhere_and_change_nothing(): void
    {
        $legacy = $this->product->images()->create(['path' => 'products/old/jug.png', 'alt_text' => 'Old', 'sort_order' => 0, 'is_primary' => true]);
        $before = $this->snapshot();

        foreach ($this->refusedPaths() as $label => $path) {
            $this->addImage($this->product, $path)->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);
            $this->createProduct([['path' => $path]])->assertUnprocessable()->assertJsonValidationErrors(['images.0.path' => VendorLibraryImage::MESSAGE]);
            $this->updateImage($this->product, $legacy, ['path' => $path])->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);

            $this->assertSame($before, $this->snapshot(), "{$label}: nothing may change.");
        }
    }

    public function test_the_refusal_names_no_other_vendor_and_no_file(): void
    {
        $theirs = $this->libraryImage($this->other);

        $response = $this->addImage($this->product, $theirs->file_path)->assertUnprocessable();

        $this->assertSame(['path' => [VendorLibraryImage::MESSAGE]], $response->json('errors'));
        foreach ([$this->other->name, $this->other->email, basename($theirs->file_path), "media/{$this->other->id}/"] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_product_creation_validates_every_image(): void
    {
        $own = $this->libraryImage($this->vendor);
        $theirs = $this->libraryImage($this->other);
        $products = Product::query()->count();

        $this->createProduct([['path' => $own->file_path], ['path' => $theirs->file_path], ['path' => $own->file_path]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.1.path'])
            ->assertJsonMissingValidationErrors(['images.0.path', 'images.2.path']);

        $this->assertSame($products, Product::query()->count());
        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_uploader_id_never_grants_ownership(): void
    {
        // An admin image uploaded by an admin whose id equals the vendor's, and another vendor's image row whose
        // uploader_id says this vendor: neither is in this vendor's folder.
        $admin = $this->media('media/2026/10/banner.png', $this->vendor->id);
        $mislabelled = $this->media("vendors/media/{$this->other->id}/mislabelled.png", $this->vendor->id);

        foreach ([$admin, $mislabelled] as $media) {
            $this->addImage($this->product, $media->file_path)->assertUnprocessable()->assertJsonValidationErrors('path');
        }
        $this->assertSame(0, ProductImage::query()->count());
    }

    // ---------------------------------------------------------------- update and legacy images

    public function test_changing_an_image_path_needs_an_own_library_image(): void
    {
        $image = $this->attach($this->product, $this->libraryImage($this->vendor)->file_path);
        $next = $this->libraryImage($this->vendor);
        $theirs = $this->libraryImage($this->other);

        $this->updateImage($this->product, $image, ['path' => $theirs->file_path])->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->assertSame($image->path, $image->fresh()->path);

        $this->updateImage($this->product, $image, ['path' => $next->file_path])->assertOk()->assertJsonPath('image.path', $next->file_path);
        $this->assertSame($next->file_path, $image->fresh()->path);
    }

    public function test_an_unchanged_legacy_path_stays_accepted_on_update(): void
    {
        $legacy = $this->attach($this->product, 'products/old/jug.png', false, 1);
        $this->attach($this->product, $this->libraryImage($this->vendor)->file_path, true, 0);

        $this->updateImage($this->product, $legacy, ['path' => 'products/old/jug.png', 'alt_text' => 'Old jug', 'is_primary' => true, 'sort_order' => 0])
            ->assertOk()
            ->assertJsonPath('image.path', 'products/old/jug.png')
            ->assertJsonPath('image.alt_text', 'Old jug');

        $this->assertTrue($legacy->fresh()->is_primary);
        $this->assertSame(1, $this->product->images()->where('is_primary', true)->count());
    }

    public function test_a_legacy_path_cannot_be_moved_to_another_legacy_path_or_borrowed_by_another_image(): void
    {
        $legacy = $this->attach($this->product, 'products/old/jug.png');
        $other = $this->attach($this->product, $this->libraryImage($this->vendor)->file_path, false, 1);

        // Another non-library path for the legacy image, and the legacy path for an image it does not belong to.
        $this->updateImage($this->product, $legacy, ['path' => 'products/old/vase.png'])->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->updateImage($this->product, $other, ['path' => 'products/old/jug.png'])->assertUnprocessable()->assertJsonValidationErrors('path');
        // Nor can it be added again.
        $this->addImage($this->product, 'products/old/jug.png')->assertUnprocessable()->assertJsonValidationErrors('path');

        $this->assertSame(['products/old/jug.png', $other->path], $this->product->images()->pluck('path')->all());
    }

    public function test_a_legacy_image_is_still_managed_and_removing_it_keeps_its_file(): void
    {
        Storage::disk('public')->put('products/old/jug.png', 'legacy');
        $legacy = $this->attach($this->product, 'products/old/jug.png', false, 1);
        $own = $this->attach($this->product, $this->libraryImage($this->vendor)->file_path, true, 0);

        // Without a path, an update never touches it.
        $this->updateImage($this->product, $legacy, ['alt_text' => 'Renamed', 'is_primary' => true, 'sort_order' => 0])->assertOk();
        $this->assertSame('products/old/jug.png', $legacy->fresh()->path);
        $this->assertTrue($legacy->fresh()->is_primary);
        $this->assertFalse($own->fresh()->is_primary);

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $legacy]))->assertOk();
        $this->assertModelMissing($legacy);
        Storage::disk('public')->assertExists('products/old/jug.png');
    }

    // ---------------------------------------------------------------- 404 first

    public function test_the_404_ownership_checks_come_before_validation(): void
    {
        $theirs = $this->libraryImage($this->other);
        $mine = $this->libraryImage($this->vendor);
        $image = $this->attach($this->product, $mine->file_path, true);
        $theirProduct = $this->product($this->other);
        $before = $this->snapshot();

        foreach ([$theirs->file_path, 'https://example.test/x.png', $mine->file_path] as $path) {
            // Another vendor on this vendor's product: 404 whatever the path, even one of their own library images.
            $this->actingAs($this->other, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $path])->assertNotFound();
            $this->actingAs($this->other, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $image]), ['path' => $path])->assertNotFound();
            // This vendor's image through another vendor's product, and through another of their own products.
            $this->updateImage($theirProduct, $image, ['path' => $path])->assertNotFound();
            $this->updateImage($this->product($this->vendor), $image, ['path' => $path])->assertNotFound();
        }

        $this->assertSame($before['images'], $this->snapshot()['images']);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, string> */
    private function refusedPaths(): array
    {
        $own = $this->libraryImage($this->vendor);
        $theirs = $this->libraryImage($this->other);
        $admin = $this->media('media/2026/10/admin.png', null);
        $document = $this->media("vendors/media/{$this->vendor->id}/guide.pdf", $this->vendor->id, ['file_type' => 'document', 'mime_type' => 'application/pdf']);
        $private = $this->media("vendors/media/{$this->vendor->id}/private.png", $this->vendor->id, ['disk' => 'local']);
        $v = $this->vendor->id;
        $o = $this->other->id;
        $file = basename($own->file_path);

        return [
            'another vendor\'s image' => $theirs->file_path,
            'an admin image' => $admin->file_path,
            'a file in the folder without a media row' => "vendors/media/{$v}/missing.png",
            'the folder itself' => "vendors/media/{$v}/",
            'a document of the library' => $document->file_path,
            'an image on another disk' => $private->file_path,
            'an external URL' => 'https://cdn.example.test/'.$own->file_path,
            'a protocol-relative URL' => '//cdn.example.test/'.$own->file_path,
            'a storage URL' => '/storage/'.$own->file_path,
            'an absolute path' => '/'.$own->file_path,
            'a Windows absolute path' => 'C:\\'.str_replace('/', '\\', $own->file_path),
            'backslashes' => str_replace('/', '\\', $own->file_path),
            'a traversal into the own folder' => "vendors/media/{$o}/../{$v}/{$file}",
            'a traversal out of the own folder' => "vendors/media/{$v}/../{$o}/".basename($theirs->file_path),
            'a dot segment' => "vendors/media/{$v}/./{$file}",
            'a doubled slash' => "vendors/media/{$v}//{$file}",
            'another case' => strtoupper($own->file_path),
            'another case for the file only' => "vendors/media/{$v}/".strtoupper($file),
            'a look-alike folder' => "vendors/media-old/{$v}/{$file}",
            'a padded vendor id' => "vendors/media/0{$v}/{$file}",
            'a suffix' => $own->file_path.'.png',
            'a query string' => $own->file_path.'?v=1',
            'a null byte' => "vendors/media/{$v}/\0{$file}",
        ];
    }

    private function addImage(Product $product, string $path): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path]);
    }

    private function updateImage(Product $product, ProductImage $image, array $data): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$product, $image]), $data);
    }

    private function createProduct(array $images): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2, 'images' => $images,
        ]);
    }

    /** @return array{products: array<int, mixed>, images: array<int, mixed>} */
    private function snapshot(): array
    {
        return [
            'products' => Product::query()->orderBy('id')->get()->toArray(),
            'images' => ProductImage::query()->orderBy('id')->get()->toArray(),
        ];
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
        return $this->media("vendors/media/{$vendor->id}/".uniqid('', true).'.png', $vendor->id);
    }

    private function media(string $path, ?int $uploader, array $attributes = []): Media
    {
        Storage::disk('public')->put($path, 'image');

        return Media::create($attributes + [
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $uploader,
        ]);
    }
}
