<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * MYSTORE-SEC-02B: the direct profile logo/cover upload (POST / DELETE vendor/dashboard/profile/media/{type}) is
 * retired. The store logo is chosen from the vendor's own media library (VendorStoreLogoMediaTest); image paths
 * stored before (vendors/profile/...) keep working: they are shown, kept by profile updates, and the logo can be
 * cleared without deleting any file.
 */
class VendorProfileMediaTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    public function test_the_direct_profile_media_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('vendor.dashboard.profile.media.store'));
        $this->assertFalse(Route::has('vendor.dashboard.profile.media.destroy'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/VendorDashboard/ProfileMediaController.php'));
    }

    public function test_uploading_or_deleting_through_the_old_urls_does_nothing(): void
    {
        $legacy = $this->legacyImages();

        foreach (['logo', 'cover', 'avatar'] as $type) {
            $status = $this->actingAs($this->vendor, 'vendor')
                ->post('/vendor/dashboard/profile/media/'.$type, ['file' => UploadedFile::fake()->image('new.png', 64, 64)], ['Accept' => 'application/json'])
                ->getStatusCode();
            $this->assertContains($status, [404, 405], "POST {$type}");

            $status = $this->actingAs($this->vendor, 'vendor')
                ->deleteJson('/vendor/dashboard/profile/media/'.$type)
                ->getStatusCode();
            $this->assertContains($status, [404, 405], "DELETE {$type}");
        }

        $profile = $this->vendor->profile()->first();
        $this->assertSame($legacy['logo'], $profile->logo);
        $this->assertSame($legacy['cover'], $profile->cover_image);
        Storage::disk('public')->assertExists([$legacy['logo'], $legacy['cover']]);
        // Nothing new was stored anywhere on the disk.
        $this->assertSame([$legacy['cover'], $legacy['logo']], $this->allFiles());
    }

    public function test_stored_legacy_paths_are_still_shown(): void
    {
        $legacy = $this->legacyImages();

        $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))
            ->assertOk()
            ->assertJsonPath('media.logo_url', Storage::disk('public')->url($legacy['logo']))
            ->assertJsonPath('media.cover_url', Storage::disk('public')->url($legacy['cover']));

        // The dashboard shell (sidebar and header) shows the stored logo.
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.my-store'))
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url($legacy['logo']).'"', false);
    }

    public function test_a_profile_update_keeps_the_stored_logo_and_cover(): void
    {
        $legacy = $this->legacyImages();

        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.profile.update'), [
            'name' => 'Renamed Owner',
            'store_name' => 'Renamed Store',
            'phone' => $this->vendor->phone,
        ])->assertOk();

        $profile = $this->vendor->profile()->first();
        $this->assertSame('Renamed Store', $profile->store_name);
        $this->assertSame($legacy['logo'], $profile->logo);
        $this->assertSame($legacy['cover'], $profile->cover_image);
    }

    public function test_a_legacy_logo_can_be_cleared_or_replaced_from_the_library_without_deleting_files(): void
    {
        $legacy = $this->legacyImages();

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();
        $this->assertNull($this->vendor->profile()->value('logo'));
        Storage::disk('public')->assertExists($legacy['logo']);

        $path = "vendors/media/{$this->vendor->id}/chosen.png";
        Storage::disk('public')->put($path, 'image');
        $media = Media::create([
            'file_name' => 'chosen.png', 'file_original_name' => 'chosen.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $this->vendor->id,
        ]);
        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $media->id])->assertOk();

        $profile = $this->vendor->profile()->first();
        $this->assertSame($path, $profile->logo);
        $this->assertSame($legacy['cover'], $profile->cover_image);
        Storage::disk('public')->assertExists([$legacy['logo'], $legacy['cover'], $path]);
    }

    /** @return array{logo: string, cover: string} paths stored by the retired upload, with their files */
    private function legacyImages(): array
    {
        $legacy = [
            'logo' => "vendors/profile/logos/{$this->vendor->id}/old-logo.png",
            'cover' => "vendors/profile/covers/{$this->vendor->id}/old-cover.jpg",
        ];
        Storage::disk('public')->put($legacy['logo'], 'logo');
        Storage::disk('public')->put($legacy['cover'], 'cover');
        $this->vendor->profile()->update(['logo' => $legacy['logo'], 'cover_image' => $legacy['cover']]);

        return $legacy;
    }

    /** @return list<string> */
    private function allFiles(): array
    {
        $files = Storage::disk('public')->allFiles();
        sort($files);

        return $files;
    }
}
