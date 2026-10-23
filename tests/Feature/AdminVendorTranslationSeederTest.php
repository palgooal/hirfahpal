<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminVendorTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-ADM-009 / FR-ADM-015: AdminVendorTranslationSeeder. Exactly the 4 commission rate keys and the 2 store review card keys, Arabic and English, English equal
 * to the defaults in the code, edited values never overwritten, the guarded corrections of Arabic rows auto-created
 * with English, nothing deleted, idempotent, stale cache cleared, not called from DatabaseSeeder.
 */
class AdminVendorTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const ARABIC = [
        'dashboard.Commission_rate_updated_successfully' => 'تم تحديث نسبة العمولة بنجاح',
        'dashboard.Current_commission_rate' => 'نسبة العمولة الحالية',
        'dashboard.Not_set' => 'غير محددة',
        'dashboard.Update_commission_rate' => 'تحديث نسبة العمولة',
        'dashboard.Recent_Reviews' => 'آخر التقييمات',
        'dashboard.No_reviews_found' => 'لا توجد تقييمات لهذا المتجر',
    ];

    public function test_it_covers_exactly_the_six_keys_with_the_approved_arabic(): void
    {
        $this->assertSame(array_keys(self::ARABIC), array_keys(AdminVendorTranslationSeeder::TRANSLATIONS));

        foreach (AdminVendorTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame(self::ARABIC[$key], $values['ar'], $key);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
        }
        foreach (['Commission_Rate', 'Customer', 'Rating', 'Comment', 'Status', 'Pending', 'Published', 'Hidden'] as $shared) {
            $this->assertArrayNotHasKey('dashboard.'.$shared, AdminVendorTranslationSeeder::TRANSLATIONS, $shared);
        }
    }

    public function test_english_values_and_corrections_match_the_defaults_in_the_code(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Dashboard/VendorManagementController.php'))
            .file_get_contents(resource_path('views/dashboard/vendors/show.blade.php'));

        foreach (AdminVendorTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertStringContainsString("t('{$key}', '{$values['en']}')", $code, $key);
        }

        $this->assertCount(6, AdminVendorTranslationSeeder::CORRECTIONS);
        foreach (AdminVendorTranslationSeeder::CORRECTIONS as $correction) {
            $this->assertSame('ar', $correction['locale']);
            $this->assertSame(AdminVendorTranslationSeeder::TRANSLATIONS[$correction['key']]['en'], $correction['from']);
            $this->assertSame(AdminVendorTranslationSeeder::TRANSLATIONS[$correction['key']]['ar'], $correction['to']);
        }
    }

    public function test_every_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminVendorTranslationSeeder::class);

        foreach (AdminVendorTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(12, TranslationValue::count());
    }

    public function test_auto_created_english_arabic_rows_are_corrected_and_the_cache_cleared(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        foreach (AdminVendorTranslationSeeder::CORRECTIONS as $correction) {
            TranslationValue::create(['key' => $correction['key'], 'locale' => 'ar', 'value' => $correction['from']]);
            cache()->put("translation.ar.{$correction['key']}", $correction['from'], 60);
        }

        $this->seed(AdminVendorTranslationSeeder::class);

        app()->setLocale('ar');
        foreach (self::ARABIC as $key => $arabic) {
            $this->assertSame($arabic, $this->value($key, 'ar'));
            $this->assertSame($arabic, t($key, 'fallback'));
        }
    }

    public function test_edited_values_are_never_overwritten_and_nothing_is_deleted(): void
    {
        $custom = [
            ['dashboard.Not_set', 'ar', 'لم تُحدَّد'],
            ['dashboard.Current_commission_rate', 'en', 'Rate now'],
            ['dashboard.Update_commission_rate', 'ar', 'UPDATE COMMISSION RATE'],
        ];
        foreach ($custom as [$key, $locale, $value]) {
            TranslationValue::create(['key' => $key, 'locale' => $locale, 'value' => $value]);
        }
        TranslationValue::create(['key' => 'dashboard.Commission_Rate', 'locale' => 'ar', 'value' => 'Commission Rate']);

        $this->seed(AdminVendorTranslationSeeder::class);

        foreach ($custom as [$key, $locale, $value]) {
            $this->assertSame($value, $this->value($key, $locale), "{$key} [{$locale}] is kept.");
            $this->assertSame(1, TranslationValue::where('key', $key)->where('locale', $locale)->count());
        }
        $this->assertSame('Commission Rate', $this->value('dashboard.Commission_Rate', 'ar'));
        $this->assertSame(12 + 1, TranslationValue::count());
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->seed(AdminVendorTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminVendorTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('AdminVendorTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
