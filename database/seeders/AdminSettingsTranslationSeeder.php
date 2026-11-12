<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * SETTINGS-FIX-02 (SET-04, SET-08): Arabic and English texts of the footer section of the settings page
 * (dashboard.Settings_Footer_*: its title, its hint and the label of each of the 23 footer fields, the Arabic ones as
 * they were written in the view) and the accessible names of the storefront footer's social links
 * (storefront.Footer_Social_*). Both are read without auto-creating rows, so a page viewed before this seeder runs
 * never stores the English default in Arabic. The merchant's footer content itself (Setting::footerDefaults() and
 * settings.footer_content) is not a translation and is not touched.
 *
 * SETTINGS-I18N-01: the general settings labels (CORRECTIONS). t() auto-creates a missing row with the English default
 * in whatever locale is being viewed, so opening the page in Arabic before any seeder had run stored "Site Name" and the
 * like as the Arabic values. Same approach as AdminAbilityTranslationSeeder: a missing row is inserted with the approved
 * value, and an Arabic row is replaced only while it still holds the English default exactly (never an edited value).
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; nothing is deleted. Not called from
 * DatabaseSeeder.
 */
class AdminSettingsTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // SETTINGS-MEDIA-01: the logo and favicon pickers (removal notes) and an image uploaded before the media library.
        'dashboard.Settings_Logo_Removed_Note' => ['ar' => 'سيُزال الشعار من الموقع عند الحفظ، ويبقى الملف في مكتبة الوسائط.', 'en' => 'The logo will be removed from the site when you save. The file stays in the media library.'],
        'dashboard.Settings_Favicon_Removed_Note' => ['ar' => 'ستُزال أيقونة الموقع عند الحفظ، ويبقى الملف في مكتبة الوسائط.', 'en' => 'The favicon will be removed from the site when you save. The file stays in the media library.'],
        'dashboard.Settings_Legacy_Image_Note' => ['ar' => 'صورة مرفوعة سابقًا، تظهر في الموقع حتى تختار صورة من مكتبة الوسائط.', 'en' => 'A previously uploaded image. It is shown on the site until you choose an image from the media library.'],
        'dashboard.Settings_Legacy_Image_Remove' => ['ar' => 'إزالة الصورة المرفوعة سابقًا (يبقى الملف محفوظًا)', 'en' => 'Remove the previously uploaded image (the file is kept)'],
        'dashboard.Settings_Footer_Title' => ['ar' => 'إعدادات التذييل', 'en' => 'Footer settings'],
        'dashboard.Settings_Footer_Hint' => ['ar' => 'هذه البيانات تظهر مباشرة في تذييل الموقع.', 'en' => 'These details appear directly in the site footer.'],
        // FOOTER-I18N-01C: the two languages of the footer texts, and how an empty English field behaves.
        'dashboard.Settings_Language_Ar' => ['ar' => 'العربية', 'en' => 'Arabic'],
        'dashboard.Settings_Language_En' => ['ar' => 'الإنجليزية', 'en' => 'English'],
        'dashboard.Settings_Footer_Language_Hint' => ['ar' => 'لكل نص حقل بالعربية وآخر بالإنجليزية. الحقل الفارغ يعرض النص الافتراضي الظاهر فيه.', 'en' => 'Each text has an Arabic and an English field. An empty field shows the default text displayed in it.'],
        'dashboard.Settings_Footer_Tagline' => ['ar' => 'الشعار النصي', 'en' => 'Tagline'],
        'dashboard.Settings_Footer_Description' => ['ar' => 'وصف الفوتر', 'en' => 'Footer description'],
        'dashboard.Settings_Footer_Instagram_Url' => ['ar' => 'رابط إنستغرام', 'en' => 'Instagram link'],
        'dashboard.Settings_Footer_Facebook_Url' => ['ar' => 'رابط فيسبوك', 'en' => 'Facebook link'],
        'dashboard.Settings_Footer_X_Url' => ['ar' => 'رابط إكس (X)', 'en' => 'X link'],
        'dashboard.Settings_Footer_About_Title' => ['ar' => 'عنوان قسم عن الموقع', 'en' => 'About section title'],
        'dashboard.Settings_Footer_About_Story_Label' => ['ar' => 'نص رابط القصة والرؤية', 'en' => 'Story and vision link text'],
        'dashboard.Settings_Footer_About_Authenticity_Label' => ['ar' => 'نص رابط الأصالة والجودة', 'en' => 'Authenticity and quality link text'],
        'dashboard.Settings_Footer_About_Contact_Label' => ['ar' => 'نص رابط التواصل', 'en' => 'Contact link text'],
        'dashboard.Settings_Footer_Links_Title' => ['ar' => 'عنوان الروابط المفيدة', 'en' => 'Useful links title'],
        'dashboard.Settings_Footer_Orders_Label' => ['ar' => 'نص رابط الطلبات', 'en' => 'Orders link text'],
        'dashboard.Settings_Footer_Cart_Label' => ['ar' => 'نص رابط السلة', 'en' => 'Cart link text'],
        'dashboard.Settings_Footer_Login_Label' => ['ar' => 'نص رابط تسجيل الدخول', 'en' => 'Sign-in link text'],
        'dashboard.Settings_Footer_Account_Label' => ['ar' => 'نص رابط الحساب', 'en' => 'Account link text'],
        'dashboard.Settings_Footer_Customer_Service_Title' => ['ar' => 'عنوان خدمة العملاء', 'en' => 'Customer service title'],
        'dashboard.Settings_Footer_Shipping_Label' => ['ar' => 'نص رابط الشحن', 'en' => 'Shipping link text'],
        'dashboard.Settings_Footer_Returns_Label' => ['ar' => 'نص رابط الاستبدال والإرجاع', 'en' => 'Exchanges and returns link text'],
        'dashboard.Settings_Footer_Faq_Label' => ['ar' => 'نص رابط الأسئلة الشائعة', 'en' => 'FAQ link text'],
        'dashboard.Settings_Footer_Copyright' => ['ar' => 'نص الحقوق', 'en' => 'Copyright text'],
        'dashboard.Settings_Footer_Payment_Methods_Title' => ['ar' => 'عنوان طرق الدفع', 'en' => 'Payment methods title'],
        'dashboard.Settings_Footer_Card_Payment_Label' => ['ar' => 'نص بطاقة الائتمان', 'en' => 'Credit card label'],
        'dashboard.Settings_Footer_Apple_Pay_Label' => ['ar' => 'نص Apple Pay', 'en' => 'Apple Pay label'],
        'dashboard.Settings_Footer_Cash_On_Delivery_Label' => ['ar' => 'نص الدفع عند الاستلام', 'en' => 'Cash on delivery label'],
        // SETTINGS-UX-01: the tabs (site availability reuses dashboard.Site_Availability_Title) and the field groups.
        'dashboard.Settings_Tabs' => ['ar' => 'أقسام الإعدادات', 'en' => 'Settings sections'],
        'dashboard.Settings_Tab_General' => ['ar' => 'الإعدادات العامة', 'en' => 'General settings'],
        'dashboard.Settings_Tab_Footer' => ['ar' => 'إعدادات التذييل', 'en' => 'Footer settings'],
        'dashboard.Settings_Tab_Has_Errors' => ['ar' => 'يحتوي على أخطاء', 'en' => 'Contains errors'],
        'dashboard.Settings_Group_Identity' => ['ar' => 'هوية الموقع', 'en' => 'Site identity'],
        'dashboard.Settings_Group_Contact' => ['ar' => 'بيانات التواصل', 'en' => 'Contact details'],
        'dashboard.Settings_Group_Regional' => ['ar' => 'الإعدادات الإقليمية', 'en' => 'Regional settings'],
        'dashboard.Settings_Group_Footer_Identity' => ['ar' => 'هوية التذييل', 'en' => 'Footer identity'],
        'dashboard.Settings_Group_Footer_Social' => ['ar' => 'روابط التواصل الاجتماعي', 'en' => 'Social links'],
        'dashboard.Settings_Group_Footer_Navigation' => ['ar' => 'روابط التنقل', 'en' => 'Navigation links'],
        'dashboard.Settings_Group_Footer_Service' => ['ar' => 'خدمة العملاء والسياسات', 'en' => 'Customer service and policies'],
        'dashboard.Settings_Group_Footer_Payment' => ['ar' => 'طرق الدفع', 'en' => 'Payment methods'],
        'dashboard.Settings_Group_Footer_Copyright' => ['ar' => 'حقوق النشر', 'en' => 'Copyright'],
        // SETTINGS-UX-02B: the required mark, the error summary title, the save button and the field hints.
        'dashboard.Settings_Required' => ['ar' => 'مطلوب', 'en' => 'Required'],
        'dashboard.Settings_Form_Errors' => ['ar' => 'يرجى تصحيح ما يلي:', 'en' => 'Please correct the following:'],
        'dashboard.Settings_Save' => ['ar' => 'حفظ الإعدادات', 'en' => 'Save settings'],
        'dashboard.Settings_Hint_Timezone' => ['ar' => 'المنطقة الزمنية، مثل Asia/Hebron.', 'en' => 'Time zone, such as Asia/Hebron.'],
        'dashboard.Settings_Hint_Locale' => ['ar' => 'رمز اللغة، مثل ar أو en.', 'en' => 'Language code, such as ar or en.'],
        'dashboard.Settings_Hint_Currency' => ['ar' => 'رمز العملة من ثلاثة أحرف، مثل ILS.', 'en' => 'Three-letter currency code, such as ILS.'],
        'dashboard.Settings_Hint_Social' => ['ar' => 'اكتب الرابط كاملًا بدءًا بـ https://، أو اتركه فارغًا لإخفاء الأيقونة.', 'en' => 'Enter the full address, starting with https://. Leave it empty to hide the icon.'],
        // SETTINGS-UX-03: the short description under each footer group's title.
        'dashboard.Settings_Group_Footer_Identity_Hint' => ['ar' => 'الشعار النصي والنص القصير أسفل الشعار.', 'en' => 'The tagline and the short text under the logo.'],
        'dashboard.Settings_Group_Footer_Social_Hint' => ['ar' => 'حسابات التواصل الاجتماعي المعروضة كأيقونات.', 'en' => 'The social media accounts shown as icons.'],
        'dashboard.Settings_Group_Footer_Navigation_Hint' => ['ar' => 'عناوين ونصوص روابط عمودَي «عن الموقع» و«روابط مفيدة».', 'en' => 'Titles and link texts of the About and Useful links columns.'],
        'dashboard.Settings_Group_Footer_Service_Hint' => ['ar' => 'عنوان عمود خدمة العملاء ونصوص روابطه.', 'en' => 'The customer service column title and its link texts.'],
        'dashboard.Settings_Group_Footer_Payment_Hint' => ['ar' => 'طرق الدفع المعروضة أسفل التذييل.', 'en' => 'The payment methods shown at the bottom of the footer.'],
        'dashboard.Settings_Group_Footer_Copyright_Hint' => ['ar' => 'النص الظاهر بعد اسم الموقع والسنة.', 'en' => 'The text shown after the site name and the year.'],
        // FOOTER-SOCIAL-02B: the social links manager (partials/social-links.blade.php) and its validation messages.
        'dashboard.Settings_Social_Url' => ['ar' => 'رابط :platform', 'en' => ':platform link'],
        'dashboard.Settings_Social_Order' => ['ar' => 'الترتيب', 'en' => 'Order'],
        'dashboard.Settings_Social_Order_Of' => ['ar' => 'ترتيب :platform', 'en' => ':platform order'],
        'dashboard.Settings_Social_Enabled' => ['ar' => 'إظهار في التذييل', 'en' => 'Show in the footer'],
        'dashboard.Settings_Social_Move_Up' => ['ar' => 'نقل :platform للأعلى', 'en' => 'Move :platform up'],
        'dashboard.Settings_Social_Move_Down' => ['ar' => 'نقل :platform للأسفل', 'en' => 'Move :platform down'],
        'dashboard.Settings_Social_Remove' => ['ar' => 'إزالة :platform', 'en' => 'Remove :platform'],
        'dashboard.Settings_Social_Add' => ['ar' => 'إضافة منصة', 'en' => 'Add platform'],
        'dashboard.Settings_Social_Add_Button' => ['ar' => 'إضافة', 'en' => 'Add'],
        'dashboard.Settings_Social_Choose' => ['ar' => 'اختر منصة', 'en' => 'Choose a platform'],
        'dashboard.Settings_Social_Empty' => ['ar' => 'لم تُضف أي منصة بعد.', 'en' => 'No platforms added yet.'],
        'dashboard.Settings_Social_Moved' => ['ar' => ':platform الآن في الموضع :position من :count.', 'en' => ':platform is now in position :position of :count.'],
        'dashboard.Settings_Social_Added' => ['ar' => 'أُضيفت :platform.', 'en' => ':platform added.'],
        'dashboard.Settings_Social_Removed' => ['ar' => 'أُزيلت :platform.', 'en' => ':platform removed.'],
        'dashboard.Settings_Social_Url_Invalid' => ['ar' => 'أدخل رابط :platform صحيحًا يبدأ بـ https://.', 'en' => 'Enter a valid :platform address starting with https://.'],
        'dashboard.Settings_Social_Saved_Invalid' => ['ar' => 'هذا الرابط المحفوظ ليس رابط :platform صالحًا، لذلك لا يظهر في المتجر. صحّحه أو أزِل المنصة قبل الحفظ.', 'en' => 'This saved address is not a valid :platform address, so it is not shown in the store. Correct it or remove the platform before saving.'],
        'dashboard.Settings_Social_Position_Distinct' => ['ar' => 'اختر ترتيبًا مختلفًا لكل منصة.', 'en' => 'Choose a different order for each platform.'],
        // The storefront footer's social links (their accessible names).
        'storefront.Footer_Social_Instagram' => ['ar' => 'إنستغرام', 'en' => 'Instagram'],
        'storefront.Footer_Social_Facebook' => ['ar' => 'فيسبوك', 'en' => 'Facebook'],
        'storefront.Footer_Social_X' => ['ar' => 'إكس (X)', 'en' => 'X'],
        // FOOTER-SOCIAL-02A: the five new platforms (App\Support\Storefront\SocialPlatforms) and the new-tab notice.
        'storefront.Footer_Social_WhatsApp' => ['ar' => 'واتساب', 'en' => 'WhatsApp'],
        'storefront.Footer_Social_TikTok' => ['ar' => 'تيك توك', 'en' => 'TikTok'],
        'storefront.Footer_Social_YouTube' => ['ar' => 'يوتيوب', 'en' => 'YouTube'],
        'storefront.Footer_Social_LinkedIn' => ['ar' => 'لينكدإن', 'en' => 'LinkedIn'],
        'storefront.Footer_Social_Telegram' => ['ar' => 'تيليجرام', 'en' => 'Telegram'],
        // FOOTER-FIX-01: the footer's link columns as navigation landmarks.
        'storefront.Footer_Nav_About' => ['ar' => 'عن الموقع', 'en' => 'About'],
        'storefront.Footer_Nav_Links' => ['ar' => 'روابط مفيدة', 'en' => 'Useful links'],
        'storefront.Footer_Nav_Service' => ['ar' => 'خدمة العملاء', 'en' => 'Customer service'],
        'storefront.Footer_Social_New_Tab' => ['ar' => 'يُفتح في نافذة جديدة', 'en' => 'opens in a new tab'],
        // The cities and governorates page (settings/locations) and its two notices (LocationManagementController).
        'dashboard.Location_Cities' => ['ar' => 'المدن', 'en' => 'Cities'],
        'dashboard.Location_Cities_Hint' => ['ar' => 'أضف المدينة أولًا، ثم أضف محافظاتها.', 'en' => 'Add a city before adding its governorates.'],
        'dashboard.Location_Add_City' => ['ar' => 'إضافة مدينة', 'en' => 'Add city'],
        'dashboard.Location_Governorates' => ['ar' => 'المحافظات', 'en' => 'Governorates'],
        'dashboard.Location_Governorates_Hint' => ['ar' => 'اختر المدينة التي تتبع لها هذه المحافظة.', 'en' => 'Choose the city this governorate belongs to.'],
        'dashboard.Location_Select_City' => ['ar' => 'اختر المدينة', 'en' => 'Choose a city'],
        'dashboard.Location_Governorate' => ['ar' => 'المحافظة', 'en' => 'Governorate'],
        'dashboard.Location_Add_Governorate' => ['ar' => 'إضافة محافظة', 'en' => 'Add governorate'],
        'dashboard.Location_Existing' => ['ar' => 'المواقع المضافة', 'en' => 'Configured locations'],
        'dashboard.Location_No_Governorates' => ['ar' => 'لم تُضف محافظات بعد.', 'en' => 'No governorates added yet.'],
        'dashboard.Location_No_Cities' => ['ar' => 'لم تُضف مدن بعد.', 'en' => 'No cities added yet.'],
        'dashboard.Location_City_Added' => ['ar' => 'تمت إضافة المدينة بنجاح.', 'en' => 'City added successfully.'],
        'dashboard.Location_Governorate_Added' => ['ar' => 'تمت إضافة المحافظة بنجاح.', 'en' => 'Governorate added successfully.'],
        // LOC-UI-7B: the page's header, summary, search, unassigned governorates group and add dialogs.
        'dashboard.Location_Intro' => ['ar' => 'أدر المدن والمحافظات المتاحة لمواقع المنتجات.', 'en' => 'Manage the cities and governorates available for product locations.'],
        'dashboard.Location_Search_Label' => ['ar' => 'البحث في المواقع', 'en' => 'Search locations'],
        'dashboard.Location_Search_Placeholder' => ['ar' => 'ابحث باسم المدينة أو المحافظة', 'en' => 'Search by city or governorate'],
        'dashboard.Location_Search_Empty' => ['ar' => 'لا توجد مواقع مطابقة لبحثك.', 'en' => 'No locations match your search.'],
        'dashboard.Location_Search_Results' => ['ar' => 'عدد النتائج: :count', 'en' => 'Results: :count'],
        'dashboard.Location_City_Governorates_Count' => ['ar' => 'عدد المحافظات: :count', 'en' => 'Governorates: :count'],
        'dashboard.Location_Add_Governorate_To' => ['ar' => 'إضافة محافظة إلى :name', 'en' => 'Add governorate to :name'],
        'dashboard.Location_Unassigned_Title' => ['ar' => 'محافظات غير مرتبطة بمدينة', 'en' => 'Unassigned governorates'],
        'dashboard.Location_Unassigned_Hint' => ['ar' => 'هذه المحافظات غير مرتبطة بأي مدينة، لذلك لا يمكن اختيارها للمنتجات.', 'en' => 'These governorates are not linked to a city, so they cannot be chosen for products.'],
        'dashboard.Location_Unassigned_Count' => ['ar' => 'غير المرتبطة: :count', 'en' => 'Unassigned: :count'],
        'dashboard.Location_Empty_Hint' => ['ar' => 'أضف أول مدينة للبدء.', 'en' => 'Add the first city to start.'],
        'dashboard.Location_Cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],
        'dashboard.Location_Close' => ['ar' => 'إغلاق', 'en' => 'Close'],
        'dashboard.Location_Saving' => ['ar' => 'جارٍ الحفظ…', 'en' => 'Saving…'],
        'dashboard.Location_Dismiss' => ['ar' => 'إخفاء', 'en' => 'Dismiss'],
    ];

    /**
     * The general settings labels (and the page's breadcrumb and save button): key => [from (the English default the
     * view passes to t(), also the English value), to (the Arabic value)].
     *
     * @var array<string, array{from: string, to: string}>
     */
    public const CORRECTIONS = [
        'dashboard.Home' => ['from' => 'Home', 'to' => 'الرئيسية'],
        'dashboard.Settings_hint' => ['from' => 'Manage the core site configuration.', 'to' => 'إدارة الإعدادات الأساسية للموقع.'],
        'dashboard.Site_Name' => ['from' => 'Site Name', 'to' => 'اسم الموقع'],
        'dashboard.Site_Description' => ['from' => 'Site Description', 'to' => 'وصف الموقع'],
        'dashboard.Logo' => ['from' => 'Logo', 'to' => 'الشعار'],
        'dashboard.Favicon' => ['from' => 'Favicon', 'to' => 'أيقونة الموقع'],
        'dashboard.Email' => ['from' => 'Email', 'to' => 'البريد الإلكتروني'],
        'dashboard.Phone' => ['from' => 'Phone', 'to' => 'رقم الهاتف'],
        'dashboard.Address' => ['from' => 'Address', 'to' => 'العنوان'],
        'dashboard.Timezone' => ['from' => 'Timezone', 'to' => 'المنطقة الزمنية'],
        'dashboard.Default_Locale' => ['from' => 'Default Locale', 'to' => 'اللغة الافتراضية'],
        'dashboard.Default_Currency' => ['from' => 'Default Currency', 'to' => 'العملة الافتراضية'],
        'dashboard.Save' => ['from' => 'Save', 'to' => 'حفظ'],
        // FOOTER-SOCIAL-02D: the notice after a save (SettingController::update(), read with auto-create, so the first
        // save stored the English default in Arabic too).
        'dashboard.Settings_updated_successfully' => ['from' => 'Settings updated successfully.', 'to' => 'تم حفظ الإعدادات بنجاح.'],
        // The link to the cities and governorates (the regional settings), also the locations page's breadcrumb.
        'dashboard.Location_Management' => ['from' => 'Manage cities and governorates', 'to' => 'إدارة المدن والمحافظات'],
    ];

    /**
     * SETTINGS-UX-01 wording: Arabic values this seeder used to insert, replaced only while a row still holds them
     * exactly. key => [from (the old Arabic value), to (the approved one)].
     *
     * @var array<string, array{from: string, to: string}>
     */
    public const ARABIC_CORRECTIONS = [
        'dashboard.Settings_Footer_Title' => ['from' => 'إعدادات الفوتر', 'to' => 'إعدادات التذييل'],
    ];

    public function run(): void
    {
        $now = now();

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

        foreach (self::CORRECTIONS + self::ARABIC_CORRECTIONS as $key => $correction) {
            // Guarded update of the Arabic row only, and only while it still holds the English default (or the old
            // Arabic value) exactly.
            // Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" would also replace an edit that
            // differs only in case.
            $row = TranslationValue::query()->where('key', $key)->where('locale', 'ar')->first();

            if (! $row || $row->value !== $correction['from']) {
                continue;
            }

            if (TranslationValue::query()->whereKey($row->getKey())->update(['value' => $correction['to'], 'updated_at' => $now]) > 0) {
                cache()->forget("translation.ar.{$key}");
            }
        }
    }
}
