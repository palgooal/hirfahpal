<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P2-Security: category images come from the admin media library only. Vendor-owned
 * media (vendors/media/{vendorId}/..., in any spelling the storage resolves the same way) is refused with a field
 * error on image_media_id, for every admin including a super admin, and the category keeps its image. The
 * existing checks (media.view, existence, allowed image, removal, conflict) are unchanged.
 */
class CategoryVendorMediaOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_OWNED = 'This image belongs to a vendor. Choose an image from the admin media library.';

    private const MISSING = 'The selected image no longer exists. Choose another image.';

    private const INVALID = 'The selected file is not a supported image (JPG, PNG, GIF or WebP).';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- admin media

    public function test_admin_media_can_be_assigned_on_create_and_update(): void
    {
        $first = $this->media();
        $second = $this->media();
        // A look-alike folder is not a vendor library.
        $lookAlike = $this->media('vendors/media-old/7/'.uniqid().'.png');

        $this->store($this->admin(['categories.create', 'media.view']), ['name' => 'Pottery', 'image_media_id' => $first->id])->assertSessionHasNoErrors();
        $category = Category::where('name', 'Pottery')->firstOrFail();
        $this->assertSame($first->id, $category->image_media_id);

        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $category->fresh()->image_media_id);

        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $lookAlike->id])->assertSessionHasNoErrors();
        $this->assertSame($lookAlike->id, $category->fresh()->image_media_id);
    }

    // ---------------------------------------------------------------- vendor media refused

    public function test_vendor_media_cannot_be_assigned_on_create(): void
    {
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');

        $this->store($this->admin(['categories.create', 'media.view']), ['name' => 'Pottery', 'image_media_id' => $vendorMedia->id])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasErrors(['image_media_id' => self::VENDOR_OWNED]);

        $this->assertDatabaseMissing('categories', ['name' => 'Pottery']);
    }

    public function test_a_super_admin_cannot_bypass_the_rule(): void
    {
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');
        $old = $this->media();
        $category = $this->category('Glass', $old);
        $super = Admin::factory()->create(['super_admin' => true]);

        $this->store($super, ['name' => 'Pottery', 'image_media_id' => $vendorMedia->id])
            ->assertSessionHasErrors(['image_media_id' => self::VENDOR_OWNED]);
        $this->update($super, $category, ['name' => 'Changed', 'image_media_id' => $vendorMedia->id])
            ->assertSessionHasErrors(['image_media_id' => self::VENDOR_OWNED]);

        $this->assertDatabaseMissing('categories', ['name' => 'Pottery']);
        $this->assertSame($old->id, $category->fresh()->image_media_id);
        $this->assertSame('Glass', $category->fresh()->name);
    }

    public function test_a_crafted_json_request_gets_422_and_the_category_keeps_its_image(): void
    {
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');
        $old = $this->media();
        $category = $this->category('Pottery', $old);

        $this->actingAs($this->admin(['categories.edit', 'media.view']), 'admin')
            ->putJson(route('dashboard.categories.update', $category), [
                'name' => 'Changed', 'slug' => 'pottery', 'parent_id' => null, 'image_media_id' => $vendorMedia->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_media_id' => self::VENDOR_OWNED]);

        $fresh = $category->fresh();
        $this->assertSame($old->id, $fresh->image_media_id);
        $this->assertSame('Pottery', $fresh->name, 'Nothing else is saved either.');
        $this->assertModelExists($vendorMedia);
    }

    public function test_equivalent_spellings_of_a_vendor_path_are_refused(): void
    {
        $category = $this->category('Pottery', null);

        foreach (['/vendors/media/7/a.png', 'vendors\\media\\7\\b.png', 'media/../vendors/media/7/c.png', 'VENDORS/Media/7/d.png'] as $path) {
            $media = $this->media($path);

            $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $media->id])
                ->assertSessionHasErrors(['image_media_id' => self::VENDOR_OWNED]);
            $this->assertNull($category->fresh()->image_media_id, $path);
        }
    }

    public function test_the_refusal_is_shown_in_arabic_and_in_english(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');
        $category = $this->category('Pottery', null);
        $admin = $this->admin(['categories.edit', 'media.view']);

        foreach ([
            'ar' => 'هذه الصورة تابعة لأحد البائعين. يرجى اختيار صورة من مكتبة وسائط الإدارة.',
            'en' => self::VENDOR_OWNED,
        ] as $locale => $message) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->putJson(route('dashboard.categories.update', $category), ['name' => 'Pottery', 'slug' => 'pottery', 'parent_id' => null, 'image_media_id' => $vendorMedia->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['image_media_id' => $message]);
        }

        $this->assertNull($category->fresh()->image_media_id);
    }

    // ---------------------------------------------------------------- existing behaviour unchanged

    public function test_removing_the_image_still_works(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);

        $this->update($this->admin(['categories.edit']), $category, ['remove_image' => '1', 'image_media_id' => ''])->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->image_media_id);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_an_unchanged_image_is_still_no_change(): void
    {
        // A link made before this rule (here forced directly) is not touched while the field is unchanged.
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');
        $category = $this->category('Pottery', $vendorMedia);

        $this->update($this->admin(['categories.edit']), $category, ['name' => 'Renamed', 'image_media_id' => $vendorMedia->id])->assertSessionHasNoErrors();

        $this->assertSame($vendorMedia->id, $category->fresh()->image_media_id);
        $this->assertSame('Renamed', $category->fresh()->name);
    }

    public function test_the_existing_checks_come_first_and_are_unchanged(): void
    {
        $category = $this->category('Pottery', null);
        $vendorMedia = $this->media('vendors/media/7/'.uniqid().'.png');
        $pdf = $this->media(null, ['file_type' => 'document', 'mime_type' => 'application/pdf']);

        // Without media.view: 403 before anything is read, vendor media included.
        $this->update($this->admin(['categories.edit']), $category, ['image_media_id' => $vendorMedia->id])->assertForbidden();
        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => 999999])
            ->assertSessionHasErrors(['image_media_id' => self::MISSING]);
        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $pdf->id])
            ->assertSessionHasErrors(['image_media_id' => self::INVALID]);
        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $vendorMedia->id, 'remove_image' => '1'])
            ->assertSessionHasErrors('image_media_id');

        $this->assertNull($category->fresh()->image_media_id);
    }

    // ---------------------------------------------------------------- other protections unchanged

    public function test_the_vendor_library_and_the_category_deletion_protection_are_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $own = $this->media('vendors/media/'.$vendor->id.'/'.uniqid().'.png');
        $used = $this->media();
        $this->category('Pottery', $used);

        $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.products.media.library'))
            ->assertOk()
            ->assertJsonPath('images.0.id', $own->id);
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $own))->assertOk();
        $this->assertModelMissing($own);

        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')
            ->deleteJson(route('media-library.media.destroy', $used->id))
            ->assertStatus(409);
        $this->assertModelExists($used);
        Storage::disk('public')->assertExists($used->file_path);
    }

    // ---------------------------------------------------------------- helpers

    private function store(Admin $admin, array $data)
    {
        return $this->actingAs($admin, 'admin')->from(route('dashboard.categories.create'))->post(route('dashboard.categories.store'), $data);
    }

    private function update(Admin $admin, Category $category, array $data)
    {
        $fresh = $category->fresh();

        return $this->actingAs($admin, 'admin')->from(route('dashboard.categories.edit', $category))
            ->put(route('dashboard.categories.update', $category), $data + ['name' => $fresh->name, 'slug' => $fresh->slug, 'parent_id' => $fresh->parent_id]);
    }

    private function admin(array $abilities = []): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function category(string $name, ?Media $image): Category
    {
        $category = Category::create(['name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
        $category->forceFill(['image_media_id' => $image?->id])->save();

        return $category->fresh();
    }

    private function media(?string $path = null, array $attributes = []): Media
    {
        $path ??= 'media/2026/10/'.uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'file');
        $name = basename(str_replace('\\', '/', $path));

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
}
