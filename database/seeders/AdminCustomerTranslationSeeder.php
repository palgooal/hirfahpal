<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * CUS-UI-02: Arabic and English texts of the admin customers list and details page (customer-specific keys,
 * dashboard.Customers_*), the order status labels they show (dashboard.Orders_Status_*, named for reuse by the
 * orders pages) and the status-change message of CustomerManagementController (dashboard.Customer_status_updated).
 * The pages read them without auto-creating rows. Shared keys (Status, Phone, Active, …) are not touched; the
 * approved keys reused as they are (Orders, Reviews, Actions, City, Pagination_*, Nav_Customers) are not seeded here.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the old default listed in CORRECTIONS is
 * replaced only while unchanged. Not called from DatabaseSeeder.
 */
class AdminCustomerTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Customers list.
        'dashboard.Customers_Intro' => [
            'ar' => 'تابع حسابات العملاء في المتجر: ابحث عنهم واطّلع على طلباتهم وحالة حساباتهم.',
            'en' => 'Follow customer accounts in the store: find them and check their orders and account status.',
        ],
        'dashboard.Customers_Search_Label' => ['ar' => 'البحث في العملاء', 'en' => 'Search customers'],
        'dashboard.Customers_Search_Placeholder' => ['ar' => 'الاسم أو البريد الإلكتروني أو رقم الهاتف', 'en' => 'Name, email or phone'],
        'dashboard.Customers_Filter_Status' => ['ar' => 'حالة الحساب', 'en' => 'Account status'],
        'dashboard.Customers_Filter_All' => ['ar' => 'كل الحالات', 'en' => 'All statuses'],
        'dashboard.Customers_Apply' => ['ar' => 'تطبيق', 'en' => 'Apply'],
        'dashboard.Customers_Clear' => ['ar' => 'مسح الفلاتر', 'en' => 'Clear filters'],
        'dashboard.Customers_Column_Customer' => ['ar' => 'العميل', 'en' => 'Customer'],
        'dashboard.Customers_Column_Phone' => ['ar' => 'رقم الهاتف', 'en' => 'Phone'],
        'dashboard.Customers_Column_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Customers_View' => ['ar' => 'عرض', 'en' => 'View'],
        'dashboard.Customers_Status_Active' => ['ar' => 'نشط', 'en' => 'Active'],
        'dashboard.Customers_Status_Pending' => ['ar' => 'بانتظار التفعيل', 'en' => 'Pending activation'],
        'dashboard.Customers_Status_Blocked' => ['ar' => 'محظور', 'en' => 'Blocked'],
        'dashboard.Customers_Empty_Title' => ['ar' => 'لا يوجد عملاء بعد', 'en' => 'No customers yet'],
        'dashboard.Customers_Empty_Text' => [
            'ar' => 'يظهر العملاء هنا عند تسجيلهم في المتجر.',
            'en' => 'Customers appear here once they sign up in the store.',
        ],
        'dashboard.Customers_No_Results_Title' => ['ar' => 'لا يوجد عملاء مطابقون', 'en' => 'No matching customers'],
        'dashboard.Customers_No_Results_Text' => ['ar' => 'جرّب كلمة بحث أخرى أو امسح الفلاتر.', 'en' => 'Try another search or clear the filters.'],

        // Customer details.
        'dashboard.Customers_Show_Back' => ['ar' => 'العودة إلى العملاء', 'en' => 'Back to customers'],
        'dashboard.Customers_Show_Contact' => ['ar' => 'بيانات التواصل', 'en' => 'Contact details'],
        'dashboard.Customers_Show_Email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
        'dashboard.Customers_Status_Title' => ['ar' => 'حالة الحساب', 'en' => 'Account status'],
        'dashboard.Customers_Status_Label' => ['ar' => 'الحالة الجديدة', 'en' => 'New status'],
        'dashboard.Customers_Status_Hint' => [
            'ar' => 'العميل المحظور أو بانتظار التفعيل لا يستطيع تسجيل الدخول، ويُسجَّل خروجه من جلسته الحالية.',
            'en' => 'A blocked or pending customer cannot sign in and is signed out of the current session.',
        ],
        'dashboard.Customers_Status_Save' => ['ar' => 'حفظ الحالة', 'en' => 'Save status'],
        'dashboard.Customers_Recent_Orders' => ['ar' => 'آخر الطلبات', 'en' => 'Recent orders'],
        'dashboard.Customers_Order_Number' => ['ar' => 'رقم الطلب', 'en' => 'Order number'],
        'dashboard.Customers_Order_Total' => ['ar' => 'الإجمالي', 'en' => 'Total'],
        'dashboard.Customers_No_Orders' => ['ar' => 'لا طلبات لهذا العميل.', 'en' => 'This customer has no orders.'],
        'dashboard.Customers_Addresses' => ['ar' => 'العناوين', 'en' => 'Addresses'],
        'dashboard.Customers_Address_Default' => ['ar' => 'العنوان الافتراضي', 'en' => 'Default address'],
        'dashboard.Customers_No_Addresses' => ['ar' => 'لا عناوين محفوظة.', 'en' => 'No saved addresses.'],
        'dashboard.Customers_Review_Rating' => ['ar' => 'التقييم: :rating من 5', 'en' => 'Rating: :rating of 5'],
        'dashboard.Customers_No_Reviews' => ['ar' => 'لا تقييمات من هذا العميل.', 'en' => 'This customer has no reviews.'],

        // Order statuses (the values of orders.status), for the customer's recent orders and later the orders pages.
        'dashboard.Orders_Status_Pending' => ['ar' => 'قيد الانتظار', 'en' => 'Pending'],
        'dashboard.Orders_Status_Processing' => ['ar' => 'قيد التنفيذ', 'en' => 'Processing'],
        'dashboard.Orders_Status_Completed' => ['ar' => 'مكتمل', 'en' => 'Completed'],
        'dashboard.Orders_Status_Partially_Cancelled' => ['ar' => 'ملغى جزئيًا', 'en' => 'Partially cancelled'],
        'dashboard.Orders_Status_Cancelled' => ['ar' => 'ملغى', 'en' => 'Cancelled'],

        // Read by CustomerManagementController::updateStatus() with t() (auto-create on): seeding it first keeps the
        // Arabic row from being created with the English default (see CORRECTIONS). The English value is that default.
        'dashboard.Customer_status_updated' => ['ar' => 'تم تحديث حالة العميل.', 'en' => 'Customer status updated successfully.'],
    ];

    /**
     * Earlier default texts that are no longer correct. Each row is replaced only while it still holds the exact
     * old default; any other value is an edit and is kept (same mechanism as AdminProductTranslationSeeder).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        [
            'key' => 'dashboard.Customer_status_updated',
            'locale' => 'ar',
            'from' => 'Customer status updated successfully.',
            'to' => 'تم تحديث حالة العميل.',
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
