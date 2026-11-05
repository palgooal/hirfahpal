<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VP-DATA-01: the stored store logo on the public vendors directory and vendor page, with the store name's initial
 * as the fallback whenever there is no usable logo (none, empty, missing file, or a path outside the vendor's own
 * logo locations). Both pages resolve the logo the same way (VendorProfile::publicLogoUrl()).
 */
class StorefrontVendorLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_stored_library_logo_is_shown_on_both_pages(): void
    {
        $vendor = $this->vendor('Olive Workshop', 'olive-workshop');
        $path = $this->storeLogo($vendor, "vendors/media/{$vendor->id}/logo.png");
        $url = Storage::disk('public')->url($path);

        foreach ([route('vendors'), route('vendors.profile', ['storeSlug' => 'olive-workshop'])] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('<img src="'.e($url).'" alt=""', $html, $page);
            $this->assertDoesNotMatchRegularExpression('/<span aria-hidden="true"\s+class="[^"]*rounded-full[^"]*">O<\/span>/', $html, $page);
        }
    }

    public function test_a_logo_from_the_retired_upload_folder_is_still_shown(): void
    {
        $vendor = $this->vendor('Olive Workshop', 'olive-workshop');
        $path = $this->storeLogo($vendor, "vendors/profile/logos/{$vendor->id}/old.webp");

        $this->get(route('vendors'))->assertOk()->assertSee('<img src="'.e(Storage::disk('public')->url($path)).'"', false);
    }

    public function test_without_a_usable_logo_the_store_names_initial_is_shown(): void
    {
        $this->vendor('متجر الزيتون', 'zaytoun');
        $this->vendor('olive workshop', 'olive');

        $directory = $this->get(route('vendors'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<span aria-hidden="true"\s+class="[^"]*rounded-full[^"]*">م<\/span>/u', $directory);
        $this->assertMatchesRegularExpression('/<span aria-hidden="true"\s+class="[^"]*rounded-full[^"]*">O<\/span>/', $directory);
        $this->assertStringNotContainsString('<img src="'.Storage::disk('public')->url('vendors'), $directory);

        $this->get(route('vendors.profile', ['storeSlug' => 'zaytoun']))->assertOk()
            ->assertSee('>م</span>', false);
        $this->assertSame('M', (new VendorProfile(['store_name' => '  mosaic']))->publicInitial());
    }

    public function test_unusable_stored_values_fall_back_to_the_initial_without_a_broken_image(): void
    {
        $vendor = $this->vendor('Olive Workshop', 'olive-workshop');
        $other = $this->vendor('Other Store', 'other-store');
        $theirs = $this->storeLogo($other, "vendors/media/{$other->id}/theirs.png");

        $cases = [
            'empty' => '',
            'missing file' => "vendors/media/{$vendor->id}/gone.png",
            'another vendor' => $theirs,
            'traversal' => "vendors/media/{$vendor->id}/../{$other->id}/theirs.png",
            'absolute' => "/vendors/media/{$vendor->id}/logo.png",
            'url' => 'https://evil.example/logo.png',
            'not an image' => "vendors/media/{$vendor->id}/notes.txt",
            'outside the logo folders' => 'media/2026/11/admin.png',
        ];
        Storage::disk('public')->put("vendors/media/{$vendor->id}/notes.txt", 'x');
        Storage::disk('public')->put('media/2026/11/admin.png', 'x');

        foreach ($cases as $case => $stored) {
            $vendor->profile()->update(['logo' => $stored]);
            $profile = $vendor->profile()->first();

            $this->assertNull($profile->publicLogoUrl(), $case);
            $html = $this->get(route('vendors.profile', ['storeSlug' => 'olive-workshop']))->assertOk()->getContent();
            $this->assertStringContainsString('>O</span>', $html, $case);
            $this->assertStringNotContainsString('evil.example', $html, $case);
        }

        // The other vendor's own page still shows its own logo.
        $this->get(route('vendors.profile', ['storeSlug' => 'other-store']))->assertSee(e(Storage::disk('public')->url($theirs)), false);
    }

    public function test_a_logo_removed_in_my_store_falls_back_to_the_initial(): void
    {
        $vendor = $this->vendor('Olive Workshop', 'olive-workshop');
        $path = "vendors/media/{$vendor->id}/chosen.png";
        Storage::disk('public')->put($path, 'image');
        $media = Media::create([
            'file_name' => 'chosen.png', 'file_original_name' => 'chosen.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $media->id])->assertOk();
        $this->get(route('vendors'))->assertSee('<img src="'.e(Storage::disk('public')->url($path)).'"', false);

        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();
        $this->get(route('vendors'))->assertDontSee('<img src="'.e(Storage::disk('public')->url($path)).'"', false)->assertSee('>O</span>', false);
        Storage::disk('public')->assertExists($path); // the library image itself is kept
    }

    public function test_only_visible_stores_show_and_no_private_data_or_disk_paths_are_exposed(): void
    {
        $visible = $this->vendor('Olive Workshop', 'olive-workshop');
        $this->storeLogo($visible, "vendors/media/{$visible->id}/logo.png");
        $pending = $this->vendor('Pending Store', 'pending-store', 'pending');
        $pendingLogo = $this->storeLogo($pending, "vendors/media/{$pending->id}/logo.png");

        $html = $this->get(route('vendors'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Pending Store', $html);
        $this->assertStringNotContainsString(Storage::disk('public')->url($pendingLogo), $html);
        $this->get(route('vendors.profile', ['storeSlug' => 'pending-store']))->assertNotFound();

        foreach ([route('vendors'), route('vendors.profile', ['storeSlug' => 'olive-workshop'])] as $page) {
            $this->get($page)->assertOk()
                ->assertDontSee($visible->email)->assertDontSee($visible->phone)
                ->assertDontSee(str_replace('\\', '/', storage_path()), false)
                ->assertDontSee(storage_path(), false);
        }
    }

    public function test_logos_add_no_database_queries_per_store(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('vendors'))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $first = $this->vendor('Store One', 'store-one');
        $this->storeLogo($first, "vendors/media/{$first->id}/logo.png");
        $withOne = $count();

        foreach (['two', 'three', 'four'] as $name) {
            $vendor = $this->vendor('Store '.$name, 'store-'.$name);
            $this->storeLogo($vendor, "vendors/media/{$vendor->id}/logo.png");
        }

        $this->assertSame($withOne, $count());
    }

    private function vendor(string $storeName, string $slug, string $approval = 'approved'): Vendor
    {
        $vendor = Vendor::factory()->withProfile($approval)->create(['status' => 'active']);
        $vendor->profile()->update(['store_name' => $storeName, 'slug' => $slug, 'logo' => null]);

        return $vendor;
    }

    private function storeLogo(Vendor $vendor, string $path): string
    {
        Storage::disk('public')->put($path, 'image');
        $vendor->profile()->update(['logo' => $path]);

        return $path;
    }
}
