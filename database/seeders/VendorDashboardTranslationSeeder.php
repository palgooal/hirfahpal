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
        // G7-C2-5: the product image gallery of the vendor product editor (resources/js/vendor-product-editor.js).
        'vendor.Product_Gallery_Change' => ['ar' => 'تغيير', 'en' => 'Change'],
        'vendor.Product_Gallery_Set_Main' => ['ar' => 'تعيين كصورة رئيسية', 'en' => 'Set as main'],
        'vendor.Product_Gallery_Remove' => ['ar' => 'إزالة', 'en' => 'Remove'],
        'vendor.Product_Gallery_Confirm_Remove' => [
            'ar' => 'إزالة هذه الصورة من المنتج؟ يبقى الملف في مكتبة الوسائط.',
            'en' => 'Remove this image from the product? The file stays in your media library.',
        ],
        'vendor.Product_Gallery_No_Main' => ['ar' => 'لم تُختر صورة رئيسية.', 'en' => 'No main image selected.'],
        'vendor.Product_Gallery_No_Gallery' => ['ar' => 'لم تُختر صور إضافية للمعرض.', 'en' => 'No gallery images selected.'],
        'vendor.Product_Gallery_Count' => ['ar' => ':count من :max صور', 'en' => ':count of :max images'],
        'vendor.Product_Gallery_Title_Main' => ['ar' => 'اختر الصورة الرئيسية للمنتج', 'en' => 'Choose the main product image'],
        'vendor.Product_Gallery_Title_Replace' => ['ar' => 'اختر صورة بديلة', 'en' => 'Choose a replacement image'],
        'vendor.Product_Gallery_Title_Gallery' => ['ar' => 'اختر صورة للمعرض', 'en' => 'Choose a gallery image'],
        'vendor.Product_Gallery_Loading' => ['ar' => 'جارٍ تحميل الصور…', 'en' => 'Loading images…'],
        'vendor.Product_Gallery_Library_Empty' => [
            'ar' => 'لا توجد صور في مكتبة الوسائط الخاصة بك بعد. ارفع صورة من هذه النافذة.',
            'en' => 'There are no images in your media library yet. Upload one from this window.',
        ],
        'vendor.Product_Gallery_Library_Error' => ['ar' => 'تعذّر تحميل مكتبة الوسائط.', 'en' => 'Your media library could not be loaded.'],
        'vendor.Product_Gallery_Saving' => ['ar' => 'جارٍ الحفظ…', 'en' => 'Saving…'],
        'vendor.Product_Gallery_Uploading' => ['ar' => 'جارٍ الرفع…', 'en' => 'Uploading…'],
        'vendor.Product_Gallery_Added' => ['ar' => 'أُضيفت الصورة.', 'en' => 'Image added.'],
        'vendor.Product_Gallery_Replaced' => ['ar' => 'استُبدلت الصورة.', 'en' => 'Image replaced.'],
        'vendor.Product_Gallery_Removed' => [
            'ar' => 'أُزيلت الصورة. يبقى الملف في مكتبة الوسائط.',
            'en' => 'Image removed. The file stays in your media library.',
        ],
        'vendor.Product_Gallery_Main_Changed' => ['ar' => 'تغيّرت الصورة الرئيسية.', 'en' => 'Main image changed.'],
        'vendor.Product_Gallery_Limit' => [
            'ar' => 'يمكن أن يحتوي المنتج على 6 صور كحد أقصى. أزل صورة أو استبدلها لإضافة أخرى.',
            'en' => 'A product can have up to 6 images. Remove or replace an image to add another.',
        ],
        'vendor.Product_Gallery_Not_In_Library' => [
            'ar' => 'هذه الصورة ليست في مكتبة الوسائط الخاصة بك أو أُزيلت منها. اختر صورة أخرى.',
            'en' => 'This image is not in your media library, or it was removed. Choose another image.',
        ],
        'vendor.Product_Gallery_Gone' => [
            'ar' => 'لم تعد هذه الصورة أو هذا المنتج متاحًا. أعد تحميل الصفحة وحاول مجددًا.',
            'en' => 'This image or product is no longer available. Reload the page and try again.',
        ],
        'vendor.Product_Gallery_Session' => ['ar' => 'انتهت جلستك. سجّل الدخول مجددًا للمتابعة.', 'en' => 'Your session has ended. Sign in again to continue.'],
        'vendor.Product_Gallery_Forbidden' => ['ar' => 'ليست لديك صلاحية للقيام بذلك.', 'en' => 'You do not have permission to do this.'],
        'vendor.Product_Gallery_Invalid' => [
            'ar' => 'تعذّرت معالجة الطلب. أعد تحميل الصفحة وحاول مجددًا.',
            'en' => 'The request could not be processed. Reload the page and try again.',
        ],
        'vendor.Product_Gallery_Network' => ['ar' => 'تعذّر الاتصال. تحقق من الشبكة وحاول مجددًا.', 'en' => 'The connection failed. Check the network and try again.'],
        'vendor.Product_Gallery_Failed' => ['ar' => 'حدث خطأ ما. حاول مجددًا.', 'en' => 'Something went wrong. Try again.'],
        'vendor.Product_Gallery_Upload_Invalid' => [
            'ar' => 'يجب أن يكون الملف صورة JPEG أو PNG أو GIF أو WebP بحجم 10 ميغابايت كحد أقصى.',
            'en' => 'The file must be a JPEG, PNG, GIF or WebP image of up to 10 MB.',
        ],
        'vendor.Product_Gallery_Image_Alt' => ['ar' => 'صورة المنتج', 'en' => 'Product image'],
        'vendor.Product_Gallery_Media_Alt' => ['ar' => 'صورة من مكتبة الوسائط', 'en' => 'Media library image'],
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
