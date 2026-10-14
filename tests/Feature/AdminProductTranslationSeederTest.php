<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminProductTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-PROD-M3: AdminProductTranslationSeeder. Product-specific keys only (the list and the M0 messages), Arabic and
 * English, English equal to the defaults in the code, existing values never overwritten, the one guarded correction
 * (Product_saved auto-created with English in Arabic), shared keys untouched, and a stale cache entry cleared.
 */
class AdminProductTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_key_is_product_specific_and_seeded_in_arabic_and_english(): void
    {
        $this->seed(AdminProductTranslationSeeder::class);

        foreach (AdminProductTranslationSeeder::TRANSLATIONS as $key => $values) {
            // Product-specific keys, and the labels of the products.audit (M4), products.suspend / unsuspend (M5)
            // and products.archive / restore (M6) abilities.
            $this->assertMatchesRegularExpression('/^dashboard\.(Products_|Product_|(Audit|Suspend|Unsuspend|Archive|Restore)_Products$)/', $key, "{$key} is not a shared key.");
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }

        $this->assertSame(count(AdminProductTranslationSeeder::TRANSLATIONS) * 2, TranslationValue::count());
    }

    public function test_the_four_m0_messages_and_the_list_keys_are_covered(): void
    {
        foreach ([
            'Product_delete_disabled', 'Product_stock_below_reserved', 'Product_stock_conflict', 'Product_form_outdated', 'Product_saved', 'Audit_Products',
            // AD-UI-PROD-M5.
            'Suspend_Products', 'Unsuspend_Products', 'Product_suspended', 'Product_unsuspended', 'Product_already_suspended',
            'Product_not_suspended', 'Product_moderation_failed', 'Product_Attribute_Reason',
            // AD-UI-PROD-M6.
            'Archive_Products', 'Restore_Products', 'Product_archived', 'Product_restored', 'Product_already_archived', 'Product_not_archived',
            // AD-UI-PROD-M7-FIX-01 (the form and the attribute names of its validation messages).
            'Products_Form_Field_Name', 'Products_Form_Field_Stock', 'Products_Form_Field_Price', 'Products_Form_Category_Inactive',
            'Products_Form_Suspended_Note', 'Products_Form_Archived_Note', 'Products_Form_Save',
        ] as $key) {
            $this->assertArrayHasKey('dashboard.'.$key, AdminProductTranslationSeeder::TRANSLATIONS);
        }

        // Every key the list and the controller pass to their translation helpers is seeded (the pagination keys
        // come from AdminCategoryTranslationSeeder).
        $used = $this->keysInCode('Products_');

        $this->assertNotEmpty($used);
        foreach ($used as $short) {
            $this->assertArrayHasKey('dashboard.'.$short, AdminProductTranslationSeeder::TRANSLATIONS, "{$short} is used but not seeded.");
        }
    }

    public function test_the_english_values_are_the_defaults_used_in_the_code(): void
    {
        $code = $this->sources();

        foreach (AdminProductTranslationSeeder::TRANSLATIONS as $key => $values) {
            $short = substr($key, strlen('dashboard.'));
            $english = preg_quote(str_replace("'", "\\'", $values['en']), '/');

            $this->assertMatchesRegularExpression(
                "/'(?:dashboard\\.)?{$short}',\\s*'{$english}'/",
                $code,
                "{$key}: its English value must be the default used in the code."
            );
        }
    }

    public function test_an_edited_translation_is_never_overwritten(): void
    {
        TranslationValue::create(['key' => 'dashboard.Products_Intro', 'locale' => 'ar', 'value' => 'منتجات المتجر.']);

        $this->seed(AdminProductTranslationSeeder::class);

        $this->assertSame('منتجات المتجر.', $this->value('dashboard.Products_Intro', 'ar'));
        $this->assertSame(1, TranslationValue::where('key', 'dashboard.Products_Intro')->where('locale', 'ar')->count());
    }

    public function test_running_twice_creates_no_duplicates_and_changes_nothing(): void
    {
        $this->seed(AdminProductTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminProductTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_the_auto_created_english_product_saved_in_arabic_is_corrected_and_the_cache_cleared(): void
    {
        TranslationValue::create(['key' => 'dashboard.Product_saved', 'locale' => 'ar', 'value' => 'Product saved successfully.']);
        cache()->put('translation.ar.dashboard.Product_saved', 'Product saved successfully.', 60);

        $this->seed(AdminProductTranslationSeeder::class);

        $this->assertSame('تم حفظ المنتج بنجاح.', $this->value('dashboard.Product_saved', 'ar'));
        config(['palgoals-locale.auto_create' => false]);
        app()->setLocale('ar');
        $this->assertSame('تم حفظ المنتج بنجاح.', t('dashboard.Product_saved', 'fallback'));
    }

    public function test_a_customised_value_is_never_replaced_by_the_correction_even_if_it_differs_only_in_case(): void
    {
        foreach (['تم الحفظ.', 'PRODUCT SAVED SUCCESSFULLY.'] as $custom) {
            TranslationValue::query()->where('key', 'dashboard.Product_saved')->delete();
            TranslationValue::create(['key' => 'dashboard.Product_saved', 'locale' => 'ar', 'value' => $custom]);

            $this->seed(AdminProductTranslationSeeder::class);

            $this->assertSame($custom, $this->value('dashboard.Product_saved', 'ar'));
        }
    }

    public function test_shared_keys_are_left_untouched(): void
    {
        foreach (['dashboard.Edit' => 'Edit', 'dashboard.Status' => 'Status', 'dashboard.Name' => 'Name', 'dashboard.Price' => 'Price'] as $key => $english) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $english]);
        }

        $this->seed(AdminProductTranslationSeeder::class);

        foreach (['dashboard.Edit' => 'Edit', 'dashboard.Status' => 'Status', 'dashboard.Name' => 'Name', 'dashboard.Price' => 'Price'] as $key => $english) {
            $this->assertSame($english, $this->value($key, 'ar'), "{$key} is shared (OPEN-PROD-14).");
        }
    }

    public function test_t_serves_the_seeded_values_and_a_stale_cache_entry_is_cleared(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.dashboard.Products_Filter_Stock_Low', 'stale', 60);

        $this->seed(AdminProductTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('مخزون منخفض', t('dashboard.Products_Filter_Stock_Low', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('Low stock', t('dashboard.Products_Filter_Stock_Low', 'fallback'));
    }

    private function sources(): string
    {
        return file_get_contents(app_path('Http/Controllers/Dashboard/ProductManagementController.php'))
            .file_get_contents(resource_path('views/dashboard/products/index.blade.php'))
            .file_get_contents(resource_path('views/dashboard/products/show.blade.php'))
            .file_get_contents(resource_path('views/dashboard/products/form.blade.php'))
            .file_get_contents(base_path('data/abilities.php'));
    }

    /** @return list<string> */
    private function keysInCode(string $prefix): array
    {
        preg_match_all("/'({$prefix}[A-Za-z_]+)'/", $this->sources(), $matches);

        return array_values(array_unique($matches[1]));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
