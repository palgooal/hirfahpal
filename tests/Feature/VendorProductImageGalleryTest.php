<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Support\Products\ProductGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-3 (V4): after every successful vendor image change the gallery has one shape:
 * sort_order 0..n-1, exactly one main image and it is the first, none when empty. Adding appends (or puts first when
 * sent as the main image), choosing a main image moves it first, removing closes the gap and hands a removed main
 * image over to the first remaining one. Nothing is lost, duplicated or re-pathed, no file is deleted, and a refused
 * request changes nothing. Concurrency is checked on MySQL separately: SQLite, used here, ignores lockForUpdate().
 */
class VendorProductImageGalleryTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = $this->product();
    }

    // ---------------------------------------------------------------- adding

    public function test_the_first_image_becomes_the_main_one(): void
    {
        $a = $this->add($this->path(), ['is_primary' => false])->assertCreated()->json('image.id');

        $this->assertGallery([$a]);
    }

    public function test_adding_appends_and_keeps_the_main_image_whatever_sort_order_is_sent(): void
    {
        $a = $this->add($this->path())->json('image.id');
        $b = $this->add($this->path(), ['sort_order' => 0])->json('image.id');
        $c = $this->add($this->path(), ['sort_order' => 99, 'is_primary' => false])->assertCreated()->json('image.id');

        $this->assertGallery([$a, $b, $c]);
    }

    public function test_adding_a_main_image_puts_it_first(): void
    {
        [$a, $b] = [$this->add($this->path())->json('image.id'), $this->add($this->path())->json('image.id')];

        $response = $this->add($this->path(), ['is_primary' => true])->assertCreated();
        $c = $response->json('image.id');

        $this->assertGallery([$c, $a, $b]);
        $this->assertTrue($response->json('image.is_primary'));
        $this->assertSame([$c, $a, $b], array_column($response->json('product.images'), 'id'), 'The response shows the new order.');
    }

    // ---------------------------------------------------------------- choosing the main image

    public function test_choosing_another_main_image_moves_it_first_and_keeps_the_others_in_order(): void
    {
        [$a, $b, $c, $d] = $this->seedGallery(4);

        $response = $this->patchImage($d, ['is_primary' => true, 'sort_order' => 3])->assertOk();
        $this->assertGallery([$d, $a, $b, $c]);
        $this->assertSame(0, $response->json('image.sort_order'));

        $this->patchImage($b, ['is_primary' => true])->assertOk();
        $this->assertGallery([$b, $d, $a, $c]);
    }

    public function test_editing_alt_text_or_path_never_moves_or_demotes_an_image(): void
    {
        [$a, $b, $c] = $this->seedGallery(3);

        $this->patchImage($a, ['alt_text' => 'Main jug'])->assertOk();
        $this->patchImage($c, ['path' => $this->path(), 'alt_text' => 'Third'])->assertOk();
        // Unchecking the main image without choosing another: it stays the main, first image.
        $this->patchImage($a, ['is_primary' => false])->assertOk();

        $this->assertGallery([$a, $b, $c]);
        $this->assertSame('Main jug', ProductImage::find($a)->alt_text);
    }

    // ---------------------------------------------------------------- removing

    public function test_removing_the_main_image_promotes_the_next_and_closes_the_gap(): void
    {
        [$a, $b, $c] = $this->seedGallery(3);

        $this->remove($a)->assertOk()->assertJsonPath('product.images.0.id', $b);

        $this->assertGallery([$b, $c]);
    }

    public function test_removing_another_image_keeps_the_main_one_and_closes_the_gap(): void
    {
        [$a, $b, $c, $d] = $this->seedGallery(4);

        $this->remove($b)->assertOk();
        $this->assertGallery([$a, $c, $d]);
        $this->remove($d)->assertOk();
        $this->assertGallery([$a, $c]);
    }

    public function test_removing_the_last_image_leaves_an_empty_gallery_and_every_file(): void
    {
        $ids = $this->seedGallery(2);
        $paths = ProductImage::query()->whereIn('id', $ids)->pluck('path')->all();

        foreach ($ids as $id) {
            $this->remove($id)->assertOk();
        }

        $this->assertGallery([]);
        $this->assertNull($this->product->fresh()->primaryImage);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    // ---------------------------------------------------------------- every mutation, and legacy data

    public function test_every_mutation_leaves_consecutive_order_and_one_main_image(): void
    {
        $ids = [];
        foreach (range(1, 6) as $i) {
            $ids[] = $this->add($this->path(), ['is_primary' => $i % 3 === 0])->assertCreated()->json('image.id');
            $this->assertInvariants();
        }

        foreach ([[$ids[4], true], [$ids[1], true], [$ids[0], false]] as [$id, $primary]) {
            $this->patchImage($id, ['is_primary' => $primary])->assertOk();
            $this->assertInvariants();
        }

        foreach ([$ids[1], $ids[5], $ids[3]] as $id) {
            $this->remove($id)->assertOk();
            $this->assertInvariants();
        }

        $this->assertCount(3, $this->product->images()->get());
    }

    public function test_a_messy_legacy_gallery_is_normalised_on_its_next_change_without_losing_anything(): void
    {
        // Duplicate and gapped sort_order, two images marked main, the main one not first, a legacy path.
        $rows = [
            ['path' => 'products/old/a.png', 'sort_order' => 5, 'is_primary' => false],
            ['path' => $this->path(), 'sort_order' => 2, 'is_primary' => true],
            ['path' => $this->path(), 'sort_order' => 2, 'is_primary' => false],
            ['path' => $this->path(), 'sort_order' => 9, 'is_primary' => true],
        ];
        $ids = array_map(fn ($row) => $this->product->images()->create($row)->id, $rows);

        // Only an alt text changes; the gallery comes out consistent and complete.
        $this->patchImage($ids[0], ['alt_text' => 'Old'])->assertOk();

        // Order before: [1 (2, main), 2 (2), 3 (9, main), 0 (5)] by sort_order then id; the first marked main stays main.
        $this->assertGallery([$ids[1], $ids[2], $ids[0], $ids[3]]);
        $this->assertSame('products/old/a.png', ProductImage::find($ids[0])->path);
    }

    public function test_legacy_images_stay_manageable_and_seven_are_never_trimmed(): void
    {
        $ids = $this->seedGallery(6);
        $ids[] = $this->product->images()->create(['path' => 'products/old/jug.png', 'sort_order' => 6, 'is_primary' => false])->id;
        Storage::disk('public')->put('products/old/jug.png', 'legacy');

        // Alt text, then main image: the legacy image moves first, all seven kept.
        $this->patchImage($ids[6], ['alt_text' => 'Old jug'])->assertOk();
        $this->assertGallery($ids);
        $this->patchImage($ids[6], ['is_primary' => true])->assertOk();
        $this->assertGallery([$ids[6], ...array_slice($ids, 0, 6)]);
        $this->assertSame('products/old/jug.png', ProductImage::find($ids[6])->path);

        // Still full; removing it keeps its file and the six others in order.
        $this->add($this->path())->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->remove($ids[6])->assertOk();
        $this->assertGallery(array_slice($ids, 0, 6));
        Storage::disk('public')->assertExists('products/old/jug.png');
    }

    public function test_product_creation_puts_the_chosen_main_image_first_in_the_sent_order(): void
    {
        $paths = [$this->path(), $this->path(), $this->path()];

        $response = $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2,
            'images' => [['path' => $paths[0], 'sort_order' => 0], ['path' => $paths[1], 'sort_order' => 1], ['path' => $paths[2], 'sort_order' => 2, 'is_primary' => true]],
        ])->assertCreated();

        $product = Product::query()->findOrFail($response->json('product.id'));
        $this->assertSame([$paths[2], $paths[0], $paths[1]], $product->images()->pluck('path')->all());
        $this->assertInvariants($product);
        $this->assertSame([$paths[2], $paths[0], $paths[1]], array_column($response->json('product.images'), 'path'));
    }

    // ---------------------------------------------------------------- refusals change nothing

    public function test_refused_requests_leave_the_gallery_untouched(): void
    {
        $ids = $this->seedGallery(6);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->path($other);
        $before = $this->rows();

        $this->add($this->path(), ['is_primary' => true])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->add($theirs)->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->patchImage($ids[3], ['path' => $theirs, 'is_primary' => true])->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->patchImage($ids[3], ['is_primary' => 'yes please', 'alt_text' => 'Changed'])->assertUnprocessable();
        // 404s: another vendor, and an image through another product.
        $this->actingAs($other, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $ids[3]]), ['is_primary' => true])->assertNotFound();
        $this->actingAs($other, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $ids[3]]))->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product(), $ids[3]]))->assertNotFound();

        $this->assertSame($before, $this->rows());
    }

    public function test_the_normaliser_handles_an_empty_gallery_and_an_unknown_chosen_id(): void
    {
        ProductGallery::normalize($this->product);
        $this->assertGallery([]);

        $ids = $this->seedGallery(2);
        ProductGallery::normalize($this->product, 999_999);
        $this->assertGallery($ids);
    }

    // ---------------------------------------------------------------- helpers

    /** The gallery is exactly these ids, in this order, with the invariants. */
    private function assertGallery(array $ids, ?Product $product = null): void
    {
        $product ??= $this->product;
        $this->assertSame($ids, ProductImage::query()->where('product_id', $product->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all());
        $this->assertInvariants($product);
    }

    private function assertInvariants(?Product $product = null): void
    {
        $images = ProductImage::query()->where('product_id', ($product ?? $this->product)->id)->orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame($images->isEmpty() ? [] : range(0, $images->count() - 1), $images->pluck('sort_order')->map(fn ($order) => (int) $order)->all(), 'sort_order is 0..n-1.');
        $this->assertSame($images->isEmpty() ? [] : [true, ...array_fill(0, $images->count() - 1, false)], $images->pluck('is_primary')->all(), 'Only the first image is the main one.');
        $this->assertSame($images->count(), $images->pluck('id')->unique()->count());
    }

    /** @return list<int> six or fewer library images, already consistent */
    private function seedGallery(int $count): array
    {
        return array_map(
            fn ($i) => $this->product->images()->create(['path' => $this->path(), 'sort_order' => $i, 'is_primary' => $i === 0])->id,
            range(0, $count - 1)
        );
    }

    private function rows(): array
    {
        return ProductImage::query()->orderBy('id')->get()->toArray();
    }

    private function add(string $path, array $extra = []): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $path] + $extra);
    }

    private function patchImage(int $id, array $data): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $id]), $data);
    }

    private function remove(int $id): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.images.destroy', [$this->product, $id]));
    }

    private function product(): Product
    {
        return Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    private function path(?Vendor $vendor = null): string
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
