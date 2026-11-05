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
        // VENDOR-MEDIA-04A: the media library page and its sidebar link.
        'vendor.Nav_Media_Library' => [
            'ar' => 'مكتبة الوسائط',
            'en' => 'Media Library',
        ],
        'vendor.Media_Library_Description' => [
            'ar' => 'احتفظ بصور متجرك في مكان واحد، جاهزة لإضافتها إلى منتجاتك.',
            'en' => 'Keep your store images in one place, ready to add to your products.',
        ],
        // VENDOR-MEDIA-04B: the read-only gallery of the media library page.
        'vendor.Media_Library_Images' => ['ar' => 'صورك', 'en' => 'Your images'],
        'vendor.Media_Library_Search_Label' => ['ar' => 'ابحث في الصور', 'en' => 'Search images'],
        'vendor.Media_Library_Search_Placeholder' => ['ar' => 'الاسم أو النص البديل', 'en' => 'Name or alt text'],
        'vendor.Media_Library_Search_Clear' => ['ar' => 'مسح البحث', 'en' => 'Clear search'],
        'vendor.Media_Library_Count' => ['ar' => 'عدد الصور: :count', 'en' => 'Images: :count'],
        'vendor.Media_Library_Empty' => [
            'ar' => 'لا توجد صور في مكتبة الوسائط بعد. تظهر هنا الصور التي ترفعها من محرّر المنتج.',
            'en' => 'There are no images in your media library yet. Images you upload from the product editor appear here.',
        ],
        'vendor.Media_Library_No_Results' => ['ar' => 'لا توجد صور تطابق بحثك.', 'en' => 'No images match your search.'],
        'vendor.Media_Library_Invalid' => [
            'ar' => 'تعذّر تنفيذ البحث. غيّر عبارة البحث وحاول مجددًا.',
            'en' => 'The search could not be run. Change it and try again.',
        ],
        'vendor.Media_Library_Untitled' => ['ar' => 'صورة بلا اسم', 'en' => 'Untitled image'],
        'vendor.Media_Library_Open' => ['ar' => 'معاينة :name', 'en' => 'Preview :name'],
        'vendor.Media_Library_Pagination' => ['ar' => 'صفحات مكتبة الوسائط', 'en' => 'Media library pages'],
        'vendor.Media_Library_Previous' => ['ar' => 'السابق', 'en' => 'Previous'],
        'vendor.Media_Library_Next' => ['ar' => 'التالي', 'en' => 'Next'],
        'vendor.Media_Library_Page_Status' => ['ar' => 'الصفحة :page من :last', 'en' => 'Page :page of :last'],
        'vendor.Media_Library_Preview' => ['ar' => 'معاينة الصورة', 'en' => 'Image preview'],
        'vendor.Media_Library_Field_Name' => ['ar' => 'الاسم', 'en' => 'Name'],
        'vendor.Media_Library_Close' => ['ar' => 'إغلاق', 'en' => 'Close'],
        'vendor.Media_Library_Open_Original' => ['ar' => 'فتح الصورة الأصلية', 'en' => 'Open the original image'],
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
        // MYSTORE-UI-01, MYSTORE-UI-01C: the My Store logo section (chosen from the media library; removing it keeps
        // the image) and preparation days. The picker's own texts are the product gallery's (Product_Gallery_*).
        'vendor.My_Store_Logo' => ['ar' => 'شعار المتجر', 'en' => 'Store logo'],
        'vendor.My_Store_Logo_Hint' => [
            'ar' => 'اختر صورة من مكتبة الوسائط لتظهر بجانب اسم متجرك في لوحة البائع. إزالة الشعار لا تحذف الصورة من المكتبة.',
            'en' => 'Choose an image from your media library to appear next to your store name in the vendor dashboard. Removing the logo does not delete the image from the library.',
        ],
        'vendor.My_Store_Logo_Choose' => ['ar' => 'اختيار من مكتبة الوسائط', 'en' => 'Choose from media library'],
        'vendor.My_Store_Logo_Current' => ['ar' => 'الشعار الحالي', 'en' => 'Current logo'],
        'vendor.My_Store_Logo_Uploaded' => ['ar' => 'تم تعيين الشعار.', 'en' => 'Logo updated.'],
        'vendor.My_Store_Logo_Upload_Failed' => [
            'ar' => 'تعذّر تعيين هذه الصورة شعارًا. اختر صورة أخرى من مكتبة الوسائط.',
            'en' => 'This image could not be set as the logo. Choose another image from your media library.',
        ],
        'vendor.My_Store_Logo_Confirm_Delete' => [
            'ar' => 'هل تريد إزالة الشعار من متجرك؟ تبقى الصورة في مكتبة الوسائط.',
            'en' => 'Remove the logo from your store? The image stays in your media library.',
        ],
        'vendor.My_Store_Logo_Deleted' => [
            'ar' => 'تمت إزالة الشعار. تبقى الصورة في مكتبة الوسائط.',
            'en' => 'Logo removed. The image stays in your media library.',
        ],
        'vendor.My_Store_Logo_Delete_Failed' => ['ar' => 'تعذّرت إزالة الشعار. حاول مرة أخرى.', 'en' => 'The logo could not be removed. Try again.'],
        // MYSTORE-UI-01D: the My Store logo section's Remove button (the logo is cleared; the image stays).
        'vendor.My_Store_Logo_Remove' => ['ar' => 'إزالة الشعار', 'en' => 'Remove logo'],
        'vendor.Delete' => ['ar' => 'حذف', 'en' => 'Delete'],
        // MYSTORE-UX-01D: the save bar's unsaved-changes line.
        'vendor.My_Store_Unsaved' => ['ar' => 'لديك تغييرات غير محفوظة', 'en' => 'You have unsaved changes'],
        // MYSTORE-UX-01E: the hint under the read-only blocks and the required-fields legend.
        'vendor.My_Store_Readonly_Hint' => ['ar' => 'هذه البيانات غير قابلة للتعديل من هذه الصفحة.', 'en' => 'These details cannot be edited on this page.'],
        'vendor.My_Store_Required_Legend' => ['ar' => 'الحقول المعلَّمة بـ * مطلوبة.', 'en' => 'Fields marked * are required.'],
        'vendor.My_Store_Preparation_Days' => ['ar' => 'مدة التجهيز (بالأيام)', 'en' => 'Preparation days'],
        'vendor.My_Store_Preparation_Days_Hint' => [
            'ar' => 'المدة المعتادة لتجهيز الطلب قبل شحنه، بالأيام (من 0 إلى 365).',
            'en' => 'Your usual time to prepare an order before shipping, in days (0 to 365).',
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
        // VPM-01A: the image section's labels, the admin-style card line and badge, and the inline chooser.
        'vendor.Product_Gallery_Section' => ['ar' => 'صور المنتج', 'en' => 'Product images'],
        'vendor.Product_Gallery_Hint' => [
            'ar' => 'اختر حتى 6 صور من مكتبة الوسائط الخاصة بك. تظهر الصورة الرئيسية أولًا في المتجر.',
            'en' => 'Choose up to 6 images from your media library. The main image is shown first in the store.',
        ],
        'vendor.Product_Gallery_Main_Label' => ['ar' => 'الصورة الرئيسية للمنتج', 'en' => 'Main product image'],
        'vendor.Product_Gallery_Main_Help' => ['ar' => 'تظهر أولًا في المتجر.', 'en' => 'Shown first on the store.'],
        'vendor.Product_Gallery_Choose_Main' => ['ar' => 'اختر من الوسائط', 'en' => 'Choose from media'],
        'vendor.Product_Gallery_Gallery_Label' => ['ar' => 'معرض المنتج', 'en' => 'Product gallery'],
        'vendor.Product_Gallery_Gallery_Help' => ['ar' => 'اختر صورًا إضافية من مكتبة الوسائط الخاصة بك.', 'en' => 'Choose additional images from your media library.'],
        'vendor.Product_Gallery_Add' => ['ar' => 'أضف صورة إلى المعرض', 'en' => 'Add gallery image'],
        'vendor.Product_Gallery_Panel_Title' => ['ar' => 'اختر صورة من الوسائط', 'en' => 'Choose an image from media'],
        'vendor.Product_Gallery_Upload' => ['ar' => 'ارفع صورة جديدة', 'en' => 'Upload new image'],
        'vendor.Product_Gallery_Upload_Hint' => [
            'ar' => 'JPEG أو PNG أو GIF أو WebP، حتى 10 ميغابايت لكل صورة وحتى 10 صور في المرة الواحدة. تُضاف أول صورة مرفوعة إلى المنتج، وتبقى البقية في مكتبة الوسائط.',
            'en' => 'JPEG, PNG, GIF or WebP, up to 10 MB each and up to 10 at a time. The first uploaded image is added to the product; the others stay in your media library.',
        ],
        'vendor.Product_Gallery_Close' => ['ar' => 'إغلاق', 'en' => 'Close'],
        'vendor.Product_Gallery_Position' => ['ar' => 'الصورة :position', 'en' => 'Image :position'],
        'vendor.Product_Gallery_Main_Badge' => ['ar' => 'الصورة الرئيسية', 'en' => 'Main image'],
        // VPM-01B: the image picker dialog.
        'vendor.Product_Gallery_Picker_Intro' => ['ar' => 'تظهر هنا صور مكتبة الوسائط الخاصة بك فقط.', 'en' => 'Only images from your own media library are shown.'],
        'vendor.Product_Gallery_Search_Label' => ['ar' => 'ابحث في الصور', 'en' => 'Search images'],
        'vendor.Product_Gallery_Search_Placeholder' => ['ar' => 'الاسم أو النص البديل', 'en' => 'Name or alt text'],
        'vendor.Product_Gallery_Picker_Grid' => ['ar' => 'صور مكتبة الوسائط', 'en' => 'Media library images'],
        'vendor.Product_Gallery_Library_Count' => ['ar' => 'عدد الصور: :count', 'en' => 'Images: :count'],
        'vendor.Product_Gallery_No_Results' => ['ar' => 'لا توجد صور تطابق بحثك.', 'en' => 'No images match your search.'],
        'vendor.Product_Gallery_Retry' => ['ar' => 'إعادة المحاولة', 'en' => 'Try again'],
        'vendor.Product_Gallery_Pagination' => ['ar' => 'صفحات مكتبة الوسائط', 'en' => 'Media library pages'],
        'vendor.Product_Gallery_Previous' => ['ar' => 'السابق', 'en' => 'Previous'],
        'vendor.Product_Gallery_Next' => ['ar' => 'التالي', 'en' => 'Next'],
        'vendor.Product_Gallery_Page_Status' => ['ar' => 'الصفحة :page من :last', 'en' => 'Page :page of :last'],
        'vendor.Product_Gallery_Choose' => ['ar' => 'اختر :name', 'en' => 'Choose :name'],
        'vendor.Product_Gallery_Attached' => ['ar' => 'مضافة إلى هذا المنتج', 'en' => 'Already in this product'],
        'vendor.Product_Gallery_Uploaded' => [
            'ar' => 'الصور المرفوعة: :count. أُضيفت أول صورة إلى المنتج، والبقية في مكتبة الوسائط الخاصة بك.',
            'en' => 'Images uploaded: :count. The first one was added to the product; the others are in your media library.',
        ],
        'vendor.Product_Gallery_Upload_Max' => ['ar' => 'يمكنك رفع 10 صور كحد أقصى في المرة الواحدة.', 'en' => 'You can upload up to 10 images at a time.'],
        // VPM-01C: an image the product already has.
        'vendor.Product_Gallery_Duplicate' => ['ar' => 'هذه الصورة مضافة إلى هذا المنتج بالفعل. اختر صورة أخرى.', 'en' => 'This image is already in this product. Choose another image.'],
        'vendor.Product_Gallery_Current' => ['ar' => 'الصورة التي تستبدلها', 'en' => 'The image being replaced'],
        // VPM-01D: moving images.
        'vendor.Product_Gallery_Move_Up' => ['ar' => 'نقل لأعلى', 'en' => 'Move up'],
        'vendor.Product_Gallery_Move_Down' => ['ar' => 'نقل لأسفل', 'en' => 'Move down'],
        'vendor.Product_Gallery_Moved' => ['ar' => 'أصبحت «:name» الصورة :position.', 'en' => ':name is now image :position.'],
        // VPM-01E: images from before the media library, missing files, and the six-image limit.
        'vendor.Product_Gallery_Legacy' => ['ar' => 'ليست في مكتبة الوسائط الخاصة بك', 'en' => 'Not in your media library'],
        'vendor.Product_Gallery_Missing' => ['ar' => 'ملف الصورة غير موجود', 'en' => 'Image file not found'],
        'vendor.Product_Gallery_Full' => [
            'ar' => 'يحتوي هذا المنتج على الحد الأقصى وهو :max صور. أزل صورة أو استبدلها لإضافة أخرى.',
            'en' => 'This product has the maximum of :max images. Remove or replace one to add another.',
        ],
        'vendor.Product_Gallery_Over_Max' => [
            'ar' => 'يحتوي هذا المنتج على :count صور، وهذا أكثر من الحد الأقصى الحالي وهو :max. لا يمكن إضافة صور جديدة، ويمكنك إزالة الصور أو استبدالها أو ترتيبها أو تعيين الصورة الرئيسية. لا تُزال أي صورة تلقائيًا.',
            'en' => 'This product has :count images, more than the current maximum of :max. Nothing can be added. You can still remove, replace, reorder and set the main image; no image is removed automatically.',
        ],
        // VPM-01A: the product editor's form, title, list button and product load / save / delete texts.
        // VENDOR-UI-CLEANUP-01A (F-3): the products list's action column and buttons.
        'vendor.Product_List_Actions' => ['ar' => 'الإجراءات', 'en' => 'Actions'],
        'vendor.Product_List_Edit' => ['ar' => 'تعديل', 'en' => 'Edit'],
        'vendor.Product_List_Delete' => ['ar' => 'حذف', 'en' => 'Delete'],
        // VENDOR-UI-CLEANUP-01B: the products list's column headers (SKU and Stock reuse Product_Sku and Product_Stock),
        // the stored product status values, and the row actions' accessible names.
        'vendor.Product_List_Product' => ['ar' => 'المنتج', 'en' => 'Product'],
        'vendor.Product_List_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'vendor.Product_List_Price' => ['ar' => 'السعر', 'en' => 'Price'],
        'vendor.Product_Status_Draft' => ['ar' => 'مسودة', 'en' => 'Draft'],
        'vendor.Product_Status_Active' => ['ar' => 'نشط', 'en' => 'Active'],
        'vendor.Product_Status_Inactive' => ['ar' => 'غير نشط', 'en' => 'Inactive'],
        'vendor.Product_Status_Out_Of_Stock' => ['ar' => 'نفد من المخزون', 'en' => 'Out of stock'],
        // VENDOR-PRODUCT-PUBLISH-UI-01B: the editor's publication status field (the three options reuse the labels above).
        'vendor.Product_Status' => ['ar' => 'حالة النشر', 'en' => 'Publication status'],
        'vendor.Product_Status_Hint' => [
            'ar' => 'تظهر المنتجات النشطة في المتجر فقط عندما تستوفي شروطه القائمة. لا تظهر المنتجات المسودة وغير النشطة.',
            'en' => 'Active products are shown in the store only when they meet its existing conditions. Draft and inactive products are not shown.',
        ],
        'vendor.Product_Status_Keep_Out_Of_Stock' => [
            'ar' => 'نفد من المخزون (إبقاء الحالة الحالية)',
            'en' => 'Out of stock (keep current status)',
        ],
        // VENDOR-PRODUCT-PUBLISH-UI-01D: the editor's notices for an archived or suspended product, and the refusal shown
        // when the product was archived while it was open.
        'vendor.Product_Notice_Archived' => [
            'ar' => 'هذا المنتج مؤرشف من الإدارة ولا يمكن تعديله. يرجى التواصل مع الإدارة لاستعادته.',
            'en' => 'This product is archived by the administration and cannot be edited. Contact the administration to restore it.',
        ],
        'vendor.Product_Notice_Suspended' => [
            'ar' => 'هذا المنتج معلّق من الإدارة، ولن يظهر في المتجر أو يكون متاحًا للبيع حتى يُرفع التعليق، مهما كانت حالة نشره. يمكنك الاستمرار في تعديل بياناته.',
            'en' => 'This product is suspended by the administration. It is not shown in the store or available for sale until the suspension is lifted, whatever its publication status. You can still edit it.',
        ],
        'vendor.Product_Archived_Refused' => [
            'ar' => 'تمت أرشفة هذا المنتج، ولم يعد بإمكانك تعديله.',
            'en' => 'This product was archived and can no longer be edited.',
        ],
        // VENDOR-PRODUCT-LIST-PAGINATION-01B: the products list's pager (Previous, Next and the page status reuse the
        // media library's texts).
        'vendor.Product_List_Pagination' => ['ar' => 'صفحات المنتجات', 'en' => 'Product pages'],
        // ORD-01A: the orders list's pager (its accessible name; the controls reuse the media library's texts).
        'vendor.Order_List_Pagination' => ['ar' => 'صفحات الطلبات', 'en' => 'Order pages'],
        // ORD-01B: the orders list's search and filters (the status options use the Order_Status_* labels above).
        'vendor.Order_Filters_Label' => ['ar' => 'البحث في الطلبات وتصفيتها', 'en' => 'Search and filter orders'],
        'vendor.Order_Filter_Search' => ['ar' => 'بحث', 'en' => 'Search'],
        'vendor.Order_Filter_Search_Placeholder' => ['ar' => 'رقم الطلب أو اسم العميل', 'en' => 'Order number or customer name'],
        'vendor.Order_Filter_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'vendor.Order_Filter_All_Statuses' => ['ar' => 'جميع الحالات', 'en' => 'All statuses'],
        'vendor.Order_Filter_Needs_Response' => ['ar' => 'بانتظار ردّك', 'en' => 'Needs your response'],
        'vendor.Order_Filter_Apply' => ['ar' => 'تطبيق', 'en' => 'Apply'],
        'vendor.Order_Filter_Reset' => ['ar' => 'مسح عوامل التصفية', 'en' => 'Clear filters'],
        'vendor.Order_Filter_No_Results' => ['ar' => 'لا توجد طلبات تطابق البحث أو التصفية.', 'en' => 'No orders match your search or filters.'],
        // ORD-01C: the orders list's columns (with Recent_Orders_Number / _Status / _Date and Product_List_Actions), its
        // actions (each accessible name contains the visible label) and the action error.
        'vendor.Order_List_Customer' => ['ar' => 'العميل', 'en' => 'Customer'],
        'vendor.Order_List_Total' => ['ar' => 'الإجمالي', 'en' => 'Total'],
        'vendor.Order_Action_Accept' => ['ar' => 'قبول', 'en' => 'Accept'],
        'vendor.Order_Action_Accept_Named' => ['ar' => 'قبول الطلب :number', 'en' => 'Accept order :number'],
        'vendor.Order_Action_Reject' => ['ar' => 'رفض', 'en' => 'Reject'],
        'vendor.Order_Action_Reject_Named' => ['ar' => 'رفض الطلب :number', 'en' => 'Reject order :number'],
        'vendor.Order_Action_Preparing' => ['ar' => 'قيد التحضير', 'en' => 'Preparing'],
        'vendor.Order_Action_Preparing_Named' => ['ar' => 'نقل الطلب :number إلى قيد التحضير', 'en' => 'Mark order :number as preparing'],
        'vendor.Order_Action_Ready' => ['ar' => 'جاهز', 'en' => 'Ready'],
        'vendor.Order_Action_Ready_Named' => ['ar' => 'تحديد الطلب :number جاهزًا للتسليم', 'en' => 'Mark order :number as ready for delivery'],
        'vendor.Order_Action_Error' => ['ar' => 'تعذّر تحديث الطلب. حاول مرة أخرى.', 'en' => 'The order could not be updated. Please try again.'],
        // ORD-01D: the Accept and Reject dialogs (":number" is the order number, ":max" the reason limit) and the
        // translated outcome of every action; a server's own message is never shown.
        'vendor.Order_Accept_Title' => ['ar' => 'قبول الطلب :number؟', 'en' => 'Accept order :number?'],
        'vendor.Order_Accept_Description' => ['ar' => 'سيتم قبول الطلب وخصم الكمية المحجوزة له من مخزونك. لا يمكنك التراجع عن ذلك من لوحة التحكم.', 'en' => 'The order will be accepted and the stock reserved for it will be deducted from your stock. You cannot undo this from your dashboard.'],
        'vendor.Order_Accept_Confirm' => ['ar' => 'تأكيد القبول', 'en' => 'Confirm acceptance'],
        'vendor.Order_Reject_Title' => ['ar' => 'رفض الطلب :number؟', 'en' => 'Reject order :number?'],
        'vendor.Order_Reject_Description' => ['ar' => 'سيتم رفض الطلب وتحرير الكمية المحجوزة له. يُحفظ السبب مع الطلب.', 'en' => 'The order will be rejected and the stock reserved for it released. The reason is saved with the order.'],
        'vendor.Order_Reject_Reason' => ['ar' => 'سبب الرفض', 'en' => 'Reason for rejection'],
        'vendor.Order_Reject_Reason_Hint' => ['ar' => 'مطلوب. حتى :max حرف.', 'en' => 'Required. Up to :max characters.'],
        'vendor.Order_Reject_Confirm' => ['ar' => 'رفض الطلب', 'en' => 'Reject order'],
        'vendor.Order_Reject_Reason_Required' => ['ar' => 'اكتب سبب رفض هذا الطلب.', 'en' => 'Enter the reason for rejecting this order.'],
        'vendor.Order_Reject_Reason_Too_Long' => ['ar' => 'يجب ألا يزيد السبب عن :max حرف.', 'en' => 'The reason must be :max characters or fewer.'],
        'vendor.Order_Reject_Reason_Invalid' => ['ar' => 'لم يُقبل السبب. راجعه وحاول مرة أخرى.', 'en' => 'The reason was not accepted. Check it and try again.'],
        'vendor.Order_Action_Success_Accept' => ['ar' => 'تم قبول الطلب :number.', 'en' => 'Order :number was accepted.'],
        'vendor.Order_Action_Success_Reject' => ['ar' => 'تم رفض الطلب :number.', 'en' => 'Order :number was rejected.'],
        'vendor.Order_Action_Success_Preparing' => ['ar' => 'الطلب :number قيد التحضير الآن.', 'en' => 'Order :number is now being prepared.'],
        'vendor.Order_Action_Success_Ready' => ['ar' => 'الطلب :number جاهز للتسليم.', 'en' => 'Order :number is ready for delivery.'],
        'vendor.Order_Action_Conflict' => ['ar' => 'تعذّر تحديث الطلب لأنه تغيّر أو لم يعد متاحًا. تم تحديث القائمة.', 'en' => 'This order could not be updated because it has changed or is no longer available. The list has been refreshed.'],
        'vendor.Order_Action_Conflict_Accept' => ['ar' => 'تعذّر قبول الطلب. ربما تغيّر، أو لم تعد الكمية المحجوزة له متاحة. تم تحديث القائمة.', 'en' => 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The list has been refreshed.'],
        'vendor.Order_Action_Session' => ['ar' => 'انتهت جلستك. سجّل الدخول مرة أخرى ثم حاول مجددًا.', 'en' => 'Your session has ended. Sign in again, then try again.'],
        'vendor.Order_Action_Forbidden' => ['ar' => 'لا يمكنك تحديث هذا الطلب.', 'en' => 'You cannot update this order.'],
        'vendor.Order_Action_Network' => ['ar' => 'تعذّر الاتصال. تحقّق من اتصالك وحاول مرة أخرى.', 'en' => 'The connection failed. Check your connection and try again.'],
        // ORD-02A: the vendor order details page title (":number" is the vendor order number).
        'vendor.Order_Details_Title' => ['ar' => 'الطلب :number', 'en' => 'Order :number'],
        // ORD-02A (step 3): the details page's sections, labels and states (statuses, actions, outcomes and several labels
        // reuse the orders list's keys); the two conflict texts say the order, not the list, was refreshed.
        'vendor.Order_Details_Summary' => ['ar' => 'ملخص الطلب', 'en' => 'Order summary'],
        'vendor.Order_Details_Placed' => ['ar' => 'تم إنشاء الطلب', 'en' => 'Order placed'],
        'vendor.Order_Details_No_Actions' => ['ar' => 'لا توجد إجراءات متاحة لهذا الطلب في حالته الحالية.', 'en' => 'No actions are available for this order in its current status.'],
        'vendor.Order_Details_Items' => ['ar' => 'المنتجات', 'en' => 'Items'],
        'vendor.Order_Details_Quantity' => ['ar' => 'الكمية', 'en' => 'Quantity'],
        'vendor.Order_Details_Unit_Price' => ['ar' => 'سعر الوحدة', 'en' => 'Unit price'],
        'vendor.Order_Details_Line_Total' => ['ar' => 'المجموع', 'en' => 'Line total'],
        'vendor.Order_Details_No_Items' => ['ar' => 'لا يحتوي هذا الطلب على منتجات.', 'en' => 'This order has no items.'],
        'vendor.Order_Details_Amounts' => ['ar' => 'المبالغ', 'en' => 'Amounts'],
        'vendor.Order_Details_Subtotal' => ['ar' => 'المجموع الفرعي', 'en' => 'Subtotal'],
        'vendor.Order_Details_Delivery_Fee' => ['ar' => 'رسوم التوصيل', 'en' => 'Delivery fee'],
        'vendor.Order_Details_Timeline' => ['ar' => 'مراحل الطلب', 'en' => 'Order timeline'],
        'vendor.Order_Details_Receipt_Confirmed' => ['ar' => 'أكّد العميل الاستلام', 'en' => 'Receipt confirmed by the customer'],
        'vendor.Order_Details_Refreshing' => ['ar' => 'جارٍ تحديث الطلب…', 'en' => 'Updating the order…'],
        'vendor.Order_Details_Gone' => ['ar' => 'هذا الطلب لم يعد متاحًا.', 'en' => 'This order is no longer available.'],
        'vendor.Order_Details_Back' => ['ar' => 'العودة إلى الطلبات', 'en' => 'Back to orders'],
        'vendor.Order_Details_Conflict' => ['ar' => 'تعذّر تحديث الطلب لأنه تغيّر أو لم يعد متاحًا. تم تحديث بيانات الطلب.', 'en' => 'This order could not be updated because it has changed or is no longer available. The order has been refreshed.'],
        // ORD-02A (step 4): the orders list's link to an order's details page.
        'vendor.Order_List_View_Details' => ['ar' => 'عرض التفاصيل', 'en' => 'View details'],
        'vendor.Order_List_View_Details_Named' => ['ar' => 'عرض التفاصيل للطلب :number', 'en' => 'View details of order :number'],
        'vendor.Order_Details_Conflict_Accept' => ['ar' => 'تعذّر قبول الطلب. ربما تغيّر، أو لم تعد الكمية المحجوزة له متاحة. تم تحديث بيانات الطلب.', 'en' => 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The order has been refreshed.'],
        // VENDOR-PRODUCT-PUBLISH-UI-01E: the products list's moderation badges.
        'vendor.Product_Badge_Archived' => ['ar' => 'مؤرشف', 'en' => 'Archived'],
        'vendor.Product_Badge_Suspended' => ['ar' => 'معلّق إداريًا', 'en' => 'Suspended by administration'],
        'vendor.Product_List_Edit_Named' => ['ar' => 'تعديل المنتج: :name', 'en' => 'Edit product: :name'],
        'vendor.Product_List_Delete_Named' => ['ar' => 'حذف المنتج: :name', 'en' => 'Delete product: :name'],
        'vendor.Product_New' => ['ar' => 'إضافة منتج', 'en' => 'Add product'],
        'vendor.Product_Edit' => ['ar' => 'تعديل المنتج', 'en' => 'Edit product'],
        'vendor.Product_Section_Details' => ['ar' => 'تفاصيل المنتج', 'en' => 'Product details'],
        'vendor.Product_Section_Pricing' => ['ar' => 'السعر والمخزون والشحن', 'en' => 'Price, stock and shipping'],
        'vendor.Product_Name' => ['ar' => 'الاسم', 'en' => 'Name'],
        'vendor.Product_Category' => ['ar' => 'التصنيف', 'en' => 'Category'],
        'vendor.Product_No_Category' => ['ar' => 'بدون تصنيف', 'en' => 'No category'],
        'vendor.Product_Sku' => ['ar' => 'رمز المنتج (SKU)', 'en' => 'SKU'],
        'vendor.Product_Stock' => ['ar' => 'المخزون', 'en' => 'Stock'],
        'vendor.Product_Short_Description' => ['ar' => 'وصف مختصر', 'en' => 'Short description'],
        'vendor.Product_Description' => ['ar' => 'الوصف', 'en' => 'Description'],
        'vendor.Product_Price' => ['ar' => 'السعر الأساسي', 'en' => 'Regular price'],
        'vendor.Product_Compare_Price' => ['ar' => 'سعر التخفيض (اختياري)', 'en' => 'Sale price (optional)'],
        'vendor.Product_Compare_Price_Hint' => ['ar' => 'يجب أن يكون أقل من السعر الأساسي.', 'en' => 'Must be lower than the regular price.'],
        'vendor.Product_Low_Stock' => ['ar' => 'حد انخفاض المخزون', 'en' => 'Low stock threshold'],
        'vendor.Product_Weight' => ['ar' => 'الوزن', 'en' => 'Weight'],
        'vendor.Product_Weight_Unit' => ['ar' => 'وحدة الوزن', 'en' => 'Weight unit'],
        'vendor.Product_Weight_Unit_Select' => ['ar' => 'الوحدة', 'en' => 'Unit'],
        'vendor.Product_Dimensions' => ['ar' => 'الأبعاد (سم)', 'en' => 'Dimensions (cm)'],
        'vendor.Product_Length' => ['ar' => 'الطول', 'en' => 'Length'],
        'vendor.Product_Width' => ['ar' => 'العرض', 'en' => 'Width'],
        'vendor.Product_Height' => ['ar' => 'الارتفاع', 'en' => 'Height'],
        'vendor.Product_Section_Craft' => ['ar' => 'الحرفة والعناية والتوصيل', 'en' => 'Craft, care and delivery'],
        'vendor.Product_Material' => ['ar' => 'المادة الأساسية', 'en' => 'Main material'],
        'vendor.Product_Place_Of_Manufacture' => ['ar' => 'مكان الصنع', 'en' => 'Place of manufacture'],
        'vendor.Product_Preparation_Days' => ['ar' => 'مدة التجهيز (بالأيام)', 'en' => 'Preparation time (days)'],
        'vendor.Product_Care_Instructions' => ['ar' => 'تعليمات العناية والحفظ', 'en' => 'Care and storage instructions'],
        'vendor.Product_Shipping_Details' => ['ar' => 'تفاصيل التغليف والشحن', 'en' => 'Packaging and shipping details'],
        'vendor.Product_Confirm_Delete' => ['ar' => 'هل تريد حذف هذا المنتج؟ لا يمكن التراجع عن ذلك.', 'en' => 'Delete this product? This cannot be undone.'],
        'vendor.Product_Delete_Failed' => ['ar' => 'تعذّر حذف المنتج.', 'en' => 'The product could not be deleted.'],
        'vendor.Product_Load_Failed' => ['ar' => 'تعذّر تحميل المنتج.', 'en' => 'The product could not be loaded.'],
        'vendor.Product_Save_Failed' => ['ar' => 'تعذّر حفظ المنتج.', 'en' => 'The product could not be saved.'],
        'vendor.Product_Save_Invalid' => ['ar' => 'تعذّر حفظ المنتج. راجع هذه التفاصيل:', 'en' => 'The product could not be saved. Check these details:'],
        'vendor.Save' => ['ar' => 'حفظ', 'en' => 'Save'],
        'vendor.Cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],
        // VENDOR-MEDIA-02B: the batch limit of one media library upload.
        'vendor.Media_Upload_Max_Files' => ['ar' => 'يمكنك رفع :max صور كحد أقصى في المرة الواحدة.', 'en' => 'You can upload up to :max images at a time.'],
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
