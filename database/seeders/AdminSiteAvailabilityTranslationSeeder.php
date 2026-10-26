<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * SITE-AVAILABILITY Phase 3B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7): Arabic and English texts of the
 * site state card on the settings page, of the dashboard header badge (Phase 3C) and of SiteAvailabilityController's
 * messages (dashboard.Site_Availability_*).
 * Both read them without auto-creating rows. The approved Arabic texts of §7.1–§7.3 (override warning, maintenance
 * and launch warnings, confirmation, deleted admin) are used as written there. The ability labels belong to
 * AdminAbilityTranslationSeeder.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the old defaults listed in CORRECTIONS are
 * replaced only while a row still holds them exactly; nothing is deleted. Not called from DatabaseSeeder.
 */
class AdminSiteAvailabilityTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Card.
        'dashboard.Site_Availability_Title' => ['ar' => 'حالة الموقع', 'en' => 'Site availability'],
        'dashboard.Site_Availability_Intro' => [
            'ar' => 'تحكّم في ظهور المتجر للزوار: مفتوح، أو قريبًا، أو تحت الصيانة.',
            'en' => 'Control whether the storefront is open, coming soon or under maintenance.',
        ],
        'dashboard.Site_Availability_Effective' => ['ar' => 'الحالة الفعلية الآن', 'en' => 'Current state'],
        'dashboard.Site_Availability_Stored' => ['ar' => 'الحالة المحفوظة', 'en' => 'Saved state'],
        'dashboard.Site_Availability_Last_Change' => ['ar' => 'آخر تغيير', 'en' => 'Last change'],
        'dashboard.Site_Availability_Palestine_Time' => ['ar' => 'بتوقيت فلسطين', 'en' => 'Palestine time'],
        'dashboard.Site_Availability_Deleted_Admin' => ['ar' => 'مشرف محذوف', 'en' => 'Deleted admin'],
        'dashboard.Site_Availability_Source_Environment' => ['ar' => 'مفروضة من إعدادات الخادم', 'en' => 'Forced by the server environment'],
        'dashboard.Site_Availability_Source_Database' => ['ar' => 'محفوظة من لوحة التحكم', 'en' => 'Set in the dashboard'],
        'dashboard.Site_Availability_Source_Default' => ['ar' => 'الافتراضية (لم تُحفظ حالة بعد)', 'en' => 'Default (no state saved yet)'],
        'dashboard.Site_Availability_State_Open' => ['ar' => 'مفتوح', 'en' => 'Open'],
        'dashboard.Site_Availability_State_Coming_Soon' => ['ar' => 'قريبًا', 'en' => 'Coming soon'],
        'dashboard.Site_Availability_State_Maintenance' => ['ar' => 'صيانة', 'en' => 'Maintenance'],
        'dashboard.Site_Availability_Hint_Open' => ['ar' => 'المتجر متاح للجميع كالمعتاد.', 'en' => 'The store is available to everyone as usual.'],
        'dashboard.Site_Availability_Hint_Coming_Soon' => [
            'ar' => 'تظهر صفحة «قريبًا» للزوار والعملاء ومندوبي التوصيل، بينما يستطيع البائعون المعتمدون الوصول إلى لوحاتهم، وتبقى لوحة الإدارة متاحة.',
            'en' => 'Visitors, customers and delivery drivers see the Coming Soon page, while approved vendors can access their dashboards and the admin dashboard remains available.',
        ],
        'dashboard.Site_Availability_Hint_Maintenance' => [
            'ar' => 'تظهر صفحة الصيانة للزوار والعملاء والبائعين ومندوبي التوصيل (رمز 503)، بينما تبقى لوحة الإدارة متاحة للمسؤولين المصرّح لهم.',
            'en' => 'Visitors, customers, vendors and delivery drivers see the maintenance page (HTTP 503), while the admin dashboard remains accessible to authorized administrators.',
        ],
        'dashboard.Site_Availability_Warning_Coming_Soon' => [
            'ar' => 'عند حفظ وضع «قريبًا»، سيتوقف وصول العملاء إلى المتجر، ولن يتمكنوا من تسجيل الدخول أو إتمام الطلبات حتى إعادة فتحه. كما سيتعذر على مندوبي التوصيل الوصول إلى لوحاتهم.',
            'en' => 'Saving Coming Soon mode will block customers from accessing the store, signing in or placing orders until it is reopened. Delivery drivers will also be unable to access their dashboards.',
        ],
        'dashboard.Site_Availability_Override_Warning' => [
            'ar' => 'حالة الموقع مفروضة من إعدادات الخادم (HIRFAH_SITE_AVAILABILITY=:value) وتتقدّم على الإعداد المحفوظ هنا. التغييرات تُحفظ لكنها لا تُطبَّق حتى تُعاد القيمة إلى auto.',
            'en' => 'The site state is forced by the server settings (HIRFAH_SITE_AVAILABILITY=:value) and takes precedence over the state saved here. Changes are saved but do not apply until the value is set back to auto.',
        ],
        'dashboard.Site_Availability_Migration_Pending' => [
            'ar' => 'لم يُطبَّق ترحيل قاعدة البيانات الخاص بحالة الموقع بعد، لذلك لا يمكن حفظ الحالة. اطلب من المسؤول التقني تشغيل الترحيل.',
            'en' => 'The site availability database migration has not been applied yet, so the state cannot be saved. Ask the technical administrator to run the migration.',
        ],
        'dashboard.Site_Availability_Choose' => ['ar' => 'اختر حالة الموقع', 'en' => 'Choose the site state'],
        'dashboard.Site_Availability_Warning_Maintenance' => [
            'ar' => 'سيظهر للزوار والعملاء والبائعين ومندوبي التوصيل أن الموقع تحت الصيانة (رمز 503)، ولن يستطيعوا تسجيل الدخول أو إتمام أي عملية حتى تعيد فتح الموقع.',
            'en' => 'Visitors, customers, vendors and delivery drivers will see that the site is under maintenance (503) and cannot sign in or complete anything until you reopen the site.',
        ],
        'dashboard.Site_Availability_Warning_Launch' => ['ar' => 'سيصبح المتجر متاحًا للجميع فور الحفظ.', 'en' => 'The store will be open to everyone as soon as you save.'],
        'dashboard.Site_Availability_Confirm' => ['ar' => 'أؤكد تغيير حالة الموقع', 'en' => 'I confirm changing the site state'],
        'dashboard.Site_Availability_Save' => ['ar' => 'حفظ حالة الموقع', 'en' => 'Save site state'],

        // Dashboard header badge (Phase 3C); it also reuses the state names and the title above.
        'dashboard.Site_Availability_Badge_Override' => ['ar' => 'مفروضة من الخادم', 'en' => 'Server override'],

        // SiteAvailabilityController messages.
        'dashboard.Site_Availability_Migration_Pending_Save' => [
            'ar' => 'لم يُطبَّق ترحيل حالة الموقع بعد. لم يُحفظ شيء.',
            'en' => 'The site availability migration has not been applied yet. Nothing was saved.',
        ],
        'dashboard.Site_Availability_Invalid_State' => ['ar' => 'اختر إحدى حالات الموقع الثلاث.', 'en' => 'Choose one of the three site states.'],
        'dashboard.Site_Availability_Not_Confirmed' => [
            'ar' => 'لم يُؤكَّد التغيير. ضع علامة في مربع التأكيد للحفظ.',
            'en' => 'The change was not confirmed. Tick the confirmation box to save.',
        ],
        'dashboard.Site_Availability_Unchanged' => [
            'ar' => 'هذه هي الحالة المحفوظة بالفعل. لم يتغيّر شيء.',
            'en' => 'This is already the saved state. Nothing was changed.',
        ],
        'dashboard.Site_Availability_Saved' => ['ar' => 'حُفظت حالة الموقع.', 'en' => 'The site state was saved.'],
        'dashboard.Site_Availability_Saved_Overridden' => [
            'ar' => 'حُفظت الحالة، لكن إعدادات الخادم ما زالت تحدد حالة الموقع حتى تُعاد إلى auto.',
            'en' => 'The state was saved, but the server override still decides the site state until it is set back to auto.',
        ],
    ];

    /**
     * SA-FIX-4B: the two option hints as first shipped, replaced by the approved wording only while a row still holds
     * them exactly (an edited value is kept).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        [
            'key' => 'dashboard.Site_Availability_Hint_Coming_Soon',
            'locale' => 'ar',
            'from' => 'يرى الزوار صفحة «قريبًا»، ويستطيع البائعون المعتمدون استخدام لوحاتهم.',
            'to' => 'تظهر صفحة «قريبًا» للزوار والعملاء ومندوبي التوصيل، بينما يستطيع البائعون المعتمدون الوصول إلى لوحاتهم، وتبقى لوحة الإدارة متاحة.',
        ],
        [
            'key' => 'dashboard.Site_Availability_Hint_Coming_Soon',
            'locale' => 'en',
            'from' => 'Visitors see the Coming Soon page; approved vendors can still use their dashboards.',
            'to' => 'Visitors, customers and delivery drivers see the Coming Soon page, while approved vendors can access their dashboards and the admin dashboard remains available.',
        ],
        [
            'key' => 'dashboard.Site_Availability_Hint_Maintenance',
            'locale' => 'ar',
            'from' => 'يرى الجميع صفحة الصيانة (رمز 503)، بمن فيهم البائعون.',
            'to' => 'تظهر صفحة الصيانة للزوار والعملاء والبائعين ومندوبي التوصيل (رمز 503)، بينما تبقى لوحة الإدارة متاحة للمسؤولين المصرّح لهم.',
        ],
        [
            'key' => 'dashboard.Site_Availability_Hint_Maintenance',
            'locale' => 'en',
            'from' => 'Everyone sees the maintenance page (503), vendors included.',
            'to' => 'Visitors, customers, vendors and delivery drivers see the maintenance page (HTTP 503), while the admin dashboard remains accessible to authorized administrators.',
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
