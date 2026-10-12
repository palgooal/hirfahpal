<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\MediaLibraryController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\RoleUser;
use App\Support\Media\MediaUsageGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Palgoal\MediaLibrary\Http\Controllers\MediaController as PackageMediaController;
use Palgoal\MediaLibrary\Models\Media;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * AD-UI-CAT-M2-C (finding M1-F2): a media item a category uses cannot be deleted, singly or in bulk; nothing
 * is deleted partially; files are removed only after the commit. SQLite; the MySQL races are proven in
 * MediaDeletionConcurrencyMySqlTest.
 */
class MediaLibrarySafeDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const IN_USE = 'This media is used and cannot be deleted. Remove it from where it is used first.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- wiring

    public function test_the_package_routes_resolve_to_the_application_controller(): void
    {
        $this->assertInstanceOf(MediaLibraryController::class, app(PackageMediaController::class));

        foreach (['media-library.media.destroy', 'media-library.media.bulk-destroy', 'media-library.media.index', 'media-library.media.store'] as $name) {
            $this->assertInstanceOf(MediaLibraryController::class, Route::getRoutes()->getByName($name)->getController(), $name);
        }
    }

    public function test_every_foreign_key_to_media_is_registered_in_the_guard(): void
    {
        $references = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                if ($key['foreign_table'] === 'media') {
                    $references[$table] = $key['columns'];
                    $this->assertSame('restrict', $key['on_delete'], "{$table} must restrict deleting media.");
                }
            }
        }

        $this->assertSame(
            array_map(fn (string $column) => [$column], MediaUsageGuard::REFERENCES),
            $references,
            'Every column referencing media.id must be listed in MediaUsageGuard::REFERENCES, and only those.'
        );
        $this->assertFalse(Schema::hasTable('mediables'), 'The optional mediables table is not used.');
    }

    public function test_the_guard_reports_referenced_ids_only_and_ignores_the_legacy_image_path(): void
    {
        [$a, $b, $c] = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];
        $this->category('One', $a);
        $this->category('Two', $a);
        $this->category('Three', $c);
        // A legacy path pointing at b's file is not a reference.
        Category::create(['name' => 'Legacy', 'slug' => 'legacy', 'image' => $b->file_path, 'is_active' => true]);

        $guard = new MediaUsageGuard;
        $this->assertSame([$a->id, $c->id], $guard->inUse([$c->id, $b->id, $a->id, (string) $a->id, 0, -1]));
        $this->assertSame([], $guard->inUse([$b->id]));
        $this->assertSame([], $guard->inUse([]));
    }

    // ---------------------------------------------------------------- single delete

    public function test_a_media_item_used_by_a_category_cannot_be_deleted(): void
    {
        $media = $this->storedMedia();
        $category = $this->category('Pottery', $media);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertStatus(409)
            ->assertExactJson(['message' => self::IN_USE, 'in_use' => [$media->id]]);

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertSame($media->id, $category->fresh()->image_media_id);
    }

    public function test_an_unused_media_item_is_deleted_with_its_file(): void
    {
        $media = $this->storedMedia();
        $other = $this->storedMedia();
        $this->category('Other', $other);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertOk()
            ->assertExactJson(['message' => 'The media item was deleted.']);

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
        $this->assertModelExists($other);
        Storage::disk('public')->assertExists($other->file_path);
    }

    public function test_status_codes_stay_stable(): void
    {
        $media = $this->storedMedia();

        $this->actingAs($this->admin(), 'admin')->deleteJson(route('media-library.media.destroy', 999999))->assertNotFound();
        $this->actingAs($this->admin([]), 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertForbidden();
        $this->actingAs($this->admin([]), 'admin')->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]])->assertForbidden();
        $this->actingAs($this->admin(['media.view', 'media.create', 'media.edit']), 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertForbidden();

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_a_media_item_deleted_by_another_request_after_authorization_is_not_deleted_twice(): void
    {
        $media = $this->storedMedia();
        $this->deleteConcurrentlyAfterAuthorization($media);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertNotFound();

        // The other request owns its file cleanup; this one must not touch the file.
        Storage::disk('public')->assertExists($media->file_path);
    }

    // ---------------------------------------------------------------- bulk delete

    public function test_bulk_delete_is_refused_whole_when_one_item_is_used(): void
    {
        [$a, $b, $c] = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];
        $this->category('Uses B', $b);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$c->id, $b->id, $a->id]])
            ->assertStatus(409)
            ->assertExactJson(['message' => self::IN_USE, 'in_use' => [$b->id]]);

        foreach ([$a, $b, $c] as $media) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->file_path);
        }
    }

    public function test_bulk_delete_lists_every_used_item(): void
    {
        [$a, $b, $c] = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];
        $this->category('Uses C', $c);
        $this->category('Uses A', $a);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$a->id, $b->id, $c->id]])
            ->assertStatus(409)
            ->assertJsonPath('in_use', [$a->id, $c->id]);

        $this->assertSame(3, Media::count());
    }

    public function test_bulk_delete_of_unused_items_deletes_them_and_counts_them(): void
    {
        [$a, $b, $kept] = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$b->id, $a->id]])
            ->assertOk()
            ->assertExactJson(['message' => 'The selected media items were deleted.', 'deleted' => 2]);

        foreach ([$a, $b] as $media) {
            $this->assertModelMissing($media);
            Storage::disk('public')->assertMissing($media->file_path);
        }

        $this->assertModelExists($kept);
    }

    public function test_bulk_delete_counts_only_what_it_actually_deleted(): void
    {
        [$a, $b] = [$this->storedMedia(), $this->storedMedia()];
        $this->deleteConcurrentlyAfterAuthorization($a);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertModelMissing($b);
        Storage::disk('public')->assertMissing($b->file_path);
    }

    public function test_bulk_ids_must_be_valid_and_distinct(): void
    {
        $media = $this->storedMedia();
        $this->actingAs($this->admin(), 'admin');

        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id, $media->id]])->assertUnprocessable()->assertJsonValidationErrors(['ids.0', 'ids.1']);
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id, 999999]])->assertUnprocessable()->assertJsonValidationErrors('ids.1');
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => ['abc']])->assertUnprocessable();

        $this->assertModelExists($media);
    }

    // ---------------------------------------------------------------- shared images, unlinking

    public function test_an_image_shared_by_two_categories_stays_protected_until_both_release_it(): void
    {
        $media = $this->storedMedia();
        $first = $this->category('First', $media);
        $second = $this->category('Second', $media);
        $this->actingAs($this->admin(), 'admin');

        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409);

        $first->forceFill(['image_media_id' => null])->save();
        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409)->assertJsonPath('in_use', [$media->id]);

        $second->delete();
        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertOk();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    public function test_unlinking_or_deleting_a_category_never_deletes_the_image(): void
    {
        $media = $this->storedMedia();
        $unlinked = $this->category('Unlinked', $media);
        $deleted = $this->category('Deleted', $media);

        $unlinked->forceFill(['image_media_id' => null])->save();
        $this->actingAs(Admin::factory()->create(['super_admin' => true]), 'admin')->delete(route('dashboard.categories.destroy', $deleted))->assertRedirect();

        $this->assertModelMissing($deleted);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    // ---------------------------------------------------------------- the database as the last line

    public function test_the_foreign_key_refuses_deleting_a_used_media_row_on_its_own(): void
    {
        $media = $this->storedMedia();
        $this->category('Pottery', $media);

        try {
            DB::table('media')->where('id', $media->id)->delete();
            $this->fail('The database must refuse deleting a referenced media row.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        try {
            DB::table('categories')->insert(['name' => 'Ghost', 'slug' => 'ghost', 'image_media_id' => 999999]);
            $this->fail('The database must refuse a reference to a missing media row.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        $this->assertModelExists($media);
    }

    public function test_a_foreign_key_refusal_is_answered_with_409_and_no_detail(): void
    {
        [$used, $free] = [$this->storedMedia(), $this->storedMedia()];
        $this->category('Pottery', $used);
        $this->blindGuard();

        foreach ([
            fn () => $this->deleteJson(route('media-library.media.destroy', $used->id)),
            fn () => $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$free->id, $used->id]]),
        ] as $request) {
            $response = $this->actingAs($this->admin(), 'admin');
            $response = $request()->assertStatus(409)->assertJsonPath('message', self::IN_USE)->assertJsonPath('in_use', [$used->id]);

            $this->assertStringNotContainsStringIgnoringCase('constraint', $response->getContent());
            $this->assertStringNotContainsStringIgnoringCase('sql', $response->getContent());
            $this->assertArrayNotHasKey('exception', $response->json());
        }

        // The bulk transaction rolled back: the unused item was not deleted either.
        foreach ([$used, $free] as $media) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->file_path);
        }
    }

    public function test_an_unrelated_database_error_is_not_turned_into_409(): void
    {
        $media = $this->storedMedia();
        $this->app->instance(MediaUsageGuard::class, new class extends MediaUsageGuard
        {
            public function inUse(iterable $mediaIds): array
            {
                throw new QueryException('sqlite', 'select * from "categories"', [], new PDOException('disk I/O error'));
            }
        });

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertStatus(500);

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    // ---------------------------------------------------------------- files after the commit only

    public function test_no_file_is_deleted_when_the_transaction_fails_after_the_rows_were_deleted(): void
    {
        [$a, $b] = [$this->storedMedia(), $this->storedMedia()];

        // Fail inside the transaction right after the delete statement ran.
        DB::listen(function ($query) {
            if (preg_match('/^delete from "media"/', $query->sql)) {
                throw new RuntimeException('Simulated failure before the commit.');
            }
        });

        foreach ([
            fn () => $this->deleteJson(route('media-library.media.destroy', $a->id)),
            fn () => $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$a->id, $b->id]]),
        ] as $request) {
            $this->actingAs($this->admin(), 'admin');
            $request()->assertStatus(500);
        }

        foreach ([$a, $b] as $media) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->file_path);
        }
    }

    public function test_files_are_removed_only_after_the_rows_are_committed(): void
    {
        $media = $this->storedMedia();
        $order = [];
        $disk = Mockery::mock(Storage::disk('public'))->makePartial();
        $disk->shouldReceive('delete')->once()->andReturnUsing(function ($path) use (&$order, $media) {
            $order[] = 'file deleted, transaction level '.DB::transactionLevel().', row present: '.var_export(DB::table('media')->where('id', $media->id)->exists(), true);

            return true;
        });
        Storage::set('public', $disk);

        $this->actingAs($this->admin(), 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertOk();

        // Level 1 is RefreshDatabase's own wrapping transaction: the controller's transaction had ended.
        $this->assertSame(['file deleted, transaction level 1, row present: false'], $order);
    }

    public function test_a_failed_file_removal_is_logged_and_reported_without_claiming_cleanup(): void
    {
        [$a, $b] = [$this->storedMedia(), $this->storedMedia()];
        $disk = Mockery::mock(Storage::disk('public'))->makePartial();
        $disk->shouldReceive('delete')->with($a->file_path)->andThrow(new RuntimeException('Permission denied'));
        $disk->shouldReceive('delete')->with($b->file_path)->andReturn(false);
        Storage::set('public', $disk);
        Log::spy();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertExactJson(['message' => 'The selected media items were deleted.', 'deleted' => 2, 'file_cleanup_failed' => [$a->id, $b->id]]);

        $this->assertModelMissing($a);
        $this->assertModelMissing($b);

        foreach ([[$a, 'RuntimeException: Permission denied'], [$b, null]] as [$media, $exception]) {
            Log::shouldHaveReceived('error', ['Media file left on disk after its record was deleted.', [
                'media_id' => $media->id,
                'disk' => 'public',
                'path' => $media->file_path,
                'exception' => $exception,
            ]])->once();
        }

        Log::shouldHaveReceived('error')->twice();
    }

    // ---------------------------------------------------------------- helpers

    private function admin(array $abilities = ['media.view', 'media.delete']): Admin
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

        return $category;
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

    /** Another request deletes the row (not its file) between this request's authorization and its transaction. */
    private function deleteConcurrentlyAfterAuthorization(Media $media): void
    {
        $done = false;

        Gate::after(function ($user, string $ability) use ($media, &$done) {
            if ($ability === 'delete' && ! $done) {
                $done = true;
                DB::table('media')->where('id', $media->id)->delete();
            }
        });
    }

    /**
     * A race the check cannot see: inside the deleting transaction the guard sees no reference, so only the
     * foreign key stands between the request and the delete; afterwards it sees the real state again.
     * (Level 1 is RefreshDatabase's wrapping transaction, level 2 the controller's.)
     */
    private function blindGuard(): void
    {
        $this->app->instance(MediaUsageGuard::class, new class extends MediaUsageGuard
        {
            public function inUse(iterable $mediaIds): array
            {
                return DB::transactionLevel() > 1 ? [] : parent::inUse($mediaIds);
            }
        });
    }
}
