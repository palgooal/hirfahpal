<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Baseline Arabic and English strings for the admin auth pages, which never
 * rely on t() auto-create. Only missing (key, locale) rows are inserted;
 * existing values are left untouched because admins may have edited them.
 *
 * Other active languages are not seeded: t() falls back to the fallback
 * locale for them until they are translated from the dashboard.
 */
class AdminAuthTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Admin_Login_Title' => [
            'ar' => 'تسجيل الدخول | إدارة حرفة',
            'en' => 'Sign in | Hirfah Admin',
        ],
        'dashboard.Welcome_Back' => [
            'ar' => 'مرحبًا بعودتك',
            'en' => 'Welcome back',
        ],
        'dashboard.Admin_Login_Description' => [
            'ar' => 'سجّل دخولك للوصول إلى لوحة إدارة حرفة.',
            'en' => 'Sign in to access the Hirfah admin panel.',
        ],
        'dashboard.Email_Or_Phone' => [
            'ar' => 'البريد الإلكتروني أو رقم الجوال',
            'en' => 'Email or phone number',
        ],
        // Reused: the admin form already uses this key with the same meaning.
        'dashboard.Password' => [
            'ar' => 'كلمة المرور',
            'en' => 'Password',
        ],
        'dashboard.Show_Password' => [
            'ar' => 'إظهار كلمة المرور',
            'en' => 'Show password',
        ],
        'dashboard.Hide_Password' => [
            'ar' => 'إخفاء كلمة المرور',
            'en' => 'Hide password',
        ],
        'dashboard.Remember_Me' => [
            'ar' => 'تذكرني',
            'en' => 'Remember me',
        ],
        'dashboard.Forgot_Password' => [
            'ar' => 'نسيت كلمة المرور؟',
            'en' => 'Forgot your password?',
        ],
        'dashboard.Sign_In' => [
            'ar' => 'تسجيل الدخول',
            'en' => 'Sign in',
        ],
        'dashboard.Hirfah' => [
            'ar' => 'حرفة',
            'en' => 'Hirfah',
        ],
        'dashboard.Hirfah_Logo' => [
            'ar' => 'شعار حرفة',
            'en' => 'Hirfah logo',
        ],
        'dashboard.Brand_Name' => [
            'ar' => 'حِــرْفَة',
            'en' => 'Hirfah',
        ],
        'dashboard.Brand_Tagline' => [
            'ar' => 'سوق الصنعة الفلسطينية',
            'en' => 'Palestinian craft marketplace',
        ],
        'dashboard.Admin_Panel_Line' => [
            'ar' => 'لوحة إدارة منصة حرفة',
            'en' => 'Hirfah platform administration',
        ],
        'dashboard.Choose_Language' => [
            'ar' => 'اختيار اللغة',
            'en' => 'Choose language',
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
