<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\RoleUser;
use App\Models\Vendor;
use App\Support\Media\MediaUsageGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * MEDIA-SECURITY-01 Phase 2: an admin media item whose file a product image references (product_images.path) is
 * not deleted from the admin media library while the reference exists, for any admin including a super admin.
 * The comparison is exact on the normalised path, on the public disk. Category and vendor-media protection and
 * the deletion of unused media are unchanged.
 */
class MediaLibraryProductReferenceProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const IN_USE = 'This media is used and cannot be deleted. Remove it from where it is used first.';

    private int $products = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- refused

    public function test_a_product_referenced_media_item_cannot_be_deleted(): void
    {
        $media = $this->storedMedia();
        $this->productImage($media->file_path);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertStatus(409)
            ->assertExactJson(['message' => self::IN_USE, 'in_use' => [$media->id]]);

        $this->assertIntact($media);
    }

    public function test_a_super_admin_cannot_bypass_the_protection(): void
    {
        $media = $this->storedMedia();
        $this->productImage($media->file_path);

        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertStatus(409);

        $this->assertIntact($media);
    }

    public function test_a_bulk_delete_with_one_referenced_item_is_refused_whole(): void
    {
        $referenced = $this->storedMedia();
        $unused = $this->storedMedia();
        $this->productImage($referenced->file_path);

        foreach ([$this->admin(), Admin::factory()->create(['super_admin' => true])] as $admin) {
            $this->actingAs($admin, 'admin')
                ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$unused->id, $referenced->id]])
                ->assertStatus(409)
                ->assertExactJson(['message' => self::IN_USE, 'in_use' => [$referenced->id]]);
        }

        $this->assertIntact($referenced);
        $this->assertIntact($unused);
    }

    public function test_other_spellings_of_the_same_file_are_references_too(): void
    {
        foreach (['/%s', '%s', './%s'] as $index => $format) {
            $media = $this->storedMedia();
            $spelling = sprintf($format, $index === 1 ? str_replace('/', '\\', $media->file_path) : $media->file_path);
            $this->productImage($spelling);

            $this->actingAs($this->admin(), 'admin')
                ->deleteJson(route('media-library.media.destroy', $media->id))
                ->assertStatus(409);
            $this->assertIntact($media);
        }
    }

    public function test_every_product_referencing_the_file_keeps_it_protected(): void
    {
        $media = $this->storedMedia();
        $first = $this->productImage($media->file_path);
        $second = $this->productImage($media->file_path);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409);

        // Removing one association neither deletes the media nor lifts the protection.
        $first->delete();
        $this->assertIntact($media);
        $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409);

        $second->delete();
        $this->assertIntact($media);
        $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertOk();
        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    // ---------------------------------------------------------------- not a reference

    public function test_similar_looking_paths_are_not_the_same_file(): void
    {
        $media = $this->storedMedia('media/2026/10/a_b.png');

        foreach ([
            'media/2026/10/a_b.png.bak',
            'media/2026/10/xa_b.png',
            'media/2026/10/A_B.png',
            'media/2026/10/axb.png',
            'media/2026/1/a_b.png',
            'other/media/2026/10/a_b.png',
            'media/2026/10/a_b.png/extra',
        ] as $path) {
            $this->productImage($path);
        }

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertOk();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    public function test_a_name_with_like_wildcards_is_matched_exactly(): void
    {
        $media = $this->storedMedia('media/2026/10/50%_off.png');
        $this->productImage('media/2026/10/50%_off.png');
        $lookAlike = $this->storedMedia('media/2026/10/50x_off.png');

        $this->actingAs($this->admin(), 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409);
        $this->assertIntact($media);

        // Neither "%" nor "_" in the referenced name may match another file.
        $this->actingAs($this->admin(), 'admin')->deleteJson(route('media-library.media.destroy', $lookAlike->id))->assertOk();
        $this->assertModelMissing($lookAlike);
    }

    public function test_a_media_item_on_another_disk_is_not_a_product_file(): void
    {
        $media = $this->storedMedia();
        $media->forceFill(['disk' => 'local'])->save();
        $this->productImage($media->file_path);

        $this->assertSame([], (new MediaUsageGuard)->inUse([$media->id]));
    }

    public function test_unused_admin_media_is_still_deleted(): void
    {
        $media = $this->storedMedia();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertOk()
            ->assertExactJson(['message' => 'The media item was deleted.']);

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    // ---------------------------------------------------------------- other protections unchanged

    public function test_category_and_vendor_media_protection_are_unchanged(): void
    {
        $categoryMedia = $this->storedMedia();
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $category->forceFill(['image_media_id' => $categoryMedia->id])->save();
        $vendorMedia = $this->storedMedia('vendors/media/7/'.uniqid().'.png');

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $categoryMedia->id))
            ->assertStatus(409)
            ->assertExactJson(['message' => self::IN_USE, 'in_use' => [$categoryMedia->id]]);
        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
            ->deleteJson(route('media-library.media.destroy', $vendorMedia->id))
            ->assertForbidden();

        $this->assertIntact($categoryMedia);
        $this->assertIntact($vendorMedia);
        $this->assertSame([$categoryMedia->id], (new MediaUsageGuard)->inUse([$categoryMedia->id, $vendorMedia->id]));
    }

    // ---------------------------------------------------------------- helpers

    private function assertIntact(Media $media): void
    {
        $this->assertModelExists($media);
        $this->assertSame($media->file_path, $media->fresh()->file_path);
        Storage::disk('public')->assertExists($media->file_path);
    }

    private function admin(array $abilities = ['media.view', 'media.delete']): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    /** A product image on its own product, pointing at $path. */
    private function productImage(string $path): ProductImage
    {
        $number = ++$this->products;
        $product = Product::create([
            'vendor_id' => Vendor::factory()->approved()->create()->id,
            'name' => 'Product '.$number,
            'slug' => 'product-'.$number,
            'price' => 10,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        return $product->images()->create(['path' => $path, 'sort_order' => 1, 'is_primary' => true]);
    }

    private function storedMedia(?string $path = null): Media
    {
        $path ??= 'media/2026/10/'.uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'file');

        return Media::create([
            'file_name' => basename($path),
            'file_original_name' => basename($path),
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }
}
