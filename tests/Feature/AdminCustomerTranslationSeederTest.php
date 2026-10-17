<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminCustomerTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CUS-UI-02: AdminCustomerTranslationSeeder. Exactly the 40 approved keys, Arabic and English, the approved reused
 * keys and the shared keys left untouched, edited values never overwritten, the one guarded correction
 * (Customer_status_updated auto-created with English in Arabic), nothing deleted, idempotent, stale cache cleared,
 * not called from DatabaseSeeder.
 */
class AdminCustomerTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The 40 keys approved in CUS-UI-02. */
    private const APPROVED = [
        'Customers_Intro', 'Customers_Search_Label', 'Customers_Search_Placeholder', 'Customers_Filter_Status', 'Customers_Filter_All',
        'Customers_Apply', 'Customers_Clear', 'Customers_Column_Customer', 'Customers_Column_Phone', 'Customers_Column_Status',
        'Customers_View', 'Customers_Status_Active', 'Customers_Status_Pending', 'Customers_Status_Blocked', 'Customers_Empty_Title',
        'Customers_Empty_Text', 'Customers_No_Results_Title', 'Customers_No_Results_Text',
        'Customers_Show_Back', 'Customers_Show_Contact', 'Customers_Show_Email', 'Customers_Status_Title', 'Customers_Status_Label',
        'Customers_Status_Hint', 'Customers_Status_Save', 'Customers_Recent_Orders', 'Customers_Order_Number', 'Customers_Order_Total',
        'Customers_No_Orders', 'Customers_Addresses', 'Customers_Address_Default', 'Customers_No_Addresses', 'Customers_Review_Rating',
        'Customers_No_Reviews',
        'Orders_Status_Pending', 'Orders_Status_Processing', 'Orders_Status_Completed', 'Orders_Status_Partially_Cancelled', 'Orders_Status_Cancelled',
        'Customer_status_updated',
    ];

    /** Approved keys the customers pages reuse as they are; this seeder must not own them. */
    private const REUSED = ['Orders', 'Reviews', 'Actions', 'City', 'Nav_Customers', 'Pagination_Label', 'Pagination_Summary', 'Pagination_Previous', 'Pagination_Next', 'Pagination_Page'];

    public function test_it_covers_exactly_the_40_approved_keys_in_arabic_and_english(): void
    {
        $this->assertCount(40, self::APPROVED);
        $this->assertSame(
            array_map(fn ($key) => 'dashboard.'.$key, self::APPROVED),
            array_keys(AdminCustomerTranslationSeeder::TRANSLATIONS)
        );

        foreach (AdminCustomerTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
        }
    }

    public function test_reused_and_shared_keys_are_not_seeded(): void
    {
        foreach ([...self::REUSED, 'Status', 'Phone', 'Email', 'Active', 'Pending', 'Blocked', 'View', 'Save', 'All', 'Filter', 'Reset', 'Search_customers', 'No_records_found', 'Recent_Orders', 'Addresses', 'Customer'] as $key) {
            $this->assertArrayNotHasKey('dashboard.'.$key, AdminCustomerTranslationSeeder::TRANSLATIONS, $key);
        }
    }

    public function test_the_order_status_keys_match_the_order_statuses(): void
    {
        preg_match('/private function statuses\(\): array\s*\{\s*return \[(.*?)\];/s', file_get_contents(app_path('Http/Controllers/Dashboard/OrderManagementController.php')), $match);
        preg_match_all("/'([a-z_]+)' =>/", $match[1], $statuses);

        $this->assertSame(['pending', 'processing', 'completed', 'partially_cancelled', 'cancelled'], $statuses[1]);
        foreach ($statuses[1] as $status) {
            $key = 'dashboard.Orders_Status_'.str_replace(' ', '_', ucwords(str_replace('_', ' ', $status)));
            $this->assertArrayHasKey($key, AdminCustomerTranslationSeeder::TRANSLATIONS, $status);
        }
    }

    public function test_the_status_message_english_is_the_controller_default(): void
    {
        $this->assertStringContainsString(
            "t('dashboard.Customer_status_updated', '".AdminCustomerTranslationSeeder::TRANSLATIONS['dashboard.Customer_status_updated']['en']."')",
            file_get_contents(app_path('Http/Controllers/Dashboard/CustomerManagementController.php'))
        );
    }

    public function test_every_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminCustomerTranslationSeeder::class);

        foreach (AdminCustomerTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(80, TranslationValue::count());
    }

    public function test_edited_values_are_never_overwritten_and_nothing_is_deleted(): void
    {
        $custom = [
            ['dashboard.Customers_Intro', 'ar', 'إدارة عملاء المتجر.'],
            ['dashboard.Customers_View', 'en', 'Open'],
            ['dashboard.Orders_Status_Pending', 'ar', 'Pending'],
            ['dashboard.Customer_status_updated', 'ar', 'CUSTOMER STATUS UPDATED SUCCESSFULLY.'],
        ];
        foreach ($custom as [$key, $locale, $value]) {
            TranslationValue::create(['key' => $key, 'locale' => $locale, 'value' => $value]);
        }
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'ar', 'value' => 'قيمة أخرى']);

        $this->seed(AdminCustomerTranslationSeeder::class);

        foreach ($custom as [$key, $locale, $value]) {
            $this->assertSame($value, $this->value($key, $locale), "{$key} [{$locale}] is kept.");
            $this->assertSame(1, TranslationValue::where('key', $key)->where('locale', $locale)->count());
        }
        $this->assertSame('قيمة أخرى', $this->value('dashboard.Something_else', 'ar'));
        $this->assertSame(80 + 1, TranslationValue::count());
    }

    public function test_the_auto_created_english_status_message_in_arabic_is_corrected_and_the_cache_cleared(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        TranslationValue::create(['key' => 'dashboard.Customer_status_updated', 'locale' => 'ar', 'value' => 'Customer status updated successfully.']);
        cache()->put('translation.ar.dashboard.Customer_status_updated', 'Customer status updated successfully.', 60);

        $this->seed(AdminCustomerTranslationSeeder::class);

        $this->assertSame('تم تحديث حالة العميل.', $this->value('dashboard.Customer_status_updated', 'ar'));
        app()->setLocale('ar');
        $this->assertSame('تم تحديث حالة العميل.', t('dashboard.Customer_status_updated', 'fallback'));
    }

    public function test_shared_keys_with_english_arabic_rows_are_left_untouched(): void
    {
        $shared = ['dashboard.Status' => 'Status', 'dashboard.Phone' => 'Phone', 'dashboard.Active' => 'Active', 'dashboard.Pending' => 'Pending', 'dashboard.Blocked' => 'Blocked', 'dashboard.Recent_Orders' => 'Recent Orders'];
        foreach ($shared as $key => $value) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $value]);
        }

        $this->seed(AdminCustomerTranslationSeeder::class);

        foreach ($shared as $key => $value) {
            $this->assertSame($value, $this->value($key, 'ar'), $key);
        }
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->seed(AdminCustomerTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminCustomerTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_t_serves_the_seeded_values_after_a_stale_cache_entry(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.dashboard.Customers_Status_Blocked', 'stale', 60);

        $this->seed(AdminCustomerTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('محظور', t('dashboard.Customers_Status_Blocked', 'fallback'));
        $this->assertSame('قيد التنفيذ', t('dashboard.Orders_Status_Processing', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('Pending activation', t('dashboard.Customers_Status_Pending', 'fallback'));
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('AdminCustomerTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
