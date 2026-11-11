<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Support\Media\MediaUsageGuard;
use App\Support\Storefront\HomepageMedia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * SETTINGS-MEDIA-01: the site logo and favicon chosen from the media library (settings.logo_media_id,
 * settings.favicon_media_id; the migration itself: SettingBrandMediaMigrationTest). Choose / replace / remove / keep the legacy upload, abilities and
 * validation, protection of the media used, the storefront logo and favicon with their fallbacks and query cost, and
 * the settings page. No file is ever deleted.
 */
class SettingBrandMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ---------------------------------------------------------------- choose, replace, remove

    public function test_choosing_the_logo_and_favicon_links_them_and_keeps_the_legacy_paths_and_files(): void
    {
        Storage::disk('public')->put('settings/old-logo.png', 'old');
        $this->setting(['logo' => 'settings/old-logo.png']);
        $logo = $this->media();
        $favicon = $this->media();
        $files = Storage::disk('public')->allFiles();

        $this->save(['logo_media_id' => $logo->id, 'favicon_media_id' => $favicon->id])
            ->assertRedirect(route('dashboard.setting.index'))->assertSessionHasNoErrors();

        $setting = Setting::singleton();
        $this->assertSame([$logo->id, $favicon->id], [$setting->logo_media_id, $setting->favicon_media_id]);
        $this->assertSame('settings/old-logo.png', $setting->logo);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_replacing_keeps_the_previous_media_item_and_its_file(): void
    {
        $old = $this->media();
        $new = $this->media();
        $this->setting(['logo_media_id' => $old->id]);

        $this->save(['logo_media_id' => $new->id])->assertSessionHasNoErrors();

        $this->assertSame($new->id, Setting::singleton()->logo_media_id);
        $this->assertModelExists($old);
        Storage::disk('public')->assertExists($old->file_path);
    }

    public function test_removing_unlinks_the_media_and_clears_the_legacy_path_without_deleting_any_file(): void
    {
        Storage::disk('public')->put('settings/old-favicon.png', 'old');
        $logo = $this->media();
        $favicon = $this->media();
        $this->setting(['logo_media_id' => $logo->id, 'favicon_media_id' => $favicon->id, 'favicon' => 'settings/old-favicon.png']);
        $files = Storage::disk('public')->allFiles();

        $this->save(['remove_logo' => 1, 'remove_favicon' => 1])->assertSessionHasNoErrors();

        $setting = Setting::singleton();
        $this->assertNull($setting->logo_media_id);
        $this->assertNull($setting->favicon_media_id);
        $this->assertNull($setting->favicon);
        $this->assertModelExists($logo);
        $this->assertModelExists($favicon);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_the_legacy_image_can_be_removed_on_its_own_and_its_file_is_kept(): void
    {
        Storage::disk('public')->put('settings/old-logo.png', 'old');
        $this->setting(['logo' => 'settings/old-logo.png', 'favicon' => 'settings/old-favicon.png']);

        $this->save(['remove_legacy_logo' => 1])->assertSessionHasNoErrors();

        $setting = Setting::singleton();
        $this->assertNull($setting->logo);
        $this->assertSame('settings/old-favicon.png', $setting->favicon);
        Storage::disk('public')->assertExists('settings/old-logo.png');
    }

    public function test_a_save_without_image_fields_changes_neither_image(): void
    {
        $logo = $this->media();
        $this->setting(['logo_media_id' => $logo->id, 'favicon' => 'settings/old-favicon.png']);

        $this->save(['site_name' => 'Renamed'])->assertSessionHasNoErrors();

        $setting = Setting::singleton();
        $this->assertSame(['Renamed', $logo->id, 'settings/old-favicon.png'], [$setting->site_name, $setting->logo_media_id, $setting->favicon]);
    }

    // ---------------------------------------------------------------- validation and abilities

    public function test_choosing_and_removing_together_is_refused(): void
    {
        $media = $this->media();
        $this->setting();

        $this->save(['logo_media_id' => $media->id, 'remove_logo' => 1, 'site_name' => 'Not saved'])
            ->assertSessionHasErrors('logo_media_id');

        $this->assertNull(Setting::singleton()->logo_media_id);
        $this->assertNotSame('Not saved', Setting::singleton()->site_name);
    }

    public function test_missing_unsupported_and_vendor_media_are_refused_and_nothing_is_saved(): void
    {
        $this->setting();
        $svg = $this->media(['mime_type' => 'image/svg+xml', 'file_extension' => 'svg']);
        $document = $this->media(['file_type' => 'document', 'mime_type' => 'application/pdf']);
        $vendor = $this->media(['file_path' => 'vendors/media/7/logo.png']);

        foreach ([999999, $svg->id, $document->id, $vendor->id] as $id) {
            $this->save(['favicon_media_id' => $id, 'site_name' => 'Not saved'])->assertSessionHasErrors('favicon_media_id');
        }
        $this->save(['logo_media_id' => 'abc'])->assertSessionHasErrors('logo_media_id');

        $this->assertNull(Setting::singleton()->favicon_media_id);
        $this->assertNotSame('Not saved', Setting::singleton()->site_name);
    }

    public function test_changing_an_image_needs_media_view_but_keeping_or_removing_it_does_not(): void
    {
        $current = $this->media();
        $other = $this->media();
        $this->setting(['logo_media_id' => $current->id]);
        $editor = $this->admin(['settings.edit']);

        $this->actingAs($editor, 'admin')->put(route('dashboard.setting.update'), $this->payload(['logo_media_id' => $other->id]))->assertForbidden();
        $this->assertSame($current->id, Setting::singleton()->logo_media_id);

        $this->actingAs($editor, 'admin')->put(route('dashboard.setting.update'), $this->payload(['logo_media_id' => $current->id]))->assertSessionHasNoErrors();
        $this->assertSame($current->id, Setting::singleton()->logo_media_id);

        $this->actingAs($editor, 'admin')->put(route('dashboard.setting.update'), $this->payload(['remove_logo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull(Setting::singleton()->logo_media_id);
    }

    public function test_an_admin_without_settings_edit_cannot_change_the_images(): void
    {
        $media = $this->media();
        $this->setting();

        $this->actingAs($this->admin(['settings.view', 'media.view']), 'admin')
            ->put(route('dashboard.setting.update'), $this->payload(['logo_media_id' => $media->id]))
            ->assertForbidden();

        $this->assertNull(Setting::singleton()->logo_media_id);
    }

    public function test_an_image_deleted_after_it_was_checked_is_reported_on_its_field(): void
    {
        $media = $this->media();
        $this->setting();
        // The row disappears between the check and the save (the foreign key refuses the save).
        Media::retrieved(function (Media $retrieved) use ($media): void {
            if ($retrieved->id === $media->id) {
                DB::table('media')->where('id', $media->id)->delete();
            }
        });

        $this->save(['logo_media_id' => $media->id])->assertSessionHasErrors('logo_media_id');

        $this->assertNull(Setting::singleton()->logo_media_id);
    }

    // ---------------------------------------------------------------- protection of the media used

    public function test_media_used_as_logo_or_favicon_cannot_be_deleted_from_the_library(): void
    {
        $logo = $this->media();
        $favicon = $this->media();
        $this->setting(['logo_media_id' => $logo->id, 'favicon_media_id' => $favicon->id]);
        $admin = $this->admin(['media.view', 'media.delete']);

        foreach ([$logo, $favicon] as $media) {
            $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.destroy', $media->id))
                ->assertStatus(409)->assertJsonPath('in_use', [$media->id]);
        }
        $this->actingAs($admin, 'admin')->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$logo->id, $favicon->id]])
            ->assertStatus(409);

        $this->assertModelExists($logo);
        $this->assertModelExists($favicon);
        Storage::disk('public')->assertExists($logo->file_path);
        Storage::disk('public')->assertExists($favicon->file_path);
        $this->assertSame([$logo->id, $favicon->id], (new MediaUsageGuard)->inUse([$logo->id, $favicon->id, $this->media()->id]));
    }

    public function test_the_database_refuses_deleting_used_media_directly_and_allows_it_once_unlinked(): void
    {
        $media = $this->media();
        $this->setting(['favicon_media_id' => $media->id]);

        try {
            DB::table('media')->where('id', $media->id)->delete();
            $this->fail('The foreign key must refuse the delete.');
        } catch (QueryException) {
            $this->assertModelExists($media);
        }

        $this->save(['remove_favicon' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin(['media.view', 'media.delete']), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))->assertOk();
    }

    public function test_category_images_are_still_protected(): void
    {
        $media = $this->media();
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $category->forceFill(['image_media_id' => $media->id])->save();

        $this->assertSame([$media->id], (new MediaUsageGuard)->inUse([$media->id]));
    }

    // ---------------------------------------------------------------- storefront logo and favicon

    public function test_the_storefront_shows_the_media_logo_and_favicon_first(): void
    {
        $logo = $this->media();
        $favicon = $this->media(['mime_type' => 'image/webp', 'file_extension' => 'webp']);
        $this->setting(['logo' => 'settings/old-logo.png', 'favicon' => 'settings/old.png', 'logo_media_id' => $logo->id, 'favicon_media_id' => $favicon->id]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'src="'.HomepageMedia::url($logo->url).'"'), 'Header (twice) and footer.');
        $this->assertStringNotContainsString('old-logo.png', $html);
        $this->assertStringContainsString('<link rel="icon" href="'.HomepageMedia::url($favicon->url).'" type="image/webp">', $html);
        $this->assertSame(1, substr_count($html, 'rel="icon"'));
    }

    public function test_the_storefront_falls_back_to_the_legacy_paths_then_to_the_default_images(): void
    {
        $this->setting(['logo' => 'settings/old-logo.png', 'favicon' => 'settings/old-favicon.png']);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $legacyLogo = Storage::disk('public')->url('settings/old-logo.png');
        $this->assertSame(3, substr_count($html, 'src="'.$legacyLogo.'"'));
        $this->assertStringContainsString('<link rel="icon" href="'.Storage::disk('public')->url('settings/old-favicon.png').'" type="image/png">', $html);

        Setting::singleton()->forceFill(['logo' => null, 'favicon' => null])->save();
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'src="'.asset('assets/storefront/imgs/mcp/header-logo.png').'"'));
        $this->assertSame(1, substr_count($html, 'src="'.asset('assets/storefront/imgs/mcp/footer-logo.png').'"'));
        $this->assertStringContainsString('<link rel="icon" href="'.asset('images/brand/hirfah-logo.png').'" type="image/png">', $html);
    }

    public function test_the_storefront_without_a_settings_row_uses_the_defaults(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="icon" href="'.asset('images/brand/hirfah-logo.png').'" type="image/png">', $html);
        $this->assertStringContainsString('src="'.asset('assets/storefront/imgs/mcp/header-logo.png').'"', $html);
    }

    public function test_linked_images_cost_one_media_query_per_page_and_none_otherwise(): void
    {
        $this->setting();
        $this->get(route('home'))->assertOk(); // Warm up (compiled views, caches).
        $none = $this->mediaQueries();

        Setting::singleton()->forceFill(['logo_media_id' => $this->media()->id, 'favicon_media_id' => $this->media()->id])->save();
        $both = $this->mediaQueries();

        $this->assertSame(0, $none);
        $this->assertSame(1, $both);
    }

    // ---------------------------------------------------------------- settings page

    public function test_the_page_shows_a_media_picker_for_each_image_with_its_own_texts(): void
    {
        $logo = $this->media();
        $this->setting(['logo_media_id' => $logo->id]);

        $html = $this->actingAs($this->admin(['settings.view', 'settings.edit', 'media.view']), 'admin')
            ->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $general = $this->between($html, 'id="settings-panel-general"', '</section>');

        foreach (['logo_media_id', 'remove_logo', 'favicon_media_id', 'remove_favicon'] as $field) {
            $this->assertSame(1, substr_count($general, 'name="'.$field.'"'), $field);
        }
        $this->assertStringContainsString('id="media-picker-logo-media-id"', $general);
        $this->assertStringContainsString('id="media-picker-favicon-media-id"', $general);
        $this->assertStringContainsString('src="'.$logo->url.'"', $general);
        $this->assertStringContainsString('The logo will be removed from the site when you save.', $general);
        $this->assertStringContainsString('The favicon will be removed from the site when you save.', $general);
        $this->assertStringNotContainsString('from this category', $general);
        $this->assertStringNotContainsString('type="file" name="logo"', $general);
        $this->assertStringNotContainsString('data-settings-legacy-image', $general);
    }

    public function test_a_legacy_image_without_a_media_link_is_shown_with_its_own_removal(): void
    {
        $this->setting(['logo' => 'settings/old-logo.png', 'favicon' => 'settings/old-favicon.png', 'favicon_media_id' => $this->media()->id]);

        $general = $this->between($this->actingAs($this->admin(['settings.view', 'settings.edit']), 'admin')
            ->get(route('dashboard.setting.index'))->assertOk()->getContent(), 'id="settings-panel-general"', '</section>');

        $this->assertStringContainsString('data-settings-legacy-image="logo"', $general);
        $this->assertStringContainsString('src="'.Storage::disk('public')->url('settings/old-logo.png').'"', $general);
        $this->assertSame(1, substr_count($general, 'name="remove_legacy_logo"'));
        // The favicon is linked to the library: its legacy upload is not offered.
        $this->assertStringNotContainsString('data-settings-legacy-image="favicon"', $general);
        $this->assertStringNotContainsString('name="remove_legacy_favicon"', $general);
    }

    public function test_the_page_keeps_its_direction_in_arabic_and_english(): void
    {
        $this->setting();
        $admin = $this->admin(['settings.view', 'settings.edit', 'media.view']);
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);

        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
            $html = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->get(route('dashboard.setting.index'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<html[^>]* dir="'.$direction.'"/', $html, $locale);
            $this->assertStringContainsString('id="media-picker-logo-media-id"', $html, $locale);
        }
    }

    public function test_the_category_form_keeps_the_pickers_default_removal_note(): void
    {
        $html = $this->actingAs($this->admin(['categories.view', 'categories.create', 'media.view']), 'admin')
            ->get(route('dashboard.categories.create'))->assertOk()->getContent();

        $this->assertStringContainsString('The image will be removed from this category when you save. The file stays in the media library.', $html);
    }

    // ---------------------------------------------------------------- helpers

    private function save(array $fields)
    {
        return $this->actingAs($this->admin(['settings.view', 'settings.edit', 'media.view']), 'admin')
            ->put(route('dashboard.setting.update'), $this->payload($fields));
    }

    private function payload(array $fields): array
    {
        return array_merge([
            'site_name' => 'Site', 'timezone' => 'UTC', 'default_locale' => 'en', 'default_currency' => 'USD',
        ], $fields);
    }

    private function setting(array $values = []): Setting
    {
        $setting = Setting::singleton();
        $setting->forceFill($values)->save();

        return $setting;
    }

    private function media(array $overrides = []): Media
    {
        $name = uniqid('', true).'.png';
        $path = $overrides['file_path'] ?? 'media/2026/11/'.$name;
        Storage::disk('public')->put($path, 'file');

        return Media::create(array_merge([
            'file_name' => $name, 'file_original_name' => $name, 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 4, 'file_type' => 'image', 'disk' => 'public',
        ], $overrides));
    }

    private function admin(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    /** Queries on the media table while rendering the home page. */
    private function mediaQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        return $queries->filter(fn (string $sql) => preg_match('/from ["`]media["`]/i', $sql) === 1)->count();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }
}
