<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHeaderTranslationSeeder;
use Database\Seeders\AdminHomeTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-DASH-02: the admin dashboard home texts are seeded in Arabic and English; the auto-created values
 * (code defaults written in the other language) are corrected only while unchanged; edits are kept;
 * Admin_Control_Center (AdminHeaderTranslationSeeder) is not touched; running twice changes nothing.
 */
class AdminHomeTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The twelve keys shared with other admin pages (approved to be corrected with this page). */
    private const SHARED = [
        'dashboard.Categories', 'dashboard.Products', 'dashboard.Customers', 'dashboard.Delivery_Drivers', 'dashboard.Orders',
        'dashboard.Store', 'dashboard.Owner', 'dashboard.City', 'dashboard.Actions', 'dashboard.Order', 'dashboard.Vendor',
        'dashboard.Needs_attention',
    ];

    public function test_the_keys_and_values_are_exactly_the_approved_ones(): void
    {
        $keys = array_keys(AdminHomeTranslationSeeder::TRANSLATIONS);
        $this->assertCount(33, $keys);
        $this->assertNotContains('dashboard.Admin_Control_Center', $keys);

        // Every t() key of the page is covered (Admin_Control_Center by AdminHeaderTranslationSeeder).
        preg_match_all("/t\('(dashboard\.[A-Za-z_]+)'/", file_get_contents(resource_path('views/dashboard/admin/index.blade.php')), $m);
        $this->assertEqualsCanonicalizing(array_values(array_diff(array_unique($m[1]), ['dashboard.Admin_Control_Center'])), $keys);

        foreach (AdminHomeTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
        }

        // 31 Arabic corrections and Admin_Dashboard in English; each "from" is the code default of the page.
        $corrections = collect(AdminHomeTranslationSeeder::CORRECTIONS);
        $this->assertCount(32, $corrections);
        $this->assertSame(31, $corrections->where('locale', 'ar')->count());
        $this->assertSame([['key' => 'dashboard.Admin_Dashboard', 'locale' => 'en', 'from' => 'لوحة الإدارة']], $corrections->where('locale', 'en')->values()->all());
        $this->assertSame(array_values(array_diff($keys, ['dashboard.Review', 'dashboard.Admin_Dashboard'])), $corrections->where('locale', 'ar')->pluck('key')->all());
        foreach ($corrections->where('locale', 'ar') as $correction) {
            $this->assertSame(AdminHomeTranslationSeeder::TRANSLATIONS[$correction['key']]['en'], $correction['from'], $correction['key']);
        }

        // The view's defaults are the English values (Admin_Dashboard's default is English now).
        $view = file_get_contents(resource_path('views/dashboard/admin/index.blade.php'));
        foreach (AdminHomeTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertStringContainsString("t('{$key}', '".str_replace("'", "\\'", $values['en'])."')", $view, $key);
        }
    }

    public function test_a_missing_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminHomeTranslationSeeder::class);

        $this->assertSame('مراجعة', $this->value('dashboard.Review', 'ar'));
        $this->assertSame('Review', $this->value('dashboard.Review', 'en'));
        $this->assertSame(66, TranslationValue::count());
    }

    public function test_the_auto_created_values_are_corrected_as_found_on_the_development_database(): void
    {
        $this->autoCreatedState();
        cache()->put('translation.ar.dashboard.Actions', 'Actions', 60);
        cache()->put('translation.en.dashboard.Admin_Dashboard', 'لوحة الإدارة', 60);

        $this->seed(AdminHomeTranslationSeeder::class);

        foreach (AdminHomeTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'), "{$key} ar");
            $this->assertSame($values['en'], $this->value($key, 'en'), "{$key} en");
        }
        $this->assertNull(cache()->get('translation.ar.dashboard.Actions'));
        $this->assertNull(cache()->get('translation.en.dashboard.Admin_Dashboard'));
        $this->assertSame(66, TranslationValue::count(), 'Review added; nothing duplicated.');
        $this->assertSame(0, TranslationValue::query()->selectRaw('`key`, locale, count(*) n')->groupBy('key', 'locale')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_an_edited_translation_is_never_replaced(): void
    {
        $this->autoCreatedState();
        $edits = [
            ['dashboard.Actions', 'ar', 'العمليات'],
            ['dashboard.Order', 'ar', 'ORDER'],                 // case variant of the auto-created value
            ['dashboard.Admin_Dashboard', 'en', 'Dashboard'],
            ['dashboard.Pending_Vendors', 'en', 'Vendors to approve'],
        ];
        foreach ($edits as [$key, $locale, $value]) {
            TranslationValue::where('key', $key)->where('locale', $locale)->update(['value' => $value]);
        }
        $before = TranslationValue::whereIn('key', array_column($edits, 0))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminHomeTranslationSeeder::class);

        foreach ($edits as [$key, $locale, $value]) {
            $this->assertSame($value, $this->value($key, $locale), "{$key} {$locale}");
        }
        // Their other locale was still auto-created and is corrected; the edited rows are byte-for-byte unchanged.
        $after = collect(TranslationValue::whereIn('key', array_column($edits, 0))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        foreach ($before as $row) {
            if (in_array([$row['key'], $row['locale']], array_map(fn ($e) => [$e[0], $e[1]], $edits), true)) {
                $this->assertSame($row, $after->firstWhere('id', $row['id']));
            }
        }
        $this->assertSame('البائعون النشطون', $this->value('dashboard.Active_Vendors', 'ar'));
    }

    public function test_admin_control_center_is_left_to_its_own_seeder(): void
    {
        TranslationValue::create(['key' => 'dashboard.Admin_Control_Center', 'locale' => 'ar', 'value' => 'لوحة الإدارة']);
        TranslationValue::create(['key' => 'dashboard.Admin_Control_Center', 'locale' => 'en', 'value' => 'لوحة الإدارة']);
        $before = TranslationValue::where('key', 'dashboard.Admin_Control_Center')->orderBy('id')->get()->toArray();

        $this->seed(AdminHomeTranslationSeeder::class);

        $this->assertSame($before, TranslationValue::where('key', 'dashboard.Admin_Control_Center')->orderBy('id')->get()->toArray());

        // Its own seeder still corrects it as before.
        $this->seed(AdminHeaderTranslationSeeder::class);
        $this->assertSame('Admin panel', $this->value('dashboard.Admin_Control_Center', 'en'));
    }

    public function test_running_twice_inserts_and_corrects_nothing_more(): void
    {
        $this->autoCreatedState();
        $this->seed(AdminHomeTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(5)->minutes();
        $this->seed(AdminHomeTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_the_shared_keys_are_consistent_and_owned_by_no_other_seeder(): void
    {
        foreach (self::SHARED as $key) {
            $this->assertArrayHasKey($key, AdminHomeTranslationSeeder::TRANSLATIONS, $key);
        }

        // No other seeder defines any of this seeder's keys (no conflicting values).
        foreach (glob(database_path('seeders/*.php')) as $file) {
            if (basename($file) === 'AdminHomeTranslationSeeder.php') {
                continue;
            }
            $source = file_get_contents($file);
            foreach (array_keys(AdminHomeTranslationSeeder::TRANSLATIONS) as $key) {
                $this->assertStringNotContainsString("'{$key}'", $source, basename($file)." also defines {$key}");
            }
        }

        // The sidebar's terms are reused for the shared module names.
        $nav = AdminDashboardTranslationSeeder::TRANSLATIONS;
        foreach (['Categories', 'Products', 'Customers', 'Orders'] as $module) {
            $this->assertSame($nav["dashboard.Nav_{$module}"]['ar'], AdminHomeTranslationSeeder::TRANSLATIONS["dashboard.{$module}"]['ar'], $module);
        }
        $this->assertSame($nav['dashboard.Nav_Delivery_Drivers']['ar'], AdminHomeTranslationSeeder::TRANSLATIONS['dashboard.Delivery_Drivers']['ar']);
    }

    public function test_the_category_translations_are_not_affected(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $categories = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminHomeTranslationSeeder::class);

        $this->assertSame($categories, TranslationValue::whereIn('key', array_keys(AdminCategoryTranslationSeeder::TRANSLATIONS))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_after_seeding_the_page_creates_no_translation_row_and_shows_the_seeded_texts(): void
    {
        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminHeaderTranslationSeeder::class);
        $this->seed(AdminHomeTranslationSeeder::class);
        $admin = Admin::factory()->create(['super_admin' => true]);

        $seeded = TranslationValue::whereIn('key', array_keys(AdminHomeTranslationSeeder::TRANSLATIONS))->orderBy('id')->get(['id', 'key', 'locale', 'value'])->toArray();
        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.home'))->assertOk();
        }
        // Every key of this seeder is present: rendering creates or changes none of them. (Admin_Control_Center
        // belongs to AdminHeaderTranslationSeeder, which only corrects it; it is outside this seeder.)
        $this->assertCount(66, $seeded);
        $this->assertSame($seeded, TranslationValue::whereIn('key', array_keys(AdminHomeTranslationSeeder::TRANSLATIONS))->orderBy('id')->get(['id', 'key', 'locale', 'value'])->toArray());

        $ar = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.home'))->getContent();
        foreach (['عمليات حرفة', 'إجراءات سريعة', 'بائعون بانتظار المراجعة', 'تنبيهات مهلة الرد', 'الإجراءات'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }
        foreach (['Admin Quick Actions', 'Vendors Waiting Review', 'HIRFAH Operations'] as $english) {
            $this->assertStringNotContainsString($english, $ar, $english);
        }
        $en = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.home'))->getContent();
        $this->assertStringContainsString('aria-current="page">Admin panel</li>', $en);
        $this->assertStringNotContainsString('عمليات حرفة', $en);
    }

    /** As found on the development database: every row auto-created with the page's code default. */
    private function autoCreatedState(): void
    {
        $codeDefaults = collect(AdminHomeTranslationSeeder::TRANSLATIONS)->map(fn ($values) => $values['en'])->all();
        $codeDefaults['dashboard.Admin_Dashboard'] = 'لوحة الإدارة'; // its former (Arabic) code default
        unset($codeDefaults['dashboard.Review']);                    // never created

        foreach ($codeDefaults as $key => $value) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $value]);
            TranslationValue::create(['key' => $key, 'locale' => 'en', 'value' => $value]);
        }
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
