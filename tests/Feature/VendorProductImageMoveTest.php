<?php

namespace Tests\Feature;

use App\Http\Requests\VendorDashboard\MoveProductImageRequest;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * VPM-01D: PATCH vendor.dashboard.products.images.move {direction: up|down} moves one image one place, under the
 * product lock, relative to the order at that moment. The gallery is written back 0..n-1 and the first image is the
 * main one (the rule "set as main" follows too). A move past either end changes nothing. No image is added, removed,
 * re-pathed or limited: galleries above six and historical duplicates move like any other.
 */
class VendorProductImageMoveTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    /** @var list<int> the ids fill() created, in order */
    private array $filled = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = $this->product($this->vendor);
    }

    // ---------------------------------------------------------------- moving

    public function test_moving_a_middle_image_up_and_down(): void
    {
        [$a, $b, $c, $d] = $this->fill(4);

        $this->move($c, 'up')->assertOk()->assertJsonPath('message', 'Product image moved successfully.')->assertJsonPath('image.id', $c);
        $this->assertOrder([$a, $c, $b, $d]);

        $response = $this->move($c, 'down')->assertOk();
        $this->assertOrder([$a, $b, $c, $d]);
        $this->assertSame([$a, $b, $c, $d], array_column($response->json('product.images'), 'id'), 'The answer is the gallery in its new order.');
    }

    public function test_moving_to_the_first_place_makes_it_the_main_image_and_moving_the_main_image_down_hands_main_over(): void
    {
        [$a, $b, $c] = $this->fill(3);

        $this->move($b, 'up')->assertOk()->assertJsonPath('product.images.0.id', $b)->assertJsonPath('product.images.0.is_primary', true);
        $this->assertOrder([$b, $a, $c]);

        $this->move($b, 'down')->assertOk();
        $this->assertOrder([$a, $b, $c]);
    }

    public function test_a_move_past_either_end_changes_nothing(): void
    {
        [$a, $b, $c] = $this->fill(3);
        $before = $this->rows(true);

        $this->move($a, 'up')->assertOk()->assertJsonPath('product.images.0.id', $a);
        $this->move($c, 'down')->assertOk()->assertJsonPath('product.images.2.id', $c);

        $this->assertSame($before, $this->rows(true), 'Not even a timestamp is written.');
    }

    public function test_every_image_keeps_its_id_path_and_alt_text(): void
    {
        $ids = $this->fill(4);
        $metadata = fn () => ProductImage::query()->orderBy('id')->get(['id', 'product_id', 'path', 'alt_text'])->toArray();
        $before = $metadata();

        foreach ([[$ids[3], 'up'], [$ids[3], 'up'], [$ids[3], 'up'], [$ids[0], 'down'], [$ids[2], 'down']] as [$id, $direction]) {
            $this->move($id, $direction)->assertOk();
            $this->assertNormalised();
        }

        $this->assertSame($before, $metadata());
        $this->assertSame(4, $this->product->images()->count());
    }

    public function test_the_direction_is_required_and_must_be_up_or_down(): void
    {
        [$a, $b] = $this->fill(2);
        $before = $this->rows();

        foreach ([[], ['direction' => ''], ['direction' => 'left'], ['direction' => 'UP'], ['direction' => ['up']], ['direction' => 1]] as $body) {
            $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.move', [$this->product, $b]), $body)
                ->assertUnprocessable()->assertJsonValidationErrors('direction');
        }

        $this->assertSame($before, $this->rows());
    }

    // ---------------------------------------------------------------- set as main

    public function test_set_as_main_and_moving_share_one_definition_of_the_main_image(): void
    {
        [$a, $b, $c] = $this->fill(3);

        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $c]), ['is_primary' => true])->assertOk();
        $this->assertOrder([$c, $a, $b]);
        $this->move($b, 'up')->assertOk();
        $this->assertOrder([$c, $b, $a]);
        $this->move($b, 'up')->assertOk();
        $this->assertOrder([$b, $c, $a]);
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.update', [$this->product, $a]), ['is_primary' => true])->assertOk();
        $this->assertOrder([$a, $b, $c]);
    }

    // ---------------------------------------------------------------- legacy data

    public function test_a_gallery_above_six_moves_without_losing_or_refusing_images(): void
    {
        $ids = $this->fill(8);

        $this->move($ids[7], 'up')->assertOk();
        $this->move($ids[0], 'down')->assertOk();

        $this->assertOrder([$ids[1], $ids[0], $ids[2], $ids[3], $ids[4], $ids[5], $ids[7], $ids[6]]);
        $this->assertSame(8, $this->product->images()->count(), 'Nothing trimmed.');
    }

    public function test_historical_duplicates_move_like_any_other_image(): void
    {
        $path = $this->library();
        $a = $this->product->images()->create(['path' => $path, 'alt_text' => 'first', 'sort_order' => 0, 'is_primary' => true])->id;
        $b = $this->product->images()->create(['path' => $this->library(), 'sort_order' => 1, 'is_primary' => false])->id;
        $c = $this->product->images()->create(['path' => $path, 'alt_text' => 'again', 'sort_order' => 2, 'is_primary' => false])->id;

        $this->move($c, 'up')->assertOk();
        $this->move($c, 'up')->assertOk();

        $this->assertOrder([$c, $a, $b]);
        $this->assertSame([$path, $path], ProductImage::query()->whereKey([$a, $c])->pluck('path')->all(), 'Both rows kept, nothing merged.');
    }

    public function test_a_gallery_not_yet_normalised_moves_from_the_order_the_vendor_sees(): void
    {
        // Legacy: gaps and ties in sort_order, and the main image not first by sort_order. The vendor sees the main image
        // first, then sort_order, then id (orderImages in the editor, ProductGallery on the server).
        $a = $this->product->images()->create(['path' => $this->library(), 'sort_order' => 5, 'is_primary' => false])->id;
        $b = $this->product->images()->create(['path' => $this->library(), 'sort_order' => 9, 'is_primary' => true])->id;
        $c = $this->product->images()->create(['path' => $this->library(), 'sort_order' => 5, 'is_primary' => false])->id;
        $d = $this->product->images()->create(['path' => $this->library(), 'sort_order' => 2, 'is_primary' => false])->id;
        // Seen as: b (main), d (2), a (5), c (5, higher id).

        $this->move($a, 'down')->assertOk();

        $this->assertOrder([$b, $d, $c, $a]);
    }

    // ---------------------------------------------------------------- access

    public function test_only_the_owning_approved_vendor_moves_its_own_images(): void
    {
        [$a, $b] = $this->fill(2);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->product($other);
        $theirImage = $theirs->images()->create(['path' => $this->library($other), 'sort_order' => 0, 'is_primary' => true])->id;
        $before = $this->rows();

        $url = route('vendor.dashboard.products.images.move', [$this->product, $b]);
        $this->patchJson($url, ['direction' => 'up'])->assertUnauthorized();
        $this->actingAs(Vendor::factory()->withProfile('pending')->create(), 'vendor')->patchJson($url, ['direction' => 'up'])->assertForbidden();
        $this->actingAs($other, 'vendor')->patchJson($url, ['direction' => 'up'])->assertNotFound();
        // An image of another product, through this product's URL.
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.move', [$this->product, $theirImage]), ['direction' => 'up'])->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.move', [$this->product, 999_999]), ['direction' => 'up'])->assertNotFound();

        $this->assertSame($before, $this->rows());
    }

    public function test_the_route_is_gated_like_the_other_image_routes(): void
    {
        $route = app('router')->getRoutes()->getByName('vendor.dashboard.products.images.move');

        $this->assertSame(['PATCH'], $route->methods());
        $this->assertSame('vendor/dashboard/products/{product}/images/{productImage}/move', $route->uri());
        foreach (['auth:vendor', 'vendor.approved'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    // ---------------------------------------------------------------- concurrency (simulated; MySQL: VendorProductImageConcurrencyMySqlTest)

    public function test_a_move_committed_between_validation_and_the_transaction_is_not_lost(): void
    {
        [$a, $b, $c] = $this->fill(3);
        // Another move of c (up) commits after this request validated: this one applies to the order it finds.
        $this->app->afterResolving(MoveProductImageRequest::class, function () use ($c) {
            ProductImage::query()->whereKey($c)->update(['sort_order' => 1]);
            ProductImage::query()->whereKey($this->fillId(1))->update(['sort_order' => 2]);
        });

        $this->move($c, 'up')->assertOk();

        $this->assertOrder([$c, $a, $b], 'Both moves applied: c went up twice.');
    }

    // ---------------------------------------------------------------- the editor

    public function test_the_editor_gives_the_move_texts_in_arabic_and_english_and_moves_through_the_server(): void
    {
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(VendorDashboardTranslationSeeder::class);
        $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hot, 'http://localhost:5173');
        Vite::useHotFile($hot);

        try {
            $expected = ['ar' => ['نقل لأعلى', 'نقل لأسفل', 'أصبحت «:name» الصورة :position.'], 'en' => ['Move up', 'Move down', ':name is now image :position.']];
            foreach ($expected as $locale => [$up, $down, $moved]) {
                $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
                preg_match('#<script type="application/json" data-product-gallery-messages>(.*?)</script>#s', $html, $match);
                $messages = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame([$up, $down, $moved], [$messages['moveUp'], $messages['moveDown'], $messages['moved']], $locale);
            }
        } finally {
            @unlink($hot);
        }

        $source = file_get_contents(resource_path('js/vendor-product-editor.js'));
        $this->assertStringContainsString("await mutate(`\${endpoint}/\${editingId}/images/\${image.id}/move`, 'PATCH', { direction }, '')", $source, 'A saved product moves through the server, redrawn from its answer.');
        $this->assertStringContainsString('images = moveLocal(images, image, direction);', $source, 'A new product moves locally.');
        $this->assertStringNotContainsString('draggable', $source, 'No drag and drop.');
    }

    // ---------------------------------------------------------------- helpers

    private function move(int $imageId, string $direction): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.products.images.move', [$this->product, $imageId]), ['direction' => $direction]);
    }

    /** The gallery is exactly these images in this order, 0..n-1, the first one the only main image. */
    private function assertOrder(array $ids, string $message = ''): void
    {
        $rows = $this->product->images()->orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame($ids, $rows->pluck('id')->all(), $message);
        $this->assertNormalised();
    }

    private function assertNormalised(): void
    {
        $rows = $this->product->images()->orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame(range(0, $rows->count() - 1), $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(array_merge([true], array_fill(0, $rows->count() - 1, false)), $rows->pluck('is_primary')->map(fn ($v) => (bool) $v)->all());
    }

    private function rows(bool $withTimestamps = false): array
    {
        $columns = ['id', 'product_id', 'path', 'alt_text', 'sort_order', 'is_primary'];

        return ProductImage::query()->orderBy('id')->get($withTimestamps ? [...$columns, 'updated_at'] : $columns)->toArray();
    }

    /** @return list<int> */
    private function fill(int $count): array
    {
        return $this->filled = array_map(fn ($i) => $this->product->images()->create(['path' => $this->library(), 'alt_text' => "Image {$i}", 'sort_order' => $i, 'is_primary' => $i === 0])->id, range(0, $count - 1));
    }

    private function fillId(int $index): int
    {
        return $this->filled[$index];
    }

    private function product(Vendor $vendor): Product
    {
        return Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
    }

    private function library(?Vendor $vendor = null): string
    {
        $vendor ??= $this->vendor;

        return "vendors/media/{$vendor->id}/".uniqid('', true).'.png';
    }
}
