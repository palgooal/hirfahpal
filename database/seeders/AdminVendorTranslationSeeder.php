<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * FR-ADM-009: Arabic and English texts of the vendor commission rate card on the admin vendor details page
 * (dashboard/vendors/show) and of the update message of VendorManagementController::updateCommissionRate.
 * FR-ADM-015: the title and the empty state of the store reviews card on the same page.
 * AD-UI-VENDORS-01: the texts of the migrated vendor details page; product status labels are reused from
 * AdminProductTranslationSeeder (Products_Status_*).
 * AD-UI-VENDORS-02: the texts of the migrated create vendor form (Vendors_Create_*, Vendors_Form_*).
 * Shared keys already used on the page (Commission_Rate, …) are not touched.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the English defaults that t() auto-creates
 * in Arabic (listed in CORRECTIONS) are replaced only while unchanged. Not called from DatabaseSeeder.
 */
class AdminVendorTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Commission_rate_updated_successfully' => ['ar' => 'تم تحديث نسبة العمولة بنجاح', 'en' => 'Commission rate updated successfully.'],
        'dashboard.Current_commission_rate' => ['ar' => 'نسبة العمولة الحالية', 'en' => 'Current rate'],
        'dashboard.Not_set' => ['ar' => 'غير محددة', 'en' => 'Not set'],
        'dashboard.Update_commission_rate' => ['ar' => 'تحديث نسبة العمولة', 'en' => 'Update Commission Rate'],
        // FR-ADM-015: the store reviews card (shared keys Customer, Rating, Comment, Status, Pending, … are reused).
        'dashboard.Recent_Reviews' => ['ar' => 'آخر التقييمات', 'en' => 'Recent Reviews'],
        'dashboard.No_reviews_found' => ['ar' => 'لا توجد تقييمات لهذا المتجر', 'en' => 'No reviews found'],        // AD-UI-VENDORS-01: the vendor details page (Vendors_*) and the vendor order status labels it shows
        // (VendorOrders_Status_*, named for reuse by the vendor order pages). Read without auto-creating rows.
        'dashboard.Vendors_Show_Back' => ['ar' => 'العودة إلى البائعين', 'en' => 'Back to vendors'],
        'dashboard.Vendors_Approval_Status' => ['ar' => 'حالة الموافقة', 'en' => 'Approval status'],
        'dashboard.Vendors_Approval_Approved' => ['ar' => 'معتمد', 'en' => 'Approved'],
        'dashboard.Vendors_Approval_Pending' => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending review'],
        'dashboard.Vendors_Approval_Rejected' => ['ar' => 'مرفوض', 'en' => 'Rejected'],
        'dashboard.Vendors_Account_Status' => ['ar' => 'حالة الحساب', 'en' => 'Account status'],
        'dashboard.Vendors_Account_Active' => ['ar' => 'نشط', 'en' => 'Active'],
        'dashboard.Vendors_Account_Pending' => ['ar' => 'بانتظار التفعيل', 'en' => 'Pending activation'],
        'dashboard.Vendors_Account_Blocked' => ['ar' => 'محظور', 'en' => 'Blocked'],
        'dashboard.Vendors_Show_Store' => ['ar' => 'المتجر والمالك', 'en' => 'Store and owner'],
        'dashboard.Vendors_Show_Store_Name' => ['ar' => 'اسم المتجر', 'en' => 'Store name'],
        'dashboard.Vendors_Show_Owner' => ['ar' => 'المالك', 'en' => 'Owner'],
        'dashboard.Vendors_Show_Email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
        'dashboard.Vendors_Show_Phone' => ['ar' => 'رقم الهاتف', 'en' => 'Phone'],
        'dashboard.Vendors_Show_Short_Description' => ['ar' => 'الوصف المختصر', 'en' => 'Short description'],
        'dashboard.Vendors_Show_No_Description' => ['ar' => 'لا يوجد وصف.', 'en' => 'No description available.'],
        'dashboard.Vendors_Show_Description' => ['ar' => 'الوصف', 'en' => 'Description'],
        'dashboard.Vendors_Show_Location' => ['ar' => 'الموقع', 'en' => 'Location'],
        'dashboard.Vendors_Show_Governorate' => ['ar' => 'المحافظة', 'en' => 'Governorate'],
        'dashboard.Vendors_Show_City' => ['ar' => 'المدينة', 'en' => 'City'],
        'dashboard.Vendors_Show_Address' => ['ar' => 'العنوان', 'en' => 'Address'],
        'dashboard.Vendors_Decision_Title' => ['ar' => 'قرار الإدارة', 'en' => 'Admin decision'],
        'dashboard.Vendors_Approve_Title' => ['ar' => 'اعتماد المتجر', 'en' => 'Approve the store'],
        'dashboard.Vendors_Approve_Hint' => ['ar' => 'يستطيع المتجر المعتمد البيع ما دام حسابه نشطًا. لا تتغير حالة الحساب.', 'en' => 'An approved store can sell while its account is active. The account status is not changed.'],
        'dashboard.Vendors_Approve_Submit' => ['ar' => 'اعتماد البائع', 'en' => 'Approve vendor'],
        'dashboard.Vendors_Reject_Title' => ['ar' => 'رفض المتجر', 'en' => 'Reject the store'],
        'dashboard.Vendors_Reject_Hint' => ['ar' => 'يظهر السبب للبائع. لا تتغير حالة الحساب.', 'en' => 'The vendor sees the reason. The account status is not changed.'],
        'dashboard.Vendors_Rejection_Reason' => ['ar' => 'سبب الرفض', 'en' => 'Rejection reason'],
        'dashboard.Vendors_Reject_Submit' => ['ar' => 'رفض البائع', 'en' => 'Reject vendor'],
        'dashboard.Vendors_No_Decision_Permission' => ['ar' => 'لا تملك صلاحية اتخاذ هذا القرار.', 'en' => 'You do not have permission to make this decision.'],
        'dashboard.Vendors_Commission_Title' => ['ar' => 'نسبة العمولة', 'en' => 'Commission rate'],
        'dashboard.Vendors_Commission_Hint' => ['ar' => 'بين 0 و100، بمنزلتين عشريتين كحد أقصى. اتركها فارغة لإلغاء النسبة.', 'en' => 'Between 0 and 100, with up to two decimal places. Leave it empty to clear the rate.'],
        'dashboard.Vendors_Recent_Products' => ['ar' => 'آخر المنتجات', 'en' => 'Recent products'],
        'dashboard.Vendors_No_Products' => ['ar' => 'لا توجد منتجات لهذا البائع.', 'en' => 'This vendor has no products.'],
        'dashboard.Vendors_Column_Product' => ['ar' => 'المنتج', 'en' => 'Product'],
        'dashboard.Vendors_Column_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Vendors_Column_Price' => ['ar' => 'السعر', 'en' => 'Price'],
        'dashboard.Vendors_Recent_Orders' => ['ar' => 'آخر الطلبات الفرعية', 'en' => 'Recent vendor orders'],
        'dashboard.Vendors_No_Orders' => ['ar' => 'لا توجد طلبات لهذا البائع.', 'en' => 'This vendor has no orders.'],
        'dashboard.Vendors_Column_Number' => ['ar' => 'رقم الطلب', 'en' => 'Order number'],
        'dashboard.Vendors_Column_Total' => ['ar' => 'الإجمالي', 'en' => 'Total'],
        'dashboard.Vendors_Review_Rating' => ['ar' => 'التقييم: :rating من 5', 'en' => 'Rating: :rating of 5'],
        'dashboard.Vendors_Review_Status_Pending' => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending'],
        'dashboard.Vendors_Review_Status_Published' => ['ar' => 'منشور', 'en' => 'Published'],
        'dashboard.Vendors_Review_Status_Hidden' => ['ar' => 'مخفي', 'en' => 'Hidden'],
        'dashboard.VendorOrders_Status_Pending' => ['ar' => 'بانتظار القبول', 'en' => 'Pending'],
        'dashboard.VendorOrders_Status_Accepted' => ['ar' => 'مقبول', 'en' => 'Accepted'],
        'dashboard.VendorOrders_Status_Preparing' => ['ar' => 'قيد التجهيز', 'en' => 'Preparing'],
        'dashboard.VendorOrders_Status_Ready_For_Delivery' => ['ar' => 'جاهز للتوصيل', 'en' => 'Ready for delivery'],
        'dashboard.VendorOrders_Status_Assigned' => ['ar' => 'تم الإسناد', 'en' => 'Assigned'],
        'dashboard.VendorOrders_Status_Out_For_Delivery' => ['ar' => 'خرج للتوصيل', 'en' => 'Out for delivery'],
        'dashboard.VendorOrders_Status_Delivered' => ['ar' => 'تم التسليم', 'en' => 'Delivered'],
        'dashboard.VendorOrders_Status_Completed' => ['ar' => 'مكتمل', 'en' => 'Completed'],
        'dashboard.VendorOrders_Status_Rejected' => ['ar' => 'مرفوض', 'en' => 'Rejected'],
        'dashboard.VendorOrders_Status_Cancelled' => ['ar' => 'ملغى', 'en' => 'Cancelled'],
        // AD-UI-VENDORS-02: the create vendor form (the field labels shared with the details page are reused).
        'dashboard.Vendors_Create_Title' => ['ar' => 'إضافة بائع', 'en' => 'Add vendor'],
        'dashboard.Vendors_Create_Intro' => ['ar' => 'أنشئ حساب بائع وملف متجره. تُحدَّد حالة الحساب وقرار الموافقة كلٌّ على حدة.', 'en' => 'Create a vendor account and its store profile. The account status and the approval decision are set separately.'],
        'dashboard.Vendors_Form_Errors' => ['ar' => 'يرجى تصحيح ما يلي:', 'en' => 'Please correct the following:'],
        'dashboard.Vendors_Form_Required' => ['ar' => 'مطلوب', 'en' => 'Required'],
        'dashboard.Vendors_Form_Optional' => ['ar' => 'اختياري', 'en' => 'Optional'],
        'dashboard.Vendors_Form_Section_Account' => ['ar' => 'الحساب', 'en' => 'Account'],
        'dashboard.Vendors_Form_Owner_Name' => ['ar' => 'اسم المالك', 'en' => 'Owner name'],
        'dashboard.Vendors_Form_Password' => ['ar' => 'كلمة المرور', 'en' => 'Password'],
        'dashboard.Vendors_Form_Password_Hint' => ['ar' => 'ثمانية أحرف على الأقل.', 'en' => 'At least 8 characters.'],
        'dashboard.Vendors_Form_Password_Confirmation' => ['ar' => 'تأكيد كلمة المرور', 'en' => 'Confirm password'],
        'dashboard.Vendors_Form_Section_Store' => ['ar' => 'ملف المتجر', 'en' => 'Store profile'],
        'dashboard.Vendors_Form_Slug' => ['ar' => 'المعرّف في الرابط', 'en' => 'Slug'],
        'dashboard.Vendors_Form_Slug_Hint' => ['ar' => 'يُستخدم في رابط المتجر. اتركه فارغًا ليُنشأ من اسم المتجر.', 'en' => 'Used in the store link. Leave it empty to create it from the store name.'],
        'dashboard.Vendors_Form_Commission_Hint' => ['ar' => 'بين 0 و100، بمنزلتين عشريتين كحد أقصى.', 'en' => 'Between 0 and 100, with up to two decimal places.'],
        'dashboard.Vendors_Form_Select_Governorate' => ['ar' => 'اختر المحافظة', 'en' => 'Select governorate'],
        'dashboard.Vendors_Form_Select_City' => ['ar' => 'اختر المدينة', 'en' => 'Select city'],
        'dashboard.Vendors_Form_Section_Approval' => ['ar' => 'الموافقة', 'en' => 'Approval'],
        'dashboard.Vendors_Form_Rejection_Hint' => ['ar' => 'مطلوب عندما تكون حالة الموافقة «مرفوض».', 'en' => 'Required when the approval status is Rejected.'],
        'dashboard.Vendors_Form_Submit' => ['ar' => 'إنشاء البائع', 'en' => 'Create vendor'],
        'dashboard.Vendors_Form_Cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],
    ];

    /**
     * Arabic rows auto-created by t() with the English default. Each row is replaced only while it still holds that
     * exact default; any other value is an edit and is kept (same mechanism as AdminCustomerTranslationSeeder).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        ['key' => 'dashboard.Commission_rate_updated_successfully', 'locale' => 'ar', 'from' => 'Commission rate updated successfully.', 'to' => 'تم تحديث نسبة العمولة بنجاح'],
        ['key' => 'dashboard.Current_commission_rate', 'locale' => 'ar', 'from' => 'Current rate', 'to' => 'نسبة العمولة الحالية'],
        ['key' => 'dashboard.Not_set', 'locale' => 'ar', 'from' => 'Not set', 'to' => 'غير محددة'],
        ['key' => 'dashboard.Update_commission_rate', 'locale' => 'ar', 'from' => 'Update Commission Rate', 'to' => 'تحديث نسبة العمولة'],
        ['key' => 'dashboard.Recent_Reviews', 'locale' => 'ar', 'from' => 'Recent Reviews', 'to' => 'آخر التقييمات'],
        ['key' => 'dashboard.No_reviews_found', 'locale' => 'ar', 'from' => 'No reviews found', 'to' => 'لا توجد تقييمات لهذا المتجر'],
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
