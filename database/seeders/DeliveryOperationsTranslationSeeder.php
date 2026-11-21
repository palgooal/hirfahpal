<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * E2-MVP batch 04: Arabic and English texts of the admin delivery operations: cancelling a vendor order before pickup,
 * recording a failed delivery after pickup, and the tasks of a driver who is no longer eligible (the vendor order page
 * and the delivery driver page). The pages read them with t() and these English values as defaults.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; nothing is deleted. Not called from
 * DatabaseSeeder. Opened in Arabic before this seeder runs, t() inserts an Arabic row holding the English default; such
 * a placeholder (never edited since) is repaired, as AdminVendorOrderTranslationSeeder does.
 */
class DeliveryOperationsTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Delivery_Ops_Cancel_Title' => [
            'ar' => 'إلغاء الطلب الفرعي',
            'en' => 'Cancel this vendor order',
        ],
        'dashboard.Delivery_Ops_Cancel_Notice' => [
            'ar' => 'يُلغى الطلب الفرعي وتنتهي مهمة التوصيل النشطة. لا يُعاد المخزون المخصوم بعد قبول البائع تلقائيًا، ولا يُنفَّذ أي استرداد مالي تلقائي.',
            'en' => 'The vendor order is cancelled and its active delivery task ends. Stock deducted after the vendor accepted is not restored automatically, and no refund is made automatically.',
        ],
        'dashboard.Delivery_Ops_Cancel_Reason' => [
            'ar' => 'سبب الإلغاء',
            'en' => 'Cancellation reason',
        ],
        'dashboard.Delivery_Ops_Cancel_Submit' => [
            'ar' => 'إلغاء الطلب الفرعي',
            'en' => 'Cancel vendor order',
        ],
        'dashboard.Delivery_Ops_Cancelled_Success' => [
            'ar' => 'تم إلغاء الطلب الفرعي.',
            'en' => 'The vendor order was cancelled.',
        ],
        'dashboard.Delivery_Ops_Cancelled_By_Admin' => [
            'ar' => 'ألغته الإدارة',
            'en' => 'Cancelled by administration',
        ],
        'dashboard.Delivery_Ops_Failure_Note' => [
            'ar' => 'ملاحظة الإدارة',
            'en' => 'Administration note',
        ],
        'dashboard.Delivery_Ops_Driver_Not_Eligible' => [
            'ar' => 'لم يعد هذا المندوب معتمدًا أو نشطًا. أعد إسناد المهمة قبل الاستلام، أو سجّل التعذر وأكّد الاسترجاع بعد الاستلام.',
            'en' => 'This driver is no longer approved or active. Reassign the task before pickup, or record the failure and confirm the return after pickup.',
        ],
        'dashboard.Delivery_Ops_Active_Tasks' => [
            'ar' => 'المهام النشطة وفي العهدة',
            'en' => 'Active and in-custody tasks',
        ],
        'dashboard.Delivery_Ops_No_Active_Tasks' => [
            'ar' => 'لا توجد مهام نشطة.',
            'en' => 'No active tasks.',
        ],
        // E2-MVP batch 05: online payment, the vendor order created by an admin, and the drivers' cash custody.
        'dashboard.Delivery_Ops_Awaiting_Payment' => [
            'ar' => 'هذا الطلب مدفوع إلكترونيًا ولم يُسجَّل دفعه بعد، لذلك لا يمكن إسناده أو تسليمه.',
            'en' => 'This online order is not marked paid yet, so it cannot be assigned or delivered.',
        ],
        'dashboard.Delivery_Ops_Parent_Closed' => [
            'ar' => 'الطلب الرئيسي مغلق، لذلك لا يمكن إضافة طلب فرعي إليه.',
            'en' => 'The parent order is closed, so no vendor order can be added to it.',
        ],
        'dashboard.Delivery_Ops_Total_Mismatch' => [
            'ar' => 'يجب أن يساوي الإجمالي المجموع الفرعي مضافًا إليه رسوم التوصيل.',
            'en' => 'The total must equal the subtotal plus the delivery fee.',
        ],
        'dashboard.Delivery_Ops_Total_Exceeds_Order' => [
            'ar' => 'ستتجاوز الطلبات الفرعية لهذا الطلب إجماليه.',
            'en' => 'The vendor orders of this order would exceed its grand total.',
        ],
        'dashboard.Delivery_Ops_View_Cash' => [
            'ar' => 'عرض عهدة المندوبين النقدية',
            'en' => 'View Driver Cash',
        ],
        'dashboard.Delivery_Ops_Settle_Cash' => [
            'ar' => 'تسجيل تسويات المندوبين النقدية',
            'en' => 'Record Driver Cash Settlements',
        ],
        'dashboard.Delivery_Ops_Cash_Custody' => [
            'ar' => 'العهدة النقدية',
            'en' => 'Cash custody',
        ],
        'dashboard.Delivery_Ops_Cash_Collected' => [
            'ar' => 'المحصّل عند التسليم',
            'en' => 'Collected on delivery',
        ],
        'dashboard.Delivery_Ops_Cash_Settled' => [
            'ar' => 'المستلم من المندوب',
            'en' => 'Received by the platform',
        ],
        'dashboard.Delivery_Ops_Cash_Balance' => [
            'ar' => 'الرصيد لدى المندوب',
            'en' => 'Balance held by the driver',
        ],
        'dashboard.Delivery_Ops_Cash_Amount_Received' => [
            'ar' => 'المبلغ المستلم',
            'en' => 'Amount received',
        ],
        'dashboard.Delivery_Ops_Cash_Record' => [
            'ar' => 'تسجيل المبلغ المستلم',
            'en' => 'Record cash received',
        ],
        'dashboard.Delivery_Ops_Cash_Settlement' => [
            'ar' => 'مبلغ مستلم',
            'en' => 'Cash received',
        ],
        'dashboard.Delivery_Ops_Cash_Reversal' => [
            'ar' => 'قيد عكسي',
            'en' => 'Reversal',
        ],
        'dashboard.Delivery_Ops_Cash_Reversal_Reason' => [
            'ar' => 'سبب التصحيح',
            'en' => 'Reason for the correction',
        ],
        'dashboard.Delivery_Ops_Cash_Reverse' => [
            'ar' => 'عكس القيد',
            'en' => 'Reverse',
        ],
        'dashboard.Delivery_Ops_Cash_Reversed' => [
            'ar' => 'تم عكسه',
            'en' => 'Reversed',
        ],
        'dashboard.Delivery_Ops_Settlement_Exceeds' => [
            'ar' => 'يجب أن يكون المبلغ أكبر من صفر وألا يتجاوز الرصيد لدى المندوب.',
            'en' => 'The amount must be more than zero and not above the balance the driver holds.',
        ],
        'dashboard.Delivery_Ops_Settlement_Recorded' => [
            'ar' => 'تم تسجيل المبلغ المستلم من المندوب.',
            'en' => 'The cash received from the driver was recorded.',
        ],
        'dashboard.Delivery_Ops_Settlement_Reversed' => [
            'ar' => 'تم عكس القيد.',
            'en' => 'The settlement was reversed.',
        ],
        'dashboard.Delivery_Ops_Reversal_Not_Allowed' => [
            'ar' => 'يمكن عكس قيد مبلغ مستلم لم يُعكس من قبل فقط.',
            'en' => 'Only a settlement not reversed yet can be reversed.',
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

        $this->repairArabicPlaceholders();
    }

    /** An Arabic row t() auto-created with the English default and never edited since is replaced by the Arabic text. */
    private function repairArabicPlaceholders(): void
    {
        foreach (self::TRANSLATIONS as $key => $values) {
            // Compared in PHP: MySQL's collation is case-insensitive.
            $row = TranslationValue::query()->where('key', $key)->where('locale', 'ar')->first();

            if (! $row || $row->value !== $values['en'] || ! $row->created_at?->equalTo($row->updated_at)) {
                continue;
            }

            $changed = TranslationValue::query()
                ->whereKey($row->getKey())
                ->where('updated_at', $row->updated_at)
                ->update(['value' => $values['ar'], 'updated_at' => now()]);

            if ($changed > 0) {
                cache()->forget("translation.ar.{$key}");
            }
        }
    }
}
