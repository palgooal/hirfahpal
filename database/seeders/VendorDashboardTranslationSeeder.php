<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English strings of the vendor dashboard shell (VUI-01B). Strings
 * shared with the auth pages (brand, logo, vendor portal, sign out, choose
 * language) come from AdminAuthTranslationSeeder and VendorAuthTranslationSeeder.
 * Only missing (key, locale) rows are inserted; edited values are kept.
 */
class VendorDashboardTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'vendor.Shell_Title_Suffix' => [
            'ar' => 'لوحة البائع في حرفة',
            'en' => 'Hirfah vendor dashboard',
        ],
        'vendor.Nav_Main' => [
            'ar' => 'القائمة الرئيسية',
            'en' => 'Main menu',
        ],
        'vendor.Nav_Dashboard' => [
            'ar' => 'لوحة التحكم',
            'en' => 'Dashboard',
        ],
        'vendor.Nav_My_Store' => [
            'ar' => 'متجري',
            'en' => 'My Store',
        ],
        'vendor.Nav_Products' => [
            'ar' => 'المنتجات',
            'en' => 'Products',
        ],
        'vendor.Nav_Orders' => [
            'ar' => 'الطلبات',
            'en' => 'Orders',
        ],
        'vendor.Nav_Reviews' => [
            'ar' => 'التقييمات',
            'en' => 'Reviews',
        ],
        'vendor.Nav_Returns' => [
            'ar' => 'المرتجعات',
            'en' => 'Returns',
        ],
        'vendor.Nav_Disputes' => [
            'ar' => 'النزاعات',
            'en' => 'Disputes',
        ],
        'vendor.Nav_Finance' => [
            'ar' => 'المالية',
            'en' => 'Finance',
        ],
        'vendor.Nav_Soon' => [
            'ar' => 'قريبًا',
            'en' => 'Soon',
        ],
        'vendor.Menu' => [
            'ar' => 'القائمة',
            'en' => 'Menu',
        ],
        'vendor.Menu_Collapse' => [
            'ar' => 'طي القائمة',
            'en' => 'Collapse menu',
        ],
        'vendor.Menu_Expand' => [
            'ar' => 'إظهار القائمة',
            'en' => 'Expand menu',
        ],
        'vendor.Account_Menu' => [
            'ar' => 'قائمة الحساب',
            'en' => 'Account menu',
        ],
        'vendor.Breadcrumb' => [
            'ar' => 'مسار التنقل',
            'en' => 'Breadcrumb',
        ],
        'vendor.Dashboard_Welcome' => [
            'ar' => 'مرحبًا بك في متجرك',
            'en' => 'Welcome to your store',
        ],
        // Dashboard home (VUI-02B).
        'vendor.Dashboard_Overview' => [
            'ar' => 'ملخص المتجر',
            'en' => 'Store overview',
        ],
        'vendor.Kpi_Awaiting_Response' => [
            'ar' => 'بانتظار ردّك',
            'en' => 'Awaiting your response',
        ],
        'vendor.Kpi_In_Progress' => [
            'ar' => 'قيد التنفيذ',
            'en' => 'In progress',
        ],
        'vendor.Kpi_Active_Products' => [
            'ar' => 'المنتجات المفعّلة',
            'en' => 'Active products',
        ],
        'vendor.Kpi_Of_Total' => [
            'ar' => 'من أصل :total',
            'en' => 'of :total',
        ],
        'vendor.Kpi_No_Products' => [
            'ar' => 'لم تُضف منتجات بعد',
            'en' => 'No products added yet',
        ],
        'vendor.Kpi_Low_Available_Stock' => [
            'ar' => 'مخزون متاح منخفض',
            'en' => 'Low available stock',
        ],
        'vendor.Recent_Orders' => [
            'ar' => 'أحدث الطلبات',
            'en' => 'Recent orders',
        ],
        'vendor.Recent_Orders_Number' => [
            'ar' => 'رقم الطلب',
            'en' => 'Order number',
        ],
        'vendor.Recent_Orders_Date' => [
            'ar' => 'التاريخ',
            'en' => 'Date',
        ],
        'vendor.Recent_Orders_Status' => [
            'ar' => 'الحالة',
            'en' => 'Status',
        ],
        'vendor.Recent_Orders_Items' => [
            'ar' => 'العناصر',
            'en' => 'Items',
        ],
        'vendor.Recent_Orders_Empty' => [
            'ar' => 'لا توجد طلبات بعد',
            'en' => 'No orders yet',
        ],
        'vendor.Dashboard_Load_Error' => [
            'ar' => 'تعذّر تحميل بيانات لوحة التحكم.',
            'en' => 'Dashboard data could not be loaded.',
        ],
        'vendor.Dashboard_Retry' => [
            'ar' => 'إعادة المحاولة',
            'en' => 'Retry',
        ],
        // Vendor order status display labels (display only; the lifecycle is backend-owned).
        'vendor.Order_Status_Pending' => [
            'ar' => 'قيد الانتظار',
            'en' => 'Pending',
        ],
        'vendor.Order_Status_Accepted' => [
            'ar' => 'مقبول',
            'en' => 'Accepted',
        ],
        'vendor.Order_Status_Preparing' => [
            'ar' => 'قيد التجهيز',
            'en' => 'Preparing',
        ],
        'vendor.Order_Status_Ready_For_Delivery' => [
            'ar' => 'جاهز للتسليم',
            'en' => 'Ready for delivery',
        ],
        'vendor.Order_Status_Assigned' => [
            'ar' => 'مُسند لمندوب',
            'en' => 'Assigned to a driver',
        ],
        'vendor.Order_Status_Out_For_Delivery' => [
            'ar' => 'خرج للتوصيل',
            'en' => 'Out for delivery',
        ],
        'vendor.Order_Status_Delivered' => [
            'ar' => 'تم التسليم',
            'en' => 'Delivered',
        ],
        'vendor.Order_Status_Completed' => [
            'ar' => 'مكتمل',
            'en' => 'Completed',
        ],
        'vendor.Order_Status_Rejected' => [
            'ar' => 'مرفوض',
            'en' => 'Rejected',
        ],
        'vendor.Order_Status_Cancelled' => [
            'ar' => 'ملغى',
            'en' => 'Cancelled',
        ],
        // My Store (VUI-03B). Store name reuses vendor.Store_Name from VendorAuthTranslationSeeder.
        'vendor.My_Store_Identity' => [
            'ar' => 'هوية المتجر',
            'en' => 'Store identity',
        ],
        'vendor.My_Store_Info' => [
            'ar' => 'معلومات المتجر',
            'en' => 'Store information',
        ],
        'vendor.My_Store_Short_Description' => [
            'ar' => 'وصف مختصر',
            'en' => 'Short description',
        ],
        'vendor.My_Store_Description' => [
            'ar' => 'الوصف',
            'en' => 'Description',
        ],
        'vendor.My_Store_Location' => [
            'ar' => 'الموقع',
            'en' => 'Location',
        ],
        'vendor.My_Store_Governorate' => [
            'ar' => 'المحافظة',
            'en' => 'Governorate',
        ],
        'vendor.My_Store_City' => [
            'ar' => 'المدينة',
            'en' => 'City',
        ],
        'vendor.My_Store_Address' => [
            'ar' => 'العنوان',
            'en' => 'Address',
        ],
        'vendor.My_Store_Account' => [
            'ar' => 'بيانات الحساب',
            'en' => 'Account information',
        ],
        'vendor.My_Store_Full_Name' => [
            'ar' => 'الاسم الكامل',
            'en' => 'Full name',
        ],
        'vendor.My_Store_Email' => [
            'ar' => 'البريد الإلكتروني',
            'en' => 'Email',
        ],
        'vendor.My_Store_Phone' => [
            'ar' => 'رقم الهاتف',
            'en' => 'Phone',
        ],
        'vendor.My_Store_Not_Specified' => [
            'ar' => 'غير محدد',
            'en' => 'Not specified',
        ],
        'vendor.My_Store_Save' => [
            'ar' => 'حفظ التغييرات',
            'en' => 'Save changes',
        ],
        'vendor.My_Store_Saving' => [
            'ar' => 'جارٍ الحفظ…',
            'en' => 'Saving…',
        ],
        'vendor.My_Store_Saved' => [
            'ar' => 'تم حفظ التغييرات بنجاح.',
            'en' => 'Changes saved successfully.',
        ],
        'vendor.My_Store_Load_Error' => [
            'ar' => 'تعذّر تحميل بيانات المتجر.',
            'en' => 'Store details could not be loaded.',
        ],
        'vendor.My_Store_Save_Error' => [
            'ar' => 'تعذّر حفظ التغييرات. حاول مرة أخرى.',
            'en' => 'Your changes could not be saved. Please try again.',
        ],
        'vendor.My_Store_Session_Error' => [
            'ar' => 'انتهت جلستك. سجّل الدخول من جديد ثم أعد المحاولة.',
            'en' => 'Your session has ended. Sign in again, then try again.',
        ],
        'vendor.My_Store_Access_Error' => [
            'ar' => 'لا يمكنك تعديل بيانات المتجر حاليًا.',
            'en' => 'You cannot edit the store details right now.',
        ],
        'vendor.My_Store_Fix_Errors' => [
            'ar' => 'يرجى مراجعة الحقول المحددة.',
            'en' => 'Please review the highlighted fields.',
        ],
        'vendor.My_Store_Error_Required' => [
            'ar' => 'هذا الحقل مطلوب.',
            'en' => 'This field is required.',
        ],
        'vendor.My_Store_Error_Too_Long' => [
            'ar' => 'النص أطول من الحد المسموح.',
            'en' => 'This text is longer than allowed.',
        ],
        'vendor.My_Store_Error_Invalid' => [
            'ar' => 'يرجى مراجعة هذه القيمة.',
            'en' => 'Please check this value.',
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
    }
}
