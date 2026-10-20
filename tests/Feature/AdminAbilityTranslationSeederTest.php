<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminAbilityTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-PROD-M6 (A3): AdminAbilityTranslationSeeder. Exactly 53 ability-label keys (17 inserted in Arabic and
 * English, 36 Arabic rows corrected from the English default, including the 2 contact-messages labels of
 * CONTACT-ADMIN-IMPLEMENTATION-01 and the 2 maintenance labels of SITE-AVAILABILITY Phase 3A), English equal to the
 * defaults in data/abilities.php, customised values kept, nothing deleted, no other key touched, idempotent, stale cache cleared, not called from
 * DatabaseSeeder. TR-INIT-01: on a new database the 36 CORRECTIONS keys are inserted too (ar = to, en = from), so all
 * 53 labels exist in both languages (106 rows), without duplicates.
 */
class AdminAbilityTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_exactly_53_keys_split_into_17_inserts_and_36_corrections(): void
    {
        $this->assertCount(17, AdminAbilityTranslationSeeder::TRANSLATIONS);
        $this->assertCount(36, AdminAbilityTranslationSeeder::CORRECTIONS);
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

    public function test_a_new_database_gets_all_53_labels_in_both_languages(): void
    {
        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach (AdminAbilityTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        // TR-INIT-01: the CORRECTIONS keys are initialised with their approved values.
        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            $this->assertSame($correction['to'], $this->value($key, 'ar'), $key);
            $this->assertSame($correction['from'], $this->value($key, 'en'), $key);
        }
        $this->assertSame(106, TranslationValue::count(), '53 keys x 2 languages.');
        $this->assertSame('التقييمات', $this->value('dashboard.Reviews', 'ar'));
        $this->assertNoDuplicates();
    }

    public function test_old_english_arabic_rows_without_an_english_row_are_corrected_and_completed(): void
    {
        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $correction['from']]);
        }
        cache()->put('translation.en.dashboard.Settings', 'stale', 60);

        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach (AdminAbilityTranslationSeeder::CORRECTIONS as $key => $correction) {
            $this->assertSame($correction['to'], $this->value($key, 'ar'), "{$key} [ar] corrected.");
            $this->assertSame($correction['from'], $this->value($key, 'en'), "{$key} [en] inserted.");
        }
        $this->assertSame(106, TranslationValue::count());
        $this->assertNull(cache()->get('translation.en.dashboard.Settings'), 'The inserted row clears its cache entry.');
        $this->assertNoDuplicates();
    }

    public function test_the_36_english_arabic_rows_are_corrected_and_the_english_rows_kept(): void
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
        $this->assertSame(72 + 34, TranslationValue::count(), 'The existing rows are corrected, not duplicated.');
        $this->assertNoDuplicates();
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
        // A customised English row of a CORRECTIONS key is kept too.
        TranslationValue::create(['key' => 'dashboard.Disputes', 'locale' => 'en', 'value' => 'Customer disputes']);

        $this->seed(AdminAbilityTranslationSeeder::class);

        foreach ($custom as $key => $value) {
            $this->assertSame($value, $this->value($key, 'ar'), $key);
        }
        $this->assertSame('See products', $this->value('dashboard.View_Products', 'en'));
        $this->assertSame('Customer disputes', $this->value('dashboard.Disputes', 'en'));
        $this->assertSame('النزاعات', $this->value('dashboard.Disputes', 'ar'), 'Its missing Arabic row is inserted.');
        $this->assertSame('Admins', $this->value('dashboard.Admins', 'en'), 'A missing English row is inserted beside a custom Arabic one.');
        $this->assertSame(106, TranslationValue::count());
        $this->assertNoDuplicates();
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
        $this->assertSame(count($others) + 106, TranslationValue::count());
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

    public function test_running_twice_on_a_new_database_changes_nothing(): void
    {
        $this->seed(AdminAbilityTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminAbilityTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(106, TranslationValue::count());
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

    private function assertNoDuplicates(): void
    {
        $this->assertSame(0, TranslationValue::query()->select('key', 'locale')->groupBy('key', 'locale')->havingRaw('COUNT(*) > 1')->get()->count(), 'No duplicate (key, locale).');
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
