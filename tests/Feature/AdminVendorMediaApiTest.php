<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Vendor;
use App\Support\Media\VendorMediaPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P3: the read-only vendor media API. Every vendor is a folder (any status, with or
 * without a profile or media), labelled by its store name or else its name; a folder holds the canonical images
 * under vendors/media/{vendorId}/ only. Both endpoints need media.view and vendors.view, nothing is written, and
 * the responses carry an allow-list of fields. The earlier media protections are unchanged.
 */
class AdminVendorMediaApiTest extends TestCase
{
    use RefreshDatabase;

    private const FOLDER_KEYS = ['approval_status', 'label', 'media_count', 'media_url', 'status', 'store_name', 'vendor_id', 'vendor_name'];

    private const MEDIA_KEYS = ['alt', 'created_at', 'file_original_name', 'height', 'id', 'mime_type', 'size', 'title', 'url', 'width'];

    private const PAGE_KEYS = ['current_page', 'data', 'last_page', 'per_page', 'total'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- folders

    public function test_every_vendor_is_a_folder_with_its_label_statuses_and_count(): void
    {
        $zeta = $this->vendor(1, 'Zed', 'active', 'approved', 'Zeta Store');
        $beta = $this->vendor(2, 'Beta', 'pending');
        $alpha = $this->vendor(3, 'Alpha', 'blocked', 'rejected', '');
        $blank = $this->vendor(4, 'Gamma', 'active', 'pending', '   ');
        $this->media('vendors/media/1/a.png');
        $this->media('vendors/media/1/b.png');
        $this->media('vendors/media/1/c.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']);
        $this->media('vendors/media/3/d.png');

        $response = $this->folders()->assertOk();

        $this->assertSame(self::PAGE_KEYS, $this->sortedKeys($response->json()));
        $this->assertSame([
            $this->folder($alpha, 'Alpha', '', 'blocked', 'rejected', 1),
            $this->folder($beta, 'Beta', null, 'pending', null, 0),
            $this->folder($blank, 'Gamma', '   ', 'active', 'pending', 0),
            $this->folder($zeta, 'Zeta Store', 'Zeta Store', 'active', 'approved', 2),
        ], $response->json('data'));
        $this->assertSame(1, $response->json('current_page'));
        $this->assertSame(1, $response->json('last_page'));
        $this->assertSame(24, $response->json('per_page'));
        $this->assertSame(4, $response->json('total'));
    }

    public function test_folders_are_sorted_by_label_then_id_and_paginate_stably(): void
    {
        foreach ([5, 2, 9, 4, 7] as $id) {
            $this->vendor($id, 'Same', 'active', 'approved', 'Pottery');
        }
        $this->vendor(1, 'Aaa');

        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->folders(['per_page' => 2, 'page' => $page])->assertOk();
            $this->assertSame(3, $response->json('last_page'));
            $this->assertSame(6, $response->json('total'));
            $ids = [...$ids, ...array_column($response->json('data'), 'vendor_id')];
        }

        $this->assertSame([1, 2, 4, 5, 7, 9], $ids);
    }

    public function test_search_matches_the_store_name_or_the_vendor_name(): void
    {
        $this->vendor(1, 'Ahmad', 'active', 'approved', 'Clay House');
        $this->vendor(2, 'Clayton');
        $this->vendor(3, 'Sara', 'active', 'approved', 'Glass Works');

        $this->assertSame([1, 2], $this->folderIds(['search' => 'clay']));
        $this->assertSame([3], $this->folderIds(['search' => 'Sara']));
        $this->assertSame([], $this->folderIds(['search' => 'nothing']));
    }

    public function test_the_status_and_has_media_filters(): void
    {
        $this->vendor(1, 'A', 'active');
        $this->vendor(2, 'B', 'pending');
        $this->vendor(3, 'C', 'blocked');
        $this->vendor(4, 'D', 'blocked');
        $this->media('vendors/media/3/a.png');
        $this->media('vendors/media/2/b.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']);

        $this->assertSame([1], $this->folderIds(['status' => 'active']));
        $this->assertSame([2], $this->folderIds(['status' => 'pending']));
        $this->assertSame([3, 4], $this->folderIds(['status' => 'blocked']));
        $this->assertSame([3], $this->folderIds(['has_media' => 1]));
        $this->assertSame([3], $this->folderIds(['has_media' => 1, 'status' => 'blocked']));
        $this->assertSame([], $this->folderIds(['has_media' => 1, 'status' => 'pending']), 'A document is not an image.');
        $this->assertSame([1, 2, 3, 4], $this->folderIds(['has_media' => 0]));
    }

    public function test_counts_hold_canonical_images_only(): void
    {
        $this->vendor(1, 'One');
        $this->vendor(10, 'Ten');
        $this->vendor(11, 'Eleven');
        $admin = $this->admin();
        $this->seedLookAlikes($admin);

        $counts = array_column($this->folders()->json('data'), 'media_count', 'vendor_id');

        $this->assertSame([11 => 0, 1 => 1, 10 => 2], $counts);
    }

    public function test_media_counts_take_a_fixed_number_of_queries(): void
    {
        $admin = $this->admin(['media.view', 'vendors.view']);
        $queries = [];

        foreach ([2, 12] as $vendors) {
            for ($i = 1; $i <= $vendors; $i++) {
                $vendor = Vendor::factory()->approved()->create();
                $this->media('vendors/media/'.$vendor->id.'/a.png');
                $this->media('vendors/media/'.$vendor->id.'/b.png');
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin, 'admin')->getJson(route('dashboard.vendor-media.folders'))->assertOk();
            $queries[] = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'media'))->count();
            DB::disableQueryLog();
        }

        // The page query and the pagination count, whatever the number of vendors.
        $this->assertSame([2, 2], $queries);
    }

    // ---------------------------------------------------------------- one folder

    public function test_a_folder_holds_its_own_canonical_images_only(): void
    {
        $this->vendor(1, 'One', 'pending', 'pending', 'One Store');
        $this->vendor(10, 'Ten');
        $this->vendor(11, 'Eleven');
        $admin = $this->admin();
        $ids = $this->seedLookAlikes($admin);

        $one = $this->vendorMedia(1)->assertOk();
        $this->assertSame([$ids['one']], array_column($one->json('data'), 'id'));
        $this->assertSame(['vendor_id' => 1, 'label' => 'One Store', 'status' => 'pending', 'approval_status' => 'pending'], $one->json('vendor'));

        $this->assertEqualsCanonicalizing($ids['ten'], array_column($this->vendorMedia(10)->json('data'), 'id'));
        $this->assertSame([], $this->vendorMedia(11)->json('data'));

        foreach ([...$ids['ten'], $ids['one']] as $id) {
            $this->assertTrue(VendorMediaPath::isVendorOwned(Media::findOrFail($id)->file_path), 'A listed item keeps its vendor protections.');
        }
    }

    public function test_a_folder_is_newest_first_searchable_and_paginated(): void
    {
        $this->vendor(7, 'Seven');
        $this->vendor(8, 'Eight');
        $old = $this->media('vendors/media/7/old.png', ['file_original_name' => 'jug-old.png', 'created_at' => now()->subDays(2)]);
        $new = $this->media('vendors/media/7/new.png', ['file_original_name' => 'jug-new.png', 'created_at' => now()]);
        $mid = $this->media('vendors/media/7/mid.png', ['file_original_name' => 'bowl.png', 'created_at' => now()->subDay()]);
        $this->media('vendors/media/8/other.png', ['file_original_name' => 'jug-other.png']);

        $this->assertSame([$new->id, $mid->id, $old->id], array_column($this->vendorMedia(7)->json('data'), 'id'));
        $this->assertSame([$new->id, $old->id], array_column($this->vendorMedia(7, ['search' => 'jug'])->json('data'), 'id'));

        $second = $this->vendorMedia(7, ['per_page' => 2, 'page' => 2])->assertOk();
        $this->assertSame([$old->id], array_column($second->json('data'), 'id'));
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3], array_intersect_key($second->json(), array_flip(['current_page', 'last_page', 'per_page', 'total'])));
    }

    public function test_media_items_carry_the_allowed_fields_only(): void
    {
        $this->vendor(7, 'Seven');
        $item = $this->media('vendors/media/7/a.png', ['alt' => 'Alt', 'title' => 'Title', 'width' => 800, 'height' => 600, 'uploader_id' => 7, 'caption' => 'Cap', 'description' => 'Desc']);

        $response = $this->vendorMedia(7)->assertOk();

        $this->assertSame([...self::PAGE_KEYS, 'vendor'], $this->sortedKeys($response->json()));
        $this->assertSame([
            'id' => $item->id,
            'url' => Storage::disk('public')->url('vendors/media/7/a.png'),
            'file_original_name' => 'photo-a.png',
            'alt' => 'Alt',
            'title' => 'Title',
            'mime_type' => 'image/png',
            'size' => 4,
            'width' => 800,
            'height' => 600,
            'created_at' => $item->fresh()->created_at->toJSON(),
        ], $response->json('data.0'));
        $this->assertSame(self::MEDIA_KEYS, $this->sortedKeys($response->json('data.0')));
        $this->assertSame(self::FOLDER_KEYS, $this->sortedKeys($this->folders()->json('data.0')));
    }

    public function test_orphan_media_and_unknown_vendors_are_not_exposed(): void
    {
        $this->vendor(1, 'One');
        $this->media('vendors/media/999/orphan.png');

        $this->assertSame([1], $this->folderIds());
        $this->assertSame(0, $this->folders()->json('data.0.media_count'));
        $this->vendorMedia(999)->assertNotFound();
        $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')->getJson('/dashboard/vendor-media/folders/0/media')->assertNotFound();
        $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')->getJson('/dashboard/vendor-media/folders/abc/media')->assertNotFound();
        $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')->getJson('/dashboard/vendor-media/folders/01/media')->assertNotFound();
        $this->assertDatabaseCount('media', 1);
    }

    // ---------------------------------------------------------------- validation

    public function test_parameters_are_validated_and_page_sizes_fall_back(): void
    {
        $this->vendor(1, 'One');

        $this->assertSame(24, $this->folders(['per_page' => 0])->assertOk()->json('per_page'));
        $this->assertSame(24, $this->folders(['per_page' => 101])->assertOk()->json('per_page'));
        $this->assertSame(100, $this->folders(['per_page' => 100])->assertOk()->json('per_page'));
        $this->assertSame(40, $this->vendorMedia(1, ['per_page' => 500])->assertOk()->json('per_page'));
        $this->assertSame(40, $this->vendorMedia(1)->json('per_page'));

        $this->folders(['per_page' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->folders(['page' => 0])->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->folders(['search' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->folders(['status' => 'deleted'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->folders(['has_media' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('has_media');
        $this->folders(['search' => ['a']])->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->vendorMedia(1, ['page' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->vendorMedia(1, ['search' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    // ---------------------------------------------------------------- security

    public function test_access_needs_an_admin_with_both_abilities(): void
    {
        $vendor = $this->vendor(1, 'One');
        $urls = [route('dashboard.vendor-media.folders'), route('dashboard.vendor-media.media', 1), route('dashboard.vendor-media.media', 999)];

        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }

        foreach ($urls as $url) {
            $this->actingAs($vendor, 'vendor')->getJson($url)->assertUnauthorized();
        }

        $this->app['auth']->forgetGuards();

        foreach ($urls as $url) {
            // The same 403 for an existing and an unknown vendor: nothing is read before the check.
            $this->actingAs($this->admin(), 'admin')->getJson($url)->assertForbidden();
            $this->actingAs($this->admin(['media.view']), 'admin')->getJson($url)->assertForbidden();
            $this->actingAs($this->admin(['vendors.view']), 'admin')->getJson($url)->assertForbidden();
            $this->actingAs($this->admin(['media.view', 'vendors.edit', 'media.edit']), 'admin')->getJson($url)->assertForbidden();
        }

        $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')->getJson($urls[0])->assertOk();
        $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')->getJson($urls[1])->assertOk();
        $super = Admin::factory()->create(['super_admin' => true]);
        $this->actingAs($super, 'admin')->getJson($urls[0])->assertOk();
        $this->actingAs($super, 'admin')->getJson($urls[1])->assertOk();
        $this->actingAs($super, 'admin')->getJson($urls[2])->assertNotFound();
    }

    public function test_only_get_routes_exist_and_nothing_is_written(): void
    {
        $this->vendor(7, 'Seven');
        $media = $this->media('vendors/media/7/a.png', ['alt' => 'Kept']);
        $super = Admin::factory()->create(['super_admin' => true]);

        foreach ([route('dashboard.vendor-media.folders'), route('dashboard.vendor-media.media', 7), url('/dashboard/vendor-media/folders/7/media/'.$media->id)] as $url) {
            foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
                $status = $this->actingAs($super, 'admin')->{$method}($url, ['alt' => 'Changed'])->status();
                $this->assertContains($status, [404, 405], $method.' '.$url);
            }
        }

        $this->actingAs($super, 'admin')->postJson(route('dashboard.vendor-media.folders'))->assertMethodNotAllowed();
        $this->actingAs($super, 'admin')->deleteJson(route('dashboard.vendor-media.media', 7))->assertMethodNotAllowed();
        $this->assertSame('Kept', $media->fresh()->alt);
        Storage::disk('public')->assertExists('vendors/media/7/a.png');

        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'dashboard/vendor-media'));
        $this->assertSame(['dashboard.vendor-media.folders', 'dashboard.vendor-media.media'], $routes->map->getName()->sort()->values()->all());
        $routes->each(fn ($route) => $this->assertSame(['GET', 'HEAD'], $route->methods()));
        $routes->each(fn ($route) => $this->assertContains('auth:admin', $route->gatherMiddleware()));
    }

    // ---------------------------------------------------------------- earlier protections unchanged

    public function test_the_earlier_media_protections_are_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $own = $this->media('vendors/media/'.$vendor->id.'/'.uniqid().'.png');
        $adminMedia = $this->media();
        $referenced = $this->media();
        Product::create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active'])
            ->images()->create(['path' => $referenced->file_path, 'sort_order' => 1, 'is_primary' => true]);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $super = Admin::factory()->create(['super_admin' => true]);

        // The admin library lists admin media only.
        $listed = array_column($this->actingAs($super, 'admin')->getJson(route('media-library.media.index'))->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$adminMedia->id, $referenced->id], $listed);

        // Vendor media keeps its metadata and deletion protections; referenced media its deletion protection.
        $this->actingAs($super, 'admin')->putJson(route('media-library.media.update', $own->id), ['alt' => 'x'])->assertForbidden();
        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $own->id))->assertForbidden();
        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $referenced->id))->assertStatus(409);

        // A category refuses vendor media.
        $this->actingAs($super, 'admin')
            ->putJson(route('dashboard.categories.update', $category), ['name' => 'Pottery', 'slug' => 'pottery', 'parent_id' => null, 'image_media_id' => $own->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_media_id');
        $this->assertNull($category->fresh()->image_media_id);

        // The vendor's own library is unchanged.
        $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.products.media.library'))
            ->assertOk()
            ->assertJsonPath('images.0.id', $own->id);

        $this->assertModelExists($own);
        $this->assertModelExists($referenced);
        $this->assertNull($own->fresh()->alt);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Vendor 1: one canonical image. Vendor 10: two. Vendor 11: none. Everything else is a look-alike, a
     * non-canonical spelling, a non-image, an orphan or admin media, and is in no folder.
     *
     * @return array{one: int, ten: list<int>}
     */
    private function seedLookAlikes(Admin $admin): array
    {
        $one = $this->media('vendors/media/1/a.png', ['uploader_id' => 10]);
        $ten = [$this->media('vendors/media/10/b.png')->id, $this->media('vendors/media/10/c.png', ['uploader_id' => 1])->id];

        foreach ([
            'VENDORS/media/1/upper.png', 'vendors/Media/10/upper.png', '/vendors/media/1/lead.png', 'vendors//media/1/double.png',
            'vendors/media/1//double.png', 'vendors/media/1/sub/deep.png', 'vendors\\media\\1\\back.png', 'vendors/media/1/x\\y.png',
            'vendors/media/01/zero.png', 'vendors/media/1 /space.png', 'vendors/media/1x/x.png', 'vendors/media/1/../10/up.png',
            'vendors/media/1/./dot.png', 'vendors/media/1/.', 'vendors/media/1/..', 'vendors/media/1/', 'vendors/media/10',
            'vendors/media-old/1/old.png', 'vendors/media/11/doc.pdf', 'vendors/media/999/orphan.png', 'media/2026/10/admin.png',
        ] as $path) {
            // Rows only: several of these paths cannot exist side by side on a disk.
            $this->media($path, str_ends_with($path, '.pdf') ? ['file_type' => 'document', 'mime_type' => 'application/pdf'] : [], false);
        }
        // uploader_id never makes media a vendor's.
        $this->media(null, ['uploader_id' => 1]);
        $this->media(null, ['uploader_id' => $admin->id]);

        return ['one' => $one->id, 'ten' => $ten];
    }

    private function folders(array $query = []): TestResponse
    {
        return $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')
            ->getJson(route('dashboard.vendor-media.folders', $query));
    }

    private function folderIds(array $query = []): array
    {
        return array_column($this->folders($query)->assertOk()->json('data'), 'vendor_id');
    }

    private function vendorMedia(int $vendorId, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin(['media.view', 'vendors.view']), 'admin')
            ->getJson(route('dashboard.vendor-media.media', ['vendor' => $vendorId] + $query));
    }

    private function folder(Vendor $vendor, string $label, ?string $storeName, string $status, ?string $approval, int $count): array
    {
        return [
            'vendor_id' => $vendor->id,
            'label' => $label,
            'store_name' => $storeName,
            'vendor_name' => $vendor->name,
            'status' => $status,
            'approval_status' => $approval,
            'media_count' => $count,
            'media_url' => route('dashboard.vendor-media.media', $vendor->id),
        ];
    }

    private function sortedKeys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }

    private function vendor(int $id, string $name, string $status = 'active', ?string $approval = null, ?string $storeName = null): Vendor
    {
        $vendor = Vendor::factory()->create(['id' => $id, 'name' => $name, 'status' => $status]);

        if ($approval !== null) {
            $vendor->profile()->create(['store_name' => $storeName ?? $name.' Store', 'slug' => 'store-'.$id, 'approval_status' => $approval]);
        }

        return $vendor;
    }

    private function admin(array $abilities = []): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function media(?string $path = null, array $attributes = [], bool $store = true): Media
    {
        $path ??= 'media/2026/10/'.uniqid('', true).'.png';

        if ($store) {
            Storage::disk('public')->put($path, 'file');
        }

        $name = basename(str_replace('\\', '/', $path));

        $media = Media::create($attributes + [
            'file_name' => $name,
            'file_original_name' => 'photo-'.$name,
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);

        if (isset($attributes['created_at'])) {
            $media->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $media;
    }
}
