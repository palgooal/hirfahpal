<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\MediaLibraryController;
use App\Http\Controllers\VendorDashboard\ProfileLogoController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * MYSTORE-MEDIA-01: the store logo chosen from the vendor's own media library by media id
 * (vendor.dashboard.profile.logo.update / .destroy), and the library deletion that refuses a logo image (409).
 * Row locks are not observable on SQLite: a deletion between validation and the attaching transaction is simulated
 * here; the real interleaving is VendorProductImageConcurrencyMySqlTest's logo scenarios.
 */
class VendorStoreLogoMediaTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    // ---------------------------------------------------------------- choosing the logo

    public function test_an_own_library_image_becomes_the_logo_by_its_stored_path(): void
    {
        $media = $this->library($this->vendor);

        $this->choose($media->id)
            ->assertOk()
            ->assertExactJson([
                'message' => 'Store logo updated.',
                'logo' => ['media_id' => $media->id, 'url' => Storage::disk('public')->url($media->file_path)],
            ]);

        $this->assertSame($media->file_path, $this->logo());
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_the_logo_read_by_the_profile_endpoint_is_the_chosen_image(): void
    {
        $media = $this->library($this->vendor);
        $this->choose($media->id)->assertOk();

        $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))
            ->assertOk()
            ->assertJsonPath('media.logo_url', Storage::disk('public')->url($media->file_path));
    }

    public function test_another_vendors_admin_and_non_image_media_are_refused(): void
    {
        $other = Vendor::factory()->approved()->create();
        $refused = [
            'another vendor' => $this->library($other),
            'admin' => $this->media('media/2026/11/admin-logo.png', null),
            'non-image' => $this->media("vendors/media/{$this->vendor->id}/terms.pdf", $this->vendor->id, ['file_type' => 'document', 'mime_type' => 'application/pdf']),
            'other disk' => $this->media("vendors/media/{$this->vendor->id}/private.png", $this->vendor->id, ['disk' => 'local']),
            'look-alike folder' => $this->media("vendors/media-old/{$this->vendor->id}/old.png", $this->vendor->id),
            'non-canonical path' => $this->media("vendors/media/{$this->vendor->id}/../{$other->id}/x.png", $this->vendor->id),
        ];

        foreach ($refused as $case => $media) {
            $this->choose($media->id)->assertStatus(422)->assertJsonPath('errors.media_id.0', ProfileLogoController::NOT_IN_LIBRARY);
            $this->assertNull($this->logo(), $case);
        }
    }

    public function test_missing_deleted_and_invalid_media_ids_are_refused(): void
    {
        $deleted = $this->library($this->vendor);
        $deleted->delete();

        $this->choose(999_999)->assertStatus(422)->assertJsonPath('errors.media_id.0', ProfileLogoController::NOT_IN_LIBRARY);
        $this->choose($deleted->id)->assertStatus(422)->assertJsonPath('errors.media_id.0', ProfileLogoController::NOT_IN_LIBRARY);

        foreach ([null, '', 0, -3, 'abc', '1.5', [1], true] as $invalid) {
            $this->choose($invalid)->assertStatus(422)->assertJsonValidationErrors('media_id');
        }
        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), [])
            ->assertStatus(422)->assertJsonValidationErrors('media_id');

        $this->assertNull($this->logo());
    }

    public function test_forged_paths_and_urls_are_never_used(): void
    {
        $own = $this->library($this->vendor);
        $other = $this->library(Vendor::factory()->approved()->create());

        // A path or URL instead of an id is not an id; extra keys are ignored: the logo is the id's own stored path.
        foreach (["vendors/media/{$this->vendor->id}/x.png", 'https://evil.example/logo.png', '../../.env', $own->file_path] as $forged) {
            $this->choose($forged)->assertStatus(422)->assertJsonValidationErrors('media_id');
        }
        $this->actingAs($this->vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $own->id, 'path' => $other->file_path, 'logo' => 'https://evil.example/x.png', 'url' => '/etc/passwd'])
            ->assertOk();

        $this->assertSame($own->file_path, $this->logo());
    }

    public function test_guests_customers_admins_and_unapproved_vendors_cannot_set_or_clear_the_logo(): void
    {
        $media = $this->library($this->vendor);
        $routes = [['putJson', 'vendor.dashboard.profile.logo.update'], ['deleteJson', 'vendor.dashboard.profile.logo.destroy']];

        foreach ($routes as [$method, $name]) {
            $this->{$method}(route($name), ['media_id' => $media->id])->assertUnauthorized();
            $this->actingAs(Customer::factory()->create(), 'customer')->{$method}(route($name), ['media_id' => $media->id])->assertUnauthorized();
            $this->actingAs(Admin::factory()->create(), 'admin')->{$method}(route($name), ['media_id' => $media->id])->assertUnauthorized();
        }

        $pending = Vendor::factory()->create();
        $pending->profile()->create(['store_name' => 'Pending', 'slug' => 'pending-'.uniqid(), 'approval_status' => 'pending']);
        $theirs = $this->library($pending);
        $this->actingAs($pending, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $theirs->id])->assertForbidden();

        $this->assertNull($this->logo());
        $this->assertNull($pending->profile()->value('logo'));
    }

    public function test_the_logo_routes_are_csrf_protected_web_routes(): void
    {
        foreach (['vendor.dashboard.profile.logo.update', 'vendor.dashboard.profile.logo.destroy'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            $this->assertContains('web', $middleware, $name); // the web group verifies the CSRF token
            $this->assertContains('auth:vendor', $middleware, $name);
            $this->assertContains('vendor.approved', $middleware, $name);
        }
    }

    // ---------------------------------------------------------------- clearing and replacing

    public function test_clearing_the_logo_keeps_the_library_image_and_its_file(): void
    {
        $media = $this->library($this->vendor);
        $this->choose($media->id)->assertOk();

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))
            ->assertOk()
            ->assertExactJson(['message' => 'Store logo removed.', 'logo' => null]);

        $this->assertNull($this->logo());
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
        // Clearing again is harmless.
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();
    }

    public function test_clearing_a_legacy_uploaded_logo_deletes_no_file_either(): void
    {
        $legacy = "vendors/profile/logos/{$this->vendor->id}/old.png";
        Storage::disk('public')->put($legacy, 'old');
        $this->vendor->profile()->update(['logo' => $legacy]);

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();

        $this->assertNull($this->logo());
        Storage::disk('public')->assertExists($legacy);
    }

    public function test_the_logo_image_cannot_be_deleted_from_the_library_until_it_is_released(): void
    {
        $media = $this->library($this->vendor);
        $this->choose($media->id)->assertOk();

        $this->remove($media)->assertStatus(409)->assertJsonPath('message', MediaLibraryController::USED_AS_LOGO);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);

        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();
        $this->remove($media)->assertOk();
        $this->assertModelMissing($media);
    }

    public function test_replacing_the_logo_releases_the_previous_image_for_deletion(): void
    {
        $first = $this->library($this->vendor);
        $second = $this->library($this->vendor);

        $this->choose($first->id)->assertOk();
        $this->choose($second->id)->assertOk();

        $this->assertSame($second->file_path, $this->logo());
        $this->remove($second)->assertStatus(409);
        $this->remove($first)->assertOk();
        $this->assertModelMissing($first);
        Storage::disk('public')->assertMissing($first->file_path);
    }

    public function test_a_released_logo_image_still_used_by_a_product_stays_protected_by_the_product(): void
    {
        $media = $this->library($this->vendor);
        $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $product->images()->create(['path' => $media->file_path, 'sort_order' => 0, 'is_primary' => true]);

        $this->choose($media->id)->assertOk();
        $this->choose($this->library($this->vendor)->id)->assertOk();

        $this->remove($media)->assertStatus(409)->assertJsonPath('message', 'This image is currently used by a product.');
        $this->assertModelExists($media);
    }

    public function test_another_vendors_logo_does_not_change_and_is_not_touched(): void
    {
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->library($other);
        $this->actingAs($other, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $theirs->id])->assertOk();

        $own = $this->library($this->vendor);
        $this->choose($own->id)->assertOk();
        $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.profile.logo.destroy'))->assertOk();

        $this->assertSame($theirs->file_path, $other->profile()->value('logo'));
    }

    // ---------------------------------------------------------------- the whole-profile update (MYSTORE-MEDIA-01B)

    public function test_the_profile_update_refuses_any_logo_cover_or_avatar_value(): void
    {
        $own = $this->library($this->vendor);
        $theirs = $this->library(Vendor::factory()->approved()->create());
        $values = [
            'arbitrary path' => 'vendors/profile/logos/1/x.png',
            'traversal' => '../../.env',
            'external URL' => 'https://evil.example/logo.png',
            'another vendor\'s media path' => $theirs->file_path,
            'own library path' => $own->file_path, // a valid image too: logos are chosen by media id only
            'null' => null,
            'empty' => '',
            'array' => ['x'],
        ];
        $this->vendor->profile()->update(['logo' => 'vendors/media/'.$this->vendor->id.'/saved.png', 'cover_image' => 'covers/saved.png']);
        $this->vendor->forceFill(['avatar' => 'vendors/avatars/saved.png'])->save();

        foreach (['logo', 'cover_image', 'avatar'] as $field) {
            foreach ($values as $case => $value) {
                $this->updateProfile([$field => $value, 'store_name' => 'Changed'])
                    ->assertStatus(422)
                    ->assertJsonValidationErrors($field);
                $this->assertSavedImagesAndNameUnchanged("{$field}: {$case}");
            }
        }
    }

    public function test_an_ordinary_profile_update_succeeds_and_keeps_the_chosen_logo(): void
    {
        $media = $this->library($this->vendor);
        $this->choose($media->id)->assertOk();

        $this->updateProfile(['store_name' => 'Renamed Workshop', 'short_description' => 'New text'])->assertOk();

        $profile = $this->vendor->profile()->first();
        $this->assertSame('Renamed Workshop', $profile->store_name);
        $this->assertSame('New text', $profile->short_description);
        $this->assertSame($media->file_path, $profile->logo);
        // The chosen image is still protected, and can still be changed through the media library endpoint.
        $this->remove($media)->assertStatus(409)->assertJsonPath('message', MediaLibraryController::USED_AS_LOGO);
        $other = $this->library($this->vendor);
        $this->choose($other->id)->assertOk();
        $this->assertSame($other->file_path, $this->logo());
    }

    public function test_the_profile_update_validation_message_is_arabic_on_an_arabic_page(): void
    {
        app()->setLocale('ar');

        $this->updateProfile(['logo' => 'https://evil.example/logo.png'])
            ->assertStatus(422)
            ->assertJsonPath('errors.logo.0', fn (string $message) => str_contains($message, 'يجب أن يكون حقل'));
    }

    // ---------------------------------------------------------------- concurrency (simulated; MySQL separately)

    public function test_an_image_deleted_just_before_the_attaching_transaction_is_refused(): void
    {
        $media = $this->library($this->vendor);
        $this->actingAs($this->vendor, 'vendor');
        DB::connection()->beforeStartingTransaction(function () use ($media) {
            Media::query()->whereKey($media->getKey())->delete();
        });

        $this->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $media->id])
            ->assertStatus(422)->assertJsonPath('errors.media_id.0', ProfileLogoController::NOT_IN_LIBRARY);

        $this->assertNull($this->logo());
    }

    public function test_attaching_reads_the_media_row_inside_the_transaction_before_writing_the_profile(): void
    {
        $media = $this->library($this->vendor);
        $events = [];
        DB::listen(function ($query) use (&$events) {
            $events[] = $query->sql;
        });
        DB::connection()->beforeStartingTransaction(function () use (&$events) {
            $events[] = 'BEGIN';
        });

        $this->choose($media->id)->assertOk();

        $begin = array_search('BEGIN', $events, true);
        $read = $this->firstIndex($events, fn (string $sql) => str_contains($sql, 'from "media"') && str_contains($sql, '"id" = ?'));
        $write = $this->firstIndex($events, fn (string $sql) => str_starts_with($sql, 'update "vendor_profiles"'));
        $this->assertNotFalse($begin);
        $this->assertTrue($begin < $read && $read < $write, 'BEGIN, then the media row, then the profile');

        // On MySQL the reads are locking reads (shared on the media row, FOR UPDATE on the profile).
        $rule = file_get_contents(app_path('Rules/VendorLibraryImage.php'));
        $this->assertMatchesRegularExpression('/->whereKey\(\$mediaId\)\s*->sharedLock\(\)/', $rule);
        $controller = file_get_contents(app_path('Http/Controllers/VendorDashboard/ProfileLogoController.php'));
        $this->assertMatchesRegularExpression('/lockedPathOf\(.*?\);.*?->profile\(\)->lockForUpdate\(\)/s', $controller);
        $library = file_get_contents(app_path('Http/Controllers/VendorDashboard/MediaLibraryController.php'));
        $this->assertMatchesRegularExpression("/VendorProfile::query\(\)->where\('logo', \\\$locked->file_path\)->sharedLock\(\)->exists\(\), 409/", $library);
    }

    // ---------------------------------------------------------------- helpers

    private function choose(mixed $mediaId): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.profile.logo.update'), ['media_id' => $mediaId]);
    }

    /** The My Store script's payload shape (name, store name, phone), with $changes applied. */
    private function updateProfile(array $changes): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.profile.update'), array_merge([
            'name' => $this->vendor->name,
            'store_name' => $this->vendor->profile()->value('store_name'),
            'phone' => $this->vendor->phone,
        ], $changes));
    }

    private function assertSavedImagesAndNameUnchanged(string $case): void
    {
        $profile = $this->vendor->profile()->first();

        $this->assertSame('vendors/media/'.$this->vendor->id.'/saved.png', $profile->logo, $case);
        $this->assertSame('covers/saved.png', $profile->cover_image, $case);
        $this->assertSame('vendors/avatars/saved.png', $this->vendor->fresh()->avatar, $case);
        $this->assertNotSame('Changed', $profile->store_name, $case.': the whole request is refused');
    }

    private function remove(Media $media): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media));
    }

    private function logo(): ?string
    {
        return $this->vendor->profile()->value('logo');
    }

    private function library(Vendor $vendor): Media
    {
        return $this->media("vendors/media/{$vendor->id}/".uniqid('', true).'.png', $vendor->id);
    }

    private function media(string $path, ?int $uploader, array $overrides = []): Media
    {
        Storage::disk('public')->put($path, 'image');

        return Media::create(array_merge([
            'file_name' => basename($path), 'file_original_name' => 'logo.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $uploader,
        ], $overrides));
    }

    private function firstIndex(array $items, callable $match): int|false
    {
        foreach ($items as $index => $item) {
            if ($item !== 'BEGIN' && $match($item)) {
                return $index;
            }
        }

        return false;
    }
}
