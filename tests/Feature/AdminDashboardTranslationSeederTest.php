<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-01C-2: the admin sidebar strings are seeded in Arabic and English under
 * their own dashboard.Nav_* scope, idempotently and without touching other rows.
 */
class AdminDashboardTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_key_is_seeded_in_arabic_and_english(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);

        foreach (AdminDashboardTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), "{$key} must define ar and en.");
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }

        $this->assertSame(count(AdminDashboardTranslationSeeder::TRANSLATIONS) * 2, TranslationValue::count());
    }

    public function test_keys_use_the_dashboard_nav_scope_and_arabic_values_are_arabic(): void
    {
        foreach (AdminDashboardTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertStringStartsWith('dashboard.Nav_', $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
            $this->assertNotSame('', trim($values['ar']));
            $this->assertNotSame('', trim($values['en']));
        }
    }

    public function test_running_twice_creates_no_duplicates(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['key', 'locale', 'value'])->toArray();

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['key', 'locale', 'value'])->toArray());
        $this->assertSame(0, TranslationValue::query()->selectRaw('`key`, locale, count(*) as n')->groupBy('key', 'locale')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_an_existing_value_is_never_overwritten(): void
    {
        TranslationValue::create(['key' => 'dashboard.Nav_Vendors', 'locale' => 'ar', 'value' => 'التجار']);

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame('التجار', $this->value('dashboard.Nav_Vendors', 'ar'));
        $this->assertSame('Vendors', $this->value('dashboard.Nav_Vendors', 'en'));
        $this->assertSame(1, TranslationValue::where('key', 'dashboard.Nav_Vendors')->where('locale', 'ar')->count());
    }

    public function test_an_auto_created_english_media_library_label_in_arabic_is_corrected_and_an_edit_is_kept(): void
    {
        // AD-UI-CAT-M3: rendered on the development server before seeding, t() stored the English default in Arabic.
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'value' => 'Media Library']);
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'en', 'value' => 'Media Library']);
        cache()->put('translation.ar.dashboard.Nav_Media_Library', 'Media Library', 60);

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame('مكتبة الوسائط', $this->value('dashboard.Nav_Media_Library', 'ar'));
        $this->assertSame('Media Library', $this->value('dashboard.Nav_Media_Library', 'en'));
        $this->assertNull(cache()->get('translation.ar.dashboard.Nav_Media_Library'));

        TranslationValue::where('key', 'dashboard.Nav_Media_Library')->where('locale', 'ar')->update(['value' => 'الوسائط']);
        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame('الوسائط', $this->value('dashboard.Nav_Media_Library', 'ar'), 'An edited value is never replaced.');
    }

    public function test_the_media_library_correction_is_case_sensitive(): void
    {
        // Each listed auto-created spelling is corrected; any other casing is an edit and is kept (proven on MySQL,
        // whose collation is case-insensitive, by TranslationCorrectionCaseMySqlTest).
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'value' => 'MEDIA LIBRARY']);
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->assertSame('MEDIA LIBRARY', $this->value('dashboard.Nav_Media_Library', 'ar'));

        TranslationValue::where('key', 'dashboard.Nav_Media_Library')->where('locale', 'ar')->update(['value' => 'Media library']);
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->assertSame('مكتبة الوسائط', $this->value('dashboard.Nav_Media_Library', 'ar'));
    }

    public function test_older_dashboard_keys_are_left_unchanged(): void
    {
        // The legacy labels were auto-created with English text in Arabic; the seeder must not touch them.
        TranslationValue::create(['key' => 'dashboard.Vendors', 'locale' => 'ar', 'value' => 'Vendors']);
        TranslationValue::create(['key' => 'dashboard.Languages', 'locale' => 'en', 'value' => 'Languages']);
        $before = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame($before, TranslationValue::whereIn('id', array_column($before, 'id'))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_vendor_dashboard_translations_are_not_affected(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $vendorRows = TranslationValue::where('key', 'like', 'vendor.%')->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame($vendorRows, TranslationValue::where('key', 'like', 'vendor.%')->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(0, TranslationValue::where('key', 'like', 'vendor.%')->where('key', 'like', '%Nav_%')->whereNotIn('key', array_keys(VendorDashboardTranslationSeeder::TRANSLATIONS))->count());
    }

    public function test_seeding_inserts_only_the_listed_rows_without_rendering_any_page(): void
    {
        // No view is rendered, so t() cannot auto-create anything during seeding.
        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertEqualsCanonicalizing(
            array_keys(AdminDashboardTranslationSeeder::TRANSLATIONS),
            TranslationValue::distinct()->pluck('key')->all(),
        );
    }

    public function test_seeded_values_are_served_by_t_after_seeding(): void
    {
        config(['palgoals-locale.auto_create' => false]);

        $this->seed(AdminDashboardTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('إدارة المتجر', t('dashboard.Nav_Group_Store', 'Store management'));
        app()->setLocale('en');
        $this->assertSame('Store management', t('dashboard.Nav_Group_Store', 'fallback'));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
