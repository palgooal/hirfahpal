<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * PRODUCT-STORY-MEDIA (7B-4F-1): the admin product form's story image comes from the product vendor's own media
 * library, chosen by media id (story_image_media_id) and stored as that image's file_path in story_image_path, as the
 * gallery (P5-A) and the store logo (MYSTORE-MEDIA-01). The stored vendor decides on update, the validated one on
 * create. A file is never uploaded, copied or deleted; a path sent by the browser is never used; asking to choose and
 * remove at once is refused. A vendor library deletion refuses an image that is a story image, and the admin library
 * never deletes vendor media at all (why MediaUsageGuard needs no story check).
 */
class AdminProductStoryImageTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_ONLY = 'Each selected image must come from this vendor\'s media library.';

    private const UPLOAD_DISABLED = 'Images cannot be uploaded here. Choose them from the vendor\'s media library.';

    private const CONFLICT = 'The image list could not be read. Reload the page and try again.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');

        foreach ([1, 10] as $id) {
            Vendor::factory()->approved()->create(['id' => $id]);
        }
    }

    // ---------------------------------------------------------------- choosing

    public function test_a_vendor_library_image_becomes_the_story_image_on_create(): void
    {
        $media = $this->media('vendors/media/1/story.png');
        $files = Storage::disk('public')->allFiles();

        $this->store(['story_image_media_id' => (string) $media->id])->assertSessionHasNoErrors();

        $this->assertSame('vendors/media/1/story.png', Product::query()->sole()->story_image_path);
        $this->assertSame($files, Storage::disk('public')->allFiles(), 'Nothing is uploaded or copied.');
    }

    public function test_a_vendor_library_image_becomes_or_replaces_the_story_image_on_update(): void
    {
        $product = $this->product(1);
        $first = $this->media('vendors/media/1/first.png');
        $second = $this->media('vendors/media/1/second.png');

        $this->update($product, ['story_image_media_id' => (string) $first->id])->assertSessionHasNoErrors();
        $this->assertSame('vendors/media/1/first.png', $product->fresh()->story_image_path);

        $this->update($product, ['story_image_media_id' => (string) $second->id])->assertSessionHasNoErrors();
        $this->assertSame('vendors/media/1/second.png', $product->fresh()->story_image_path);
        $this->assertTrue(Storage::disk('public')->exists('vendors/media/1/first.png'), 'The replaced image stays in the library.');
        $this->assertNotNull(Media::query()->find($first->id));
    }

    // ---------------------------------------------------------------- refusals

    public function test_another_vendors_image_is_refused_on_create_and_update(): void
    {
        $foreign = $this->media('vendors/media/10/foreign.png');

        $this->store(['story_image_media_id' => (string) $foreign->id])
            ->assertRedirect(route('dashboard.products.create'))
            ->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
        $this->assertSame(0, Product::query()->count(), 'A refused story image leaves no product.');

        $product = $this->product(1, ['story_image_path' => 'vendors/media/1/kept.png']);
        $this->update($product, ['story_image_media_id' => (string) $foreign->id, 'name' => 'Renamed'])
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
        $this->assertSame('vendors/media/1/kept.png', $product->fresh()->story_image_path);
        $this->assertSame('Clay jug', $product->fresh()->name, 'The whole save is rolled back.');
    }

    public function test_admin_media_non_images_other_disks_look_alikes_and_missing_ids_are_refused(): void
    {
        $refused = [
            'admin image' => $this->media('media/2026/admin.png', ['uploader_id' => 1]),
            'document' => $this->media('vendors/media/1/guide.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']),
            'other disk' => $this->media('vendors/media/1/private.png', ['disk' => 'local'], false),
            'look-alike folder' => $this->media('vendors/media-old/1/old.png'),
            'non-canonical path' => $this->media('vendors/media/1/../10/a.png', [], false),
        ];
        $product = $this->product(1);

        foreach ($refused + ['missing' => 999_999] as $case => $media) {
            $id = (string) ($media instanceof Media ? $media->id : $media);

            $this->store(['story_image_media_id' => $id])->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
            $this->update($product, ['story_image_media_id' => $id])->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
            $this->assertNull($product->fresh()->story_image_path, $case);
        }

        $this->assertSame(1, Product::query()->count());
    }

    public function test_a_malformed_media_id_is_a_field_error(): void
    {
        $product = $this->product(1);

        foreach (['abc', '0', '-3', '1.5'] as $value) {
            $this->update($product, ['story_image_media_id' => $value])->assertSessionHasErrors('story_image_media_id');
        }

        $this->update($product, ['remove_story_image' => 'maybe'])->assertSessionHasErrors('remove_story_image');
        $this->assertNull($product->fresh()->story_image_path);
    }

    public function test_the_stored_vendor_decides_on_update_and_the_validated_vendor_on_create(): void
    {
        $own = $this->media('vendors/media/1/own.png');
        $other = $this->media('vendors/media/10/other.png');
        $product = $this->product(1);

        // Update: vendor 1's product takes vendor 1's image only, whichever vendor the request names.
        $this->update($product, ['story_image_media_id' => (string) $other->id])->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
        $this->update($product, ['vendor_id' => '10', 'story_image_media_id' => (string) $other->id])->assertSessionHasErrors('vendor_id');
        $this->assertNull($product->fresh()->story_image_path);
        $this->assertSame(1, $product->fresh()->vendor_id);
        $this->update($product, ['story_image_media_id' => (string) $own->id])->assertSessionHasNoErrors();
        $this->assertSame('vendors/media/1/own.png', $product->fresh()->story_image_path);

        // Create: the chosen (validated) vendor's library.
        $this->store(['vendor_id' => '10', 'story_image_media_id' => (string) $own->id])->assertSessionHasErrors(['story_image_media_id' => self::VENDOR_ONLY]);
        $this->store(['vendor_id' => '10', 'story_image_media_id' => (string) $other->id, 'name' => 'Other jug'])->assertSessionHasNoErrors();
        $this->assertSame('vendors/media/10/other.png', Product::query()->where('name', 'Other jug')->sole()->story_image_path);
    }

    public function test_a_path_sent_by_the_browser_is_never_used(): void
    {
        $this->media('vendors/media/1/real.png');
        $product = $this->product(1);

        $this->update($product, ['story_image_path' => 'vendors/media/1/real.png'])->assertSessionHasNoErrors();
        $this->store(['story_image_path' => 'vendors/media/1/real.png'])->assertSessionHasNoErrors();

        $this->assertSame([null, null], Product::query()->orderBy('id')->pluck('story_image_path')->all());
    }

    // ---------------------------------------------------------------- removal and preservation

    public function test_removal_empties_the_column_and_keeps_the_image_in_the_library(): void
    {
        $media = $this->media('vendors/media/1/story.png');
        $product = $this->product(1, ['story_image_path' => 'vendors/media/1/story.png']);

        $this->update($product, ['remove_story_image' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->story_image_path);
        $this->assertNotNull(Media::query()->find($media->id));
        $this->assertTrue(Storage::disk('public')->exists('vendors/media/1/story.png'));
    }

    public function test_the_story_image_is_kept_when_nothing_is_asked(): void
    {
        $this->media('vendors/media/1/story.png');
        $product = $this->product(1, ['story_image_path' => 'vendors/media/1/story.png']);
        // A legacy value outside the library (an earlier direct upload) is kept too: it is only replaced when asked.
        $legacy = $this->product(1, ['story_image_path' => 'product-stories/old.webp']);

        foreach ([[], ['remove_story_image' => '0'], ['story_image_media_id' => '', 'remove_story_image' => '']] as $fields) {
            $this->update($product, $fields + ['name' => 'Renamed jug'])->assertSessionHasNoErrors();
            $this->update($legacy, $fields)->assertSessionHasNoErrors();

            $this->assertSame('vendors/media/1/story.png', $product->fresh()->story_image_path, json_encode($fields));
            $this->assertSame('product-stories/old.webp', $legacy->fresh()->story_image_path, json_encode($fields));
        }
        $this->assertSame('Renamed jug', $product->fresh()->name);
    }

    public function test_choosing_and_removing_at_once_is_refused(): void
    {
        $media = $this->media('vendors/media/1/new.png');
        $product = $this->product(1, ['story_image_path' => 'vendors/media/1/kept.png']);

        $this->update($product, ['story_image_media_id' => (string) $media->id, 'remove_story_image' => '1'])
            ->assertSessionHasErrors(['story_image_media_id' => self::CONFLICT]);
        $this->store(['story_image_media_id' => (string) $media->id, 'remove_story_image' => '1'])
            ->assertSessionHasErrors(['story_image_media_id' => self::CONFLICT]);

        $this->assertSame('vendors/media/1/kept.png', $product->fresh()->story_image_path);
        $this->assertSame(1, Product::query()->count());
    }

    // ---------------------------------------------------------------- no upload

    public function test_a_direct_upload_is_refused_and_nothing_is_stored(): void
    {
        $product = $this->product(1, ['story_image_path' => 'vendors/media/1/kept.png']);
        $files = Storage::disk('public')->allFiles();

        $this->store(['story_image' => UploadedFile::fake()->image('story.png')])
            ->assertSessionHasErrors(['story_image' => self::UPLOAD_DISABLED]);
        $this->update($product, ['story_image' => UploadedFile::fake()->image('story.png')])
            ->assertSessionHasErrors(['story_image' => self::UPLOAD_DISABLED]);

        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles('product-stories'));
        $this->assertSame(1, Product::query()->count());
        $this->assertSame('vendors/media/1/kept.png', $product->fresh()->story_image_path);
        $this->assertStringNotContainsString("store('product-stories'", file_get_contents(app_path('Http/Controllers/Dashboard/ProductManagementController.php')));
    }

    // ---------------------------------------------------------------- deletion protection

    public function test_the_vendor_cannot_delete_an_image_used_as_a_story_image_until_it_is_unlinked(): void
    {
        $media = $this->media('vendors/media/1/story.png');
        $product = $this->product(1);
        $this->update($product, ['story_image_media_id' => (string) $media->id])->assertSessionHasNoErrors();
        $vendor = Vendor::query()->findOrFail(1);

        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media))
            ->assertStatus(409);
        $this->assertNotNull(Media::query()->find($media->id));
        $this->assertTrue(Storage::disk('public')->exists('vendors/media/1/story.png'));

        $this->update($product, ['remove_story_image' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media))->assertOk();
        $this->assertNull(Media::query()->find($media->id));
    }

    public function test_the_admin_library_never_deletes_a_story_image_because_it_is_vendor_media(): void
    {
        $media = $this->media('vendors/media/1/story.png');
        $this->product(1, ['story_image_path' => 'vendors/media/1/story.png']);

        $this->actingAs($this->superAdmin(), 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertForbidden();
        $this->actingAs($this->superAdmin(), 'admin')->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]])->assertForbidden();

        $this->assertNotNull(Media::query()->find($media->id));
        $this->assertTrue(Storage::disk('public')->exists('vendors/media/1/story.png'));
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
            'category_id' => (string) $this->category(),
            'name' => 'Clay jug',
            'price' => '10',
            'stock_quantity' => '4',
            'status' => 'draft',
        ];
    }

    private function product(int $vendorId, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $vendorId, 'category_id' => $this->category(), 'name' => 'Clay jug', 'slug' => 'clay-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 4, 'status' => 'draft',
        ]);
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    private function media(string $path, array $attributes = [], bool $store = true): Media
    {
        if ($store) {
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

    private function category(): int
    {
        return Category::query()->firstOrCreate(['slug' => 'fixture-category'], ['name' => 'Pottery', 'is_active' => true])->id;
    }
}
