<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminMediaTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMediaTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inserts_every_key_in_arabic_and_english(): void
    {
        $this->seed(AdminMediaTranslationSeeder::class);

        foreach (AdminMediaTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);

            foreach ($values as $locale => $value) {
                $this->assertDatabaseHas('translation_values', ['key' => $key, 'locale' => $locale, 'value' => $value]);
            }
        }

        $this->assertSame(count(AdminMediaTranslationSeeder::TRANSLATIONS) * 2, TranslationValue::query()->whereIn('key', array_keys(AdminMediaTranslationSeeder::TRANSLATIONS))->count());
        $this->assertSame(count(AdminMediaTranslationSeeder::TRANSLATIONS) * 2, TranslationValue::query()->count(), 'Nothing outside the listed keys.');
    }

    public function test_the_five_media_ability_names_are_seeded_as_data_abilities_uses_them(): void
    {
        $abilities = file_get_contents(base_path('data/abilities.php'));

        foreach (['dashboard.Media_Library', 'dashboard.View_Media', 'dashboard.Upload_Media', 'dashboard.Edit_Media', 'dashboard.Delete_Media'] as $key) {
            $values = AdminMediaTranslationSeeder::TRANSLATIONS[$key] ?? null;

            $this->assertNotNull($values, $key);
            $this->assertStringContainsString("t('{$key}', '{$values['en']}')", $abilities, $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
        }

        $this->seed(AdminMediaTranslationSeeder::class);
        app()->setLocale('ar');
        $this->assertSame('رفع الوسائط', app('abilities')['media']['create']);
    }

    public function test_it_is_idempotent_and_keeps_edited_values(): void
    {
        TranslationValue::query()->create(['key' => 'dashboard.Media_in_use', 'locale' => 'ar', 'value' => 'نص معدّل']);

        $this->seed(AdminMediaTranslationSeeder::class);
        $this->seed(AdminMediaTranslationSeeder::class);

        $this->assertSame('نص معدّل', TranslationValue::query()->where(['key' => 'dashboard.Media_in_use', 'locale' => 'ar'])->value('value'));
        $this->assertSame(1, TranslationValue::query()->where(['key' => 'dashboard.Media_in_use', 'locale' => 'ar'])->count());
    }

    public function test_the_english_values_are_the_defaults_used_in_the_code(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Dashboard/MediaLibraryController.php'))
            .file_get_contents(app_path('Http/Middleware/EnsureSafeMediaUpload.php'))
            .file_get_contents(base_path('data/abilities.php'));
        // The library page and the media picker pass their keys without the dashboard. prefix ($mediaText, $pickerText).
        $view = file_get_contents(resource_path('views/vendor/media-library/media.blade.php'))
            .file_get_contents(resource_path('views/components/admin/media-picker.blade.php'));

        foreach (AdminMediaTranslationSeeder::TRANSLATIONS as $key => $values) {
            $short = substr($key, strlen('dashboard.'));

            $this->assertTrue(
                str_contains($code, "'{$key}', '{$values['en']}'") || str_contains($view, "'{$short}', '{$values['en']}'"),
                "{$key}: its English value must be the default used in the code."
            );
        }
    }

    public function test_every_key_the_library_page_uses_is_seeded(): void
    {
        $view = file_get_contents(resource_path('views/vendor/media-library/media.blade.php'));
        preg_match_all("/\\\$mediaText\\('(Media_[A-Za-z_]+)'/", $view, $direct);
        preg_match_all("/\\['[a-z_]+', '(Media_[A-Za-z_]+)', '/", $view, $fields);
        $used = array_unique([...$direct[1], ...$fields[1]]);

        $this->assertGreaterThan(40, count($used));

        foreach ($used as $key) {
            $this->assertArrayHasKey('dashboard.'.$key, AdminMediaTranslationSeeder::TRANSLATIONS, $key);
        }
    }

    public function test_every_key_the_media_picker_uses_is_seeded(): void
    {
        $picker = file_get_contents(resource_path('views/components/admin/media-picker.blade.php'));
        preg_match_all("/\\\$pickerText\\('(Media_[A-Za-z_]+)'/", $picker, $used);

        $this->assertGreaterThan(20, count(array_unique($used[1])));

        foreach (array_unique($used[1]) as $key) {
            $this->assertArrayHasKey('dashboard.'.$key, AdminMediaTranslationSeeder::TRANSLATIONS, $key);
        }
    }

    public function test_placeholders_are_kept_in_every_language(): void
    {
        foreach (AdminMediaTranslationSeeder::TRANSLATIONS as $key => $values) {
            preg_match_all('/:[a-z]+/', $values['en'], $en);
            preg_match_all('/:[a-z]+/', $values['ar'], $ar);

            $this->assertEqualsCanonicalizing($en[0], $ar[0], $key);
        }
    }

    public function test_seeding_clears_the_cached_value(): void
    {
        cache()->put('translation.ar.dashboard.Media_in_use', 'stale', 60);

        $this->seed(AdminMediaTranslationSeeder::class);

        $this->assertNull(cache()->get('translation.ar.dashboard.Media_in_use'));
    }
}
