<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English strings that only the delivery driver auth pages use (auth/accounts/login, register,
 * forgot-password, reset-password and approval-status, same design as the vendor portal). Shared strings (password, remember me, brand...) come from AdminAuthTranslationSeeder.
 * Only missing (key, locale) rows are inserted; edited values are kept. Not called from DatabaseSeeder.
 */
class DeliveryDriverAuthTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'driver.Login_Title' => [
            'ar' => 'تسجيل الدخول | بوابة مندوبي التوصيل في حرفة',
            'en' => 'Sign in | Hirfah Delivery',
        ],
        'driver.Portal' => [
            'ar' => 'بوابة مندوبي التوصيل',
            'en' => 'Delivery driver portal',
        ],
        'driver.Login_Description' => [
            'ar' => 'سجّل دخولك لإدارة مهام التوصيل ومتابعة الشحنات على حِرفة',
            'en' => 'Sign in to manage your delivery tasks and track shipments on Hirfah.',
        ],
        'driver.Login_Panel_Line' => [
            'ar' => 'أوصل إبداعات حِرفة إلى أصحابها',
            'en' => 'Deliver Hirfah’s crafts to the people waiting for them',
        ],
        'driver.Signing_In' => [
            'ar' => 'جارٍ تسجيل الدخول...',
            'en' => 'Signing in...',
        ],
        'driver.No_Account' => [
            'ar' => 'ليس لديك حساب؟',
            'en' => "Don't have an account?",
        ],
        'driver.Register_As_Driver' => [
            'ar' => 'انضم كمندوب توصيل',
            'en' => 'Join as a delivery driver',
        ],
        // The registration page (auth/accounts/register).
        'driver.Register_Title' => [
            'ar' => 'إنشاء حساب مندوب توصيل | حرفة',
            'en' => 'Create a delivery driver account | Hirfah',
        ],
        'driver.Register_Heading' => [
            'ar' => 'إنشاء حساب مندوب توصيل',
            'en' => 'Create a delivery driver account',
        ],
        'driver.Register_Description' => [
            'ar' => 'انضم إلى فريق توصيل حِرفة، وساهم في إيصال إبداعات الحرفيين إلى أصحابها.',
            'en' => 'Join the Hirfah delivery team and help bring artisans’ work to the people who ordered it.',
        ],
        'driver.Register_Panel_Line' => [
            'ar' => 'كن حلقة الوصل بين إبداعات الحرفيين وأصحابها.',
            'en' => 'Be the link between artisans’ work and the people waiting for it.',
        ],
        'driver.Required_Fields' => [
            'ar' => 'جميع الحقول مطلوبة.',
            'en' => 'All fields are required.',
        ],
        'driver.Full_Name' => [
            'ar' => 'الاسم الكامل',
            'en' => 'Full name',
        ],
        'driver.Phone' => [
            'ar' => 'رقم الجوال',
            'en' => 'Phone number',
        ],
        'driver.Email' => [
            'ar' => 'البريد الإلكتروني',
            'en' => 'Email address',
        ],
        'driver.Password_Hint' => [
            'ar' => 'ثمانية أحرف على الأقل.',
            'en' => 'At least 8 characters.',
        ],
        'driver.Confirm_Password' => [
            'ar' => 'تأكيد كلمة المرور',
            'en' => 'Confirm password',
        ],
        'driver.Terms_Agreement' => [
            'ar' => 'أوافق على الشروط والأحكام وسياسة الخصوصية.',
            'en' => 'I agree to the terms and conditions and the privacy policy.',
        ],
        'driver.Review_Notice' => [
            'ar' => 'سيخضع حسابك للمراجعة قبل تفعيل خدمات التوصيل.',
            'en' => 'Your account will be reviewed before delivery services are activated.',
        ],
        'driver.Create_Account' => [
            'ar' => 'إنشاء حساب',
            'en' => 'Create account',
        ],
        'driver.Creating_Account' => [
            'ar' => 'جارٍ إنشاء الحساب...',
            'en' => 'Creating account...',
        ],
        'driver.Have_Account' => [
            'ar' => 'لديك حساب بالفعل؟',
            'en' => 'Already have an account?',
        ],
        // E2-MVP batch 01: the service area and vehicle on the registration page, and their validation messages.
        'driver.Governorate' => [
            'ar' => 'المحافظة',
            'en' => 'Governorate',
        ],
        'driver.Choose_Governorate' => [
            'ar' => 'اختر المحافظة',
            'en' => 'Choose a governorate',
        ],
        'driver.City' => [
            'ar' => 'المدينة',
            'en' => 'City',
        ],
        'driver.Choose_City' => [
            'ar' => 'اختر المدينة',
            'en' => 'Choose a city',
        ],
        'driver.Vehicle_Type' => [
            'ar' => 'نوع المركبة',
            'en' => 'Vehicle type',
        ],
        'driver.Vehicle_Type_Placeholder' => [
            'ar' => 'مثال: دراجة نارية',
            'en' => 'e.g. Motorcycle',
        ],
        'driver.Location_Invalid' => [
            'ar' => 'القيمة المختارة غير متاحة.',
            'en' => 'The selected value is not available.',
        ],
        'driver.City_Mismatch' => [
            'ar' => 'المدينة المختارة لا تتبع المحافظة المختارة.',
            'en' => 'The selected city does not belong to the selected governorate.',
        ],
        // E2-MVP batch 01 (FR-DEL-013): the application status page (auth/accounts/approval-status).
        'driver.Status_Title' => [
            'ar' => 'حالة الطلب | بوابة مندوبي التوصيل في حرفة',
            'en' => 'Application status | Hirfah Delivery',
        ],
        'driver.Application_Status' => [
            'ar' => 'حالة طلب الانضمام',
            'en' => 'Application status',
        ],
        'driver.Pending_Heading' => [
            'ar' => 'طلبك قيد المراجعة',
            'en' => 'Your application is under review',
        ],
        'driver.Pending_Description' => [
            'ar' => 'استلمنا طلب انضمامك إلى فريق توصيل حِرفة، ويراجعه فريقنا الآن. ستتمكن من استلام مهام التوصيل بعد الموافقة.',
            'en' => 'We have received your request to join the Hirfah delivery team, and our team is reviewing it. You can receive delivery tasks once it is approved.',
        ],
        'driver.Rejected_Heading' => [
            'ar' => 'لم تتم الموافقة على طلبك',
            'en' => 'Your application was not approved',
        ],
        'driver.Rejected_Description' => [
            'ar' => 'نأسف لإبلاغك بأن طلب انضمامك إلى فريق توصيل حِرفة لم تتم الموافقة عليه.',
            'en' => 'We are sorry to let you know that your request to join the Hirfah delivery team was not approved.',
        ],
        'driver.Status_Label' => [
            'ar' => 'الحالة',
            'en' => 'Status',
        ],
        'driver.Pending_Label' => [
            'ar' => 'قيد المراجعة',
            'en' => 'Under review',
        ],
        'driver.Rejected_Label' => [
            'ar' => 'مرفوض',
            'en' => 'Not approved',
        ],
        'driver.Name_Label' => [
            'ar' => 'الاسم',
            'en' => 'Name',
        ],
        'driver.Rejection_Reason' => [
            'ar' => 'سبب الرفض',
            'en' => 'Reason',
        ],
        'driver.Sign_Out' => [
            'ar' => 'تسجيل الخروج',
            'en' => 'Sign out',
        ],
        // The password recovery pages (auth/accounts/forgot-password and reset-password).
        'driver.Forgot_Title' => [
            'ar' => 'نسيت كلمة المرور | بوابة مندوبي التوصيل في حرفة',
            'en' => 'Forgot password | Hirfah Delivery',
        ],
        'driver.Forgot_Description' => [
            'ar' => 'أدخل بريدك الإلكتروني، وسنرسل إليك رابطًا لإعادة تعيين كلمة المرور.',
            'en' => "Enter your email and we'll send you a link to reset your password.",
        ],
        'driver.Send_Reset_Link' => [
            'ar' => 'إرسال رابط إعادة التعيين',
            'en' => 'Send reset link',
        ],
        'driver.Sending_Reset_Link' => [
            'ar' => 'جارٍ إرسال الرابط...',
            'en' => 'Sending link...',
        ],
        'driver.Remembered_Password' => [
            'ar' => 'تذكرت كلمة المرور؟',
            'en' => 'Remembered your password?',
        ],
        'driver.Reset_Title' => [
            'ar' => 'تعيين كلمة مرور جديدة | بوابة مندوبي التوصيل في حرفة',
            'en' => 'Set a new password | Hirfah Delivery',
        ],
        'driver.Reset_Heading' => [
            'ar' => 'تعيين كلمة مرور جديدة',
            'en' => 'Set a new password',
        ],
        'driver.Reset_Description' => [
            'ar' => 'أنشئ كلمة مرور جديدة لحساب مندوب التوصيل.',
            'en' => 'Create a new password for your delivery driver account.',
        ],
        'driver.New_Password' => [
            'ar' => 'كلمة المرور الجديدة',
            'en' => 'New password',
        ],
        'driver.Set_Password' => [
            'ar' => 'حفظ كلمة المرور',
            'en' => 'Save password',
        ],
        'driver.Saving_Password' => [
            'ar' => 'جارٍ حفظ كلمة المرور...',
            'en' => 'Saving password...',
        ],
        'driver.Back_To_Login' => [
            'ar' => 'العودة إلى تسجيل الدخول',
            'en' => 'Back to sign in',
        ],
    ];

    /**
     * The text of a key in the current locale (English for any other locale), used by the pages as t()'s default until
     * the seeder has run.
     */
    public static function fallback(string $key, ?string $locale = null): string
    {
        $values = self::TRANSLATIONS[$key] ?? [];

        return $values[$locale ?? app()->getLocale()] ?? $values['en'] ?? $key;
    }

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
