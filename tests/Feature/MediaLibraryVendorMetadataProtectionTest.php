<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P1: vendor-owned media (vendors/media/{vendorId}/...) is read-only in the admin
 * media library. No admin, a super admin included, can update its metadata; reading it is unchanged. Admin
 * media stays editable, and the deletion protections and the vendor's own library are unchanged.
 */
class MediaLibraryVendorMetadataProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const METADATA = ['file_original_name', 'alt', 'title', 'caption', 'description'];

    private const CHANGES = [
        'file_original_name' => 'renamed.png',
        'alt' => 'Changed alt',
        'title' => 'Changed title',
        'caption' => 'Changed caption',
        'description' => 'Changed description',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- vendor media is read-only

    public function test_an_admin_with_media_edit_cannot_update_vendor_media(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $before = $media->only(self::METADATA);

        foreach (['putJson', 'patchJson'] as $method) {
            $this->actingAs($this->admin(['media.view', 'media.edit']), 'admin')
                ->{$method}(route('media-library.media.update', $media->id), self::CHANGES)
                ->assertForbidden();
        }

        $this->assertSame($before, $media->fresh()->only(self::METADATA));
    }

    public function test_a_super_admin_cannot_update_vendor_media_either(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $before = $media->only(self::METADATA);

        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
            ->putJson(route('media-library.media.update', $media->id), self::CHANGES)
            ->assertForbidden();

        $this->assertSame($before, $media->fresh()->only(self::METADATA));
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_other_spellings_of_a_vendor_path_are_read_only_too(): void
    {
        foreach (['/vendors/media/7/a.png', 'vendors\\media\\7\\b.png', 'media/../vendors/media/7/c.png'] as $path) {
            $media = $this->storedMedia($path);
            $before = $media->only(self::METADATA);

            $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
                ->putJson(route('media-library.media.update', $media->id), self::CHANGES)
                ->assertForbidden();

            $this->assertSame($before, $media->fresh()->only(self::METADATA));
        }
    }

    public function test_reading_vendor_media_is_unchanged(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');

        $this->actingAs($this->admin(['media.view']), 'admin')
            ->getJson(route('media-library.media.show', $media->id))
            ->assertOk()
            ->assertJsonPath('id', $media->id);
        $this->actingAs($this->admin(['media.view']), 'admin')
            ->getJson(route('media-library.media.edit', $media->id))
            ->assertOk()
            ->assertJsonPath('id', $media->id);
    }

    // ---------------------------------------------------------------- admin media stays editable

    public function test_admin_media_metadata_stays_editable(): void
    {
        $byEditor = $this->storedMedia();
        $bySuper = $this->storedMedia();
        // A look-alike folder is not a vendor library.
        $lookAlike = $this->storedMedia('vendors/media-old/7/'.uniqid().'.png');

        $this->actingAs($this->admin(['media.view', 'media.edit']), 'admin')
            ->putJson(route('media-library.media.update', $byEditor->id), self::CHANGES)
            ->assertOk()
            ->assertJsonPath('media.alt', 'Changed alt');
        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
            ->patchJson(route('media-library.media.update', $bySuper->id), ['title' => 'Super title'])
            ->assertOk();
        $this->actingAs($this->admin(['media.view', 'media.edit']), 'admin')
            ->putJson(route('media-library.media.update', $lookAlike->id), ['alt' => 'Look-alike'])
            ->assertOk();

        $this->assertSame(self::CHANGES, $byEditor->fresh()->only(self::METADATA));
        $this->assertSame('Super title', $bySuper->fresh()->title);
        $this->assertSame('Look-alike', $lookAlike->fresh()->alt);
    }

    public function test_the_existing_update_rules_still_apply_to_admin_media(): void
    {
        $media = $this->storedMedia();

        // Without media.edit: refused as before. A missing item: 404 as before. Invalid input: 422 as before.
        $this->actingAs($this->admin(['media.view']), 'admin')
            ->putJson(route('media-library.media.update', $media->id), ['alt' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->admin(['media.view', 'media.edit']), 'admin')
            ->putJson(route('media-library.media.update', 999999), ['alt' => 'x'])
            ->assertNotFound();
        $this->actingAs($this->admin(['media.view', 'media.edit']), 'admin')
            ->putJson(route('media-library.media.update', $media->id), ['alt' => str_repeat('a', 256)])
            ->assertUnprocessable();

        $this->assertNull($media->fresh()->alt);
    }

    // ---------------------------------------------------------------- other protections unchanged

    public function test_the_deletion_protections_are_unchanged(): void
    {
        $vendorMedia = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $referenced = $this->storedMedia();
        $vendor = Vendor::factory()->approved()->create();
        Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active'])
            ->images()->create(['path' => $referenced->file_path, 'sort_order' => 1, 'is_primary' => true]);
        $super = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $vendorMedia->id))->assertForbidden();
        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $referenced->id))->assertStatus(409);

        $this->assertModelExists($vendorMedia);
        $this->assertModelExists($referenced);
        Storage::disk('public')->assertExists($vendorMedia->file_path);
        Storage::disk('public')->assertExists($referenced->file_path);
    }

    public function test_the_vendor_library_is_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $own = $this->storedMedia('vendors/media/'.$vendor->id.'/'.uniqid().'.png');

        $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.products.media.library'))
            ->assertOk()
            ->assertJsonPath('images.0.id', $own->id);
        $this->actingAs($vendor, 'vendor')
            ->deleteJson(route('vendor.dashboard.products.media.destroy', $own))
            ->assertOk();

        $this->assertModelMissing($own);
        Storage::disk('public')->assertMissing($own->file_path);
    }

    // ---------------------------------------------------------------- helpers

    private function admin(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function storedMedia(?string $path = null): Media
    {
        $path ??= 'media/2026/10/'.uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'file');

        return Media::create([
            'file_name' => basename(str_replace('\\', '/', $path)),
            'file_original_name' => basename(str_replace('\\', '/', $path)),
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }
}
