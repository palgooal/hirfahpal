<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * AD-UI-PROD-M3 to M7: Arabic and English texts of the admin products list, details page and create / edit form
 * (product-specific keys, dashboard.Products_*), of the product ability labels and of the AD-UI-PROD-M0
 * messages of ProductManagementController. The list and those messages read them without
 * auto-creating rows, so until this seeder runs the English defaults are shown and nothing is written. Shared keys
 * (Edit, Status, Name, …) are not touched (OPEN-PROD-14). The pagination keys are seeded by
 * AdminCategoryTranslationSeeder.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the old defaults listed in CORRECTIONS are
 * replaced only while unchanged. Not called from DatabaseSeeder.
 */
class AdminProductTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Products list (AD-UI-PROD-M3).
        'dashboard.Products_Intro' => [
            'ar' => 'أشرف على المنتجات التي يعرضها البائعون في المتجر: ابحث عنها، وتابع مخزونها وحالتها.',
            'en' => 'Oversee the products vendors offer in the store: find them, check their stock and status.',
        ],
        'dashboard.Products_Add' => ['ar' => 'إضافة منتج', 'en' => 'Add product'],
        'dashboard.Products_List' => ['ar' => 'قائمة المنتجات', 'en' => 'Products list'],
        'dashboard.Products_Filters' => ['ar' => 'تصفية المنتجات', 'en' => 'Filter products'],
        'dashboard.Products_Search_Label' => ['ar' => 'البحث في المنتجات', 'en' => 'Search products'],
        'dashboard.Products_Search_Placeholder' => ['ar' => 'الاسم أو رمز SKU', 'en' => 'Name or SKU'],
        'dashboard.Products_Filter_Vendor' => ['ar' => 'البائع', 'en' => 'Vendor'],
        'dashboard.Products_Filter_Vendor_All' => ['ar' => 'كل البائعين', 'en' => 'All vendors'],
        'dashboard.Products_Filter_Category' => ['ar' => 'التصنيف', 'en' => 'Category'],
        'dashboard.Products_Filter_Category_All' => ['ar' => 'كل التصنيفات', 'en' => 'All categories'],
        'dashboard.Products_Filter_Status' => ['ar' => 'حالة المنتج', 'en' => 'Product status'],
        'dashboard.Products_Filter_Status_All' => ['ar' => 'كل الحالات', 'en' => 'All statuses'],
        'dashboard.Products_Filter_Suspension' => ['ar' => 'التعليق', 'en' => 'Suspension'],
        'dashboard.Products_Filter_Suspension_All' => ['ar' => 'معلّق وغير معلّق', 'en' => 'Suspended or not'],
        'dashboard.Products_Suspended' => ['ar' => 'معلّق', 'en' => 'Suspended'],
        'dashboard.Products_Not_Suspended' => ['ar' => 'غير معلّق', 'en' => 'Not suspended'],
        'dashboard.Products_Filter_Archive' => ['ar' => 'الأرشفة', 'en' => 'Archive'],
        'dashboard.Products_Filter_Archive_Current' => ['ar' => 'غير المؤرشفة', 'en' => 'Not archived'],
        'dashboard.Products_Filter_Archive_Archived' => ['ar' => 'المؤرشفة', 'en' => 'Archived'],
        'dashboard.Products_Filter_Archive_All' => ['ar' => 'الكل مع المؤرشفة', 'en' => 'All, archived included'],
        'dashboard.Products_Filter_Stock' => ['ar' => 'المخزون', 'en' => 'Stock'],
        'dashboard.Products_Filter_Stock_All' => ['ar' => 'أي مستوى مخزون', 'en' => 'Any stock level'],
        'dashboard.Products_Filter_Stock_Low' => ['ar' => 'مخزون منخفض', 'en' => 'Low stock'],
        'dashboard.Products_Apply' => ['ar' => 'تطبيق', 'en' => 'Apply'],
        'dashboard.Products_Reset' => ['ar' => 'إعادة الضبط', 'en' => 'Reset'],
        'dashboard.Products_Page_Out_Of_Range' => ['ar' => 'لا توجد منتجات في هذه الصفحة.', 'en' => 'There are no products on this page.'],
        'dashboard.Products_Go_Last_Page' => ['ar' => 'الانتقال إلى الصفحة الأخيرة', 'en' => 'Go to the last page'],
        'dashboard.Products_Empty_Filtered' => ['ar' => 'لا توجد منتجات تطابق هذه المعايير.', 'en' => 'No products match these filters.'],
        'dashboard.Products_Empty' => ['ar' => 'لا توجد منتجات بعد.', 'en' => 'No products yet.'],
        'dashboard.Products_Empty_Hint' => ['ar' => 'تظهر المنتجات هنا عندما يضيفها البائعون.', 'en' => 'Products appear here once vendors add them.'],
        'dashboard.Products_Column_Product' => ['ar' => 'المنتج', 'en' => 'Product'],
        'dashboard.Products_Column_Vendor' => ['ar' => 'البائع', 'en' => 'Vendor'],
        'dashboard.Products_Column_Category' => ['ar' => 'التصنيف', 'en' => 'Category'],
        'dashboard.Products_Column_Price' => ['ar' => 'السعر', 'en' => 'Price'],
        'dashboard.Products_Column_Stock' => ['ar' => 'المخزون', 'en' => 'Stock'],
        'dashboard.Products_Column_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Products_Column_Actions' => ['ar' => 'الإجراءات', 'en' => 'Actions'],
        'dashboard.Products_No_Category' => ['ar' => 'بلا تصنيف', 'en' => 'No category'],
        'dashboard.Products_Stock_On_Hand' => ['ar' => 'الفعلي', 'en' => 'On hand'],
        'dashboard.Products_Stock_Reserved' => ['ar' => 'المحجوز', 'en' => 'Reserved'],
        'dashboard.Products_Stock_Available' => ['ar' => 'المتاح', 'en' => 'Available'],
        'dashboard.Products_Archived' => ['ar' => 'مؤرشف', 'en' => 'Archived'],
        'dashboard.Products_Edit' => ['ar' => 'تعديل', 'en' => 'Edit'],
        'dashboard.Products_Edit_Named' => ['ar' => 'تعديل :name', 'en' => 'Edit :name'],
        'dashboard.Products_Status_Draft' => ['ar' => 'مسودة', 'en' => 'Draft'],
        'dashboard.Products_Status_Active' => ['ar' => 'نشط', 'en' => 'Active'],
        'dashboard.Products_Status_Inactive' => ['ar' => 'غير نشط', 'en' => 'Inactive'],
        'dashboard.Products_Status_Out_Of_Stock' => ['ar' => 'نفد من المخزون', 'en' => 'Out of stock'],

        // Product details (AD-UI-PROD-M4).
        'dashboard.Products_Show_Back' => ['ar' => 'العودة إلى المنتجات', 'en' => 'Back to products'],
        'dashboard.Products_Show_Tabs' => ['ar' => 'تفاصيل المنتج', 'en' => 'Product details'],
        'dashboard.Products_Show_Tab_Info' => ['ar' => 'معلومات المنتج', 'en' => 'Product information'],
        'dashboard.Products_Show_Tab_Images' => ['ar' => 'الصور', 'en' => 'Images'],
        'dashboard.Products_Show_Tab_Stock' => ['ar' => 'السعر والمخزون', 'en' => 'Price and stock'],
        'dashboard.Products_Show_Tab_Moderation' => ['ar' => 'الإشراف', 'en' => 'Moderation'],
        'dashboard.Products_Show_Tab_Log' => ['ar' => 'سجل الإجراءات', 'en' => 'Action log'],
        'dashboard.Products_Show_Name' => ['ar' => 'الاسم', 'en' => 'Name'],
        'dashboard.Products_Show_Sku' => ['ar' => 'رمز SKU', 'en' => 'SKU'],
        'dashboard.Products_Show_Slug' => ['ar' => 'المعرّف في الرابط', 'en' => 'Slug'],
        'dashboard.Products_Show_Weight' => ['ar' => 'الوزن', 'en' => 'Weight'],
        'dashboard.Products_Show_Dimensions' => ['ar' => 'الأبعاد', 'en' => 'Dimensions'],
        'dashboard.Products_Show_Published' => ['ar' => 'تاريخ النشر', 'en' => 'Published'],
        'dashboard.Products_Show_Created' => ['ar' => 'تاريخ الإنشاء', 'en' => 'Created'],
        'dashboard.Products_Show_Updated' => ['ar' => 'آخر تحديث', 'en' => 'Last updated'],
        'dashboard.Products_Show_Short_Description' => ['ar' => 'الوصف المختصر', 'en' => 'Short description'],
        'dashboard.Products_Show_Description' => ['ar' => 'الوصف', 'en' => 'Description'],
        'dashboard.Products_Show_No_Images' => ['ar' => 'لا صور لهذا المنتج.', 'en' => 'This product has no images.'],
        'dashboard.Products_Show_Image_Alt' => ['ar' => 'صورة المنتج :number', 'en' => 'Product image :number'],
        'dashboard.Products_Show_Primary_Image' => ['ar' => 'الصورة الرئيسية', 'en' => 'Main image'],
        'dashboard.Products_Show_Image_Not_Shown' => ['ar' => 'لا تُعرض: ليست ملف صورة مخزّنًا', 'en' => 'Not shown: not a stored image file'],
        'dashboard.Products_Show_Compare_Price' => ['ar' => 'سعر الخصم', 'en' => 'Sale price'],
        'dashboard.Products_Show_Low_Threshold' => ['ar' => 'حد التنبيه للمخزون', 'en' => 'Low stock threshold'],
        'dashboard.Products_Show_Not_Archived' => ['ar' => 'غير مؤرشف', 'en' => 'Not archived'],
        'dashboard.Products_Show_Suspension_Reason' => ['ar' => 'سبب التعليق', 'en' => 'Reason for the suspension'],
        'dashboard.Products_Show_No_Reason' => ['ar' => 'لم يُسجَّل سبب.', 'en' => 'No reason recorded.'],
        'dashboard.Products_Show_Log_Forbidden' => [
            'ar' => 'عرض سجل الإجراءات يتطلب صلاحية «عرض سجل إجراءات المنتجات».',
            'en' => 'Viewing the action log needs the "View product action log" permission.',
        ],
        'dashboard.Products_Show_Log_Empty' => ['ar' => 'لا توجد إجراءات إدارية على هذا المنتج بعد.', 'en' => 'No administrative action on this product yet.'],
        'dashboard.Products_Action_Suspended' => ['ar' => 'تعليق', 'en' => 'Suspended'],
        'dashboard.Products_Action_Unsuspended' => ['ar' => 'رفع التعليق', 'en' => 'Suspension lifted'],
        'dashboard.Products_Action_Archived' => ['ar' => 'أرشفة', 'en' => 'Archived'],
        'dashboard.Products_Action_Restored' => ['ar' => 'استعادة', 'en' => 'Restored'],

        // Suspension (AD-UI-PROD-M5): the form in the moderation tab and its results.
        'dashboard.Products_Suspend_Title' => ['ar' => 'تعليق هذا المنتج', 'en' => 'Suspend this product'],
        'dashboard.Products_Suspend_Hint' => [
            'ar' => 'لا يمكن بيع المنتج المعلّق حتى يُرفع التعليق. لا تتغير حالته ولا أرشفته.',
            'en' => 'A suspended product cannot be sold until the suspension is lifted. Its status and archive are not changed.',
        ],
        'dashboard.Products_Suspend_Reason' => ['ar' => 'لماذا يُعلَّق المنتج؟', 'en' => 'Why is the product suspended?'],
        'dashboard.Products_Suspend_Submit' => ['ar' => 'تعليق المنتج', 'en' => 'Suspend the product'],
        'dashboard.Products_Unsuspend_Title' => ['ar' => 'رفع التعليق', 'en' => 'Lift the suspension'],
        'dashboard.Products_Unsuspend_Hint' => [
            'ar' => 'يعود المنتج قابلًا للبيع إن سمحت بذلك شروطه الأخرى. لا تتغير حالته ولا أرشفته.',
            'en' => 'The product can be sold again if its other conditions allow it. Its status and archive are not changed.',
        ],
        'dashboard.Products_Unsuspend_Reason' => ['ar' => 'لماذا يُرفع التعليق؟', 'en' => 'Why is the suspension lifted?'],
        'dashboard.Products_Unsuspend_Submit' => ['ar' => 'رفع التعليق', 'en' => 'Lift the suspension'],
        'dashboard.Products_Moderation_Reason_Limits' => [
            'ar' => 'بين 3 و1000 حرف. يُحفظ في سجل الإجراءات.',
            'en' => 'Between 3 and 1000 characters. It is kept in the action log.',
        ],
        'dashboard.Product_suspended' => ['ar' => 'عُلّق المنتج، ولم يعد قابلًا للبيع.', 'en' => 'The product was suspended. It can no longer be sold.'],
        'dashboard.Product_unsuspended' => ['ar' => 'رُفع التعليق عن المنتج.', 'en' => 'The suspension was lifted.'],
        'dashboard.Product_already_suspended' => ['ar' => 'هذا المنتج معلّق أصلًا. لم يتغير شيء.', 'en' => 'This product is already suspended. Nothing was changed.'],
        'dashboard.Product_not_suspended' => ['ar' => 'هذا المنتج غير معلّق. لم يتغير شيء.', 'en' => 'This product is not suspended. Nothing was changed.'],
        'dashboard.Product_moderation_failed' => [
            'ar' => 'تعذّر حفظ التغيير، فلم يتغير شيء. حاول مجددًا.',
            'en' => 'The change could not be saved, so nothing was changed. Please try again.',
        ],
        'dashboard.Product_Attribute_Reason' => ['ar' => 'السبب', 'en' => 'reason'],

        // Archive and restore (AD-UI-PROD-M6): the form in the moderation tab and its results.
        'dashboard.Products_Archive_Title' => ['ar' => 'أرشفة هذا المنتج', 'en' => 'Archive this product'],
        'dashboard.Products_Archive_Hint' => [
            'ar' => 'لا يمكن بيع المنتج المؤرشف، ويخرج من القائمة الافتراضية. لا يُحذف شيء: تبقى طلباته وحجوزاته وتقييماته وسجله. لا تتغير حالته ولا تعليقه.',
            'en' => 'An archived product cannot be sold and leaves the default list. Nothing is deleted: its orders, reservations, reviews and log are kept. Its status and suspension are not changed.',
        ],
        'dashboard.Products_Archive_Reason' => ['ar' => 'لماذا يُؤرشف المنتج؟', 'en' => 'Why is the product archived?'],
        'dashboard.Products_Archive_Submit' => ['ar' => 'أرشفة المنتج', 'en' => 'Archive the product'],
        'dashboard.Products_Restore_Title' => ['ar' => 'استعادة هذا المنتج', 'en' => 'Restore this product'],
        'dashboard.Products_Restore_Hint' => [
            'ar' => 'يعود المنتج «مسودة» أيًّا كانت حالته، ولا يُعرض للبيع حتى يُنشر من جديد. يبقى التعليق الإداري إن وُجد.',
            'en' => 'The product comes back as a draft, whatever its status was, and is not on sale until it is published again. An administrative suspension, if any, stays.',
        ],
        'dashboard.Products_Restore_Reason' => ['ar' => 'لماذا يُستعاد المنتج؟', 'en' => 'Why is the product restored?'],
        'dashboard.Products_Restore_Submit' => ['ar' => 'استعادة كمسودة', 'en' => 'Restore as a draft'],
        'dashboard.Product_archived' => [
            'ar' => 'أُرشف المنتج، ولم يعد قابلًا للبيع، وبقيت كل بياناته.',
            'en' => 'The product was archived. It can no longer be sold, and all its data is kept.',
        ],
        'dashboard.Product_restored' => [
            'ar' => 'استُعيد المنتج كمسودة، ولن يُعرض للبيع حتى يُنشر من جديد.',
            'en' => 'The product was restored as a draft. It is not on sale until it is published again.',
        ],
        'dashboard.Product_already_archived' => ['ar' => 'هذا المنتج مؤرشف أصلًا. لم يتغير شيء.', 'en' => 'This product is already archived. Nothing was changed.'],
        'dashboard.Product_not_archived' => ['ar' => 'هذا المنتج غير مؤرشف. لم يتغير شيء.', 'en' => 'This product is not archived. Nothing was changed.'],

        // Create / edit form (AD-UI-PROD-M7-FIX-01). The field names are also the attribute names of its validation
        // messages (ProductManagementController::formAttributes()).
        'dashboard.Products_Form_Create_Title' => ['ar' => 'إضافة منتج', 'en' => 'Add product'],
        'dashboard.Products_Form_Edit_Title' => ['ar' => 'تعديل المنتج', 'en' => 'Edit product'],
        'dashboard.Products_Form_Back' => ['ar' => 'العودة إلى المنتج', 'en' => 'Back to the product'],
        'dashboard.Products_Form_Create_Intro' => [
            'ar' => 'أضف منتجًا إلى متجر أحد البائعين. لا يُعرض للبيع إلا إذا كانت حالته «نشط».',
            'en' => 'Add a product to a vendor\'s store. It is on sale only when its status is Active.',
        ],
        'dashboard.Products_Form_Edit_Intro' => [
            'ar' => 'حدّث بيانات المنتج. التعليق والأرشفة يُداران من صفحة المنتج.',
            'en' => 'Update the product details. Suspension and archiving are managed from the product page.',
        ],
        'dashboard.Products_Form_Required' => ['ar' => 'إلزامي', 'en' => 'Required'],
        'dashboard.Products_Form_Optional' => ['ar' => 'اختياري', 'en' => 'Optional'],
        'dashboard.Products_Form_Errors' => ['ar' => 'يرجى تصحيح ما يلي:', 'en' => 'Please correct the following:'],
        'dashboard.Products_Form_Suspended_Note' => [
            'ar' => 'هذا المنتج معلّق من الإدارة ولا يمكن بيعه. حفظ هذا النموذج لا يرفع التعليق.',
            'en' => 'This product is suspended by the administration and cannot be sold. Saving this form does not lift the suspension.',
        ],
        'dashboard.Products_Form_Archived_Note' => [
            'ar' => 'هذا المنتج مؤرشف ولا يمكن بيعه. حفظ هذا النموذج لا يستعيده.',
            'en' => 'This product is archived and cannot be sold. Saving this form does not restore it.',
        ],
        'dashboard.Products_Form_Moderation_Link' => ['ar' => 'فتح تبويب الإشراف', 'en' => 'Open the moderation tab'],
        'dashboard.Products_Form_Section_Basics' => ['ar' => 'البيانات الأساسية', 'en' => 'Basic details'],
        'dashboard.Products_Form_Section_Pricing' => ['ar' => 'الأسعار والمخزون', 'en' => 'Price and stock'],
        'dashboard.Products_Form_Section_Details' => ['ar' => 'الوصف والتفاصيل', 'en' => 'Description and details'],
        'dashboard.Products_Form_Section_Status' => ['ar' => 'الحالة والإعدادات', 'en' => 'Status and settings'],
        'dashboard.Products_Form_Field_Name' => ['ar' => 'اسم المنتج', 'en' => 'Product name'],
        'dashboard.Products_Form_Field_Slug' => ['ar' => 'الاسم في الرابط (slug)', 'en' => 'Link name (slug)'],
        'dashboard.Products_Form_Field_Vendor' => ['ar' => 'البائع', 'en' => 'Vendor'],
        'dashboard.Products_Form_Field_Category' => ['ar' => 'التصنيف', 'en' => 'Category'],
        'dashboard.Products_Form_Field_Sku' => ['ar' => 'رمز المنتج (SKU)', 'en' => 'SKU'],
        'dashboard.Products_Form_Field_Price' => ['ar' => 'السعر الأساسي', 'en' => 'Regular price'],
        'dashboard.Products_Form_Field_Compare_Price' => ['ar' => 'سعر الخصم', 'en' => 'Sale price'],
        'dashboard.Products_Form_Field_Stock' => ['ar' => 'كمية المخزون', 'en' => 'Stock quantity'],
        'dashboard.Products_Form_Field_Low_Stock' => ['ar' => 'حد المخزون المنخفض', 'en' => 'Low stock threshold'],
        'dashboard.Products_Form_Field_Weight' => ['ar' => 'الوزن', 'en' => 'Weight'],
        'dashboard.Products_Form_Field_Short_Description' => ['ar' => 'الوصف المختصر', 'en' => 'Short description'],
        'dashboard.Products_Form_Field_Description' => ['ar' => 'الوصف', 'en' => 'Description'],
        'dashboard.Products_Form_Field_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Products_Form_Field_Featured' => ['ar' => 'منتج مميز', 'en' => 'Featured product'],
        'dashboard.Products_Form_Category_None' => ['ar' => 'بلا تصنيف', 'en' => 'No category'],
        'dashboard.Products_Form_Category_Inactive' => ['ar' => ':name (غير نشط)', 'en' => ':name (inactive)'],
        'dashboard.Products_Form_Category_Inactive_Hint' => [
            'ar' => 'التصنيف الحالي غير نشط، ويبقى كما هو عند الحفظ. اختر تصنيفًا آخر إن أردت نقل المنتج.',
            'en' => 'The current category is inactive. It is kept when you save; choose another one to move the product.',
        ],
        'dashboard.Products_Form_Slug_Hint' => [
            'ar' => 'يُستخدم في رابط المنتج في المتجر. اتركه فارغًا ليُنشأ من الاسم. لا يقبل المسافات ولا هذه الرموز: / ? # %',
            'en' => 'Used in the store link. Leave it empty to create it from the name. Spaces and these characters are not allowed: / ? # %',
        ],
        'dashboard.Products_Form_Sku_Hint' => [
            'ar' => 'رمز المنتج، ويجب ألا يستخدمه منتج آخر.',
            'en' => 'The product code. It must not be used by another product.',
        ],
        // HIRFAH-PRICE-01: price is the regular price, compare_at_price the lower sale price.
        'dashboard.Products_Form_Price_Hint' => [
            'ar' => 'السعر الأساسي للمنتج بعملة المتجر، ويمكن إدخاله بمنزلتين عشريتين على الأكثر.',
            'en' => 'The regular price of the product in the store currency, with up to two decimal places.',
        ],
        'dashboard.Products_Form_Compare_Price_Hint' => [
            'ar' => 'اختياري. أدخل سعرًا أقل من السعر الأساسي. عند تحديده، يُعرض السعر الأساسي مشطوبًا ويُعتمد سعر الخصم للبيع.',
            'en' => 'Optional. Enter a price lower than the regular price. When provided, the regular price is displayed crossed out and the sale price is used for purchases.',
        ],
        'dashboard.Products_Form_Stock_Hint' => [
            'ar' => 'الوحدات الموجودة في المخزون، ومنها المحجوزة لطلبات مفتوحة. لا يمكن أن تقل عن الوحدات المحجوزة.',
            'en' => 'The units in stock, including those reserved for open orders. It cannot be lower than the reserved units.',
        ],
        'dashboard.Products_Form_Low_Stock_Hint' => [
            'ar' => 'يُعلَّم المنتج بأن مخزونه منخفض عندما تصل وحداته المتاحة إلى هذا العدد. القيمة الافتراضية 1.',
            'en' => 'The product is marked low in stock when its available units reach this number. 1 by default.',
        ],
        'dashboard.Products_Form_Short_Description_Hint' => ['ar' => 'حتى 1000 حرف.', 'en' => 'Up to 1000 characters.'],
        'dashboard.Products_Form_Weight_Hint' => ['ar' => 'للشحن، أدخل الوزن واختر كغ أو غ أو طن.', 'en' => 'For shipping, enter the weight and choose kg, g or ton.'],
        // The weight unit and the product images of the form, and the image validation message of the controller.
        'dashboard.Products_Form_Weight_Unit' => ['ar' => 'وحدة الوزن', 'en' => 'Weight unit'],
        'dashboard.Products_Form_Weight_Unit_Select' => ['ar' => 'الوحدة', 'en' => 'Unit'],
        'dashboard.Products_Form_Weight_Unit_KG' => ['ar' => 'كغ', 'en' => 'kg'],
        'dashboard.Products_Form_Weight_Unit_G' => ['ar' => 'غ', 'en' => 'g'],
        'dashboard.Products_Form_Weight_Unit_T' => ['ar' => 'طن', 'en' => 'ton'],
        'dashboard.Products_Form_Images' => ['ar' => 'صور المنتج', 'en' => 'Product images'],
        // PRODUCT-MEDIA-01 P5-C: the vendor field and the gallery editor (partials/gallery.blade.php, gallery-dialog.blade.php).
        // Products_Form_Images_Hint went with the six pickers and the uploads; its existing rows are left as they are.
        'dashboard.Products_Form_Vendor_Readonly_Hint' => ['ar' => 'لا يمكن تغيير البائع بعد إنشاء المنتج.', 'en' => 'The vendor cannot be changed after the product is created.'],
        // P5-C-FIX-03 (D-1): the empty first option of the vendor list on create.
        'dashboard.Products_Form_Vendor_Select' => ['ar' => 'اختر البائع', 'en' => 'Select a vendor'],
        'dashboard.Products_Form_Vendor_Create_Hint' => [
            'ar' => 'تأتي صور المنتج من مكتبة وسائط هذا البائع. لا يمكن تغيير البائع لاحقًا.',
            'en' => 'The product images come from this vendor\'s media library. The vendor cannot be changed later.',
        ],
        'dashboard.Products_Gallery_Hint' => [
            'ar' => 'اختر حتى 6 صور من مكتبة وسائط البائع. الصورة الأولى هي الصورة الرئيسية، واستخدم أزرار التحريك لتغيير الترتيب.',
            'en' => 'Choose up to 6 images from the vendor\'s media library. The first image is the main image; use the move buttons to change the order.',
        ],
        'dashboard.Products_Gallery_Count' => ['ar' => ':count من :max صور', 'en' => ':count of :max images'],
        'dashboard.Products_Gallery_Add' => ['ar' => 'إضافة صورة', 'en' => 'Add image'],
        'dashboard.Products_Gallery_Vendor_First' => ['ar' => 'اختر البائع أولًا لإضافة صوره.', 'en' => 'Choose the vendor first to add its images.'],
        'dashboard.Products_Gallery_Full' => [
            'ar' => 'يحتوي المنتج على الحد الأقصى وهو :max صور. احذف صورة لإضافة أخرى.',
            'en' => 'The product has the maximum of :max images. Remove one to add another.',
        ],
        'dashboard.Products_Gallery_Empty' => ['ar' => 'لا توجد صور بعد.', 'en' => 'No images yet.'],
        'dashboard.Products_Gallery_No_Script' => [
            'ar' => 'فعّل JavaScript لتغيير صور المنتج. تبقى الصور الحالية عند الحفظ.',
            'en' => 'Turn on JavaScript to change the product images. The current images are kept when you save.',
        ],
        'dashboard.Products_Gallery_Main' => ['ar' => 'الصورة الرئيسية', 'en' => 'Main image'],
        'dashboard.Products_Gallery_Legacy' => ['ar' => 'ليست من مكتبة البائع', 'en' => 'Not in the vendor library'],
        'dashboard.Products_Gallery_Position' => ['ar' => 'الصورة :position', 'en' => 'Image :position'],
        'dashboard.Products_Gallery_Move_Up' => ['ar' => 'تحريك للأعلى', 'en' => 'Move up'],
        'dashboard.Products_Gallery_Move_Down' => ['ar' => 'تحريك للأسفل', 'en' => 'Move down'],
        'dashboard.Products_Gallery_Replace' => ['ar' => 'استبدال', 'en' => 'Replace'],
        'dashboard.Products_Gallery_Remove' => ['ar' => 'إزالة', 'en' => 'Remove'],
        'dashboard.Products_Gallery_Added' => ['ar' => 'أُضيفت :name.', 'en' => ':name was added.'],
        'dashboard.Products_Gallery_Removed' => [
            'ar' => 'أُزيلت :name من المنتج. يبقى الملف في مكتبة الوسائط.',
            'en' => ':name was removed from the product. The file stays in the media library.',
        ],
        'dashboard.Products_Gallery_Moved' => ['ar' => 'أصبحت :name الصورة :position.', 'en' => ':name is now image :position.'],
        'dashboard.Products_Gallery_Replaced' => ['ar' => 'استُبدلت :name.', 'en' => ':name was replaced.'],
        'dashboard.Products_Gallery_Cleared' => [
            'ar' => 'أُزيلت الصور المختارة لأن البائع تغيّر.',
            'en' => 'The chosen images were cleared because the vendor changed.',
        ],
        'dashboard.Products_Gallery_Dialog_Title' => ['ar' => 'اختيار صورة', 'en' => 'Choose an image'],
        'dashboard.Products_Gallery_Dialog_Replace_Title' => ['ar' => 'اختيار صورة بديلة', 'en' => 'Choose a replacement image'],
        'dashboard.Products_Gallery_Dialog_Intro' => [
            'ar' => 'تُعرض صور هذا البائع فقط. يرفع البائعون صورهم من لوحة التحكم الخاصة بهم.',
            'en' => 'Only this vendor\'s images are shown. Vendors upload their images from their own dashboard.',
        ],
        'dashboard.Products_Gallery_Search_Label' => ['ar' => 'البحث في الصور', 'en' => 'Search images'],
        'dashboard.Products_Gallery_Search_Placeholder' => ['ar' => 'ابحث بالاسم أو العنوان أو التسمية التوضيحية', 'en' => 'Search by name, title or caption'],
        'dashboard.Products_Gallery_Choices_Label' => ['ar' => 'صور البائع', 'en' => 'Vendor images'],
        'dashboard.Products_Gallery_Choose' => ['ar' => 'اختيار :name', 'en' => 'Choose :name'],
        'dashboard.Products_Gallery_In_Gallery' => ['ar' => 'مضافة بالفعل', 'en' => 'Already added'],
        'dashboard.Products_Gallery_Loading' => ['ar' => 'جارٍ تحميل الصور…', 'en' => 'Loading images…'],
        'dashboard.Products_Gallery_Library_Empty' => ['ar' => 'لا توجد صور في مكتبة وسائط هذا البائع بعد.', 'en' => 'This vendor has no images in their media library yet.'],
        'dashboard.Products_Gallery_Library_Empty_Search' => ['ar' => 'لا توجد صور تطابق البحث.', 'en' => 'No images match your search.'],
        'dashboard.Products_Gallery_Load_Error' => ['ar' => 'تعذّر تحميل الصور.', 'en' => 'The images could not be loaded.'],
        'dashboard.Products_Gallery_Retry' => ['ar' => 'إعادة المحاولة', 'en' => 'Try again'],
        'dashboard.Products_Gallery_Load_More' => ['ar' => 'تحميل المزيد', 'en' => 'Load more'],
        'dashboard.Products_Gallery_Close' => ['ar' => 'إغلاق', 'en' => 'Close'],
        'dashboard.Products_Gallery_Forbidden' => ['ar' => 'ليست لديك صلاحية للقيام بذلك.', 'en' => 'You do not have permission to do this.'],
        'dashboard.Products_Gallery_Network' => ['ar' => 'تعذّر الاتصال. تحقّق من الشبكة وحاول مجددًا.', 'en' => 'The connection failed. Check the network and try again.'],
        'dashboard.Products_Gallery_Session' => ['ar' => 'انتهت الجلسة. سجّل الدخول مجددًا للمتابعة.', 'en' => 'Your session has ended. Sign in again to continue.'],
        'dashboard.Products_Gallery_Invalid_Request' => [
            'ar' => 'تعذّرت معالجة الطلب. غيّر البحث وحاول مجددًا.',
            'en' => 'The request could not be processed. Change the search and try again.',
        ],
        'dashboard.Products_Form_Manage_Images' => ['ar' => 'عرض الصور الحالية', 'en' => 'View current images'],
        // PRODUCT-MEDIA-01 P5-A: product images come from the product vendor's own media library only (G1), the admin
        // cannot upload them (G3), and a product keeps its vendor (G2). Products_Form_Images_Invalid went with the
        // removed upload path; its existing rows are left as they are.
        'dashboard.Products_Form_Images_Vendor_Only' => [
            'ar' => 'يجب أن تكون كل صورة مختارة من مكتبة وسائط هذا البائع.',
            'en' => 'Each selected image must come from this vendor\'s media library.',
        ],
        'dashboard.Products_Form_Images_Upload_Disabled' => [
            'ar' => 'لا يمكن رفع الصور من هنا. اخترها من مكتبة وسائط البائع.',
            'en' => 'Images cannot be uploaded here. Choose them from the vendor\'s media library.',
        ],
        'dashboard.Products_Form_Images_Max' => [
            'ar' => 'يمكن أن يحتوي المنتج على 6 صور كحد أقصى.',
            'en' => 'A product can have up to 6 images.',
        ],
        'dashboard.Products_Form_Vendor_Immutable' => [
            'ar' => 'لا يمكن تغيير بائع منتج موجود.',
            'en' => 'The vendor of an existing product cannot be changed.',
        ],
        // PRODUCT-MEDIA-01 P5-B: the ordered product gallery (reorder, main image, remove, replace, add).
        'dashboard.Products_Form_Gallery_Invalid' => [
            'ar' => 'تعذّرت قراءة قائمة الصور. أعد تحميل الصفحة وحاول مجددًا.',
            'en' => 'The image list could not be read. Reload the page and try again.',
        ],
        'dashboard.Products_Form_Gallery_Outdated' => [
            'ar' => 'تغيّرت صور المنتج أثناء فتح هذه الصفحة. أعد تحميل الصفحة وراجعها قبل الحفظ.',
            'en' => 'The product images changed while this page was open. Reload the page and review them before saving.',
        ],
        'dashboard.Products_Form_Gallery_Not_Product' => [
            'ar' => 'إحدى الصور في القائمة لا تخص هذا المنتج.',
            'en' => 'An image in the list does not belong to this product.',
        ],
        'dashboard.Products_Form_Gallery_Duplicate' => [
            'ar' => 'يمكن أن تظهر كل صورة مرة واحدة فقط في القائمة.',
            'en' => 'Each image can appear only once in the list.',
        ],
        'dashboard.Products_Form_Status_Hint' => [
            'ar' => 'لا يُعرض للبيع إلا المنتج النشط. المنتج المعلّق أو المؤرشف يبقى خارج البيع أيًّا كانت حالته.',
            'en' => 'Only an active product can be on sale. A suspended or archived product stays off sale whatever its status.',
        ],
        'dashboard.Products_Form_Featured_Hint' => ['ar' => 'يمكن إبراز المنتجات المميزة في المتجر.', 'en' => 'Featured products can be highlighted in the store.'],
        // ADMIN-PRODUCT-FORM-FIX-01: "Limited edition" (the field label the controller already used, not seeded until
        // now) and the two tag lists of "Status and settings".
        'dashboard.Products_Form_Field_Limited' => ['ar' => 'إصدار محدود', 'en' => 'Limited edition'],
        'dashboard.Products_Form_Limited_Hint' => ['ar' => 'اعرض شارة الإصدار المحدود فقط عندما تنطبق.', 'en' => 'Show the limited-edition label only when it applies.'],
        'dashboard.Products_Form_Highlight_Tags' => ['ar' => 'الوسوم البارزة', 'en' => 'Top tags'],
        'dashboard.Products_Form_Highlight_Tag_Label' => ['ar' => 'الوسم البارز :number', 'en' => 'Top tag :number'],
        'dashboard.Products_Form_Highlight_Tag_Placeholder' => ['ar' => 'جديد', 'en' => 'New'],
        'dashboard.Products_Form_Craft_Tags' => ['ar' => 'وسوم الحرفة', 'en' => 'Craft tags'],
        'dashboard.Products_Form_Craft_Tag_Label' => ['ar' => 'وسم الحرفة :number', 'en' => 'Craft tag :number'],
        'dashboard.Products_Form_Craft_Tag_Placeholder' => ['ar' => 'مرسوم يدويًا', 'en' => 'Hand painted'],
        'dashboard.Products_Form_Tag_Placeholder' => ['ar' => 'وسم اختياري', 'en' => 'Optional tag'],
        'dashboard.Products_Form_Save' => ['ar' => 'حفظ المنتج', 'en' => 'Save product'],
        'dashboard.Products_Form_Cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],

        // Ability labels (data/abilities.php, read with t() like every ability label).
        'dashboard.Audit_Products' => ['ar' => 'عرض سجل إجراءات المنتجات', 'en' => 'View product action log'],
        'dashboard.Suspend_Products' => ['ar' => 'تعليق المنتجات', 'en' => 'Suspend products'],
        'dashboard.Unsuspend_Products' => ['ar' => 'رفع تعليق المنتجات', 'en' => 'Lift product suspensions'],
        'dashboard.Archive_Products' => ['ar' => 'أرشفة المنتجات', 'en' => 'Archive products'],
        'dashboard.Restore_Products' => ['ar' => 'استعادة المنتجات المؤرشفة', 'en' => 'Restore archived products'],

        // Messages of AD-UI-PROD-M0 / M0-FIX (ProductManagementController).
        'dashboard.Product_delete_disabled' => [
            'ar' => 'حذف المنتجات نهائيًا معطّل. بقي المنتج مع طلباته وحجوزات مخزونه وتقييماته.',
            'en' => 'Permanent deletion of products is disabled. The product and its orders, stock reservations and reviews were kept.',
        ],
        'dashboard.Product_stock_below_reserved' => [
            'ar' => 'لا يمكن أن يقل المخزون عن :reserved وحدة محجوزة حاليًا لطلبات مفتوحة.',
            'en' => 'The stock quantity cannot be lower than the :reserved units currently reserved for open orders.',
        ],
        'dashboard.Product_stock_conflict' => [
            'ar' => 'تغيّر المخزون أثناء فتح هذه الصفحة (أصبح :current). راجعه واحفظ مجددًا.',
            'en' => 'The stock changed while this page was open (it is now :current). Review it and save again.',
        ],
        'dashboard.Product_form_outdated' => [
            'ar' => 'هذا النموذج قديم. أعد تحميل الصفحة واحفظ مجددًا.',
            'en' => 'This form is out of date. Reload the page and save again.',
        ],

        // Used by the products controller since before M3, auto-created on the development server with the English
        // text in Arabic (see CORRECTIONS). Product-specific, not shared.
        'dashboard.Product_saved' => ['ar' => 'تم حفظ المنتج بنجاح.', 'en' => 'Product saved successfully.'],
    ];

    /**
     * Earlier default texts that are no longer correct. Each row is replaced only while it still holds the exact
     * old default; any other value is an edit and is kept (same mechanism as AdminCategoryTranslationSeeder).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        [
            'key' => 'dashboard.Product_saved',
            'locale' => 'ar',
            'from' => 'Product saved successfully.',
            'to' => 'تم حفظ المنتج بنجاح.',
        ],
        // The compare-price and weight hints, rewritten with the weight unit and product images form (b06f67b).
        // HIRFAH-PRICE-01: the b06f67b compare-price targets ("the previous higher price") are superseded, so both
        // generations of the old hint now lead straight to the PRICE-01 text, never to an intermediate one.
        [
            'key' => 'dashboard.Products_Form_Compare_Price_Hint',
            'locale' => 'ar',
            'from' => 'سعر سابق يُعرض للمقارنة، ويجب أن يكون أعلى من السعر.',
            'to' => 'اختياري. أدخل سعرًا أقل من السعر الأساسي. عند تحديده، يُعرض السعر الأساسي مشطوبًا ويُعتمد سعر الخصم للبيع.',
        ],
        [
            'key' => 'dashboard.Products_Form_Compare_Price_Hint',
            'locale' => 'en',
            'from' => 'An earlier price shown for comparison. It must be higher than the price.',
            'to' => 'Optional. Enter a price lower than the regular price. When provided, the regular price is displayed crossed out and the sale price is used for purchases.',
        ],
        [
            'key' => 'dashboard.Products_Form_Compare_Price_Hint',
            'locale' => 'ar',
            'from' => 'السعر السابق الأعلى. عند تعبئته يمكن للمتجر عرضه مشطوبًا بجانب السعر الحالي لتوضيح الخصم. ويجب أن يكون أعلى من السعر.',
            'to' => 'اختياري. أدخل سعرًا أقل من السعر الأساسي. عند تحديده، يُعرض السعر الأساسي مشطوبًا ويُعتمد سعر الخصم للبيع.',
        ],
        [
            'key' => 'dashboard.Products_Form_Compare_Price_Hint',
            'locale' => 'en',
            'from' => 'The previous higher price. When set, the store can show it crossed out next to the current price to explain the discount. It must be higher than the price.',
            'to' => 'Optional. Enter a price lower than the regular price. When provided, the regular price is displayed crossed out and the sale price is used for purchases.',
        ],
        // HIRFAH-PRICE-01: the price hint and the two pricing labels as first shipped.
        [
            'key' => 'dashboard.Products_Form_Price_Hint',
            'locale' => 'ar',
            'from' => 'بعملة المتجر، وبمنزلتين عشريتين على الأكثر.',
            'to' => 'السعر الأساسي للمنتج بعملة المتجر، ويمكن إدخاله بمنزلتين عشريتين على الأكثر.',
        ],
        [
            'key' => 'dashboard.Products_Form_Price_Hint',
            'locale' => 'en',
            'from' => 'In the store currency, with up to two decimal places.',
            'to' => 'The regular price of the product in the store currency, with up to two decimal places.',
        ],
        [
            'key' => 'dashboard.Products_Form_Field_Price',
            'locale' => 'ar',
            'from' => 'السعر',
            'to' => 'السعر الأساسي',
        ],
        [
            'key' => 'dashboard.Products_Form_Field_Price',
            'locale' => 'en',
            'from' => 'Price',
            'to' => 'Regular price',
        ],
        [
            'key' => 'dashboard.Products_Form_Field_Compare_Price',
            'locale' => 'ar',
            'from' => 'سعر المقارنة',
            'to' => 'سعر الخصم',
        ],
        [
            'key' => 'dashboard.Products_Form_Field_Compare_Price',
            'locale' => 'en',
            'from' => 'Compare-at price',
            'to' => 'Sale price',
        ],
        [
            'key' => 'dashboard.Products_Show_Compare_Price',
            'locale' => 'ar',
            'from' => 'سعر المقارنة',
            'to' => 'سعر الخصم',
        ],
        [
            'key' => 'dashboard.Products_Show_Compare_Price',
            'locale' => 'en',
            'from' => 'Compare-at price',
            'to' => 'Sale price',
        ],
        [
            'key' => 'dashboard.Products_Form_Weight_Hint',
            'locale' => 'ar',
            'from' => 'للشحن، وبمنزلتين عشريتين على الأكثر.',
            'to' => 'للشحن، أدخل الوزن واختر كغ أو غ أو طن.',
        ],
        [
            'key' => 'dashboard.Products_Form_Weight_Hint',
            'locale' => 'en',
            'from' => 'For shipping, with up to two decimal places.',
            'to' => 'For shipping, enter the weight and choose kg, g or ton.',
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
