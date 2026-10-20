<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\TranslationValue;
use Database\Seeders\AdminSiteAvailabilityTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 3B: AdminSiteAvailabilityTranslationSeeder. Exactly the keys the settings card and
 * SiteAvailabilityController read, Arabic and English, English equal to the defaults in the code, the approved Arabic
 * texts of §7, edited values kept, nothing deleted, idempotent, stale cache cleared, not called from DatabaseSeeder.
 */
class AdminSiteAvailabilityTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_covers_exactly_the_keys_the_code_reads_with_their_english_defaults(): void
    {
        $this->assertCount(29, AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS);

        // The card and the header badge read their keys through $saText(), the controller through
        // $this->back($level, $key, $default). A key read in two places has the same default in both.
        $defaults = [];
        $sources = [
            resource_path('views/dashboard/settings/partials/site-availability.blade.php') => '/\$saText\(\'(Site_Availability_[A-Za-z_]+)\', \'([^\']+)\'\)/',
            resource_path('views/layouts/partials/dashboard/header.blade.php') => '/\$saText\(\'(Site_Availability_[A-Za-z_]+)\', \'([^\']+)\'\)/',
            app_path('Http/Controllers/Dashboard/SiteAvailabilityController.php') => '/\$this->back\(\'[a-z]+\', \'(Site_Availability_[A-Za-z_]+)\', \'([^\']+)\'\)/',
        ];
        foreach ($sources as $source => $pattern) {
            preg_match_all($pattern, file_get_contents($source), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                $this->assertSame($defaults['dashboard.'.$key] ?? $default, $default, "{$key} has one default everywhere.");
                $defaults['dashboard.'.$key] = $default;
            }
        }

        ksort($defaults);
        $seeded = AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS;
        ksort($seeded);
        $this->assertSame(array_keys($defaults), array_keys($seeded), 'The seeder covers exactly the keys the card and the controller read.');

        foreach ($seeded as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($defaults[$key], $values['en'], "{$key} en is the default in the code.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
        }
    }

    public function test_the_approved_arabic_texts_are_used_as_written(): void
    {
        $t = AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS;

        $this->assertSame('حالة الموقع مفروضة من إعدادات الخادم (HIRFAH_SITE_AVAILABILITY=:value) وتتقدّم على الإعداد المحفوظ هنا. التغييرات تُحفظ لكنها لا تُطبَّق حتى تُعاد القيمة إلى auto.', $t['dashboard.Site_Availability_Override_Warning']['ar']);
        $this->assertSame('سيظهر للزوار والعملاء والبائعين ومندوبي التوصيل أن الموقع تحت الصيانة (رمز 503)، ولن يستطيعوا تسجيل الدخول أو إتمام أي عملية حتى تعيد فتح الموقع.', $t['dashboard.Site_Availability_Warning_Maintenance']['ar']);
        $this->assertSame('سيصبح المتجر متاحًا للجميع فور الحفظ.', $t['dashboard.Site_Availability_Warning_Launch']['ar']);
        $this->assertSame('أؤكد تغيير حالة الموقع', $t['dashboard.Site_Availability_Confirm']['ar']);
        $this->assertSame('مشرف محذوف', $t['dashboard.Site_Availability_Deleted_Admin']['ar']);
        $this->assertStringContainsString(':value', $t['dashboard.Site_Availability_Override_Warning']['en']);
    }

    public function test_every_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        foreach (AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(58, TranslationValue::count());
    }

    public function test_edited_values_are_never_overwritten_and_nothing_is_deleted(): void
    {
        TranslationValue::create(['key' => 'dashboard.Site_Availability_Title', 'locale' => 'ar', 'value' => 'وضع المتجر']);
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'ar', 'value' => 'قيمة أخرى']);

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        $this->assertSame('وضع المتجر', $this->value('dashboard.Site_Availability_Title', 'ar'));
        $this->assertSame('Site availability', $this->value('dashboard.Site_Availability_Title', 'en'));
        $this->assertSame('قيمة أخرى', $this->value('dashboard.Something_else', 'ar'));
        $this->assertSame(58 + 1, TranslationValue::count());
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_t_serves_the_seeded_values_after_a_stale_cache_entry(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.dashboard.Site_Availability_State_Maintenance', 'stale', 60);

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('صيانة', t('dashboard.Site_Availability_State_Maintenance', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('Save site state', t('dashboard.Site_Availability_Save', 'fallback'));
    }

    public function test_the_card_creates_no_translation_row(): void
    {
        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Site_Availability_%')->count());
        $this->withoutVite();
        $super = Admin::create(['name' => 'Super', 'email' => 's@example.com', 'phone' => '0599000099', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);

        $this->actingAs($super, 'admin')->get(route('dashboard.setting.index'))->assertOk()->assertSee('id="site-availability"', false);

        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Site_Availability_%')->count());
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('AdminSiteAvailabilityTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
