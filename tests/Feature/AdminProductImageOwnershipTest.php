<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * PRODUCT-MEDIA-01 P5-A: the admin product form links images from the product vendor's own media library only (G1,
 * the canonical vendors/media/{vendorId}/ path, checked against the stored vendor on update), never changes a
 * product's vendor (G2) and stores no uploaded file (G3). Every admin, super admins included, gets the same refusals;
 * a refused request writes nothing, and existing images (legacy paths included) are never changed.
 */
class AdminProductImageOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_ONLY = 'Each selected image must come from this vendor\'s media library.';

    private const UPLOAD_DISABLED = 'Images cannot be uploaded here. Choose them from the vendor\'s media library.';

    private const VENDOR_IMMUTABLE = 'The vendor of an existing product cannot be changed.';

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

    // ---------------------------------------------------------------- G1: the vendor's own images

    public function test_vendor_images_are_linked_on_create_in_the_chosen_order(): void
    {
        $first = $this->media('vendors/media/1/first.png', ['alt' => 'Blue jug']);
        $second = $this->media('vendors/media/1/second.png');

        $this->store(['media_image_ids' => [$second->id, $first->id]])->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        $images = $product->images()->get();
        $this->assertSame(1, $product->vendor_id);
        $this->assertSame(['vendors/media/1/second.png', 'vendors/media/1/first.png'], $images->pluck('path')->all());
        $this->assertSame([true, false], $images->pluck('is_primary')->all(), 'The first chosen image is the main one.');
        $this->assertSame('photo-second.png', $images[0]->alt_text);
        $this->assertSame('Blue jug', $images[1]->alt_text);
    }

    public function test_another_vendors_images_are_refused_for_vendors_1_10_and_11(): void
    {
        $media = [
            1 => $this->media('vendors/media/1/a.png'),
            10 => $this->media('vendors/media/10/b.png'),
            11 => $this->media('vendors/media/11/c.png'),
        ];

        foreach ($media as $owner => $item) {
            foreach (array_diff([1, 10, 11], [$owner]) as $vendor) {
                $this->store(['vendor_id' => $vendor, 'media_image_ids' => [$item->id]])->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
            }

            // Mixed with one of the product vendor's own images: the whole request is refused.
            $other = $owner === 1 ? 10 : 1;
            $this->store(['vendor_id' => $other, 'media_image_ids' => [$media[$other]->id, $item->id]])->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
        }

        $this->assertSame(0, Product::count(), 'A refused request creates no product.');
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_look_alike_and_non_canonical_paths_are_refused(): void
    {
        foreach ([
            'vendors/media-old/1/a.png', 'vendors/media/1x/a.png', 'vendors/media/01/a.png', 'VENDORS/media/1/a.png',
            'vendors/Media/1/a.png', '/vendors/media/1/a.png', 'vendors//media/1/a.png', 'vendors/media/1//a.png',
            'vendors\\media\\1\\a.png', 'vendors/media/1/../10/a.png', 'vendors/media/1/./a.png', 'vendors/media/1/sub/a.png',
            'vendors/media/1/', 'vendors/media/1', 'vendors/products/1/5/a.png', 'products/5/a.png',
        ] as $path) {
            $item = $this->media($path, store: false);

            $this->store(['media_image_ids' => [$item->id]])->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_admin_media_is_refused_even_with_the_vendors_id_as_uploader(): void
    {
        foreach ([$this->media('media/2026/10/admin.png'), $this->media('media/2026/10/mine.png', ['uploader_id' => 1])] as $item) {
            $this->store(['media_image_ids' => [$item->id]])->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_non_images_and_missing_media_are_refused(): void
    {
        $pdf = $this->media('vendors/media/1/guide.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']);

        foreach ([[$pdf->id], [999999], ['abc']] as $ids) {
            $this->store(['media_image_ids' => $ids])->assertSessionHasErrors('media_image_ids'.(is_int($ids[0]) ? '' : '.0'));
        }

        $this->assertSame(0, Product::count());
    }

    public function test_every_admin_gets_the_same_refusals(): void
    {
        $foreign = $this->media('vendors/media/10/b.png');
        $product = $this->product(1);

        foreach ([$this->superAdmin(), $this->staff(['products.create', 'products.edit'])] as $admin) {
            $this->store(['media_image_ids' => [$foreign->id]], $admin)->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
            $this->update($product, ['media_image_ids' => [$foreign->id]], $admin)->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);
            $this->update($product, ['vendor_id' => 10], $admin)->assertSessionHasErrors(['vendor_id' => self::VENDOR_IMMUTABLE]);
            $this->store(['images' => [UploadedFile::fake()->image('a.png')]], $admin)->assertSessionHasErrors(['images' => self::UPLOAD_DISABLED]);
        }

        $this->assertSame(1, Product::count());
        $this->assertSame(0, $product->images()->count());
        $this->assertSame(1, $product->fresh()->vendor_id);
    }

    public function test_update_checks_images_against_the_stored_vendor(): void
    {
        $product = $this->product(1);
        $own = $this->media('vendors/media/1/own.png');
        $foreign = $this->media('vendors/media/10/foreign.png');

        $this->update($product, ['media_image_ids' => [$own->id]])->assertSessionHasNoErrors();
        $this->assertSame(['vendors/media/1/own.png'], $product->images()->pluck('path')->all());

        // Claiming the other vendor in the same request changes nothing: the vendor is refused first.
        $this->update($product, ['vendor_id' => 10, 'media_image_ids' => [$foreign->id]])->assertSessionHasErrors(['vendor_id' => self::VENDOR_IMMUTABLE]);
        $this->update($product, ['media_image_ids' => [$foreign->id]])->assertSessionHasErrors(['media_image_ids' => self::VENDOR_ONLY]);

        $this->assertSame(['vendors/media/1/own.png'], $product->images()->pluck('path')->all());
        $this->assertSame(1, $product->fresh()->vendor_id);
    }

    public function test_a_path_already_linked_is_not_added_twice(): void
    {
        $product = $this->product(1);
        $own = $this->media('vendors/media/1/own.png');

        $this->update($product, ['media_image_ids' => [$own->id]])->assertSessionHasNoErrors();
        $this->update($product, ['media_image_ids' => [$own->id]])->assertSessionHasNoErrors();
        $this->update($product, ['media_image_ids' => [$own->id, $own->id]])->assertSessionHasErrors('media_image_ids.0');

        $this->assertSame(1, $product->images()->count());
    }

    // ---------------------------------------------------------------- G2: a fixed vendor

    public function test_the_vendor_of_an_existing_product_cannot_be_changed_and_nothing_is_written(): void
    {
        $product = $this->product(1, ['name' => 'Clay jug']);
        $before = $product->fresh()->getAttributes();

        foreach ([10, 11, '10', 999999] as $vendor) {
            $this->update($product, ['vendor_id' => $vendor, 'name' => 'Renamed'])->assertSessionHasErrors('vendor_id');
        }

        $this->assertSame($before, $product->fresh()->getAttributes(), 'No field is saved.');
        $this->update($product, ['vendor_id' => 10])->assertSessionHasErrors(['vendor_id' => self::VENDOR_IMMUTABLE]);

        // The same vendor saves as before.
        $this->update($product, ['vendor_id' => '1', 'name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame(1, $product->fresh()->vendor_id);
    }

    public function test_a_new_product_takes_the_chosen_vendor(): void
    {
        $this->store(['vendor_id' => 11, 'name' => 'Basket'])->assertSessionHasNoErrors();

        $this->assertSame(11, Product::query()->where('name', 'Basket')->value('vendor_id'));
    }

    // ---------------------------------------------------------------- G3: no uploads

    public function test_uploaded_images_are_refused_on_create_and_update_and_nothing_is_stored(): void
    {
        $product = $this->product(1);
        $files = fn () => [UploadedFile::fake()->image('jug.png'), UploadedFile::fake()->createWithContent('bad.png', 'not an image')];

        $this->store(['images' => $files()])->assertSessionHasErrors(['images' => self::UPLOAD_DISABLED]);
        $this->update($product, ['images' => $files(), 'name' => 'Renamed'])->assertSessionHasErrors(['images' => self::UPLOAD_DISABLED]);

        $this->assertSame(1, Product::count());
        $this->assertNotSame('Renamed', $product->fresh()->name);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('products'), 'No file under products/.');

        // An empty images field is not an upload.
        $this->update($product, ['images' => []])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- existing images, the limit

    public function test_existing_images_with_legacy_paths_are_kept_unchanged(): void
    {
        $product = $this->product(1);
        $legacy = $this->legacyImages($product, ['media/2026/10/admin.png', 'products/'.$product->id.'/old.png', 'vendors/products/1/'.$product->id.'/up.png'], primary: 1);
        $own = $this->media('vendors/media/1/own.png');

        $this->update($product, ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame($legacy, $this->snapshot($product), 'Saving another field changes no image.');

        $this->update($product, ['media_image_ids' => [$own->id]])->assertSessionHasNoErrors();
        $after = $this->snapshot($product);
        $this->assertSame($legacy, array_slice($after, 0, 3), 'The existing images keep their order and main image.');
        $this->assertSame(['vendors/media/1/own.png', 3, false], array_slice(array_values($after[3]), 1), 'The new image goes last.');

        foreach (['media/2026/10/admin.png', 'products/'.$product->id.'/old.png', 'vendors/products/1/'.$product->id.'/up.png'] as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_the_six_image_limit_counts_existing_images(): void
    {
        $product = $this->product(1);
        $this->legacyImages($product, array_map(fn ($i) => "products/{$product->id}/{$i}.png", range(1, 5)));
        $ids = array_map(fn ($i) => $this->media("vendors/media/1/{$i}.png")->id, range(1, 7));

        $this->update($product, ['media_image_ids' => [$ids[0], $ids[1]]])->assertSessionHasErrors(['media_image_ids' => self::MAX]);
        $this->assertSame(5, $product->images()->count());

        $this->update($product, ['media_image_ids' => [$ids[0]]])->assertSessionHasNoErrors();
        $this->assertSame(6, $product->images()->count());

        $this->store(['media_image_ids' => $ids])->assertSessionHasErrors('media_image_ids');
        $this->store(['media_image_ids' => array_slice($ids, 0, 6)])->assertSessionHasNoErrors();
        $this->assertSame(6, Product::query()->latest('id')->first()->images()->count());
    }

    // ---------------------------------------------------------------- helpers

    private function store(array $overrides = [], ?Admin $admin = null): TestResponse
    {
        return $this->actingAs($admin ?? $this->superAdmin(), 'admin')
            ->from(route('dashboard.products.create'))
            ->post(route('dashboard.products.store'), $overrides + $this->fields());
    }

    private function update(Product $product, array $overrides = [], ?Admin $admin = null): TestResponse
    {
        $fresh = $product->fresh();

        return $this->actingAs($admin ?? $this->superAdmin(), 'admin')
            ->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $overrides + $this->fields(['vendor_id' => (string) $fresh->vendor_id, 'name' => $fresh->name, 'slug' => $fresh->slug]) + [
                'stock_quantity_original' => (string) $fresh->stock_quantity,
                'stock_version' => ProductManagementController::stockVersion($fresh),
            ]);
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'vendor_id' => '1',
            'category_id' => (string) $this->fixtureCategory(),
            'name' => 'Clay jug',
            'price' => '10',
            'stock_quantity' => '4',
            'status' => 'draft',
        ];
    }

    private function product(int $vendorId, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $vendorId, 'category_id' => $this->fixtureCategory(), 'name' => 'Clay jug', 'slug' => 'clay-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 4, 'status' => 'draft',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyImages(Product $product, array $paths, int $primary = 0): array
    {
        foreach ($paths as $index => $path) {
            Storage::disk('public')->put($path, 'file');
            $product->images()->create(['path' => $path, 'alt_text' => 'Old', 'sort_order' => $index, 'is_primary' => $index === $primary]);
        }

        return $this->snapshot($product);
    }

    /** @return array<int, array<string, mixed>> */
    private function snapshot(Product $product): array
    {
        return $product->images()->get()->map(fn ($image) => [
            'id' => $image->id, 'path' => $image->path, 'sort_order' => (int) $image->sort_order, 'is_primary' => (bool) $image->is_primary,
        ])->all();
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

    private function media(string $path, array $attributes = [], bool $store = true): Media
    {
        if ($store) {
            Storage::disk('public')->put($path, 'file');
        }

        $name = basename(str_replace('\\', '/', $path)) ?: 'item';

        return Media::create($attributes + [
            'file_name' => $name,
            'file_original_name' => 'photo-'.$name,
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
