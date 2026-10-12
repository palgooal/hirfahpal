<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHeaderTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01D-2: header strings are seeded in Arabic and English, and the English
 * dashboard.Admin_Control_Center row is corrected only while it holds the known wrong value.
 */
class AdminHeaderTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'dashboard.Admin_Control_Center';

    private const WRONG = 'لوحة الإدارة';

    private const RIGHT = 'Admin panel';

    public function test_new_header_keys_are_seeded_in_arabic_and_english(): void
    {
        $this->seed(AdminHeaderTranslationSeeder::class);

        foreach (AdminHeaderTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values));
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar']);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en']);
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
    }

    public function test_only_the_needed_keys_are_created(): void
    {
        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['dashboard.Header_Role_Super_Admin', 'dashboard.Header_Role_Admin', self::KEY],
            TranslationValue::distinct()->pluck('key')->all(),
        );
        // Two role keys in ar + en, and only the English row of the corrected key.
        $this->assertSame(5, TranslationValue::count());
        $this->assertSame(0, TranslationValue::where('key', self::KEY)->where('locale', 'ar')->count());
    }

    public function test_the_wrong_english_value_is_corrected_and_the_arabic_row_is_kept(): void
    {
        $ar = TranslationValue::create(['key' => self::KEY, 'locale' => 'ar', 'value' => self::WRONG]);
        $en = TranslationValue::create(['key' => self::KEY, 'locale' => 'en', 'value' => self::WRONG]);
        $arBefore = $ar->fresh()->only(['id', 'value', 'updated_at']);

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertSame(self::RIGHT, $en->fresh()->value);
        $this->assertSame($en->id, $en->fresh()->id, 'The row is updated in place, not recreated.');
        $this->assertEquals($arBefore, $ar->fresh()->only(['id', 'value', 'updated_at']));
    }

    public function test_an_edited_english_value_is_never_overwritten(): void
    {
        $en = TranslationValue::create(['key' => self::KEY, 'locale' => 'en', 'value' => 'Operations center']);
        $before = $en->fresh()->only(['value', 'updated_at']);

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertEquals($before, $en->fresh()->only(['value', 'updated_at']));
    }

    public function test_existing_header_key_values_are_never_overwritten(): void
    {
        TranslationValue::create(['key' => 'dashboard.Header_Role_Admin', 'locale' => 'ar', 'value' => 'مدير']);

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertSame('مدير', $this->value('dashboard.Header_Role_Admin', 'ar'));
        $this->assertSame('Admin', $this->value('dashboard.Header_Role_Admin', 'en'));
    }

    public function test_running_twice_changes_nothing_more(): void
    {
        TranslationValue::create(['key' => self::KEY, 'locale' => 'en', 'value' => self::WRONG]);

        $this->seed(AdminHeaderTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(0, TranslationValue::query()->selectRaw('`key`, locale, count(*) as n')->groupBy('key', 'locale')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_sidebar_translations_and_unrelated_rows_are_not_changed(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        TranslationValue::create(['key' => 'dashboard.Super_Admin', 'locale' => 'ar', 'value' => 'Super Admin']);
        TranslationValue::create(['key' => 'dashboard.Admin_Control_Center_Description', 'locale' => 'en', 'value' => self::WRONG]);
        $before = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertSame($before, TranslationValue::whereIn('id', array_column($before, 'id'))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_the_cached_wrong_value_is_invalidated(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        TranslationValue::create(['key' => self::KEY, 'locale' => 'en', 'value' => self::WRONG]);

        app()->setLocale('en');
        $this->assertSame(self::WRONG, t(self::KEY, 'fallback'), 'The wrong value is served and cached first.');

        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->assertNull(cache()->get('translation.en.'.self::KEY));
        $this->assertSame(self::RIGHT, t(self::KEY, 'fallback'));
    }

    public function test_seeded_role_labels_are_served_by_t(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        $this->seed(AdminHeaderTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('مشرف عام', t('dashboard.Header_Role_Super_Admin', 'Super admin'));
        app()->setLocale('en');
        $this->assertSame('Admin', t('dashboard.Header_Role_Admin', 'fallback'));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
