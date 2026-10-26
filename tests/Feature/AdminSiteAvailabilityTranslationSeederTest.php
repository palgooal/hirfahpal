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
        $this->assertCount(31, AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS);

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

    public function test_the_sa_fix_4b_wording_is_used_as_approved_and_the_maintenance_warning_is_unchanged(): void
    {
        $t = AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS;

        $this->assertSame([
            'ar' => 'تظهر صفحة «قريبًا» للزوار والعملاء ومندوبي التوصيل، بينما يستطيع البائعون المعتمدون الوصول إلى لوحاتهم، وتبقى لوحة الإدارة متاحة.',
            'en' => 'Visitors, customers and delivery drivers see the Coming Soon page, while approved vendors can access their dashboards and the admin dashboard remains available.',
        ], $t['dashboard.Site_Availability_Hint_Coming_Soon']);
        $this->assertSame([
            'ar' => 'عند حفظ وضع «قريبًا»، سيتوقف وصول العملاء إلى المتجر، ولن يتمكنوا من تسجيل الدخول أو إتمام الطلبات حتى إعادة فتحه. كما سيتعذر على مندوبي التوصيل الوصول إلى لوحاتهم.',
            'en' => 'Saving Coming Soon mode will block customers from accessing the store, signing in or placing orders until it is reopened. Delivery drivers will also be unable to access their dashboards.',
        ], $t['dashboard.Site_Availability_Warning_Coming_Soon']);
        $this->assertSame([
            'ar' => 'تظهر صفحة الصيانة للزوار والعملاء والبائعين ومندوبي التوصيل (رمز 503)، بينما تبقى لوحة الإدارة متاحة للمسؤولين المصرّح لهم.',
            'en' => 'Visitors, customers, vendors and delivery drivers see the maintenance page (HTTP 503), while the admin dashboard remains accessible to authorized administrators.',
        ], $t['dashboard.Site_Availability_Hint_Maintenance']);
        $this->assertSame([
            'ar' => 'سيظهر للزوار والعملاء والبائعين ومندوبي التوصيل أن الموقع تحت الصيانة (رمز 503)، ولن يستطيعوا تسجيل الدخول أو إتمام أي عملية حتى تعيد فتح الموقع.',
            'en' => 'Visitors, customers, vendors and delivery drivers will see that the site is under maintenance (503) and cannot sign in or complete anything until you reopen the site.',
        ], $t['dashboard.Site_Availability_Warning_Maintenance']);
    }

    // ---------------------------------------------------------------- guarded corrections (SA-FIX-4B)

    public function test_the_corrections_cover_only_the_two_hints_and_lead_to_the_seeded_values(): void
    {
        $keys = [];
        foreach (AdminSiteAvailabilityTranslationSeeder::CORRECTIONS as $correction) {
            $keys[] = $correction['key'].'|'.$correction['locale'];
            $this->assertSame(AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS[$correction['key']][$correction['locale']], $correction['to']);
            $this->assertNotSame($correction['from'], $correction['to']);
        }

        sort($keys);
        $this->assertSame([
            'dashboard.Site_Availability_Hint_Coming_Soon|ar', 'dashboard.Site_Availability_Hint_Coming_Soon|en',
            'dashboard.Site_Availability_Hint_Maintenance|ar', 'dashboard.Site_Availability_Hint_Maintenance|en',
        ], $keys);
    }

    public function test_previously_seeded_hints_are_corrected_and_everything_else_is_kept(): void
    {
        $this->seedOldHints();
        TranslationValue::create(['key' => 'dashboard.Site_Availability_Title', 'locale' => 'ar', 'value' => 'وضع المتجر']);
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'en', 'value' => 'Everyone sees the maintenance page (503), vendors included.']);

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        foreach (AdminSiteAvailabilityTranslationSeeder::CORRECTIONS as $correction) {
            $this->assertSame($correction['to'], $this->value($correction['key'], $correction['locale']));
        }
        $this->assertSame('وضع المتجر', $this->value('dashboard.Site_Availability_Title', 'ar'));
        $this->assertSame('Everyone sees the maintenance page (503), vendors included.', $this->value('dashboard.Something_else', 'en'), 'Only the listed keys are corrected.');
        $this->assertSame(62 + 1, TranslationValue::count());
    }

    public function test_customized_hints_are_kept_in_each_language(): void
    {
        $this->seedOldHints();
        $custom = [
            ['dashboard.Site_Availability_Hint_Coming_Soon', 'ar', 'نص عربي معدّل.'],
            ['dashboard.Site_Availability_Hint_Maintenance', 'en', 'An edited English hint.'],
            // Differs from the old default only in case: still an edit, never replaced.
            ['dashboard.Site_Availability_Hint_Coming_Soon', 'en', 'visitors see the coming soon page; approved vendors can still use their dashboards.'],
        ];
        foreach ($custom as [$key, $locale, $value]) {
            TranslationValue::where('key', $key)->where('locale', $locale)->update(['value' => $value]);
        }

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        foreach ($custom as [$key, $locale, $value]) {
            $this->assertSame($value, $this->value($key, $locale));
        }
        // The untouched old default of the same key in the other language is still corrected.
        $this->assertSame(AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS['dashboard.Site_Availability_Hint_Maintenance']['ar'], $this->value('dashboard.Site_Availability_Hint_Maintenance', 'ar'));
    }

    public function test_corrections_are_idempotent_and_keep_already_approved_wording(): void
    {
        $this->seedOldHints();
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_t_serves_a_corrected_hint_after_a_stale_cache_entry(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        $this->seedOldHints();
        cache()->put('translation.ar.dashboard.Site_Availability_Hint_Maintenance', 'يرى الجميع صفحة الصيانة (رمز 503)، بمن فيهم البائعون.', 60);

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame(AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS['dashboard.Site_Availability_Hint_Maintenance']['ar'], t('dashboard.Site_Availability_Hint_Maintenance', 'fallback'));
    }

    public function test_every_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        foreach (AdminSiteAvailabilityTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(62, TranslationValue::count());
    }

    public function test_edited_values_are_never_overwritten_and_nothing_is_deleted(): void
    {
        TranslationValue::create(['key' => 'dashboard.Site_Availability_Title', 'locale' => 'ar', 'value' => 'وضع المتجر']);
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'ar', 'value' => 'قيمة أخرى']);

        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);

        $this->assertSame('وضع المتجر', $this->value('dashboard.Site_Availability_Title', 'ar'));
        $this->assertSame('Site availability', $this->value('dashboard.Site_Availability_Title', 'en'));
        $this->assertSame('قيمة أخرى', $this->value('dashboard.Something_else', 'ar'));
        $this->assertSame(62 + 1, TranslationValue::count());
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

    /** The rows a database seeded before SA-FIX-4B holds for the two hints (the first shipped defaults). */
    private function seedOldHints(): void
    {
        foreach (AdminSiteAvailabilityTranslationSeeder::CORRECTIONS as $correction) {
            TranslationValue::create(['key' => $correction['key'], 'locale' => $correction['locale'], 'value' => $correction['from']]);
        }
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
