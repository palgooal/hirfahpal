<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminVendorTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-ADM-009 / FR-ADM-015 / AD-UI-VENDORS-01: AdminVendorTranslationSeeder. The 4 commission rate keys, the 2 store
 * review card keys, the 55 keys of the vendor details page (Vendors_*, VendorOrders_Status_*) and the 20 keys of the
 * create vendor form (Vendors_Create_*, Vendors_Form_*, including the 2 profile image keys) and the 11 keys of the vendors list, Arabic and English,
 * English equal to the defaults in the code, edited values never overwritten, the guarded corrections of Arabic rows auto-created
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

    public function test_it_covers_the_approved_keys_in_arabic_and_english(): void
    {
        $keys = array_keys(AdminVendorTranslationSeeder::TRANSLATIONS);
        $this->assertCount(94, $keys);
        $this->assertSame(array_keys(self::ARABIC), array_slice($keys, 0, 6));
        foreach (array_slice($keys, 6) as $key) {
            $this->assertMatchesRegularExpression('/\Adashboard\.(Vendors_|VendorOrders_Status_)/', $key);
        }

        foreach (AdminVendorTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            if (isset(self::ARABIC[$key])) {
                $this->assertSame(self::ARABIC[$key], $values['ar'], $key);
            }
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
        }
        foreach (['Commission_Rate', 'Customer', 'Rating', 'Comment', 'Status', 'Pending', 'Published', 'Hidden', 'Products_Status_Active', 'Nav_Vendors', 'Actions', 'Pagination_Page'] as $shared) {
            $this->assertArrayNotHasKey('dashboard.'.$shared, AdminVendorTranslationSeeder::TRANSLATIONS, $shared);
        }
    }

    public function test_english_values_and_corrections_match_the_defaults_in_the_code(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Dashboard/VendorManagementController.php'))
            .file_get_contents(resource_path('views/dashboard/vendors/show.blade.php'))
            .file_get_contents(resource_path('views/dashboard/vendors/create.blade.php'))
            .file_get_contents(resource_path('views/dashboard/vendors/index.blade.php'));

        foreach (AdminVendorTranslationSeeder::TRANSLATIONS as $key => $values) {
            // Either t('dashboard.Key', 'English') or the page text helper ('Key', 'English').
            $short = substr($key, strlen('dashboard.'));
            $this->assertTrue(
                str_contains($code, "t('{$key}', '{$values['en']}')") || str_contains($code, "'{$short}', '{$values['en']}'"),
                "{$key}: the English value is not the default used in the code."
            );
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
        $this->assertSame(188, TranslationValue::count());
    }

    public function test_the_profile_image_field_is_translated_with_the_defaults_of_the_create_form(): void
    {
        $expected = [
            'dashboard.Vendors_Form_Avatar' => ['ar' => 'الصورة الشخصية', 'en' => 'Profile image'],
            'dashboard.Vendors_Form_Avatar_Hint' => ['ar' => 'JPG أو PNG أو GIF أو WebP، بحجم أقصاه 5 ميغابايت.', 'en' => 'JPG, PNG, GIF or WebP, up to 5 MB.'],
        ];
        $form = file_get_contents(resource_path('views/dashboard/vendors/create.blade.php'));

        $this->seed(AdminVendorTranslationSeeder::class);

        foreach ($expected as $key => $values) {
            $this->assertSame($values, AdminVendorTranslationSeeder::TRANSLATIONS[$key], $key);
            $this->assertStringContainsString("'".substr($key, strlen('dashboard.'))."', '{$values['en']}'", $form, "{$key}: the English value is the default of the create form.");
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
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
        $this->assertSame(188 + 1, TranslationValue::count());
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
