<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * PRODUCT-MEDIA-01 P5-B: the ordered product gallery of the admin product form (no UI yet). gallery_sync=1 with
 * gallery[] = "existing:{product image id}" / "media:{media id}" makes the product's images exactly that list: the
 * order is sort_order, the first is the main image, at most six, no reference or path twice. Existing references must
 * be this product's images and media the product vendor's own (the stored vendor). Under the product lock the image
 * rows are compared with gallery_fingerprint, then ownership is checked again; any refusal writes nothing. Removing
 * an image deletes its row, never its file; unchanged rows are not rewritten; an omitted gallery changes nothing.
 */
class AdminProductGallerySyncTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_ONLY = 'Each selected image must come from this vendor\'s media library.';

    private const OUTDATED = 'The product images changed while this page was open. Reload the page and review them before saving.';

    private const NOT_PRODUCT = 'An image in the list does not belong to this product.';

    private const DUPLICATE = 'Each image can appear only once in the list.';

    private const INVALID = 'The image list could not be read. Reload the page and try again.';

    private const MAX = 'A product can have up to 6 images.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');

        foreach ([1, 10, 11] as $id) {
            Vendor::factory()->approved()->create(['id' => $id]);
        }
    }

    // ---------------------------------------------------------------- reorder, main image, remove, replace, add

    public function test_reordering_keeps_the_rows_and_follows_the_list(): void
    {
        $product = $this->product();
        [$a, $b, $c] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png', 'vendors/media/1/c.png']);

        $this->sync($product, ["existing:{$c->id}", "existing:{$a->id}", "existing:{$b->id}"])->assertSessionHasNoErrors();

        $this->assertSame([[$c->id, 0, true], [$a->id, 1, false], [$b->id, 2, false]], $this->rows($product));
        $this->assertSame($c->id, $product->fresh()->primaryImage->id, 'The storefront main image follows.');
        $this->assertSame([$c->id, $a->id, $b->id], $product->fresh()->images->pluck('id')->all());
    }

    public function test_the_first_image_becomes_the_only_main_image(): void
    {
        $product = $this->product();
        [$a, $b, $c] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png', 'vendors/media/1/c.png']);

        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}", "existing:{$c->id}"])->assertSessionHasNoErrors();

        $this->assertSame([$b->id], ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->pluck('id')->all());
    }

    public function test_removing_an_image_deletes_the_row_only(): void
    {
        $product = $this->product();
        $own = $this->media('vendors/media/1/b.png');
        [$a, $b, $c] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png', 'products/'.$product->id.'/c.png']);

        $this->sync($product, ["existing:{$a->id}", "existing:{$c->id}"])->assertSessionHasNoErrors();

        $this->assertSame([[$a->id, 0, true], [$c->id, 1, false]], $this->rows($product));
        $this->assertModelMissing($b);
        $this->assertModelExists($own);
        Storage::disk('public')->assertExists(['vendors/media/1/a.png', 'vendors/media/1/b.png', 'products/'.$product->id.'/c.png']);
    }

    public function test_replacing_an_image_in_place(): void
    {
        $product = $this->product();
        [$a, $b, $c] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png', 'vendors/media/1/c.png']);
        $new = $this->media('vendors/media/1/new.png', ['alt' => 'New jug']);

        $this->sync($product, ["existing:{$a->id}", "media:{$new->id}", "existing:{$c->id}"])->assertSessionHasNoErrors();

        $images = $product->images()->get();
        $this->assertSame([$a->id, $images[1]->id, $c->id], $images->pluck('id')->all());
        $this->assertSame('vendors/media/1/new.png', $images[1]->path);
        $this->assertSame('New jug', $images[1]->alt_text);
        $this->assertModelMissing($b);
        Storage::disk('public')->assertExists('vendors/media/1/b.png');
    }

    public function test_adding_vendor_media_does_not_rewrite_unchanged_rows(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $new = $this->media('vendors/media/1/new.png');
        $stamps = $product->images()->pluck('updated_at', 'id')->map(fn ($at) => (string) $at)->all();

        $this->travel(5)->minutes();
        $this->sync($product, ["existing:{$a->id}", "existing:{$b->id}", "media:{$new->id}"])->assertSessionHasNoErrors();

        $this->assertSame(['vendors/media/1/a.png', 'vendors/media/1/b.png', 'vendors/media/1/new.png'], $product->images()->pluck('path')->all());
        $this->assertSame($stamps, $product->images()->whereKey([$a->id, $b->id])->pluck('updated_at', 'id')->map(fn ($at) => (string) $at)->all(), 'Unchanged rows are not saved again.');
        $this->assertSame('photo-new.png', $product->images()->reorder()->latest('id')->value('alt_text'));
    }

    public function test_a_media_reference_to_a_path_the_product_has_reuses_its_row(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $sameAsB = $this->media('vendors/media/1/b.png');

        $this->sync($product, ["media:{$sameAsB->id}", "existing:{$a->id}"])->assertSessionHasNoErrors();

        $this->assertSame([[$b->id, 0, true], [$a->id, 1, false]], $this->rows($product), 'Row b is kept, not recreated.');
    }

    public function test_an_empty_gallery_removes_every_link_and_no_file(): void
    {
        $product = $this->product();
        $this->images($product, ['vendors/media/1/a.png', 'media/2026/10/admin.png']);

        $this->sync($product, [])->assertSessionHasNoErrors();

        $this->assertSame(0, $product->images()->count());
        Storage::disk('public')->assertExists(['vendors/media/1/a.png', 'media/2026/10/admin.png']);
    }

    public function test_an_omitted_gallery_leaves_the_images_and_media_image_ids_work_as_before(): void
    {
        $product = $this->product();
        $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png'], primary: 1);
        $before = $this->rows($product);
        $own = $this->media('vendors/media/1/c.png');

        $this->update($product, ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->rows($product));
        $this->assertSame('Renamed', $product->fresh()->name);

        $this->update($product, ['media_image_ids' => [$own->id]])->assertSessionHasNoErrors();
        $this->assertSame(['vendors/media/1/a.png', 'vendors/media/1/b.png', 'vendors/media/1/c.png'], $product->images()->pluck('path')->all());

        // Not both at once.
        $this->sync($product, ["media:{$own->id}"], ['media_image_ids' => [$own->id]])->assertSessionHasErrors(['gallery' => self::INVALID]);
    }

    // ---------------------------------------------------------------- limits and references

    public function test_at_most_six_images(): void
    {
        $product = $this->product();
        $refs = array_map(fn ($i) => 'media:'.$this->media("vendors/media/1/{$i}.png")->id, range(1, 7));

        $this->sync($product, $refs)->assertSessionHasErrors(['gallery' => self::MAX]);
        $this->assertSame(0, $product->images()->count());

        $this->sync($product, array_slice($refs, 0, 6))->assertSessionHasNoErrors();
        $this->assertSame(6, $product->images()->count());
    }

    public function test_a_reference_or_a_path_twice_is_refused(): void
    {
        $product = $this->product();
        [$a] = $this->images($product, ['vendors/media/1/a.png']);
        $sameAsA = $this->media('vendors/media/1/a.png');
        $before = $this->rows($product);

        $this->sync($product, ["existing:{$a->id}", "existing:{$a->id}"])->assertSessionHasErrors(['gallery.0' => self::DUPLICATE]);
        $this->sync($product, ["existing:{$a->id}", "media:{$sameAsA->id}"])->assertSessionHasErrors(['gallery' => self::DUPLICATE]);

        $this->assertSame($before, $this->rows($product));
    }

    public function test_malformed_references_are_refused(): void
    {
        $product = $this->product();

        foreach (['existing:0', 'media:-1', 'media:1x', 'other:5', 'media:', ':5', 'media:5:6', ['x'], ''] as $ref) {
            $this->sync($product, [$ref])->assertSessionHasErrors('gallery.0');
        }

        $this->sync($product, [], ['gallery_sync' => 'yes'])->assertSessionHasErrors(['gallery_sync' => self::INVALID]);
        $this->sync($product, [], ['gallery' => ['a' => 'media:1']])->assertSessionHasErrors(['gallery' => self::INVALID]);
    }

    public function test_another_products_image_is_refused(): void
    {
        $product = $this->product();
        $other = $this->product();
        [$mine] = $this->images($product, ['vendors/media/1/a.png']);
        [$theirs] = $this->images($other, ['vendors/media/1/b.png']);

        $this->sync($product, ["existing:{$mine->id}", "existing:{$theirs->id}"])->assertSessionHasErrors(['gallery' => self::NOT_PRODUCT]);

        $this->assertSame([[$mine->id, 0, true]], $this->rows($product));
        $this->assertSame([[$theirs->id, 0, true]], $this->rows($other));
    }

    public function test_another_vendors_admin_and_look_alike_media_are_refused_for_every_admin(): void
    {
        $product = $this->product();
        $before = $this->rows($product);

        $refused = [
            $this->media('vendors/media/10/b.png'), $this->media('vendors/media/11/c.png'), $this->media('media/2026/10/admin.png', ['uploader_id' => 1]),
            $this->media('vendors/media-old/1/d.png'), $this->media('vendors/media/01/e.png'), $this->media('VENDORS/media/1/f.png'),
            $this->media('vendors/media/1/guide.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']),
        ];

        foreach ([$this->superAdmin(), $this->staff(['products.edit'])] as $admin) {
            foreach ($refused as $item) {
                $this->sync($product, ["media:{$item->id}"], admin: $admin)->assertSessionHasErrors(['gallery' => self::VENDOR_ONLY]);
            }

            // A vendor named in the request changes nothing.
            $this->sync($product, ["media:{$refused[0]->id}"], ['vendor_id' => '10'], $admin)->assertSessionHasErrors('vendor_id');
        }

        $this->sync($product, ['media:999999'])->assertSessionHasErrors(['gallery' => self::VENDOR_ONLY]);
        $this->assertSame($before, $this->rows($product));
        $this->assertSame(1, $product->fresh()->vendor_id);
    }

    public function test_legacy_images_are_kept_until_removed(): void
    {
        $product = $this->product();
        $paths = ['media/2026/10/admin.png', 'products/'.$product->id.'/old.png', 'vendors/products/1/'.$product->id.'/up.png'];
        [$admin, $old, $up] = $this->images($product, $paths);

        $this->sync($product, ["existing:{$up->id}", "existing:{$admin->id}", "existing:{$old->id}"])->assertSessionHasNoErrors();
        $this->assertSame([[$up->id, 0, true], [$admin->id, 1, false], [$old->id, 2, false]], $this->rows($product));

        $this->sync($product, ["existing:{$up->id}", "existing:{$old->id}"])->assertSessionHasNoErrors();
        $this->assertSame([[$up->id, 0, true], [$old->id, 1, false]], $this->rows($product));
        Storage::disk('public')->assertExists($paths);
    }

    // ---------------------------------------------------------------- fingerprint and concurrency

    public function test_a_missing_or_stale_fingerprint_is_refused(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $before = $this->rows($product);

        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}"], ['gallery_fingerprint' => null])->assertSessionHasErrors(['gallery_fingerprint' => self::OUTDATED]);
        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}"], ['gallery_fingerprint' => sha1('other')])->assertSessionHasErrors(['gallery' => self::OUTDATED]);
        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}"], ['gallery_fingerprint' => 'short'])->assertSessionHasErrors(['gallery_fingerprint' => self::OUTDATED]);

        $this->assertSame($before, $this->rows($product));
    }

    public function test_the_fingerprint_follows_every_change(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $seen = [ProductManagementController::galleryFingerprint($product)];

        $b->update(['is_primary' => true]);
        $a->update(['is_primary' => false]);
        $seen[] = ProductManagementController::galleryFingerprint($product);
        $a->update(['sort_order' => 5]);
        $seen[] = ProductManagementController::galleryFingerprint($product);
        $this->images($product, ['vendors/media/1/c.png']);
        $seen[] = ProductManagementController::galleryFingerprint($product);
        $b->delete();
        $seen[] = ProductManagementController::galleryFingerprint($product);

        $this->assertCount(5, array_unique($seen));
        $this->assertSame(40, strlen($seen[0]));
    }

    public function test_a_vendor_change_after_the_page_was_opened_is_kept(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $displayed = ProductManagementController::galleryFingerprint($product);

        // The vendor adds an image from its own dashboard meanwhile.
        $vendorImage = $product->images()->create(['path' => 'vendors/products/1/'.$product->id.'/new.png', 'sort_order' => 2, 'is_primary' => false]);
        $current = $this->rows($product);

        $this->sync($product, ["existing:{$b->id}"], ['gallery_fingerprint' => $displayed, 'name' => 'Renamed'])->assertSessionHasErrors(['gallery' => self::OUTDATED]);

        $this->assertSame($current, $this->rows($product));
        $this->assertModelExists($vendorImage);
        $this->assertNotSame('Renamed', $product->fresh()->name, 'The whole update is refused.');
    }

    public function test_a_change_between_validation_and_the_lock_is_caught_under_the_lock(): void
    {
        $product = $this->product();
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        DB::connection()->beforeStartingTransaction(function () use ($product): void {
            DB::table('product_images')->insert(['product_id' => $product->id, 'path' => 'vendors/products/1/x.png', 'sort_order' => 9, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}"], ['name' => 'Renamed'])->assertSessionHasErrors(['gallery' => self::OUTDATED]);

        $this->assertSame(3, $product->images()->count());
        $this->assertSame([$a->id, 0, true], $this->rows($product)[0]);
        $this->assertNotSame('Renamed', $product->fresh()->name);
    }

    public function test_an_image_removed_in_another_tab_reports_a_stale_list_and_writes_nothing(): void
    {
        // P5-C-FIX-02 (D-2): tab 1 removes image b and saves; tab 2, opened before, still lists b.
        $product = $this->product(['name' => 'Clay jug']);
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $displayed = ProductManagementController::galleryFingerprint($product);

        $this->sync($product, ["existing:{$a->id}"])->assertSessionHasNoErrors();
        $afterTab1 = $this->rows($product);

        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}"], ['gallery_fingerprint' => $displayed, 'name' => 'Renamed'])
            ->assertSessionHasErrors(['gallery' => self::OUTDATED]);

        $this->assertSame($afterTab1, $this->rows($product), 'Tab 1 is not overwritten.');
        $this->assertSame('Clay jug', $product->fresh()->name, 'Nothing else is saved either.');
    }

    public function test_a_forged_image_reference_with_a_current_list_is_still_not_this_products(): void
    {
        $product = $this->product(['name' => 'Clay jug']);
        $other = $this->product();
        [$a] = $this->images($product, ['vendors/media/1/a.png']);
        [$theirs] = $this->images($other, ['vendors/media/1/b.png']);
        $before = $this->rows($product);

        foreach ([$theirs->id, 999999] as $forged) {
            // The fingerprint is current: the reference is forged, not stale.
            $this->sync($product, ["existing:{$a->id}", "existing:{$forged}"], ['name' => 'Renamed'])
                ->assertSessionHasErrors(['gallery' => self::NOT_PRODUCT]);
        }

        $this->assertSame($before, $this->rows($product));
        $this->assertSame([[$theirs->id, 0, true]], $this->rows($other));
        $this->assertSame('Clay jug', $product->fresh()->name);
    }

    // ---------------------------------------------------------------- second check, rollback

    public function test_ownership_is_checked_again_just_before_saving(): void
    {
        $product = $this->product();
        [$a] = $this->images($product, ['vendors/media/1/a.png']);
        $own = $this->media('vendors/media/1/b.png');
        $before = $this->rows($product);

        // Valid when validated; moved to another vendor's folder before the save.
        DB::connection()->beforeStartingTransaction(fn () => DB::table('media')->where('id', $own->id)->update(['file_path' => 'vendors/media/10/b.png']));

        $this->sync($product, ["existing:{$a->id}", "media:{$own->id}"], ['name' => 'Renamed'])->assertSessionHasErrors(['gallery' => self::VENDOR_ONLY]);

        $this->assertSame($before, $this->rows($product));
        $this->assertNotSame('Renamed', $product->fresh()->name);
    }

    public function test_a_refusal_inside_the_save_rolls_everything_back(): void
    {
        $product = $this->product(['name' => 'Clay jug']);
        [$a, $b] = $this->images($product, ['vendors/media/1/a.png', 'vendors/media/1/b.png']);
        $sameAsA = $this->media('vendors/media/1/a.png');
        $before = $this->rows($product);

        // The path clash is only known inside the save, after the product fields were written.
        $this->sync($product, ["existing:{$b->id}", "existing:{$a->id}", "media:{$sameAsA->id}"], ['name' => 'Renamed', 'price' => '20'])
            ->assertSessionHasErrors(['gallery' => self::DUPLICATE]);

        $this->assertSame($before, $this->rows($product));
        $this->assertSame('Clay jug', $product->fresh()->name);
        $this->assertSame('10.00', (string) $product->fresh()->price);
    }

    // ---------------------------------------------------------------- creation

    public function test_a_new_product_can_take_a_gallery_of_its_vendors_media(): void
    {
        $first = $this->media('vendors/media/11/first.png');
        $second = $this->media('vendors/media/11/second.png');
        $foreign = $this->media('vendors/media/1/foreign.png');
        $existing = $this->images($this->product(), ['vendors/media/1/x.png'])[0];

        $this->store(['vendor_id' => '11', 'gallery_sync' => '1', 'gallery' => ["media:{$foreign->id}"]])->assertSessionHasErrors(['gallery' => self::VENDOR_ONLY]);
        $this->store(['vendor_id' => '11', 'gallery_sync' => '1', 'gallery' => ["existing:{$existing->id}"]])->assertSessionHasErrors(['gallery' => self::NOT_PRODUCT]);
        $this->assertSame(1, Product::count(), 'A refused request creates no product.');

        $this->store(['vendor_id' => '11', 'name' => 'Basket', 'gallery_sync' => '1', 'gallery' => ["media:{$second->id}", "media:{$first->id}"]])->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Basket')->sole();
        $this->assertSame(['vendors/media/11/second.png', 'vendors/media/11/first.png'], $product->images()->pluck('path')->all());
        $this->assertSame([true, false], $product->images()->pluck('is_primary')->all());
    }

    // ---------------------------------------------------------------- helpers

    private function sync(Product $product, array $refs, array $overrides = [], ?Admin $admin = null): TestResponse
    {
        return $this->update($product, $overrides + [
            'gallery_sync' => '1',
            'gallery' => $refs,
            'gallery_fingerprint' => ProductManagementController::galleryFingerprint($product->fresh()),
        ], $admin);
    }

    private function update(Product $product, array $overrides = [], ?Admin $admin = null): TestResponse
    {
        $fresh = $product->fresh();
        $payload = $overrides + [
            'vendor_id' => (string) $fresh->vendor_id, 'category_id' => (string) ($fresh->category_id ?? $this->fixtureCategory()), 'name' => $fresh->name, 'slug' => $fresh->slug, 'price' => (string) $fresh->price,
            'stock_quantity' => (string) $fresh->stock_quantity, 'status' => $fresh->status,
            'stock_quantity_original' => (string) $fresh->stock_quantity, 'stock_version' => ProductManagementController::stockVersion($fresh),
        ];

        return $this->actingAs($admin ?? $this->superAdmin(), 'admin')
            ->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), array_filter($payload, fn ($value) => $value !== null));
    }

    private function store(array $overrides): TestResponse
    {
        return $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('dashboard.products.create'))
            ->post(route('dashboard.products.store'), $overrides + ['vendor_id' => '1', 'category_id' => (string) $this->fixtureCategory(), 'name' => 'Clay jug', 'price' => '10', 'stock_quantity' => '4', 'status' => 'draft']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + ['vendor_id' => 1, 'category_id' => $this->fixtureCategory(), 'name' => 'Clay jug', 'slug' => 'clay-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 4, 'status' => 'draft']);
    }

    /** @return list<ProductImage> */
    private function images(Product $product, array $paths, int $primary = 0): array
    {
        $start = (int) $product->images()->max('sort_order') + ($product->images()->exists() ? 1 : 0);

        return array_map(function (string $path, int $index) use ($product, $primary, $start) {
            Storage::disk('public')->put($path, 'file');

            return $product->images()->create(['path' => $path, 'alt_text' => 'Old', 'sort_order' => $start + $index, 'is_primary' => $start === 0 && $index === $primary]);
        }, $paths, array_keys($paths));
    }

    /** @return list<array{0: int, 1: int, 2: bool}> [id, sort_order, is_primary] in gallery order */
    private function rows(Product $product): array
    {
        return $product->images()->orderBy('id')->get()->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (ProductImage $image) => [$image->id, (int) $image->sort_order, (bool) $image->is_primary])->values()->all();
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    private function staff(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function media(string $path, array $attributes = []): Media
    {
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, 'file');
        }

        return Media::create($attributes + [
            'file_name' => basename($path),
            'file_original_name' => 'photo-'.basename($path),
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }

    /** ADMIN-PRODUCT-FORM-02: a category is required on admin saves, so fixtures share one active category. */
    private function fixtureCategory(): int
    {
        return Category::query()->firstOrCreate(['slug' => 'fixture-category'], ['name' => 'Pottery', 'is_active' => true])->id;
    }
}
