<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * AD-UI-PROD-M6 (A3): Arabic and English labels of the abilities in data/abilities.php shown by the admin roles form,
 * exactly 51 keys. TRANSLATIONS: the 17 labels with no row yet (Arabic and English, 34 rows). CORRECTIONS: the 34
 * labels whose Arabic row was, or may be, auto-created with the English default (32, plus the 2 contact-messages
 * labels of CONTACT-ADMIN-IMPLEMENTATION-01); each is replaced only while it still holds that default exactly (a
 * customised value is kept). Several of these keys are shared with other pages (Admins, Settings,
 * Vendors, Reviews, …); only their Arabic row is corrected, and only from the English default.
 *
 * TR-INIT-01: on a database where a CORRECTIONS key has no row yet (a new environment), its rows are inserted too,
 * Arabic from `to` and English from `from` (the default in data/abilities.php), so the 34 labels no longer depend on
 * t() creating them first with English text. Existing rows are never overwritten by that insert.
 *
 * Only missing (key, locale) rows are inserted; no row is deleted; no other key is touched. Not called from
 * DatabaseSeeder.
 */
class AdminAbilityTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]; the English value is the default in data/abilities.php.
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.View_Categories' => ['ar' => 'عرض التصنيفات', 'en' => 'View Categories'],
        'dashboard.Delete_Category' => ['ar' => 'حذف التصنيف', 'en' => 'Delete Category'],
        'dashboard.View_Products' => ['ar' => 'عرض المنتجات', 'en' => 'View Products'],
        'dashboard.Delete_Product' => ['ar' => 'حذف المنتج', 'en' => 'Delete Product'],
        'dashboard.View_Customers' => ['ar' => 'عرض العملاء', 'en' => 'View Customers'],
        'dashboard.Edit_Customers' => ['ar' => 'تعديل العملاء', 'en' => 'Edit Customers'],
        'dashboard.View_Delivery_Drivers' => ['ar' => 'عرض مندوبي التوصيل', 'en' => 'View Delivery Drivers'],
        'dashboard.Edit_Delivery_Drivers' => ['ar' => 'اعتماد مندوبي التوصيل وتحديثهم', 'en' => 'Approve / Update Delivery Drivers'],
        'dashboard.View_Orders' => ['ar' => 'عرض الطلبات', 'en' => 'View Orders'],
        'dashboard.Edit_Orders' => ['ar' => 'تحديث حالات الطلبات', 'en' => 'Update Order Statuses'],
        'dashboard.View_Reviews' => ['ar' => 'عرض التقييمات', 'en' => 'View Reviews'],
        'dashboard.Moderate_Reviews' => ['ar' => 'إدارة التقييمات', 'en' => 'Moderate Reviews'],
        'dashboard.Delete_Reviews' => ['ar' => 'حذف التقييمات', 'en' => 'Delete Reviews'],
        'dashboard.View_Disputes' => ['ar' => 'عرض النزاعات', 'en' => 'View Disputes'],
        'dashboard.Resolve_Disputes' => ['ar' => 'حل النزاعات', 'en' => 'Resolve Disputes'],
        'dashboard.View_Return_Requests' => ['ar' => 'عرض طلبات الاسترجاع', 'en' => 'View Return Requests'],
        'dashboard.Review_Return_Requests' => ['ar' => 'مراجعة طلبات الاسترجاع', 'en' => 'Review Return Requests'],
    ];

    /**
     * Arabic rows holding the English default (from) and their Arabic text (to). Each row is replaced only while it
     * still holds the English default exactly; any other value is an edit and is kept.
     *
     * @var array<string, array{from: string, to: string}>
     */
    public const CORRECTIONS = [
        'dashboard.Admins' => ['from' => 'Admins', 'to' => 'المشرفون'],
        'dashboard.View_Admins' => ['from' => 'View Admins', 'to' => 'عرض المشرفين'],
        'dashboard.Create_Admin' => ['from' => 'Create Admin', 'to' => 'إضافة مشرف'],
        'dashboard.Edit_Admin' => ['from' => 'Edit Admin', 'to' => 'تعديل مشرف'],
        'dashboard.Delete_Admin' => ['from' => 'Delete Admin', 'to' => 'حذف مشرف'],
        'dashboard.Languages' => ['from' => 'Languages', 'to' => 'اللغات'],
        'dashboard.View_Languages' => ['from' => 'View Languages', 'to' => 'عرض اللغات'],
        'dashboard.Create_Language' => ['from' => 'Create Language', 'to' => 'إضافة لغة'],
        'dashboard.Edit_Language' => ['from' => 'Edit Language', 'to' => 'تعديل لغة'],
        'dashboard.Delete_Language' => ['from' => 'Delete Language', 'to' => 'حذف لغة'],
        'dashboard.Translation_Values' => ['from' => 'Translation Values', 'to' => 'الترجمات'],
        'dashboard.View_Translation_Values' => ['from' => 'View Translation Values', 'to' => 'عرض الترجمات'],
        'dashboard.Create_Translation_Value' => ['from' => 'Create Translation Value', 'to' => 'إضافة ترجمة'],
        'dashboard.Edit_Translation_Value' => ['from' => 'Edit Translation Value', 'to' => 'تعديل ترجمة'],
        'dashboard.Delete_Translation_Value' => ['from' => 'Delete Translation Value', 'to' => 'حذف ترجمة'],
        'dashboard.Settings' => ['from' => 'Settings', 'to' => 'الإعدادات'],
        'dashboard.View_Settings' => ['from' => 'View Settings', 'to' => 'عرض الإعدادات'],
        'dashboard.Edit_Settings' => ['from' => 'Edit Settings', 'to' => 'تعديل الإعدادات'],
        'dashboard.Vendors' => ['from' => 'Vendors', 'to' => 'البائعون'],
        'dashboard.View_Vendors' => ['from' => 'View Vendors', 'to' => 'عرض البائعين'],
        'dashboard.Create_Vendor' => ['from' => 'Create Vendor', 'to' => 'إضافة بائع'],
        'dashboard.Approve_Reject_Vendors' => ['from' => 'Approve / Reject Vendors', 'to' => 'اعتماد البائعين أو رفضهم'],
        'dashboard.Create_Category' => ['from' => 'Create Category', 'to' => 'إضافة تصنيف'],
        'dashboard.Create_Product' => ['from' => 'Create Product', 'to' => 'إضافة منتج'],
        'dashboard.Edit_Product' => ['from' => 'Edit Product', 'to' => 'تعديل المنتج'],
        'dashboard.Vendor_Orders' => ['from' => 'Vendor Orders', 'to' => 'الطلبات الفرعية'],
        'dashboard.View_Vendor_Orders' => ['from' => 'View Vendor Orders', 'to' => 'عرض الطلبات الفرعية'],
        'dashboard.Create_Vendor_Order' => ['from' => 'Create Vendor Order', 'to' => 'إضافة طلب فرعي'],
        'dashboard.Assign_Delivery_Driver' => ['from' => 'Assign Delivery Driver', 'to' => 'إسناد مندوب التوصيل'],
        'dashboard.Reviews' => ['from' => 'Reviews', 'to' => 'التقييمات'],
        'dashboard.Disputes' => ['from' => 'Disputes', 'to' => 'النزاعات'],
        'dashboard.Return_Requests' => ['from' => 'Return Requests', 'to' => 'طلبات الاسترجاع'],
        // CONTACT-ADMIN-IMPLEMENTATION-01: the contact-messages labels. Listed here rather than in TRANSLATIONS because
        // the admin roles form reads data/abilities.php through t() with auto-create, which may store the English
        // default in Arabic before this seeder runs; a new database gets both rows (TR-INIT-01).
        'dashboard.Contact_Messages' => ['from' => 'Contact Messages', 'to' => 'رسائل التواصل'],
        'dashboard.View_Contact_Messages' => ['from' => 'View Contact Messages', 'to' => 'عرض رسائل التواصل'],
    ];

    public function run(): void
    {
        $now = now();

        // The 17 labels, then the 34 CORRECTIONS keys with their approved values (TR-INIT-01): inserted only where the
        // (key, locale) row does not exist yet, so the guarded corrections below still handle the old English rows.
        $initial = self::TRANSLATIONS + array_map(
            fn (array $correction): array => ['ar' => $correction['to'], 'en' => $correction['from']],
            self::CORRECTIONS
        );

        foreach ($initial as $key => $values) {
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

        foreach (self::CORRECTIONS as $key => $correction) {
            // Guarded update of the Arabic row only, and only while it still holds the English default exactly.
            // Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" would also replace an edit that
            // differs only in case.
            $row = TranslationValue::query()->where('key', $key)->where('locale', 'ar')->first();

            if (! $row || $row->value !== $correction['from']) {
                continue;
            }

            $changed = TranslationValue::query()->whereKey($row->getKey())->update(['value' => $correction['to'], 'updated_at' => $now]);

            if ($changed > 0) {
                cache()->forget("translation.ar.{$key}");
            }
        }
    }
}
