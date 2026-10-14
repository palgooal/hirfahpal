<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminAbilityTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-PROD-M6 (A3): AdminAbilityTranslationSeeder. Exactly 49 ability-label keys (17 inserted in Arabic and
 * English, 32 Arabic rows corrected from the English default), English equal to the defaults in data/abilities.php,
 * customised values kept, nothing deleted, no other key touched, idempotent, stale cache cleared, not called from
 * DatabaseSeeder.
 */
class AdminAbilityTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_exactly_49_keys_split_into_17_inserts_and_32_corrections(): void
    {
        $this->assertCount(17, AdminAbilityTranslationSeeder::TRANSLATIONS);
        $this->assertCount(32, AdminAbilityTranslationSeeder::CORRECTIONS);
        $this->assertSame([], array_intersect_key(AdminAbilityTranslationSeeder::TRANSLATIONS, AdminAbilityTranslationSeeder::CORRECTIONS));
    }

    public function test_every_key_is_an_ability_label_and_english_is_its_default(): void
    {
        $defaults = $this->abilityDefaults();

        foreach (AdminAbilityTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertArrayHasKey($key, $defaults, "{$key} is not an ability label.");
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($defaults[$key], $values['en'], $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
        }

        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            $this->assertArrayHasKey($key, $defaults, "{$key} is not an ability label.");
            $this->assertSame($defaults[$key], $correction['from'], $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $correction['to'], $key);
        }
    }

    public function test_the_17_missing_labels_are_inserted_in_both_languages(): void
    {
        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach (AdminAbilityTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(34, TranslationValue::count(), 'Corrections never insert.');
    }

    public function test_the_32_english_arabic_rows_are_corrected_and_the_english_rows_kept(): void
    {
        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $correction['from']]);
            TranslationValue::create(['key' => $key, 'locale' => 'en', 'value' => $correction['from']]);
        }

        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            $this->assertSame($correction['to'], $this->value($key, 'ar'), $key);
            $this->assertSame($correction['from'], $this->value($key, 'en'), $key);
        }
        $this->assertSame(64 + 34, TranslationValue::count());
    }

    public function test_customised_values_are_never_replaced_even_if_they_differ_only_in_case_or_spaces(): void
    {
        $custom = [
            'dashboard.Admins' => 'مديرو النظام',
            'dashboard.Settings' => 'SETTINGS',
            'dashboard.Reviews' => 'Reviews ',
            'dashboard.View_Products' => 'رؤية المنتجات',
        ];
        foreach ($custom as $key => $value) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $value]);
        }
        TranslationValue::create(['key' => 'dashboard.View_Products', 'locale' => 'en', 'value' => 'See products']);

        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach ($custom as $key => $value) {
            $this->assertSame($value, $this->value($key, 'ar'), $key);
        }
        $this->assertSame('See products', $this->value('dashboard.View_Products', 'en'));
    }

    public function test_other_keys_are_untouched_and_nothing_is_deleted(): void
    {
        $others = ['dashboard.Edit' => 'Edit', 'dashboard.Categories' => 'Categories', 'dashboard.Products' => 'Products', 'dashboard.Media_Library' => 'Media Library', 'dashboard.Customers' => 'Customers'];
        foreach ($others as $key => $value) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $value]);
        }
        $before = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminAbilityTranslationSeeder::class);

        $this->assertSame($before, TranslationValue::whereIn('key', array_keys($others))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(count($others) + 34, TranslationValue::count());
    }

    public function test_running_twice_changes_nothing(): void
    {
        TranslationValue::create(['key' => 'dashboard.Admins', 'locale' => 'ar', 'value' => 'Admins']);
        $this->seed(AdminAbilityTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminAbilityTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_t_serves_the_new_values_after_a_stale_cache_entry(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        TranslationValue::create(['key' => 'dashboard.Admins', 'locale' => 'ar', 'value' => 'Admins']);
        cache()->put('translation.ar.dashboard.Admins', 'Admins', 60);
        cache()->put('translation.ar.dashboard.View_Orders', 'stale', 60);

        $this->seed(AdminAbilityTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('المشرفون', t('dashboard.Admins', 'fallback'));
        $this->assertSame('عرض الطلبات', t('dashboard.View_Orders', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('View Orders', t('dashboard.View_Orders', 'fallback'));
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('AdminAbilityTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    /** @return array<string, string> key => English default in data/abilities.php */
    private function abilityDefaults(): array
    {
        preg_match_all("/t\\('(dashboard\\.[A-Za-z_]+)',\\s*'([^']+)'\\)/", file_get_contents(base_path('data/abilities.php')), $matches, PREG_SET_ORDER);

        return array_column($matches, 2, 1);
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
