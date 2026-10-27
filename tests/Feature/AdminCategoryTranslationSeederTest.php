<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * AD-UI-CAT-01C-1G / 01C-2B: the category protection and hierarchy messages are seeded in Arabic and
 * English, idempotently, without overwriting edits, and are then served by t().
 */
class AdminCategoryTranslationSeederTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private const KEYS = [
        'dashboard.Category_delete_has_products',
        'dashboard.Category_delete_has_children',
        'dashboard.Category_delete_in_use',
        'dashboard.Category_parent_missing',
        'dashboard.Category_parent_not_root',
        'dashboard.Category_parent_self',
        'dashboard.Category_parent_has_children',
        'dashboard.Category_deactivate_has_products',
        'dashboard.Category_Image',
        'dashboard.Category_image_missing',
        'dashboard.Category_image_invalid',
        'dashboard.Category_image_conflict',
        'dashboard.Category_image_vendor_owned',
        // Categories list (AD-UI-CAT-01E-3A).
        'dashboard.Categories_Intro', 'dashboard.Category_Add', 'dashboard.Categories_Dismiss', 'dashboard.Categories_List',
        'dashboard.Categories_Filters', 'dashboard.Categories_Search_Label', 'dashboard.Categories_Search_Placeholder',
        'dashboard.Categories_Filter_Level', 'dashboard.Categories_Filter_Level_All', 'dashboard.Category_Level_Root',
        'dashboard.Category_Level_Child', 'dashboard.Categories_Filter_Status', 'dashboard.Categories_Filter_Status_All',
        'dashboard.Category_Status_Active', 'dashboard.Category_Status_Inactive', 'dashboard.Categories_Apply',
        'dashboard.Categories_Reset', 'dashboard.Categories_Empty', 'dashboard.Categories_Empty_Hint',
        'dashboard.Categories_Empty_Filtered', 'dashboard.Category_Under', 'dashboard.Category_Products_Direct',
        'dashboard.Category_Children_Count', 'dashboard.Category_Sort_Order', 'dashboard.Category_Edit',
        'dashboard.Category_Edit_Named', 'dashboard.Category_Delete', 'dashboard.Category_Delete_Named',
        'dashboard.Category_Delete_Blocked_Products', 'dashboard.Category_Delete_Blocked_Children',
        'dashboard.Category_Delete_Confirm_Title', 'dashboard.Category_Delete_Confirm_Text', 'dashboard.Category_Delete_Confirm',
        'dashboard.Category_Delete_Cancel', 'dashboard.Pagination_Label', 'dashboard.Pagination_Summary',
        'dashboard.Pagination_Previous', 'dashboard.Pagination_Next', 'dashboard.Pagination_Page',
        // Category form (AD-UI-CAT-01E-3B).
        'dashboard.Category_Form_Create_Intro', 'dashboard.Category_Form_Edit_Intro', 'dashboard.Category_Form_Inactive_Note',
        'dashboard.Category_Form_Errors', 'dashboard.Category_Form_Section_Basics', 'dashboard.Category_Form_Section_Placement',
        'dashboard.Category_Field_Name', 'dashboard.Category_Field_Slug', 'dashboard.Category_Field_Slug_Hint',
        'dashboard.Category_Field_Description', 'dashboard.Category_Field_Parent', 'dashboard.Category_Field_Parent_None',
        'dashboard.Category_Field_Parent_Hint', 'dashboard.Category_Field_Parent_Locked', 'dashboard.Category_Field_Order',
        'dashboard.Category_Field_Order_Hint', 'dashboard.Category_Required', 'dashboard.Category_Optional',
        'dashboard.Category_Save', 'dashboard.Category_Saving', 'dashboard.Category_Cancel',
        // Delete confirmation and validation field names (AD-UI-CAT-01E-3C).
        'dashboard.Category_Delete_Confirm_Action', 'dashboard.Category_Delete_Confirmation_Invalid', 'dashboard.Category_Delete_Gone',
        'dashboard.Category_Attribute_Name', 'dashboard.Category_Attribute_Slug', 'dashboard.Category_Attribute_Parent',
        'dashboard.Category_Attribute_Order', 'dashboard.Category_Attribute_Description', 'dashboard.Category_Attribute_Image',
        'dashboard.Category_Attribute_Remove_Image',
        // Final audit fixes (AD-UI-CAT-01F-FIX-01).
        'dashboard.Category_slug_invalid', 'dashboard.Category_slug_not_generated', 'dashboard.Categories_Page_Out_Of_Range',
        'dashboard.Categories_Go_Last_Page',
        'dashboard.Edit_Category', 'dashboard.Category_saved', 'dashboard.Category_deleted',
    ];

    public function test_exactly_the_listed_keys_are_seeded_in_arabic_and_english(): void
    {
        $this->assertSame(self::KEYS, array_keys(AdminCategoryTranslationSeeder::TRANSLATIONS));

        $this->seed(AdminCategoryTranslationSeeder::class);

        foreach (AdminCategoryTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], "{$key} en must not be Arabic.");
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }

        $this->assertSame(count(self::KEYS) * 2, TranslationValue::count());
        $this->assertEqualsCanonicalizing(self::KEYS, TranslationValue::distinct()->pluck('key')->all());
    }

    public function test_the_english_defaults_in_the_controller_match_the_seeded_english(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Dashboard/CategoryManagementController.php'))
            .file_get_contents(base_path('data/abilities.php'));
        // The list, the form and the pagination pass their keys without the dashboard. prefix ($catText, $pageText).
        $views = file_get_contents(resource_path('views/dashboard/categories/index.blade.php'))
            .file_get_contents(resource_path('views/dashboard/categories/form.blade.php'))
            .file_get_contents(resource_path('views/dashboard/categories/delete.blade.php'))
            .file_get_contents(resource_path('views/dashboard/partials/pagination.blade.php'));
        // The image field label is passed to the media picker component as a key and a default.
        $picker = file_get_contents(resource_path('views/components/admin/media-picker.blade.php'));

        foreach (AdminCategoryTranslationSeeder::TRANSLATIONS as $key => $values) {
            if ($key === 'dashboard.Category_Image') {
                $this->assertStringContainsString("'labelKey' => 'Category_Image',\n    'labelDefault' => '{$values['en']}'", $picker, $key);

                continue;
            }

            $english = str_replace("'", "\\'", $values['en']);
            $short = substr($key, strlen('dashboard.'));

            $this->assertTrue(
                str_contains($source, "'{$key}', '{$english}'") || str_contains($views, "'{$short}', '{$english}'"),
                "{$key}: its English value must be the default used in the code."
            );
        }
    }

    public function test_an_edited_translation_is_never_overwritten(): void
    {
        TranslationValue::create(['key' => 'dashboard.Category_delete_in_use', 'locale' => 'ar', 'value' => 'التصنيف مستخدم ولا يمكن حذفه.']);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame('التصنيف مستخدم ولا يمكن حذفه.', $this->value('dashboard.Category_delete_in_use', 'ar'));
        $this->assertSame('This category is in use and cannot be deleted.', $this->value('dashboard.Category_delete_in_use', 'en'));
        $this->assertSame(1, TranslationValue::where('key', 'dashboard.Category_delete_in_use')->where('locale', 'ar')->count());
    }

    public function test_running_twice_creates_no_duplicates_and_changes_nothing(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(0, TranslationValue::query()->selectRaw('`key`, locale, count(*) as n')->groupBy('key', 'locale')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_t_serves_the_seeded_values_and_a_stale_cache_entry_is_cleared(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.dashboard.Category_parent_missing', 'stale', 60);

        $this->seed(AdminCategoryTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('التصنيف الرئيسي المختار لم يعد موجودًا.', t('dashboard.Category_parent_missing', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('This category cannot be deleted because it has associated products.', t('dashboard.Category_delete_has_products', 'fallback'));
    }

    public function test_the_old_delete_message_defaults_are_corrected_and_the_cache_is_cleared(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        $old = $this->oldDeleteMessage();
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'ar', 'value' => $old['ar']]);
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'en', 'value' => $old['en']]);
        cache()->put('translation.ar.dashboard.Category_delete_has_products', $old['ar'], 60);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame('لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات.', $this->value('dashboard.Category_delete_has_products', 'ar'));
        $this->assertSame('This category cannot be deleted because it has associated products.', $this->value('dashboard.Category_delete_has_products', 'en'));
        $this->assertSame(1, TranslationValue::where('key', 'dashboard.Category_delete_has_products')->where('locale', 'ar')->count());
        app()->setLocale('ar');
        $this->assertSame('لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات.', t('dashboard.Category_delete_has_products', 'fallback'));
    }

    public function test_a_customised_delete_message_is_never_replaced_by_the_correction(): void
    {
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'ar', 'value' => 'هذا التصنيف مستخدم في منتجات، ولا يمكن حذفه.']);
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'en', 'value' => $this->oldDeleteMessage()['en']]);
        $customised = TranslationValue::where('key', 'dashboard.Category_delete_has_products')->where('locale', 'ar')->first(['id', 'value', 'updated_at'])->toArray();

        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);

        // The edited Arabic row is untouched; only the English row still holding the old default is corrected.
        $this->assertSame($customised, TranslationValue::where('key', 'dashboard.Category_delete_has_products')->where('locale', 'ar')->first(['id', 'value', 'updated_at'])->toArray());
        $this->assertSame('This category cannot be deleted because it has associated products.', $this->value('dashboard.Category_delete_has_products', 'en'));
    }

    public function test_a_customisation_differing_only_in_case_is_never_replaced(): void
    {
        // On MySQL's case-insensitive collation a SQL "=" would match this; the seeder compares exactly
        // (proven on MySQL by TranslationCorrectionCaseMySqlTest).
        $variant = mb_strtoupper($this->oldDeleteMessage()['en']);
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'en', 'value' => $variant]);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame($variant, $this->value('dashboard.Category_delete_has_products', 'en'));
    }

    public function test_auto_created_english_labels_in_arabic_are_corrected_and_edits_are_kept(): void
    {
        // AD-UI-CAT-01E-3A: as found on the development database (t() auto-created the English default in Arabic).
        foreach (['dashboard.Edit_Category' => 'Edit Category', 'dashboard.Category_saved' => 'Category saved successfully.'] as $key => $english) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $english]);
            TranslationValue::create(['key' => $key, 'locale' => 'en', 'value' => $english]);
        }
        // An edit and a case variant are not the auto-created value: kept.
        TranslationValue::create(['key' => 'dashboard.Category_deleted', 'locale' => 'ar', 'value' => 'حُذف.']);
        cache()->put('translation.ar.dashboard.Edit_Category', 'Edit Category', 60);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame('تعديل التصنيف', $this->value('dashboard.Edit_Category', 'ar'));
        $this->assertSame('Edit Category', $this->value('dashboard.Edit_Category', 'en'));
        $this->assertSame('تم حفظ التصنيف بنجاح.', $this->value('dashboard.Category_saved', 'ar'));
        $this->assertSame('Category saved successfully.', $this->value('dashboard.Category_saved', 'en'));
        $this->assertSame('حُذف.', $this->value('dashboard.Category_deleted', 'ar'));
        $this->assertNull(cache()->get('translation.ar.dashboard.Edit_Category'));

        TranslationValue::where('key', 'dashboard.Edit_Category')->where('locale', 'ar')->update(['value' => 'EDIT CATEGORY']);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->assertSame('EDIT CATEGORY', $this->value('dashboard.Edit_Category', 'ar'), 'A case variant is an edit.');
    }

    public function test_the_correction_touches_only_the_listed_rows(): void
    {
        $this->assertSame(['dashboard.Category_delete_has_products', 'dashboard.Edit_Category', 'dashboard.Category_saved', 'dashboard.Category_deleted'], array_values(array_unique(array_column(AdminCategoryTranslationSeeder::CORRECTIONS, 'key'))));

        // A different key holding the same old text is not touched.
        TranslationValue::create(['key' => 'dashboard.Some_other_key', 'locale' => 'en', 'value' => $this->oldDeleteMessage()['en']]);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame($this->oldDeleteMessage()['en'], $this->value('dashboard.Some_other_key', 'en'));
    }

    /** @return array{ar: string, en: string} */
    private function oldDeleteMessage(): array
    {
        $old = collect(AdminCategoryTranslationSeeder::CORRECTIONS)->where('key', 'dashboard.Category_delete_has_products')->pluck('from', 'locale')->all();
        $this->assertSame('This category has products and cannot be deleted. Deactivate it instead.', $old['en']);

        return $old;
    }

    public function test_the_blocked_delete_shows_the_seeded_arabic_message_without_creating_rows(): void
    {
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $rows = TranslationValue::count();

        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        DB::table('products')->insert(['vendor_id' => Vendor::factory()->create()->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10]);
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);

        $this->withSession(['locale' => 'ar'])
            ->confirmedDelete($admin, $category)
            ->assertSessionHas('danger', AdminCategoryTranslationSeeder::TRANSLATIONS['dashboard.Category_delete_has_products']['ar']);

        $this->assertSame($rows, TranslationValue::count(), 'No translation row is auto-created.');
    }

    public function test_a_refused_parent_shows_the_seeded_arabic_hierarchy_message_without_creating_rows(): void
    {
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $rows = TranslationValue::count();

        $root = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $child = Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $root->id]);
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);

        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'ar'])
            ->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Small jugs', 'parent_id' => $child->id, 'is_active' => 1])
            ->assertSessionHasErrors(['parent_id' => AdminCategoryTranslationSeeder::TRANSLATIONS['dashboard.Category_parent_not_root']['ar']]);

        $this->assertSame($rows, TranslationValue::count(), 'No translation row is auto-created.');
    }

    public function test_seeding_renders_nothing_and_auto_creates_nothing(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame(count(self::KEYS) * 2, TranslationValue::count());
        $this->assertSame(0, TranslationValue::whereNotIn('key', self::KEYS)->count());
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
