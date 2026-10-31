<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-PUBLISH-BE-01B (DETAIL-02, DETAIL-03): every vendor image change (attach, replace, alt text, set as
 * main, move, remove) on an archived product is refused with 422 under the product row lock, and nothing is written:
 * no image row, order, main flag, library row, file or timestamp changes. Archived wins over suspended. A suspended
 * product that is not archived keeps every image operation and stays suspended. Another vendor's product is a 404
 * whatever its state, and an admin restore makes the images editable again.
 */
class VendorProductArchivedImageTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    /** @var array<string, int> name => product image id */
    private array $images = [];

    private string $free = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = $this->productWithGallery($this->vendor);
    }

    /** Each operation and the status it answers when allowed. */
    public static function operations(): array
    {
        return [
            'attach' => ['attach', 201],
            'replace' => ['replace', 200],
            'alt text' => ['alt', 200],
            'set as main' => ['main', 200],
            'move' => ['move', 200],
            'remove' => ['remove', 200],
        ];
    }

    public static function archivedStates(): array
    {
        $states = [];

        foreach (self::operations() as $name => [$operation]) {
            $states["{$name}, archived"] = [$operation, ['archived_at']];
            $states["{$name}, archived and suspended"] = [$operation, ['archived_at', 'suspended_at']];
        }

        return $states;
    }

    // ---------------------------------------------------------------- archived: refused, nothing written

    #[DataProvider('archivedStates')]
    public function test_an_archived_products_images_cannot_be_changed(string $operation, array $columns): void
    {
        $this->moderate($this->product, $columns);
        $before = $this->snapshot();

        $this->operation($operation)
            ->assertUnprocessable()
            ->assertJsonPath('message', ProductImageController::ARCHIVED_MESSAGE)
            ->assertJsonMissingPath('errors');

        $this->assertSame($before, $this->snapshot(), 'No image row, library row, file or product column changed.');
    }

    // ---------------------------------------------------------------- suspended only: allowed

    #[DataProvider('operations')]
    public function test_a_suspended_products_images_can_still_be_changed_and_it_stays_suspended(string $operation, int $status): void
    {
        $this->product->forceFill(['status' => 'active', 'published_at' => now()])->save();
        $this->moderate($this->product, ['suspended_at']);
        $suspendedAt = $this->product->fresh()->suspended_at;

        $this->operation($operation)->assertStatus($status);

        $this->assertEquals($suspendedAt, $this->product->fresh()->suspended_at);
        $this->assertFalse($this->product->fresh()->isSellable());
        $this->assertGalleryInvariants();
    }

    // ---------------------------------------------------------------- ownership

    #[DataProvider('operations')]
    public function test_another_vendors_archived_product_is_not_found(string $operation): void
    {
        $this->moderate($this->product, ['archived_at']);
        $before = $this->snapshot();
        $intruder = Vendor::factory()->approved()->create();

        $this->operation($operation, $intruder)->assertNotFound();

        $this->assertSame($before, $this->snapshot());
    }

    // ---------------------------------------------------------------- restore

    #[DataProvider('operations')]
    public function test_an_admin_restore_makes_the_images_editable_again(string $operation, int $status): void
    {
        $this->moderate($this->product, ['archived_at']);
        $this->operation($operation)->assertUnprocessable();

        $admin = Admin::create(['name' => 'Restorer', 'email' => 'restorer@example.com', 'phone' => '0599000888', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.restore', $this->product->id), ['archive_reason' => 'Listed again'])->assertRedirect();
        $this->assertFalse($this->product->fresh()->isArchived());

        $this->operation($operation)->assertStatus($status);
        $this->assertGalleryInvariants();
    }

    // ---------------------------------------------------------------- helpers

    private function operation(string $operation, ?Vendor $as = null): TestResponse
    {
        $request = $this->actingAs($as ?? $this->vendor, 'vendor');
        $image = fn (string $name) => [$this->product, $this->images[$name]];

        return match ($operation) {
            'attach' => $request->postJson(route('vendor.dashboard.products.images.store', $this->product), ['path' => $this->free]),
            'replace' => $request->patchJson(route('vendor.dashboard.products.images.update', $image('B')), ['path' => $this->free]),
            'alt' => $request->patchJson(route('vendor.dashboard.products.images.update', $image('A')), ['alt_text' => 'Changed']),
            'main' => $request->patchJson(route('vendor.dashboard.products.images.update', $image('C')), ['is_primary' => true]),
            'move' => $request->patchJson(route('vendor.dashboard.products.images.move', $image('C')), ['direction' => 'up']),
            'remove' => $request->deleteJson(route('vendor.dashboard.products.images.destroy', $image('A'))),
        };
    }

    /** A product with images A, B and C (A main) from the vendor's library, and a free library image. */
    private function productWithGallery(Vendor $vendor): Product
    {
        $product = Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);

        foreach (['A', 'B', 'C'] as $position => $name) {
            $this->images[$name] = $product->images()->create([
                'path' => $this->library($vendor), 'alt_text' => "Alt {$name}", 'sort_order' => $position, 'is_primary' => $position === 0,
            ])->id;
        }
        $this->free = $this->library($vendor);

        return $product;
    }

    private function library(Vendor $vendor): string
    {
        $path = "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'image '.$path);
        Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        return $path;
    }

    private function moderate(Product $product, array $columns): void
    {
        $product->forceFill(array_fill_keys($columns, now()->subHour()))->save();
    }

    /** Every image row (order, main flag, path, alt text, timestamps), every library row, every file and its content, and the product row. */
    private function snapshot(): array
    {
        $disk = Storage::disk('public');

        return [
            'images' => ProductImage::query()->orderBy('id')->get()->map->getAttributes()->all(),
            'media' => Media::query()->orderBy('id')->get()->map->getAttributes()->all(),
            'files' => collect($disk->allFiles())->sort()->values()->mapWithKeys(fn ($file) => [$file => $disk->get($file)])->all(),
            'product' => $this->product->fresh()->getAttributes(),
        ];
    }

    private function assertGalleryInvariants(): void
    {
        $images = ProductImage::query()->where('product_id', $this->product->id)->orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame(range(0, $images->count() - 1), $images->pluck('sort_order')->map(fn ($order) => (int) $order)->all());
        $this->assertSame([true, ...array_fill(0, $images->count() - 1, false)], $images->pluck('is_primary')->all());
    }
}
