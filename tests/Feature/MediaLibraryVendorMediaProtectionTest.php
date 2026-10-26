<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Vendor;
use App\Support\Media\VendorMediaPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MEDIA-SECURITY-01: the admin media library never deletes vendor-owned media (vendors/media/{vendorId}/...),
 * neither its row nor its file, whatever the admin's abilities (a super admin included). Admin media keeps its
 * existing deletion, and the vendor's own library is unchanged.
 */
class MediaLibraryVendorMediaProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- admin deletion

    public function test_an_admin_with_media_delete_cannot_delete_vendor_media(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertForbidden();

        $this->assertVendorMediaIntact($media);
    }

    public function test_a_super_admin_cannot_delete_vendor_media_either(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $super = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($super, 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertForbidden();

        $this->assertVendorMediaIntact($media);
    }

    public function test_a_bulk_delete_containing_vendor_media_is_refused_whole(): void
    {
        $vendorMedia = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $adminMedia = $this->storedMedia();

        foreach ([$this->admin(), Admin::factory()->create(['super_admin' => true])] as $admin) {
            $this->actingAs($admin, 'admin')
                ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$adminMedia->id, $vendorMedia->id]])
                ->assertForbidden();
        }

        $this->assertVendorMediaIntact($vendorMedia);
        $this->assertVendorMediaIntact($adminMedia);
    }

    public function test_spellings_that_resolve_to_a_vendor_folder_are_protected_too(): void
    {
        foreach (['/vendors/media/7/a.png', 'vendors\\media\\7\\b.png', 'media/../vendors/media/7/c.png', 'VENDORS/Media/7/d.png'] as $path) {
            $media = $this->storedMedia($path);

            $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
                ->deleteJson(route('media-library.media.destroy', $media->id))
                ->assertForbidden();

            $this->assertModelExists($media);
            $this->assertSame($path, $media->fresh()->file_path);
        }
    }

    public function test_admin_media_keeps_its_existing_deletion(): void
    {
        $single = $this->storedMedia();
        $bulk = [$this->storedMedia(), $this->storedMedia()];
        // A look-alike folder is not a vendor library.
        $lookAlike = $this->storedMedia('vendors/media-old/7/'.uniqid().'.png');

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $single->id))
            ->assertOk()
            ->assertExactJson(['message' => 'The media item was deleted.']);
        $this->assertModelMissing($single);
        Storage::disk('public')->assertMissing($single->file_path);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => array_map(fn (Media $media) => $media->id, $bulk)])
            ->assertOk()
            ->assertJsonPath('deleted', 2);
        foreach ($bulk as $media) {
            $this->assertModelMissing($media);
            Storage::disk('public')->assertMissing($media->file_path);
        }

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $lookAlike->id))
            ->assertOk();
        $this->assertModelMissing($lookAlike);
    }

    public function test_an_admin_without_media_delete_is_still_refused_as_before(): void
    {
        $media = $this->storedMedia();

        $this->actingAs($this->admin(['media.view']), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertForbidden();

        $this->assertVendorMediaIntact($media);
    }

    // ---------------------------------------------------------------- ownership rule

    /** @return array<string, array{?string, bool}> */
    public static function paths(): array
    {
        return [
            'vendor library file' => ['vendors/media/5/a.png', true],
            'multi-digit vendor id' => ['vendors/media/123/a.png', true],
            'leading slash' => ['/vendors/media/5/a.png', true],
            'backslashes' => ['vendors\\media\\5\\a.png', true],
            'empty and dot segments' => ['./vendors//media/5/./a.png', true],
            'parent segment into a vendor folder' => ['media/../vendors/media/5/a.png', true],
            'other case' => ['VENDORS/Media/5/a.png', true],
            'nested file' => ['vendors/media/5/sub/a.png', true],
            'look-alike folder name' => ['vendors/media-old/5/a.png', false],
            'look-alike prefix' => ['vendors/mediax/5/a.png', false],
            'non-numeric id' => ['vendors/media/abc/a.png', false],
            'zero id' => ['vendors/media/0/a.png', false],
            'no file' => ['vendors/media/5', false],
            'vendor folder deeper in the path' => ['media/2026/10/vendors/media/5/a.png', false],
            'parent segment out of a vendor folder' => ['vendors/media/5/../../../media/a.png', false],
            'vendor product uploads' => ['vendors/products/5/1/a.png', false],
            'prefixed folder name' => ['xvendors/media/5/a.png', false],
            'admin library' => ['media/2026/10/a.png', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('paths')]
    public function test_the_ownership_rule_matches_only_vendor_library_paths(?string $path, bool $owned): void
    {
        $this->assertSame($owned, VendorMediaPath::isVendorOwned($path));
    }

    // ---------------------------------------------------------------- vendor library unchanged

    public function test_the_vendor_library_rules_are_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $own = $this->storedMedia('vendors/media/'.$vendor->id.'/'.uniqid().'.png');
        $used = $this->storedMedia('vendors/media/'.$vendor->id.'/'.uniqid().'.png');
        $foreign = $this->storedMedia('vendors/media/'.$other->id.'/'.uniqid().'.png');
        $product = Product::create(['vendor_id' => $vendor->id, 'name' => 'Clay jug', 'slug' => 'clay-jug', 'price' => 10, 'stock_quantity' => 10, 'status' => 'active']);
        $product->images()->create(['path' => $used->file_path, 'sort_order' => 1, 'is_primary' => true]);

        // Another vendor's item: not found. An item used by a product: 409. Its own unused item: deleted.
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $foreign))->assertNotFound();
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $used))->assertStatus(409);
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $own))->assertOk();

        $this->assertModelMissing($own);
        Storage::disk('public')->assertMissing($own->file_path);
        $this->assertVendorMediaIntact($used);
        $this->assertVendorMediaIntact($foreign);
    }

    // ---------------------------------------------------------------- helpers

    private function assertVendorMediaIntact(Media $media): void
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
