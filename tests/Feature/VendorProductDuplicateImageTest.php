<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VPM-01C: a vendor product takes each library image (exact path) at most once. A new product with the same path twice
 * is refused (422, images.N.path); adding a path the product has, or replacing an image with a path another image of
 * the product has, is refused (409, errors.path) inside the transaction under the product lock, so nothing is written.
 * Keeping a slot's own path stays allowed. Historical duplicates are kept: never removed, merged or reordered, and they
 * never block other changes. There is no database unique constraint.
 */
class VendorProductDuplicateImageTest extends TestCase
{
    use RefreshDatabase;

    private const CONFLICT = ['message' => ProductImageController::DUPLICATE_MESSAGE, 'errors' => ['path' => [ProductImageController::DUPLICATE_MESSAGE]]];

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

    public function test_creating_a_product_with_the_same_image_twice_is_refused_and_nothing_is_written(): void
    {
        $a = $this->library();
        $b = $this->library();
        $products = Product::query()->count();

        $this->create([['path' => $a], ['path' => $b], ['path' => $a]])
            ->assertUnprocessable()
            // distinct marks every occurrence of the repeated path; the different one is fine.
            ->assertJsonValidationErrors(['images.0.path' => ProductImageController::DUPLICATE_MESSAGE, 'images.2.path' => ProductImageController::DUPLICATE_MESSAGE])
            ->assertJsonMissingValidationErrors(['images.1.path']);

        $this->assertSame($products, Product::query()->count());
        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_creating_a_product_with_different_images_still_works(): void
    {
        $paths = [$this->library(), $this->library(), $this->library()];

        $response = $this->create(array_map(fn ($path) => ['path' => $path], $paths))->assertCreated();

        $this->assertSame($paths, array_column($response->json('product.images'), 'path'));
    }

    // ---------------------------------------------------------------- adding

    public function test_adding_an_image_the_product_has_is_a_409_and_nothing_is_written(): void
    {
        $ids = $this->fill(2);
        $before = $this->rows();
        $path = ProductImage::find($ids[1])->path;

        $this->add($path)->assertStatus(409)->assertExactJson(self::CONFLICT);
        $this->add($path, true)->assertStatus(409);

        $this->assertSame($before, $this->rows(), 'No row added, none changed (main image and order kept).');
    }

    public function test_the_same_image_may_still_go_on_another_product_of_the_vendor(): void
    {
        $path = $this->library();
        $this->add($path)->assertCreated();

        $this->add($path, false, $this->product($this->vendor))->assertCreated();
        $this->assertSame(2, ProductImage::query()->where('path', $path)->count());
    }

    // ---------------------------------------------------------------- replacing

    public function test_replacing_with_another_slots_image_is_a_409_and_the_gallery_is_kept(): void
    {
        $ids = $this->fill(3);
        $before = $this->rows();

        $this->replace($ids[2], ProductImage::find($ids[0])->path)->assertStatus(409)->assertExactJson(self::CONFLICT);
        $this->replace($ids[0], ProductImage::find($ids[1])->path)->assertStatus(409);

        $this->assertSame($before, $this->rows());
    }

    public function test_replacing_a_slot_with_its_own_image_is_allowed_and_changes_nothing_else(): void
    {
        $ids = $this->fill(3);
        $own = ProductImage::find($ids[1])->path;

        $response = $this->replace($ids[1], $own, 'Clay jug')->assertOk();

        $this->assertSame($ids, array_column($response->json('product.images'), 'id'));
        $this->assertSame($own, ProductImage::find($ids[1])->path);
        $this->assertSame('Clay jug', ProductImage::find($ids[1])->alt_text);
        $this->assertSame(3, $this->product->images()->count());
    }

    public function test_replacing_with_a_free_image_still_works(): void
    {
        $ids = $this->fill(3);
        $free = $this->library();

        $this->replace($ids[2], $free)->assertOk()->assertJsonPath('product.images.2.path', $free);
        $this->assertSame($ids, $this->product->images()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- historical duplicates

    public function test_historical_duplicates_are_kept_readable_and_editable(): void
    {
        $a = $this->library();
        $b = $this->library();
        $first = $this->product->images()->create(['path' => $a, 'sort_order' => 0, 'is_primary' => true])->id;
        $second = $this->product->images()->create(['path' => $a, 'sort_order' => 1, 'is_primary' => false])->id;
        $third = $this->product->images()->create(['path' => $b, 'sort_order' => 2, 'is_primary' => false])->id;
        $this->actingAs($this->vendor, 'vendor');

        // Readable as they are, and the product's other fields can be saved.
        $this->getJson(route('vendor.dashboard.products.show', $this->product))->assertOk()
            ->assertJsonPath('product.images.0.path', $a)->assertJsonPath('product.images.1.path', $a);
        $this->putJson(route('vendor.dashboard.products.update', $this->product), ['name' => 'Jug renamed', 'price' => 12, 'stock_quantity' => 3])->assertOk();

        // The duplicate rows stay manageable: alt text, its own path, the main image.
        $this->replace($second, $a, 'Second view')->assertOk();
        $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $second]), ['alt_text' => 'Side'])->assertOk();
        $this->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $second]), ['is_primary' => true])->assertOk()
            ->assertJsonPath('product.images.0.id', $second);

        // No new duplicate: adding A again, or putting A on B's slot.
        $this->add($a)->assertStatus(409);
        $this->replace($third, $a)->assertStatus(409);
        $this->assertSame([$second, $first, $third], $this->product->images()->pluck('id')->all(), 'Ids and order are kept; nothing merged or removed.');
        $this->assertSame([$a, $a, $b], $this->product->images()->pluck('path')->all());

        // A free image can still be added, and a duplicate row removed like any other.
        $this->add($this->library())->assertCreated();
        $this->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $first]))->assertOk();
        $this->assertSame(1, $this->product->images()->where('path', $a)->count());
    }

    // ---------------------------------------------------------------- concurrency

    public function test_an_attachment_committed_between_validation_and_the_transaction_is_seen_under_the_lock(): void
    {
        // The same path attached by a concurrent request after this one validated: the locked check sees it.
        $path = $this->library();
        $this->afterValidation(StoreProductImageRequest::class, fn () => $this->product->images()->create(['path' => $path, 'sort_order' => 0, 'is_primary' => true]));

        $this->add($path)->assertStatus(409)->assertExactJson(self::CONFLICT);
        $this->assertSame(1, ProductImage::query()->where('path', $path)->count());
    }

    public function test_a_replacement_racing_an_attachment_of_the_same_path_is_refused(): void
    {
        $ids = $this->fill(2);
        $path = $this->library();
        $this->afterValidation(UpdateProductImageRequest::class, fn () => $this->product->images()->create(['path' => $path, 'sort_order' => 5, 'is_primary' => false]));

        $this->replace($ids[1], $path)->assertStatus(409);
        $this->assertNotSame($path, ProductImage::find($ids[1])->path);
        $this->assertSame(1, ProductImage::query()->where('path', $path)->count());
    }

    public function test_the_duplicate_check_runs_under_the_product_lock_in_every_attaching_path(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/VendorDashboard/ProductImageController.php'));

        $this->assertMatchesRegularExpression('/\$locked = \$this->lockProduct\(\$product\);.*?ensureStillInLibrary\(\$locked, \$data\[\'path\'\], \'path\'\);\s*\/\/[^\n]*\n\s*\$this->ensureNotAttached\(\$locked, \$data\[\'path\'\]\);\s*\$image = \$locked->images\(\)->create/s', $source, 'store: lock, library, duplicate, then write.');
        $this->assertMatchesRegularExpression('/\$data\[\'path\'\] !== \$image->path\) \{\s*\$this->ensureStillInLibrary\(\$locked, \$data\[\'path\'\], \'path\'\);\s*\/\/[^\n]*\n\s*\$this->ensureNotAttached\(\$locked, \$data\[\'path\'\], \$image->id\);/', $source, 'update: only a changed path, under the lock.');
    }

    // ---------------------------------------------------------------- ownership, limit, schema

    public function test_ownership_is_checked_before_and_independently_of_duplicates(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->library($other);
        $theirProduct = $this->product($other);
        $theirProduct->images()->create(['path' => $theirs, 'sort_order' => 0, 'is_primary' => true]);
        $mine = $this->library();
        $this->product->images()->create(['path' => $mine, 'sort_order' => 0, 'is_primary' => true]);

        // Another vendor's image: not in the library (422 path), never a 409 that would reveal anything.
        $this->add($theirs)->assertUnprocessable()->assertJsonValidationErrors(['path' => VendorLibraryImage::MESSAGE]);
        // Another vendor's product: 404, whatever the path.
        $this->add($mine, false, $theirProduct)->assertNotFound();
        $this->assertSame(1, $theirProduct->images()->count());
    }

    public function test_the_six_image_limit_comes_first_and_is_unchanged(): void
    {
        $ids = $this->fill(6);

        $this->add(ProductImage::find($ids[0])->path)->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->add($this->library())->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->replace($ids[5], ProductImage::find($ids[0])->path)->assertStatus(409);
        $this->replace($ids[5], $this->library())->assertOk();
        $this->assertSame(6, $this->product->images()->count());
    }

    public function test_there_is_no_database_unique_constraint_on_product_image_paths(): void
    {
        foreach (Schema::getIndexes('product_images') as $index) {
            $this->assertFalse($index['unique'] && in_array('path', $index['columns'], true), 'Historical duplicates must remain storable.');
        }
    }

    // ---------------------------------------------------------------- helpers

    private function create(array $images): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'name' => 'Jug', 'price' => 10, 'stock_quantity' => 2, 'images' => $images,
        ]);
    }

    private function add(string $path, bool $primary = false, ?Product $product = null): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $product ?? $this->product), ['path' => $path, 'alt_text' => null, 'is_primary' => $primary]);
    }

    private function replace(int $imageId, string $path, ?string $alt = null): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $imageId]), ['path' => $path, 'alt_text' => $alt]);
    }

    /** Runs $callback right after the form request validated, as a concurrent request committing then would. */
    private function afterValidation(string $requestClass, callable $callback): void
    {
        $this->app->afterResolving($requestClass, fn () => $callback());
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return ProductImage::query()->orderBy('id')->get(['id', 'product_id', 'path', 'sort_order', 'is_primary'])->toArray();
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
