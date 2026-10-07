<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English strings that only the vendor auth pages use. Shared
 * strings (password, remember me, brand...) come from AdminAuthTranslationSeeder.
 * Only missing (key, locale) rows are inserted; edited values are kept.
 */
class VendorAuthTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'vendor.Login_Title' => [
            'ar' => 'تسجيل الدخول | بوابة البائعين في حرفة',
            'en' => 'Sign in | Hirfah Vendor',
        ],
        'vendor.Portal' => [
            'ar' => 'بوابة البائعين',
            'en' => 'Vendor portal',
        ],
        'vendor.Login_Description' => [
            'ar' => 'سجّل دخولك لإدارة متجرك ومنتجاتك وطلباتك على حرفة.',
            'en' => 'Sign in to manage your store, products and orders on Hirfah.',
        ],
        'vendor.Panel_Line' => [
            'ar' => 'بوابة البائعين في منصة حرفة',
            'en' => 'The Hirfah vendor portal',
        ],
        'vendor.Login_Panel_Line' => [
            'ar' => 'أدر متجرك وابدأ رحلتك مع حرفة',
            'en' => 'Manage your store and start your journey with Hirfah',
        ],
        'vendor.No_Account' => [
            'ar' => 'ليس لديك حساب؟',
            'en' => "Don't have an account?",
        ],
        'vendor.Register_As_Vendor' => [
            'ar' => 'سجّل كبائع',
            'en' => 'Register as a vendor',
        ],
        'vendor.Register_Title' => [
            'ar' => 'إنشاء حساب بائع | حرفة',
            'en' => 'Create vendor account | Hirfah',
        ],
        'vendor.Register_Heading' => [
            'ar' => 'أنشئ حساب البائع',
            'en' => 'Create your vendor account',
        ],
        'vendor.Register_Description' => [
            'ar' => 'سجّل لعرض منتجاتك الحرفية لعملاء حرفة.',
            'en' => 'Register to offer your handmade products to Hirfah customers.',
        ],
        'vendor.Full_Name' => [
            'ar' => 'الاسم الكامل',
            'en' => 'Full name',
        ],
        'vendor.Phone' => [
            'ar' => 'رقم الجوال',
            'en' => 'Phone number',
        ],
        'vendor.Email' => [
            'ar' => 'البريد الإلكتروني',
            'en' => 'Email address',
        ],
        'vendor.Password_Hint' => [
            'ar' => 'ثمانية أحرف على الأقل.',
            'en' => 'At least 8 characters.',
        ],
        'vendor.Confirm_Password' => [
            'ar' => 'تأكيد كلمة المرور',
            'en' => 'Confirm password',
        ],
        'vendor.Terms_Agreement' => [
            'ar' => 'أوافق على الشروط والأحكام وسياسة الخصوصية.',
            'en' => 'I agree to the terms and privacy policy.',
        ],
        'vendor.Create_Account' => [
            'ar' => 'إنشاء الحساب',
            'en' => 'Create account',
        ],
        'vendor.Have_Account' => [
            'ar' => 'لديك حساب؟',
            'en' => 'Already have an account?',
        ],
        'vendor.Forgot_Title' => [
            'ar' => 'نسيت كلمة المرور | بوابة البائعين في حرفة',
            'en' => 'Forgot password | Hirfah Vendor',
        ],
        'vendor.Forgot_Description' => [
            'ar' => 'أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.',
            'en' => "Enter your email and we'll send you a link to reset your password.",
        ],
        'vendor.Send_Reset_Link' => [
            'ar' => 'إرسال رابط إعادة التعيين',
            'en' => 'Send reset link',
        ],
        'vendor.Remembered_Password' => [
            'ar' => 'تذكرت كلمة المرور؟',
            'en' => 'Remembered your password?',
        ],
        'vendor.Reset_Title' => [
            'ar' => 'تعيين كلمة مرور جديدة | بوابة البائعين في حرفة',
            'en' => 'Set a new password | Hirfah Vendor',
        ],
        'vendor.Reset_Heading' => [
            'ar' => 'تعيين كلمة مرور جديدة',
            'en' => 'Set a new password',
        ],
        'vendor.Reset_Description' => [
            'ar' => 'أنشئ كلمة مرور جديدة لحساب البائع الخاص بك.',
            'en' => 'Create a new password for your vendor account.',
        ],
        'vendor.New_Password' => [
            'ar' => 'كلمة المرور الجديدة',
            'en' => 'New password',
        ],
        'vendor.Set_Password' => [
            'ar' => 'تعيين كلمة المرور',
            'en' => 'Set password',
        ],
        'vendor.Back_To_Login' => [
            'ar' => 'العودة إلى تسجيل الدخول',
            'en' => 'Back to sign in',
        ],
        'vendor.Status_Title' => [
            'ar' => 'حالة الطلب | بوابة البائعين في حرفة',
            'en' => 'Application status | Hirfah Vendor',
        ],
        'vendor.Application_Status' => [
            'ar' => 'حالة الطلب',
            'en' => 'Application status',
        ],
        'vendor.Pending_Heading' => [
            'ar' => 'طلبك قيد المراجعة',
            'en' => 'Your application is under review',
        ],
        'vendor.Pending_Description' => [
            'ar' => 'تم استلام طلب انضمامك إلى حرفة كبائع، وتتم مراجعته حاليًا من فريق الإدارة.',
            'en' => 'We have received your request to join Hirfah as a vendor, and our team is reviewing it.',
        ],
        'vendor.Pending_Label' => [
            'ar' => 'بانتظار المراجعة',
            'en' => 'Awaiting review',
        ],
        'vendor.Rejected_Heading' => [
            'ar' => 'لم تتم الموافقة على طلبك',
            'en' => 'Your application was not approved',
        ],
        'vendor.Rejected_Description' => [
            'ar' => 'نأسف لإبلاغك بأنه لم تتم الموافقة على طلب انضمامك إلى حرفة كبائع.',
            'en' => 'We are sorry to let you know that your request to join Hirfah as a vendor was not approved.',
        ],
        'vendor.Rejected_Label' => [
            'ar' => 'لم تتم الموافقة',
            'en' => 'Not approved',
        ],
        'vendor.Rejection_Reason' => [
            'ar' => 'سبب عدم الموافقة',
            'en' => 'Reason',
        ],
        'vendor.Status_Label' => [
            'ar' => 'الحالة',
            'en' => 'Status',
        ],
        'vendor.Store_Name' => [
            'ar' => 'اسم المتجر',
            'en' => 'Store name',
        ],
        'vendor.Sign_Out' => [
            'ar' => 'تسجيل الخروج',
            'en' => 'Sign out',
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
