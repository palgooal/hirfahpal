<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorProfileMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_upload_a_logo(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();

        $response = $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'logo'), [
                'file' => UploadedFile::fake()->create('logo.png', 128, 'image/png'),
            ])
            ->assertOk()
            ->assertJsonPath('type', 'logo');

        $path = $response->json('path');
        $this->assertStringStartsWith('vendors/profile/logos/'.$vendor->id.'/', $path);
        $this->assertSame($path, $vendor->fresh('profile')->profile->logo);
        Storage::disk('public')->assertExists($path);
    }

    public function test_vendor_can_upload_a_cover(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();

        $response = $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'cover'), [
                'file' => UploadedFile::fake()->create('cover.webp', 256, 'image/webp'),
            ])
            ->assertOk()
            ->assertJsonPath('type', 'cover');

        $path = $response->json('path');
        $this->assertStringStartsWith('vendors/profile/covers/'.$vendor->id.'/', $path);
        $this->assertSame($path, $vendor->fresh('profile')->profile->cover_image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_media_deletes_the_previous_owned_file(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $oldPath = 'vendors/profile/logos/'.$vendor->id.'/old-logo.png';
        Storage::disk('public')->put($oldPath, 'old');
        $vendor->profile()->update(['logo' => $oldPath]);

        $response = $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'logo'), [
                'file' => UploadedFile::fake()->create('new-logo.png', 128, 'image/png'),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_deleting_media_clears_the_profile_and_removes_the_owned_file(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $path = 'vendors/profile/covers/'.$vendor->id.'/cover.jpg';
        Storage::disk('public')->put($path, 'cover');
        $vendor->profile()->update(['cover_image' => $path]);

        $this->actingAs($vendor, 'vendor')
            ->deleteJson(route('vendor.dashboard.profile.media.destroy', 'cover'))
            ->assertOk()
            ->assertJsonPath('path', null)
            ->assertJsonPath('url', null);

        $this->assertNull($vendor->fresh('profile')->profile->cover_image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_replacing_legacy_or_foreign_paths_does_not_delete_them(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $legacyPath = 'logos/olive.png';
        $foreignPath = 'vendors/profile/logos/'.($vendor->id + 100).'/foreign.png';
        Storage::disk('public')->put($legacyPath, 'legacy');
        Storage::disk('public')->put($foreignPath, 'foreign');

        foreach ([$legacyPath, $foreignPath] as $path) {
            $vendor->profile()->update(['logo' => $path]);

            $this->actingAs($vendor, 'vendor')
                ->postJson(route('vendor.dashboard.profile.media.store', 'logo'), [
                    'file' => UploadedFile::fake()->create('new-logo.png', 128, 'image/png'),
                ])
                ->assertOk();

            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_media_upload_validates_file_type_and_size(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'logo'), [
                'file' => UploadedFile::fake()->create('logo.pdf', 128, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'cover'), [
                'file' => UploadedFile::fake()->create('cover.png', 4097, 'image/png'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_unapproved_vendor_cannot_mutate_profile_media(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->withProfile('pending')->create();

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.profile.media.store', 'logo'), [
                'file' => UploadedFile::fake()->create('logo.png', 128, 'image/png'),
            ])
            ->assertForbidden();

        $this->actingAs($vendor, 'vendor')
            ->deleteJson(route('vendor.dashboard.profile.media.destroy', 'logo'))
            ->assertForbidden();
    }
}
