<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English strings of the admin dashboard sidebar (AD-UI-01C). The
 * keys use their own dashboard.Nav_* scope: the older dashboard.* labels were
 * auto-created with English values in Arabic, and insertOrIgnore never fixes
 * an existing row. Brand name and logo text come from AdminAuthTranslationSeeder.
 * Groups and items that are deferred in the spec (delivery, finance) are not
 * rendered, so they have no keys yet. Only missing (key, locale) rows are
 * inserted; edited values are kept. Not called from DatabaseSeeder.
 */
class AdminDashboardTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Sidebar landmark, brand subtitle and controls.
        'dashboard.Nav_Main' => [
            'ar' => 'القائمة الرئيسية',
            'en' => 'Main menu',
        ],
        'dashboard.Nav_Brand_Subtitle' => [
            'ar' => 'لوحة الإدارة',
            'en' => 'Admin panel',
        ],
        'dashboard.Nav_Collapse' => [
            'ar' => 'تصغير القائمة',
            'en' => 'Collapse menu',
        ],
        'dashboard.Nav_Expand' => [
            'ar' => 'توسيع القائمة',
            'en' => 'Expand menu',
        ],
        'dashboard.Nav_Open' => [
            'ar' => 'فتح القائمة',
            'en' => 'Open menu',
        ],
        'dashboard.Nav_Close' => [
            'ar' => 'إغلاق القائمة',
            'en' => 'Close menu',
        ],

        // Group titles (spec section 3.1).
        'dashboard.Nav_Group_Users' => [
            'ar' => 'إدارة المستخدمين',
            'en' => 'User management',
        ],
        'dashboard.Nav_Group_Store' => [
            'ar' => 'إدارة المتجر',
            'en' => 'Store management',
        ],
        'dashboard.Nav_Group_Quality' => [
            'ar' => 'الجودة والمتابعة',
            'en' => 'Quality and follow-up',
        ],
        'dashboard.Nav_Group_System' => [
            'ar' => 'النظام',
            'en' => 'System',
        ],

        // Items (spec section 3.1; "Add" links are not part of the sidebar).
        'dashboard.Nav_Home' => [
            'ar' => 'الرئيسية',
            'en' => 'Home',
        ],
        'dashboard.Nav_Vendors' => [
            'ar' => 'البائعون',
            'en' => 'Vendors',
        ],
        'dashboard.Nav_Customers' => [
            'ar' => 'العملاء',
            'en' => 'Customers',
        ],
        'dashboard.Nav_Delivery_Drivers' => [
            'ar' => 'مندوبو التوصيل',
            'en' => 'Delivery drivers',
        ],
        'dashboard.Nav_Admins_Permissions' => [
            'ar' => 'المشرفون والصلاحيات',
            'en' => 'Admins and permissions',
        ],
        'dashboard.Nav_Categories' => [
            'ar' => 'التصنيفات',
            'en' => 'Categories',
        ],
        'dashboard.Nav_Products' => [
            'ar' => 'المنتجات',
            'en' => 'Products',
        ],
        'dashboard.Nav_Orders' => [
            'ar' => 'الطلبات',
            'en' => 'Orders',
        ],
        'dashboard.Nav_Vendor_Orders' => [
            'ar' => 'الطلبات الفرعية',
            'en' => 'Vendor orders',
        ],
        'dashboard.Nav_Reviews' => [
            'ar' => 'التقييمات',
            'en' => 'Reviews',
        ],
        'dashboard.Nav_Disputes' => [
            'ar' => 'النزاعات',
            'en' => 'Disputes',
        ],
        'dashboard.Nav_Return_Requests' => [
            'ar' => 'الاسترجاع',
            'en' => 'Return requests',
        ],
        // CONTACT-ADMIN-IMPLEMENTATION-01: also the title of the contact-message pages.
        'dashboard.Nav_Contact_Messages' => [
            'ar' => 'رسائل التواصل',
            'en' => 'Contact messages',
        ],
        'dashboard.Nav_Languages' => [
            'ar' => 'اللغات',
            'en' => 'Languages',
        ],
        'dashboard.Nav_Translations' => [
            'ar' => 'الترجمات',
            'en' => 'Translations',
        ],
        'dashboard.Nav_Settings' => [
            'ar' => 'الإعدادات',
            'en' => 'Settings',
        ],
        // AD-UI-CAT-M3.
        'dashboard.Nav_Media_Library' => [
            'ar' => 'مكتبة الوسائط',
            'en' => 'Media Library',
        ],

        // User card, kept in the sidebar until AD-UI-01D.
        'dashboard.Nav_Account_Menu' => [
            'ar' => 'قائمة الحساب',
            'en' => 'Account menu',
        ],
        'dashboard.Nav_Sign_Out' => [
            'ar' => 'تسجيل الخروج',
            'en' => 'Sign out',
        ],
    ];

    /**
     * Rows t() auto-created with the English default in Arabic before this seeder ran. Each is replaced only
     * while it still holds that exact value; any other value is an edit and is kept.
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        // AD-UI-CAT-M3: the sidebar item was rendered on the development server before seeding.
        ['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'from' => 'Media Library', 'to' => 'مكتبة الوسائط'],
        ['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'from' => 'Media library', 'to' => 'مكتبة الوسائط'],
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
            // Guarded update: only this (key, locale) row, and only while it still holds the auto-created value
            // exactly. Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" would also replace an
            // edit that differs only in case (e.g. "MEDIA LIBRARY").
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
