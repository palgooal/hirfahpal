<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Http\Controllers\MediaController as PackageMediaController;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P2: the admin media list (dashboard/media-library/media, also read by the shared
 * picker) holds admin media only. Vendor media (vendors/media/{vendorId}/..., in any spelling the storage
 * resolves the same way) is left out before paginating, whatever its uploader_id. The JSON contract, search,
 * type filter, page size and the existing upload, edit and delete behaviour are unchanged.
 */
class MediaLibraryAdminListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- admin media only

    public function test_admin_media_is_listed_and_vendor_media_is_not(): void
    {
        $admin = $this->storedMedia();
        $vendor1 = $this->storedMedia('vendors/media/1/'.uniqid().'.png');
        $vendor10 = $this->storedMedia('vendors/media/10/'.uniqid().'.png');

        $ids = $this->ids($this->list());

        $this->assertSame([$admin->id], $ids);
        $this->assertNotContains($vendor1->id, $ids);
        $this->assertNotContains($vendor10->id, $ids);
    }

    public function test_vendor_media_with_an_admin_uploader_id_is_left_out_and_never_named(): void
    {
        $uploader = $this->admin();
        $mine = $this->storedMedia(null, ['uploader_id' => $uploader->id]);
        // A vendor whose id equals the admin's id: uploader_id says nothing about ownership.
        $this->storedMedia('vendors/media/'.$uploader->id.'/'.uniqid().'.png', ['uploader_id' => $uploader->id]);

        $data = $this->list()->json('data');

        $this->assertSame([$mine->id], array_column($data, 'id'));
        $this->assertSame($uploader->name, $data[0]['uploader_name']);
        $this->assertStringNotContainsString('vendors/media', json_encode($data));
    }

    public function test_equivalent_spellings_of_vendor_paths_are_left_out(): void
    {
        foreach ([
            '/vendors/media/7/a.png',
            'vendors\\media\\7\\b.png',
            'media/../vendors/media/7/c.png',
            'VENDORS/Media/7/d.png',
            './vendors/media/7/e.png',
            'vendors//media/7/f.png',
            'vendors/media/7/g.png/',
        ] as $path) {
            $this->storedMedia($path);
        }
        // Admin paths that only look similar stay listed.
        $kept = [
            $this->storedMedia('vendors/media-old/7/'.uniqid().'.png'),
            $this->storedMedia('media/2026/10/vendors-'.uniqid().'.png'),
            $this->storedMedia('media/2026/10/'.uniqid().'_media.png'),
        ];

        $this->assertEqualsCanonicalizing(array_map(fn (Media $media) => $media->id, $kept), $this->ids($this->list()));
    }

    // ---------------------------------------------------------------- search, pages, type

    public function test_search_is_scoped_to_admin_media(): void
    {
        $admin = $this->storedMedia(null, ['file_original_name' => 'clay-jug.png']);
        $this->storedMedia('vendors/media/3/'.uniqid().'.png', ['file_original_name' => 'clay-jug-vendor.png']);
        $this->storedMedia(null, ['file_original_name' => 'basket.png']);

        $this->assertSame([$admin->id], $this->ids($this->list(['search' => 'clay'])));
        $this->assertSame([$admin->id], $this->ids($this->list(['q' => 'clay'])));
    }

    public function test_totals_and_pages_count_admin_media_only(): void
    {
        $admin = [];
        for ($i = 0; $i < 3; $i++) {
            $admin[] = $this->storedMedia(null, ['created_at' => now()->subMinutes(10 - $i)]);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->storedMedia('vendors/media/4/'.uniqid().'.png', ['created_at' => now()->subMinutes(5 - $i)]);
        }

        $first = $this->list(['per_page' => 2, 'page' => 1]);
        $second = $this->list(['per_page' => 2, 'page' => 2]);

        $first->assertJsonPath('total', 3)->assertJsonPath('last_page', 2)->assertJsonPath('per_page', 2);
        $this->assertSame([$admin[2]->id, $admin[1]->id], $this->ids($first), 'Newest first, as before.');
        $this->assertSame([$admin[0]->id], $this->ids($second));
        $this->list(['per_page' => 500])->assertJsonPath('per_page', 40);
    }

    public function test_the_type_filter_is_unchanged(): void
    {
        $image = $this->storedMedia();
        $this->storedMedia(null, ['file_type' => 'document', 'mime_type' => 'application/pdf']);

        $this->assertSame([$image->id], $this->ids($this->list(['type' => 'image'])));
        $this->assertCount(2, $this->list()->json('data'));
        $this->actingAs($this->admin(), 'admin')
            ->getJson(route('media-library.media.index', ['type' => 'nope']))
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------------- contract and access

    public function test_the_json_contract_matches_the_package_list(): void
    {
        $uploader = $this->admin();
        $this->storedMedia(null, ['uploader_id' => $uploader->id, 'alt' => 'Alt']);
        $this->storedMedia();
        $request = Request::create('/dashboard/media-library/media', 'GET', ['type' => 'image', 'per_page' => 1, 'page' => 2]);
        $request->headers->set('Accept', 'application/json');
        $this->actingAs($uploader, 'admin');
        app()->instance('request', $request);

        // The package's own list, called directly (the container binds the application's controller in its place).
        $package = (new PackageMediaController)->index($request)->getData(true);
        $ours = $this->list(['type' => 'image', 'per_page' => 1, 'page' => 2], $uploader)->json();

        $this->assertSame(array_keys($package), array_keys($ours));
        foreach (array_diff(array_keys($package), ['data', 'links', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'path']) as $key) {
            $this->assertSame($package[$key], $ours[$key], $key);
        }
        $this->assertSame(array_column($package['data'], 'id'), array_column($ours['data'], 'id'));
        foreach ($package['data'] as $index => $item) {
            $this->assertSame($item, array_diff_key($ours['data'][$index], ['uploader_name' => true]));
            $this->assertArrayHasKey('uploader_name', $ours['data'][$index]);
        }
    }

    public function test_access_to_the_list_is_unchanged(): void
    {
        $this->getJson(route('media-library.media.index'))->assertUnauthorized();
        $this->actingAs($this->admin([]), 'admin')->getJson(route('media-library.media.index'))->assertForbidden();
        $this->actingAs($this->admin(), 'admin')->get(route('media-library.page'))->assertOk();
    }

    // ---------------------------------------------------------------- shared picker

    public function test_the_category_picker_list_holds_admin_media_only(): void
    {
        $admin = $this->storedMedia(null, ['file_original_name' => 'bowl.png']);
        $this->storedMedia('vendors/media/5/'.uniqid().'.png', ['file_original_name' => 'bowl-vendor.png']);

        // The picker's own request (admin-media-picker.js): page, per_page 24, type image, optional search.
        $this->assertSame([$admin->id], $this->ids($this->list(['page' => 1, 'per_page' => 24, 'type' => 'image'])));
        $this->assertSame([$admin->id], $this->ids($this->list(['page' => 1, 'per_page' => 24, 'type' => 'image', 'search' => 'bowl'])));
    }

    public function test_an_existing_admin_category_image_is_still_shown_in_the_picker(): void
    {
        $media = $this->storedMedia(null, ['file_original_name' => 'kept.png']);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $category->forceFill(['image_media_id' => $media->id])->save();
        $super = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($super, 'admin')
            ->get(route('dashboard.categories.edit', $category))
            ->assertOk()
            ->assertSee('name="image_media_id" value="'.$media->id.'"', false)
            ->assertSee('kept.png');
    }

    // ---------------------------------------------------------------- other behaviour unchanged

    public function test_admin_upload_edit_and_delete_are_unchanged(): void
    {
        $admin = $this->admin(['media.view', 'media.create', 'media.edit', 'media.delete']);

        $upload = $this->actingAs($admin, 'admin')
            ->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('new.png', 20, 20)])
            ->assertCreated();
        $uploaded = Media::findOrFail($upload->json('id'));
        $this->assertStringStartsWith('media/', $uploaded->file_path);
        $this->assertContains($uploaded->id, $this->ids($this->list([], $admin)));

        $this->actingAs($admin, 'admin')->putJson(route('media-library.media.update', $uploaded->id), ['alt' => 'Edited'])->assertOk();
        $this->assertSame('Edited', $uploaded->fresh()->alt);

        $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.destroy', $uploaded->id))->assertOk();
        $this->assertModelMissing($uploaded);
    }

    public function test_vendor_media_stays_protected_for_every_admin(): void
    {
        $media = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $super = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($super, 'admin')->putJson(route('media-library.media.update', $media->id), ['alt' => 'x'])->assertForbidden();
        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertForbidden();
        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]])->assertForbidden();

        $this->assertModelExists($media);
        $this->assertNull($media->fresh()->alt);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_the_vendor_dashboard_list_is_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $own = $this->storedMedia('vendors/media/'.$vendor->id.'/'.uniqid().'.png');
        $this->storedMedia('vendors/media/'.($vendor->id + 1000).'/'.uniqid().'.png');
        $this->storedMedia();

        $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.products.media.library'))
            ->assertOk()
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0.id', $own->id);
    }

    // ---------------------------------------------------------------- helpers

    private function list(array $query = [], ?Admin $admin = null): TestResponse
    {
        return $this->actingAs($admin ?? $this->admin(), 'admin')
            ->getJson(route('media-library.media.index', $query))
            ->assertOk();
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return array_column($response->json('data'), 'id');
    }

    private function admin(array $abilities = ['media.view']): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function storedMedia(?string $path = null, array $attributes = []): Media
    {
        $path ??= 'media/2026/10/'.uniqid('', true).'.png';
        Storage::disk('public')->put($path, 'file');
        $name = basename(str_replace('\\', '/', $path)) ?: 'file.png';

        $media = Media::create($attributes + [
            'file_name' => $name,
            'file_original_name' => $name,
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);

        if (isset($attributes['created_at'])) {
            $media->forceFill(['created_at' => $attributes['created_at']])->saveQuietly();
        }

        return $media;
    }
}
