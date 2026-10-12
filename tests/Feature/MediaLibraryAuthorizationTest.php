<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\RoleUser;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * AD-UI-CAT-M2-A (finding M1-F3): each media library action needs its own media.* ability; super admins keep
 * their bypass through Gate::before. Nothing is granted automatically.
 */
class MediaLibraryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['page', 'index', 'show', 'edit', 'store', 'update', 'destroy', 'bulk'];

    /** The actions each ability opens, and nothing else. */
    private const MATRIX = [
        'media.view' => ['page', 'index', 'show', 'edit'],
        'media.create' => ['store'],
        'media.edit' => ['update'],
        'media.delete' => ['destroy', 'bulk'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The page renders inside the admin shell since AD-UI-CAT-M3, like every other admin page test.
        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_the_application_policy_is_the_registered_one(): void
    {
        $this->assertSame(MediaPolicy::class, config('media-library.policy'));
        $this->assertInstanceOf(MediaPolicy::class, Gate::getPolicyFor(Media::class));
    }

    public function test_the_ability_catalogue_offers_the_four_media_abilities(): void
    {
        $this->assertSame(['name', 'view', 'create', 'edit', 'delete'], array_keys(app('abilities')['media']));
    }

    public function test_each_ability_opens_only_its_own_actions(): void
    {
        foreach (self::MATRIX as $ability => $allowed) {
            $admin = $this->admin([$ability]);

            foreach (self::ACTIONS as $action) {
                $media = $this->storedMedia();
                $response = $this->perform($admin, $action, $media);

                if (in_array($action, $allowed, true)) {
                    $this->assertTrue($response->isSuccessful(), "$ability should allow $action, got {$response->status()}.");
                } else {
                    $this->assertSame(403, $response->status(), "$ability should refuse $action.");
                }
            }
        }
    }

    public function test_refused_actions_change_nothing(): void
    {
        $media = $this->storedMedia();
        $viewer = $this->admin(['media.view']);

        foreach (['store', 'update', 'destroy', 'bulk'] as $action) {
            $this->perform($viewer, $action, $media)->assertForbidden();
        }

        $this->assertSame(1, Media::count());
        $this->assertNull($media->fresh()->alt);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_bulk_delete_is_refused_whole_without_the_delete_ability(): void
    {
        $items = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];
        $ids = array_map(fn (Media $media) => $media->id, $items);

        foreach ([['media.view', 'media.create', 'media.edit'], []] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')
                ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => $ids])
                ->assertForbidden();
        }

        $this->assertSame(3, Media::count());

        $this->actingAs($this->admin(['media.delete']), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        $this->assertSame(0, Media::count());
    }

    public function test_full_media_abilities_allow_every_action(): void
    {
        $admin = $this->admin(array_keys(self::MATRIX));

        foreach (self::ACTIONS as $action) {
            $this->assertTrue($this->perform($admin, $action, $this->storedMedia())->isSuccessful(), $action);
        }
    }

    public function test_super_admin_keeps_full_access_without_ability_rows(): void
    {
        $super = Admin::factory()->create(['super_admin' => true]);

        foreach (self::ACTIONS as $action) {
            $this->assertTrue($this->perform($super, $action, $this->storedMedia())->isSuccessful(), $action);
        }

        $this->assertSame(0, RoleUser::query()->where('user_id', $super->id)->count());
    }

    public function test_other_abilities_and_denied_rows_do_not_open_the_library(): void
    {
        $unrelated = $this->admin(['categories.view', 'categories.create', 'categories.edit', 'categories.delete', 'products.edit', 'settings.edit']);
        $denied = Admin::factory()->create();

        foreach (array_keys(self::MATRIX) as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $denied->id, 'ability' => 'deny']);
        }

        foreach ([$unrelated, $denied] as $admin) {
            foreach (self::ACTIONS as $action) {
                $this->perform($admin, $action, $this->storedMedia())->assertForbidden();
            }
        }
    }

    public function test_an_inactive_admin_with_every_media_ability_is_still_signed_out(): void
    {
        foreach (['blocked', 'pending'] as $status) {
            $admin = $this->admin(array_keys(self::MATRIX), $status);

            $this->actingAs($admin, 'admin')->getJson(route('media-library.media.index'))->assertUnauthorized();
            $this->assertGuest('admin');
        }
    }

    public function test_media_abilities_do_not_reach_other_admin_screens(): void
    {
        $admin = $this->admin(array_keys(self::MATRIX));

        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.index'))->assertForbidden();
    }

    private function perform(Admin $admin, string $action, Media $media): TestResponse
    {
        $this->actingAs($admin, 'admin');

        return match ($action) {
            'page' => $this->get(route('media-library.page')),
            'index' => $this->getJson(route('media-library.media.index')),
            'show' => $this->getJson(route('media-library.media.show', $media->id)),
            'edit' => $this->getJson(route('media-library.media.edit', $media->id)),
            'store' => $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('new.png')]),
            'update' => $this->putJson(route('media-library.media.update', $media->id), ['alt' => 'Changed']),
            'destroy' => $this->deleteJson(route('media-library.media.destroy', $media->id)),
            'bulk' => $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]]),
        };
    }

    private function admin(array $abilities, string $status = 'active'): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false, 'status' => $status]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function storedMedia(): Media
    {
        $name = uniqid('', true).'.png';
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, 'file');

        return Media::create([
            'file_name' => $name,
            'file_original_name' => $name,
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }
}
