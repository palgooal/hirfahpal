<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminMediaTranslationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use PDOException;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * AD-UI-CAT-M4-B: the category image from the media library (image_media_id). Decisions M4-D2 (explicit
 * remove_image; absent or empty means no change), M4-D3 (a new image needs media.view; removing needs
 * categories.edit only), M4-D4 (categories.image is no longer written), M4-D5 (3 attempts).
 */
class CategoryImageMediaTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private const MISSING = 'The selected image no longer exists. Choose another image.';

    private const INVALID = 'The selected file is not a supported image (JPG, PNG, GIF or WebP).';

    private const CONFLICT = 'Choose a new image or remove the current one, not both.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- create

    public function test_a_category_is_created_without_an_image_and_without_any_media_ability(): void
    {
        $this->store($this->admin(['categories.create']), ['name' => 'Pottery'])->assertSessionHasNoErrors();
        $this->store($this->admin(['categories.create']), ['name' => 'Glass', 'image_media_id' => ''])->assertSessionHasNoErrors();
        $this->store($this->admin(['categories.create']), ['name' => 'Wood', 'remove_image' => '1'])->assertSessionHasNoErrors();

        foreach (['Pottery', 'Glass', 'Wood'] as $name) {
            $this->assertNull(Category::where('name', $name)->firstOrFail()->image_media_id, $name);
        }
    }

    public function test_a_category_is_created_with_an_image_by_an_admin_who_may_view_the_library(): void
    {
        $media = $this->media();

        // Without categories.view the admin returns to a form they may open (AUD-04, CategoryPermissionRedirectTest).
        $this->store($this->admin(['categories.create', 'media.view']), ['name' => 'Pottery', 'image_media_id' => $media->id])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasNoErrors();

        $this->assertSame($media->id, Category::where('name', 'Pottery')->firstOrFail()->image_media_id);
    }

    public function test_choosing_an_image_without_media_view_is_refused(): void
    {
        $media = $this->media();

        $this->store($this->admin(['categories.create']), ['name' => 'Pottery', 'image_media_id' => $media->id])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Pottery']);
    }

    // ---------------------------------------------------------------- update: keep

    public function test_the_image_is_kept_when_the_field_is_absent_empty_or_unchanged(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);
        // No media ability at all: keeping the image needs none, even when the current id is posted back.
        $editor = $this->admin(['categories.edit']);

        foreach ([[], ['image_media_id' => ''], ['image_media_id' => null], ['image_media_id' => $media->id], ['image_media_id' => (string) $media->id, 'remove_image' => '0']] as $index => $fields) {
            $this->update($editor, $category, ['name' => 'Pottery '.$index] + $fields)->assertSessionHasNoErrors();

            $this->assertSame($media->id, $category->fresh()->image_media_id, json_encode($fields));
            $this->assertSame('Pottery '.$index, $category->fresh()->name, 'The other fields are saved.');
        }
    }

    // ---------------------------------------------------------------- update: replace

    public function test_the_image_is_replaced_and_the_old_media_item_is_left_untouched(): void
    {
        [$old, $new] = [$this->media(), $this->media()];
        $category = $this->category('Pottery', $old);

        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['image_media_id' => $new->id])->assertSessionHasNoErrors();

        $this->assertSame($new->id, $category->fresh()->image_media_id);
        $this->assertModelExists($old);
        Storage::disk('public')->assertExists($old->file_path);
    }

    public function test_replacing_without_media_view_is_refused_and_changes_nothing(): void
    {
        [$old, $new] = [$this->media(), $this->media()];
        $category = $this->category('Pottery', $old);

        $this->update($this->admin(['categories.edit']), $category, ['name' => 'Changed', 'image_media_id' => $new->id])->assertForbidden();

        $this->assertSame($old->id, $category->fresh()->image_media_id);
        $this->assertSame('Pottery', $category->fresh()->name);
    }

    // ---------------------------------------------------------------- update: remove

    public function test_remove_image_unlinks_only_and_needs_no_media_ability(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);

        $this->update($this->admin(['categories.edit']), $category, ['remove_image' => '1', 'image_media_id' => ''])->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->image_media_id);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_remove_with_a_new_image_is_refused(): void
    {
        [$old, $new] = [$this->media(), $this->media()];
        $category = $this->category('Pottery', $old);

        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['name' => 'Changed', 'remove_image' => '1', 'image_media_id' => $new->id])
            ->assertSessionHasErrors(['image_media_id' => self::CONFLICT]);

        $this->assertSame($old->id, $category->fresh()->image_media_id);
        $this->assertSame('Pottery', $category->fresh()->name);
    }

    // ---------------------------------------------------------------- invalid choices

    public function test_a_missing_or_malformed_id_is_refused(): void
    {
        $category = $this->category('Pottery', null);
        $admin = $this->admin(['categories.edit', 'media.view']);

        $this->update($admin, $category, ['image_media_id' => 999999])->assertSessionHasErrors(['image_media_id' => self::MISSING]);
        $this->update($admin, $category, ['image_media_id' => 'abc'])->assertSessionHasErrors('image_media_id');
        $this->update($admin, $category, ['image_media_id' => -3])->assertSessionHasErrors('image_media_id');
        $this->update($admin, $category, ['remove_image' => 'yes'])->assertSessionHasErrors('remove_image');

        $this->assertNull($category->fresh()->image_media_id);
    }

    public function test_media_that_is_not_an_allowed_image_is_refused(): void
    {
        $category = $this->category('Pottery', null);
        $admin = $this->admin(['categories.edit', 'media.view']);
        $cases = [
            'pdf document' => ['file_type' => 'document', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf'],
            'svg' => ['file_type' => 'image', 'mime_type' => 'image/svg+xml', 'file_extension' => 'svg'],
            'image type, wrong mime' => ['file_type' => 'image', 'mime_type' => 'text/html', 'file_extension' => 'html'],
            'video' => ['file_type' => 'video', 'mime_type' => 'video/mp4', 'file_extension' => 'mp4'],
        ];

        foreach ($cases as $label => $attributes) {
            $media = $this->media($attributes);
            $this->update($admin, $category, ['image_media_id' => $media->id])->assertSessionHasErrors(['image_media_id' => self::INVALID]);
            $this->assertNull($category->fresh()->image_media_id, $label);
        }
    }

    public function test_the_legacy_image_column_is_never_written(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', null);
        DB::table('categories')->where('id', $category->id)->update(['image' => 'legacy/path.png']);
        $admin = $this->admin(['categories.edit', 'categories.create', 'media.view']);

        $this->update($admin, $category, ['image' => 'hacked.png', 'image_media_id' => $media->id])->assertSessionHasNoErrors();
        $this->update($admin, $category, ['image' => '', 'remove_image' => '1'])->assertSessionHasNoErrors();
        $this->store($admin, ['name' => 'New', 'image' => 'injected.png'])->assertSessionHasNoErrors();

        $this->assertSame('legacy/path.png', $category->fresh()->image);
        $this->assertNull(Category::where('name', 'New')->firstOrFail()->image);
    }

    public function test_image_media_id_is_not_mass_assignable(): void
    {
        // Assigned explicitly by the controller; a request can never set it through fill().
        $this->assertNotContains('image_media_id', (new Category)->getFillable());

        $category = new Category(['name' => 'Pottery', 'image_media_id' => 5]);
        $this->assertNull($category->image_media_id);
    }

    // ---------------------------------------------------------------- the database as the last line

    public function test_an_image_deleted_between_the_check_and_the_save_is_reported_on_its_field(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', null);

        // Another request deletes the media row right after this request found it (outside its transaction).
        $deleted = false;
        DB::listen(function ($query) use ($media, &$deleted) {
            if (! $deleted && str_contains($query->sql, 'from "media" where "media"."id" = ?')) {
                $deleted = true;
                DB::table('media')->where('id', $media->id)->delete();
            }
        });

        $this->update($this->admin(['categories.edit', 'media.view']), $category, ['name' => 'Changed', 'image_media_id' => $media->id])
            ->assertRedirect(route('dashboard.categories.edit', $category))
            ->assertSessionHasErrors(['image_media_id' => self::MISSING])
            ->assertSessionDoesntHaveErrors('parent_id');

        $this->assertTrue($deleted, 'The race was simulated.');
        $this->assertNull($category->fresh()->image_media_id, 'No broken reference is saved.');
        $this->assertSame('Pottery', $category->fresh()->name);
    }

    public function test_a_foreign_key_failure_no_vanished_row_explains_is_not_turned_into_a_field_error(): void
    {
        $media = $this->media();
        $parent = $this->category('Parent', null);

        Category::saving(function () {
            throw new QueryException('sqlite', 'insert into "categories"', [], new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed'));
        });

        $this->store($this->admin(['categories.create', 'media.view']), ['name' => 'Child', 'parent_id' => $parent->id, 'image_media_id' => $media->id])
            ->assertStatus(500);

        $this->assertDatabaseMissing('categories', ['name' => 'Child']);
    }

    public function test_creating_and_updating_retry_deadlocks_three_times(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Dashboard/CategoryManagementController.php'));

        $this->assertStringContainsString('private const ATTEMPTS = 3;', $source);
        $this->assertSame(2, substr_count($source, '}, self::ATTEMPTS);'), 'store() and update() both retry.');
    }

    // ---------------------------------------------------------------- media stays

    public function test_deleting_the_category_keeps_the_media_and_a_used_media_item_cannot_be_deleted(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);
        $super = $this->admin(super: true);

        $this->actingAs($super, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))->assertStatus(409);

        $this->confirmedDelete($super, $category)->assertRedirect();
        $this->assertModelMissing($category);
        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    // ---------------------------------------------------------------- the form

    public function test_the_form_shows_the_picker_instead_of_the_image_path(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);

        $html = $this->actingAs($this->admin(super: true), 'admin')->get(route('dashboard.categories.edit', $category))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="image"', $html);
        $this->assertStringContainsString('name="image_media_id" value="'.$media->id.'"', $html);
        $this->assertStringContainsString('name="remove_image" value="0"', $html);
        $this->assertStringContainsString('src="'.$media->url.'"', $html);
        $this->assertStringContainsString('<bdi>'.$media->file_original_name.'</bdi>', $html);
        $this->assertStringContainsString('data-picker-open', $html);
        $this->assertStringContainsString('data-picker-upload', $html);
        $this->assertStringContainsString('data-picker-remove-button', $html);
        $this->assertStringContainsString('<dialog class="admin-media-dialog admin-media-picker-dialog"', $html);
    }

    public function test_the_picker_buttons_follow_the_abilities(): void
    {
        $media = $this->media();
        $category = $this->category('Pottery', $media);

        $cases = [
            'edit only' => [['categories.edit'], ['open' => false, 'upload' => false, 'remove' => true, 'noAccess' => true]],
            'edit + view' => [['categories.edit', 'media.view'], ['open' => true, 'upload' => false, 'remove' => true, 'noAccess' => false]],
            'edit + view + create' => [['categories.edit', 'media.view', 'media.create'], ['open' => true, 'upload' => true, 'remove' => true, 'noAccess' => false]],
        ];

        foreach ($cases as $label => [$abilities, $expected]) {
            $html = $this->actingAs($this->admin($abilities), 'admin')->get(route('dashboard.categories.edit', $category))->assertOk()->getContent();

            $this->assertSame($expected['open'], str_contains($html, 'data-picker-open'), "{$label}: choose");
            $this->assertSame($expected['open'], str_contains($html, 'data-picker-dialog'), "{$label}: dialog");
            $this->assertSame($expected['upload'], str_contains($html, 'data-picker-upload'), "{$label}: upload");
            $this->assertSame($expected['remove'], str_contains($html, 'data-picker-remove-button'), "{$label}: remove");
            $this->assertSame($expected['noAccess'], str_contains($html, 'You need permission to view the media library'), "{$label}: hint");
            $this->assertStringContainsString('src="'.$media->url.'"', $html, "{$label}: the current image is previewed without media.view.");
        }

        // Create: nothing to remove yet.
        $create = $this->actingAs($this->admin(['categories.create', 'media.view']), 'admin')->get(route('dashboard.categories.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-picker-remove-button', $create);
        $this->assertStringContainsString('name="image_media_id" value=""', $create);
    }

    public function test_after_a_refused_save_the_form_keeps_the_choice(): void
    {
        [$old, $new] = [$this->media(), $this->media()];
        $category = $this->category('Pottery', $old);
        $admin = $this->admin(['categories.edit', 'media.view']);

        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.edit', $category))
            ->put(route('dashboard.categories.update', $category), ['name' => 'Pottery', 'slug' => 'pottery', 'image_media_id' => $new->id, 'parent_id' => 999999])
            ->assertSessionHasErrors('parent_id');

        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.categories.edit', $category))->getContent();
        $this->assertStringContainsString('name="image_media_id" value="'.$new->id.'"', $html);
        $this->assertStringContainsString('src="'.$new->url.'"', $html);
    }

    public function test_the_picker_is_translated_and_creates_no_rows(): void
    {
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $category = $this->category('Pottery', $this->media());
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.edit', $category))->assertOk();
        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Media\_Picker%')->orWhere('key', 'dashboard.Category_Image')->count(), 'Nothing auto-created.');

        $this->seed(AdminMediaTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.edit', $category))->getContent();

        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $html);
        foreach (['الصورة', 'استبدال الصورة', 'إزالة الصورة', 'اختيار صورة', 'استخدام هذه الصورة', 'رفع صور'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
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

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => $super]);

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

    private function media(array $attributes = []): Media
    {
        $name = uniqid('', true).'.png';
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, 'file');

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
