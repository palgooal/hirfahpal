<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English messages of the category relation protection (DEC-CAT-Q3, AD-UI-CAT-01C-1E):
 * why a category cannot be deleted, and a parent that disappeared before saving; and of the two-level
 * hierarchy (DEC-CAT-Q13, AD-UI-CAT-01C-2A): why a parent is refused; and why a category with products
 * cannot be deactivated (AD-UI-CAT-01D-1). The controller reads them without
 * auto-creating rows, so until this seeder runs the English defaults are shown and nothing is written.
 * Only missing (key, locale) rows are inserted; edited values are kept; the old defaults listed in
 * CORRECTIONS are replaced only while unchanged. Not called from DatabaseSeeder.
 */
class AdminCategoryTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Category_delete_has_products' => [
            'ar' => 'لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات.',
            'en' => 'This category cannot be deleted because it has associated products.',
        ],
        'dashboard.Category_delete_has_children' => [
            'ar' => 'لا يمكن حذف هذا التصنيف لأن لديه تصنيفات فرعية. انقلها أو احذفها أولًا.',
            'en' => 'This category has subcategories and cannot be deleted. Move or delete them first.',
        ],
        'dashboard.Category_delete_in_use' => [
            'ar' => 'لا يمكن حذف هذا التصنيف لأنه مستخدم.',
            'en' => 'This category is in use and cannot be deleted.',
        ],
        'dashboard.Category_parent_missing' => [
            'ar' => 'التصنيف الرئيسي المختار لم يعد موجودًا.',
            'en' => 'The selected parent category no longer exists.',
        ],

        // Two-level hierarchy (DEC-CAT-Q13).
        'dashboard.Category_parent_not_root' => [
            'ar' => 'اختر تصنيفًا رئيسيًا كأب. التصنيفات الفرعية لا يمكن أن تحتوي على تصنيفات فرعية.',
            'en' => 'Choose a main category as the parent. Subcategories cannot have subcategories of their own.',
        ],
        'dashboard.Category_parent_self' => [
            'ar' => 'لا يمكن أن يكون التصنيف أبًا لنفسه.',
            'en' => 'A category cannot be its own parent.',
        ],
        'dashboard.Category_parent_has_children' => [
            'ar' => 'هذا التصنيف لديه تصنيفات فرعية، لذا لا يمكن نقله تحت تصنيف آخر.',
            'en' => 'This category has subcategories, so it cannot be moved under another category.',
        ],

        // Deactivation protection (AD-UI-CAT-01D-1).
        'dashboard.Category_deactivate_has_products' => [
            'ar' => 'لا يمكن تعطيل هذا التصنيف لأنه مرتبط بمنتجات.',
            'en' => 'This category has products and cannot be deactivated.',
        ],

        // Category image from the media library (AD-UI-CAT-M4-B).
        'dashboard.Category_Image' => [
            'ar' => 'الصورة',
            'en' => 'Image',
        ],
        'dashboard.Category_image_missing' => [
            'ar' => 'الصورة المختارة لم تعد موجودة. اختر صورة أخرى.',
            'en' => 'The selected image no longer exists. Choose another image.',
        ],
        'dashboard.Category_image_invalid' => [
            'ar' => 'الملف المختار ليس صورة مدعومة (JPG أو PNG أو GIF أو WebP).',
            'en' => 'The selected file is not a supported image (JPG, PNG, GIF or WebP).',
        ],
        'dashboard.Category_image_conflict' => [
            'ar' => 'اختر صورة جديدة أو أزل الصورة الحالية، لا الاثنين معًا.',
            'en' => 'Choose a new image or remove the current one, not both.',
        ],
        // ADMIN-MEDIA-ORGANIZATION-01: category images come from the admin media library only.
        'dashboard.Category_image_vendor_owned' => [
            'ar' => 'هذه الصورة تابعة لأحد البائعين. يرجى اختيار صورة من مكتبة وسائط الإدارة.',
            'en' => 'This image belongs to a vendor. Choose an image from the admin media library.',
        ],

        // Categories list (AD-UI-CAT-01E-3A): resources/views/dashboard/categories/index.blade.php.
        'dashboard.Categories_Intro' => [
            'ar' => 'نظّم التصنيفات التي يتصفحها المتسوقون في المتجر. يمكن أن يضم التصنيف الرئيسي تصنيفات فرعية.',
            'en' => 'Organise the categories shoppers browse in the store. A main category can hold subcategories.',
        ],
        'dashboard.Category_Add' => [
            'ar' => 'إضافة تصنيف',
            'en' => 'Add category',
        ],
        'dashboard.Categories_Dismiss' => [
            'ar' => 'إغلاق التنبيه',
            'en' => 'Dismiss',
        ],
        'dashboard.Categories_List' => [
            'ar' => 'قائمة التصنيفات',
            'en' => 'Categories list',
        ],
        'dashboard.Categories_Filters' => [
            'ar' => 'تصفية التصنيفات',
            'en' => 'Filter categories',
        ],
        'dashboard.Categories_Search_Label' => [
            'ar' => 'البحث في التصنيفات',
            'en' => 'Search categories',
        ],
        'dashboard.Categories_Search_Placeholder' => [
            'ar' => 'ابحث بالاسم',
            'en' => 'Search by name',
        ],
        'dashboard.Categories_Filter_Level' => [
            'ar' => 'النوع',
            'en' => 'Type',
        ],
        'dashboard.Categories_Filter_Level_All' => [
            'ar' => 'كل الأنواع',
            'en' => 'All types',
        ],
        'dashboard.Category_Level_Root' => [
            'ar' => 'تصنيف رئيسي',
            'en' => 'Main category',
        ],
        'dashboard.Category_Level_Child' => [
            'ar' => 'تصنيف فرعي',
            'en' => 'Subcategory',
        ],
        'dashboard.Categories_Filter_Status' => [
            'ar' => 'الحالة',
            'en' => 'Status',
        ],
        'dashboard.Categories_Filter_Status_All' => [
            'ar' => 'كل الحالات',
            'en' => 'All statuses',
        ],
        'dashboard.Category_Status_Active' => [
            'ar' => 'مفعّل',
            'en' => 'Active',
        ],
        'dashboard.Category_Status_Inactive' => [
            'ar' => 'غير مفعّل',
            'en' => 'Inactive',
        ],
        'dashboard.Categories_Apply' => [
            'ar' => 'تطبيق',
            'en' => 'Apply',
        ],
        'dashboard.Categories_Reset' => [
            'ar' => 'إعادة الضبط',
            'en' => 'Reset',
        ],
        'dashboard.Categories_Empty' => [
            'ar' => 'لا توجد تصنيفات بعد.',
            'en' => 'No categories yet.',
        ],
        'dashboard.Categories_Empty_Hint' => [
            'ar' => 'أضف أول تصنيف لتبدأ تنظيم المتجر.',
            'en' => 'Add the first category to start organising the store.',
        ],
        'dashboard.Categories_Empty_Filtered' => [
            'ar' => 'لا توجد تصنيفات تطابق عوامل التصفية.',
            'en' => 'No categories match these filters.',
        ],
        'dashboard.Category_Under' => [
            'ar' => 'ضمن',
            'en' => 'Under',
        ],
        'dashboard.Category_Products_Direct' => [
            'ar' => 'منتجات مباشرة',
            'en' => 'Direct products',
        ],
        'dashboard.Category_Children_Count' => [
            'ar' => 'تصنيفات فرعية',
            'en' => 'Subcategories',
        ],
        'dashboard.Category_Sort_Order' => [
            'ar' => 'الترتيب',
            'en' => 'Order',
        ],
        'dashboard.Category_Edit' => [
            'ar' => 'تعديل',
            'en' => 'Edit',
        ],
        'dashboard.Category_Edit_Named' => [
            'ar' => 'تعديل :name',
            'en' => 'Edit :name',
        ],
        'dashboard.Category_Delete' => [
            'ar' => 'حذف',
            'en' => 'Delete',
        ],
        'dashboard.Category_Delete_Named' => [
            'ar' => 'حذف :name',
            'en' => 'Delete :name',
        ],
        'dashboard.Category_Delete_Blocked_Products' => [
            'ar' => 'لا يمكن حذفه: مرتبط بمنتجات.',
            'en' => 'Cannot be deleted: it has products.',
        ],
        'dashboard.Category_Delete_Blocked_Children' => [
            'ar' => 'لا يمكن حذفه: لديه تصنيفات فرعية.',
            'en' => 'Cannot be deleted: it has subcategories.',
        ],
        'dashboard.Category_Delete_Confirm_Title' => [
            'ar' => 'حذف هذا التصنيف؟',
            'en' => 'Delete this category?',
        ],
        'dashboard.Category_Delete_Confirm_Text' => [
            'ar' => 'سيُحذف التصنيف :name. لا يمكن التراجع عن ذلك.',
            'en' => ':name will be deleted. This cannot be undone.',
        ],
        'dashboard.Category_Delete_Confirm' => [
            'ar' => 'حذف التصنيف',
            'en' => 'Delete category',
        ],
        'dashboard.Category_Delete_Cancel' => [
            'ar' => 'إلغاء',
            'en' => 'Cancel',
        ],

        // Admin pagination (resources/views/dashboard/partials/pagination.blade.php), first used by this list.
        'dashboard.Pagination_Label' => [
            'ar' => 'التنقل بين الصفحات',
            'en' => 'Pagination',
        ],
        'dashboard.Pagination_Summary' => [
            'ar' => 'عرض :from إلى :to من :total',
            'en' => 'Showing :from to :to of :total',
        ],
        'dashboard.Pagination_Previous' => [
            'ar' => 'السابق',
            'en' => 'Previous',
        ],
        'dashboard.Pagination_Next' => [
            'ar' => 'التالي',
            'en' => 'Next',
        ],
        'dashboard.Pagination_Page' => [
            'ar' => 'الصفحة :page',
            'en' => 'Page :page',
        ],

        // Category create / edit form (AD-UI-CAT-01E-3B): resources/views/dashboard/categories/form.blade.php.
        'dashboard.Category_Form_Create_Intro' => [
            'ar' => 'أضف تصنيفًا يتصفحه المتسوقون في المتجر. التصنيفات الجديدة تكون مفعّلة.',
            'en' => 'Add a category shoppers can browse in the store. New categories are active.',
        ],
        'dashboard.Category_Form_Edit_Intro' => [
            'ar' => 'حدّث بيانات التصنيف. تبقى حالة تفعيله كما هي.',
            'en' => 'Update the category details. Its active state stays as it is.',
        ],
        'dashboard.Category_Form_Inactive_Note' => [
            'ar' => 'هذا التصنيف غير مفعّل، والحفظ لا يغيّر ذلك.',
            'en' => 'This category is inactive. Saving does not change that.',
        ],
        'dashboard.Category_Form_Errors' => [
            'ar' => 'يرجى تصحيح ما يلي:',
            'en' => 'Please correct the following:',
        ],
        'dashboard.Category_Form_Section_Basics' => [
            'ar' => 'البيانات الأساسية',
            'en' => 'Basic details',
        ],
        'dashboard.Category_Form_Section_Placement' => [
            'ar' => 'الموقع في الشجرة',
            'en' => 'Placement',
        ],
        'dashboard.Category_Field_Name' => [
            'ar' => 'الاسم',
            'en' => 'Name',
        ],
        'dashboard.Category_Field_Slug' => [
            'ar' => 'الرابط المختصر (Slug)',
            'en' => 'Slug',
        ],
        'dashboard.Category_Field_Slug_Hint' => [
            'ar' => 'يُستخدم في رابط المتجر. اتركه فارغًا ليُنشأ من الاسم.',
            'en' => 'Used in the store link. Leave it empty to create it from the name.',
        ],
        'dashboard.Category_Field_Description' => [
            'ar' => 'الوصف',
            'en' => 'Description',
        ],
        'dashboard.Category_Field_Parent' => [
            'ar' => 'التصنيف الأب',
            'en' => 'Parent category',
        ],
        'dashboard.Category_Field_Parent_None' => [
            'ar' => 'بلا أب (تصنيف رئيسي)',
            'en' => 'None (main category)',
        ],
        'dashboard.Category_Field_Parent_Hint' => [
            'ar' => 'اختر تصنيفًا رئيسيًا ليصبح هذا تصنيفًا فرعيًا. التصنيفات الرئيسية فقط يمكن أن تكون أبًا.',
            'en' => 'Choose a main category to make this a subcategory. Only main categories can be parents.',
        ],
        'dashboard.Category_Field_Parent_Locked' => [
            'ar' => 'لهذا التصنيف تصنيفات فرعية، لذا يبقى تصنيفًا رئيسيًا.',
            'en' => 'This category has subcategories, so it stays a main category.',
        ],
        'dashboard.Category_Field_Order' => [
            'ar' => 'ترتيب العرض',
            'en' => 'Display order',
        ],
        'dashboard.Category_Field_Order_Hint' => [
            'ar' => 'الأرقام الأصغر تظهر أولًا. القيمة الافتراضية 0.',
            'en' => 'Lower numbers appear first. 0 by default.',
        ],
        'dashboard.Category_Required' => [
            'ar' => 'مطلوب',
            'en' => 'Required',
        ],
        'dashboard.Category_Optional' => [
            'ar' => 'اختياري',
            'en' => 'Optional',
        ],
        'dashboard.Category_Save' => [
            'ar' => 'حفظ التصنيف',
            'en' => 'Save category',
        ],
        'dashboard.Category_Saving' => [
            'ar' => 'جارٍ الحفظ…',
            'en' => 'Saving…',
        ],
        'dashboard.Category_Cancel' => [
            'ar' => 'إلغاء',
            'en' => 'Cancel',
        ],

        // Delete confirmation checked by the server (N-CAT-1, AD-UI-CAT-01E-3C): delete.blade.php and the controller.
        'dashboard.Category_Delete_Confirm_Action' => [
            'ar' => 'تأكيد الحذف',
            'en' => 'Confirm deletion',
        ],
        'dashboard.Category_Delete_Confirmation_Invalid' => [
            'ar' => 'لم يُؤكَّد الحذف أو انتهت صلاحية التأكيد. أكّد الحذف مرة أخرى.',
            'en' => 'The deletion was not confirmed or the confirmation expired. Confirm it again.',
        ],
        'dashboard.Category_Delete_Gone' => [
            'ar' => 'هذا التصنيف لم يعد موجودًا، وربما حُذف من قبل.',
            'en' => 'This category no longer exists. It may have been deleted already.',
        ],

        // Field names in validation messages (N-CAT-2): they replace :attribute in lang/{locale}/validation.php.
        'dashboard.Category_Attribute_Name' => [
            'ar' => 'اسم التصنيف',
            'en' => 'category name',
        ],
        'dashboard.Category_Attribute_Slug' => [
            'ar' => 'الرابط المختصر',
            'en' => 'slug',
        ],
        'dashboard.Category_Attribute_Parent' => [
            'ar' => 'التصنيف الأب',
            'en' => 'parent category',
        ],
        'dashboard.Category_Attribute_Order' => [
            'ar' => 'ترتيب العرض',
            'en' => 'display order',
        ],
        'dashboard.Category_Attribute_Description' => [
            'ar' => 'الوصف',
            'en' => 'description',
        ],
        'dashboard.Category_Attribute_Image' => [
            'ar' => 'الصورة',
            'en' => 'image',
        ],
        'dashboard.Category_Attribute_Remove_Image' => [
            'ar' => 'إزالة الصورة',
            'en' => 'image removal',
        ],

        // Final audit fixes (AD-UI-CAT-01F-FIX-01): slug format (AUD-02) and a page past the last one (AUD-03).
        'dashboard.Category_slug_invalid' => [
            'ar' => 'استخدم حروفًا عربية أو إنجليزية وأرقامًا فقط، مع شرطة واحدة بين الكلمات.',
            'en' => 'Use Arabic or English letters and numbers only, with single hyphens between words.',
        ],
        'dashboard.Category_slug_not_generated' => [
            'ar' => 'لا يمكن إنشاء رابط مختصر من هذا الاسم. أدخل الرابط المختصر.',
            'en' => 'A slug cannot be made from this name. Enter a slug.',
        ],
        'dashboard.Categories_Page_Out_Of_Range' => [
            'ar' => 'لا توجد تصنيفات في هذه الصفحة.',
            'en' => 'There are no categories on this page.',
        ],
        'dashboard.Categories_Go_Last_Page' => [
            'ar' => 'الانتقال إلى الصفحة الأخيرة',
            'en' => 'Go to the last page',
        ],

        // Keys already used by the category pages and the controller, auto-created on the development server with
        // the English text in Arabic (see CORRECTIONS).
        'dashboard.Edit_Category' => [
            'ar' => 'تعديل التصنيف',
            'en' => 'Edit Category',
        ],
        'dashboard.Category_saved' => [
            'ar' => 'تم حفظ التصنيف بنجاح.',
            'en' => 'Category saved successfully.',
        ],
        'dashboard.Category_deleted' => [
            'ar' => 'تم حذف التصنيف بنجاح.',
            'en' => 'Category deleted successfully.',
        ],
    ];

    /**
     * Earlier default texts that are no longer correct. Each row is replaced only while it still holds the
     * exact old default; any other value is an edit and is kept (same mechanism as AdminHeaderTranslationSeeder).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        // AD-UI-CAT-01D-2: a category with products can no longer be deactivated, so the delete message must not
        // suggest deactivating it instead.
        [
            'key' => 'dashboard.Category_delete_has_products',
            'locale' => 'ar',
            'from' => 'لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات. يمكنك تعطيله بدلًا من حذفه.',
            'to' => 'لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات.',
        ],
        [
            'key' => 'dashboard.Category_delete_has_products',
            'locale' => 'en',
            'from' => 'This category has products and cannot be deleted. Deactivate it instead.',
            'to' => 'This category cannot be deleted because it has associated products.',
        ],
        // AD-UI-CAT-01E-3A: auto-created by t() with the English default in Arabic (TV-21) before these keys were
        // seeded. Replaced only while the Arabic row still holds exactly that English default.
        [
            'key' => 'dashboard.Edit_Category',
            'locale' => 'ar',
            'from' => 'Edit Category',
            'to' => 'تعديل التصنيف',
        ],
        [
            'key' => 'dashboard.Category_saved',
            'locale' => 'ar',
            'from' => 'Category saved successfully.',
            'to' => 'تم حفظ التصنيف بنجاح.',
        ],
        [
            'key' => 'dashboard.Category_deleted',
            'locale' => 'ar',
            'from' => 'Category deleted successfully.',
            'to' => 'تم حذف التصنيف بنجاح.',
        ],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::TRANSLATIONS as $key => $values) {
            foreach ($values as $locale => $value) {
                // insertOrIgnore relies on unique(key, locale): an existing row is never overwritten.
                $inserted = TranslationValue::query()->insertOrIgnore([
                    'key' => $key,
                    'locale' => $locale,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($inserted > 0) {
                    cache()->forget("translation.{$locale}.{$key}");
                }
            }
        }

        foreach (self::CORRECTIONS as $correction) {
            // Guarded update: only this (key, locale) row, and only while it still holds the old default exactly.
            // Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" would also replace an edit that
            // differs only in case.
            $row = TranslationValue::query()
                ->where('key', $correction['key'])
                ->where('locale', $correction['locale'])
                ->first();

            if (! $row || $row->value !== $correction['from']) {
                continue;
            }

            $changed = TranslationValue::query()->whereKey($row->getKey())->update(['value' => $correction['to'], 'updated_at' => $now]);

            if ($changed > 0) {
                cache()->forget("translation.{$correction['locale']}.{$correction['key']}");
            }
        }
    }
}
