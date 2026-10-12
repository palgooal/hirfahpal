<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English texts of the admin dashboard home (resources/views/dashboard/admin/index.blade.php;
 * AD-UI-DASH-02). t() had auto-created these keys with the code default, so most Arabic rows hold English
 * text (and Admin_Dashboard's English row Arabic text). Twelve keys are shared with other admin pages
 * (Categories, Products, Customers, Delivery_Drivers, Orders, Store, Owner, City, Actions, Order, Vendor,
 * Needs_attention): correcting them corrects those pages too (approved by the project owner).
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the auto-created values listed in
 * CORRECTIONS are replaced only while they still hold exactly that value. dashboard.Admin_Control_Center is
 * owned by AdminHeaderTranslationSeeder and is not touched here. Not called from DatabaseSeeder.
 */
class AdminHomeTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Header card and breadcrumb.
        'dashboard.Admin_Dashboard' => ['ar' => 'لوحة الإدارة', 'en' => 'Admin panel'],
        'dashboard.Hirfah_Operations' => ['ar' => 'عمليات حرفة', 'en' => 'HIRFAH Operations'],
        'dashboard.Admin_Control_Center_Description' => [
            'ar' => 'تابِع موافقات البائعين، ومهل ردّ البائعين على الطلبات، وتعيين التوصيل يدويًا.',
            'en' => 'Monitor vendor approvals, vendor order response windows, and manual delivery assignment.',
        ],

        // Figures.
        'dashboard.Pending_Vendors' => ['ar' => 'بائعون بانتظار الموافقة', 'en' => 'Pending Vendors'],
        'dashboard.Active_Vendors' => ['ar' => 'البائعون النشطون', 'en' => 'Active Vendors'],
        'dashboard.Pending_Vendor_Orders' => ['ar' => 'طلبات فرعية معلّقة', 'en' => 'Pending Vendor Orders'],
        'dashboard.Needs_attention' => ['ar' => 'تحتاج متابعة', 'en' => 'Needs attention'],
        'dashboard.Active_Products' => ['ar' => 'المنتجات النشطة', 'en' => 'Active Products'],
        'dashboard.Low_Stock' => ['ar' => 'مخزون منخفض', 'en' => 'Low Stock'],
        'dashboard.Pending_Drivers' => ['ar' => 'مندوبون بانتظار الموافقة', 'en' => 'Pending Drivers'],
        'dashboard.Open_Disputes' => ['ar' => 'نزاعات مفتوحة', 'en' => 'Open Disputes'],

        // Quick actions.
        'dashboard.Admin_Quick_Actions' => ['ar' => 'إجراءات سريعة', 'en' => 'Admin Quick Actions'],
        'dashboard.Categories' => ['ar' => 'التصنيفات', 'en' => 'Categories'],
        'dashboard.Products' => ['ar' => 'المنتجات', 'en' => 'Products'],
        'dashboard.Customers' => ['ar' => 'العملاء', 'en' => 'Customers'],
        'dashboard.Delivery_Drivers' => ['ar' => 'مندوبو التوصيل', 'en' => 'Delivery Drivers'],
        'dashboard.Orders' => ['ar' => 'الطلبات', 'en' => 'Orders'],
        'dashboard.Pending_Reviews' => ['ar' => 'تقييمات بانتظار المراجعة', 'en' => 'Pending Reviews'],
        'dashboard.Open_Returns' => ['ar' => 'طلبات استرجاع مفتوحة', 'en' => 'Open Returns'],

        // Vendors waiting review.
        'dashboard.Vendors_waiting_review' => ['ar' => 'بائعون بانتظار المراجعة', 'en' => 'Vendors Waiting Review'],
        'dashboard.View_all' => ['ar' => 'عرض الكل', 'en' => 'View all'],
        'dashboard.Store' => ['ar' => 'المتجر', 'en' => 'Store'],
        'dashboard.Owner' => ['ar' => 'المالك', 'en' => 'Owner'],
        'dashboard.City' => ['ar' => 'المدينة', 'en' => 'City'],
        'dashboard.Actions' => ['ar' => 'الإجراءات', 'en' => 'Actions'],
        'dashboard.Review' => ['ar' => 'مراجعة', 'en' => 'Review'],
        'dashboard.No_pending_vendors' => ['ar' => 'لا يوجد بائعون بانتظار المراجعة', 'en' => 'No pending vendors'],

        // Response window alerts.
        'dashboard.Response_Window_Alerts' => ['ar' => 'تنبيهات مهلة الرد', 'en' => 'Response Window Alerts'],
        'dashboard.Open' => ['ar' => 'فتح', 'en' => 'Open'],
        'dashboard.Order' => ['ar' => 'الطلب', 'en' => 'Order'],
        'dashboard.Vendor' => ['ar' => 'البائع', 'en' => 'Vendor'],
        'dashboard.Due' => ['ar' => 'موعد الرد', 'en' => 'Due'],
        'dashboard.No_alerts_now' => ['ar' => 'لا توجد تنبيهات حاليًا', 'en' => 'No alerts now'],
    ];

    /**
     * The values t() auto-created (the code default in the other language). A row is replaced only while it
     * holds exactly this value; any other value is an edit and is kept.
     *
     * @var list<array{key: string, locale: string, from: string}>
     */
    public const CORRECTIONS = [
        ['key' => 'dashboard.Admin_Dashboard', 'locale' => 'en', 'from' => 'لوحة الإدارة'],
        ['key' => 'dashboard.Hirfah_Operations', 'locale' => 'ar', 'from' => 'HIRFAH Operations'],
        ['key' => 'dashboard.Admin_Control_Center_Description', 'locale' => 'ar', 'from' => 'Monitor vendor approvals, vendor order response windows, and manual delivery assignment.'],
        ['key' => 'dashboard.Pending_Vendors', 'locale' => 'ar', 'from' => 'Pending Vendors'],
        ['key' => 'dashboard.Active_Vendors', 'locale' => 'ar', 'from' => 'Active Vendors'],
        ['key' => 'dashboard.Pending_Vendor_Orders', 'locale' => 'ar', 'from' => 'Pending Vendor Orders'],
        ['key' => 'dashboard.Needs_attention', 'locale' => 'ar', 'from' => 'Needs attention'],
        ['key' => 'dashboard.Active_Products', 'locale' => 'ar', 'from' => 'Active Products'],
        ['key' => 'dashboard.Low_Stock', 'locale' => 'ar', 'from' => 'Low Stock'],
        ['key' => 'dashboard.Pending_Drivers', 'locale' => 'ar', 'from' => 'Pending Drivers'],
        ['key' => 'dashboard.Open_Disputes', 'locale' => 'ar', 'from' => 'Open Disputes'],
        ['key' => 'dashboard.Admin_Quick_Actions', 'locale' => 'ar', 'from' => 'Admin Quick Actions'],
        ['key' => 'dashboard.Categories', 'locale' => 'ar', 'from' => 'Categories'],
        ['key' => 'dashboard.Products', 'locale' => 'ar', 'from' => 'Products'],
        ['key' => 'dashboard.Customers', 'locale' => 'ar', 'from' => 'Customers'],
        ['key' => 'dashboard.Delivery_Drivers', 'locale' => 'ar', 'from' => 'Delivery Drivers'],
        ['key' => 'dashboard.Orders', 'locale' => 'ar', 'from' => 'Orders'],
        ['key' => 'dashboard.Pending_Reviews', 'locale' => 'ar', 'from' => 'Pending Reviews'],
        ['key' => 'dashboard.Open_Returns', 'locale' => 'ar', 'from' => 'Open Returns'],
        ['key' => 'dashboard.Vendors_waiting_review', 'locale' => 'ar', 'from' => 'Vendors Waiting Review'],
        ['key' => 'dashboard.View_all', 'locale' => 'ar', 'from' => 'View all'],
        ['key' => 'dashboard.Store', 'locale' => 'ar', 'from' => 'Store'],
        ['key' => 'dashboard.Owner', 'locale' => 'ar', 'from' => 'Owner'],
        ['key' => 'dashboard.City', 'locale' => 'ar', 'from' => 'City'],
        ['key' => 'dashboard.Actions', 'locale' => 'ar', 'from' => 'Actions'],
        ['key' => 'dashboard.No_pending_vendors', 'locale' => 'ar', 'from' => 'No pending vendors'],
        ['key' => 'dashboard.Response_Window_Alerts', 'locale' => 'ar', 'from' => 'Response Window Alerts'],
        ['key' => 'dashboard.Open', 'locale' => 'ar', 'from' => 'Open'],
        ['key' => 'dashboard.Order', 'locale' => 'ar', 'from' => 'Order'],
        ['key' => 'dashboard.Vendor', 'locale' => 'ar', 'from' => 'Vendor'],
        ['key' => 'dashboard.Due', 'locale' => 'ar', 'from' => 'Due'],
        ['key' => 'dashboard.No_alerts_now', 'locale' => 'ar', 'from' => 'No alerts now'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::TRANSLATIONS as $key => $values) {
            foreach ($values as $locale => $value) {
                // insertOrIgnore relies on unique(key, locale): an existing row is never overwritten, and two
                // concurrent runs cannot create a duplicate.
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
            $to = self::TRANSLATIONS[$correction['key']][$correction['locale']];

            // Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" alone would also replace an
            // edit that differs only in case.
            $row = TranslationValue::query()
                ->where('key', $correction['key'])
                ->where('locale', $correction['locale'])
                ->first();

            if (! $row || $row->value !== $correction['from']) {
                continue;
            }

            // Guarded update: this row only, and only if it still holds the old value (a concurrent edit made
            // after the read above is not overwritten).
            $changed = TranslationValue::query()
                ->whereKey($row->getKey())
                ->where('value', $correction['from'])
                ->update(['value' => $to, 'updated_at' => $now]);

            if ($changed > 0) {
                cache()->forget("translation.{$correction['locale']}.{$correction['key']}");
            }
        }
    }
}
