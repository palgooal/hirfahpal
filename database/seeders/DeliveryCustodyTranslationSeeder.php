<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * VEN-BE-020 (Release A): Arabic and English texts of the delivery custody and return confirmation workflow, seeded
 * before that workflow is built (Release B), so its pages never depend on t() creating the rows: the two new ability
 * labels of data/abilities.php, the admin vendor order page (reassignment restrictions, custody, failure recording,
 * the exceptional return confirmation with its evidence, the delivery history) and the vendor order details page (the
 * pending return and the confirmation of physical possession). Release B must use these keys with these English values
 * as the t() defaults. Nothing reads the keys yet except the ability labels in the admins form.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; nothing is deleted; no other key is touched.
 * Not called from DatabaseSeeder.
 *
 * The admins form reads the ability labels with auto-create on: opened in Arabic before this seeder runs, t() inserts
 * an Arabic row holding the English default. Such a placeholder is repaired (see repairArabicPlaceholders(), the rule
 * of AdminVendorOrderTranslationSeeder); any other row is kept.
 */
class DeliveryCustodyTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Ability labels (data/abilities.php, vendor-orders group).
        'dashboard.Record_Delivery_Failure' => ['ar' => 'تسجيل تعذّر التوصيل', 'en' => 'Record Delivery Failure'],
        'dashboard.Confirm_Delivery_Return' => ['ar' => 'تأكيد استرجاع البضاعة', 'en' => 'Confirm Returned Goods'],

        // Admin vendor order page: why a driver cannot be reassigned.
        'dashboard.Custody_Reassign_After_Pickup' => [
            'ar' => 'استلم المندوب هذا الطلب بالفعل، لذلك لا يمكن إسناده إلى مندوب آخر. سجّل تعذّر التوصيل أولًا.',
            'en' => 'The driver has already picked up this order, so it cannot be reassigned. Record a delivery failure first.',
        ],
        'dashboard.Custody_Reassign_Unresolved' => [
            'ar' => 'لم يُؤكَّد بعد رجوع البضاعة إلى البائع، لذلك لا يمكن إسناد هذا الطلب إلى مندوب آخر.',
            'en' => 'The return of the goods to the vendor has not been confirmed yet, so this order cannot be reassigned.',
        ],
        'dashboard.Custody_Reassign_Inconsistent' => [
            'ar' => 'سجلات توصيل هذا الطلب غير متطابقة، لذلك لا يمكن إسناده إلى مندوب آخر. راجع سجل التوصيل.',
            'en' => 'The delivery records of this order do not match, so it cannot be reassigned. Review its delivery history.',
        ],
        'dashboard.Custody_Reassign_Same_Driver' => ['ar' => 'هذا المندوب مُسنَد إلى هذا الطلب بالفعل.', 'en' => 'This driver is already assigned to this order.'],

        // Custody of the goods (kept apart from the delivery status).
        'dashboard.Custody_Status' => ['ar' => 'عهدة البضاعة', 'en' => 'Goods custody'],
        'dashboard.Custody_With_Vendor' => ['ar' => 'لدى البائع', 'en' => 'With the vendor'],
        'dashboard.Custody_With_Driver' => ['ar' => 'لدى المندوب', 'en' => 'With the driver'],
        'dashboard.Custody_Return_Pending' => ['ar' => 'بانتظار تأكيد الإرجاع', 'en' => 'Return pending confirmation'],
        'dashboard.Custody_Returned' => ['ar' => 'أُعيدت إلى البائع', 'en' => 'Returned to the vendor'],
        'dashboard.Custody_Inconsistent' => ['ar' => 'تحتاج إلى مراجعة', 'en' => 'Needs review'],

        // Recording a failed delivery or an unreachable driver (D4).
        'dashboard.Delivery_Failure_Kind' => ['ar' => 'ما الذي حدث', 'en' => 'What happened'],
        'dashboard.Delivery_Failure_Kind_Failed' => ['ar' => 'تعذّر التوصيل', 'en' => 'Delivery failed'],
        'dashboard.Delivery_Failure_Kind_Unreachable' => ['ar' => 'تعذّر الوصول إلى المندوب', 'en' => 'Driver unreachable'],
        'dashboard.Delivery_Failure_Not_Returned_Notice' => [
            'ar' => 'تسجيل التعذّر لا يعني أن البضاعة أُعيدت. لا يمكن إسناد الطلب إلى مندوب آخر حتى يُؤكَّد إرجاعها.',
            'en' => 'Recording a failure does not mean the goods were returned. The order cannot be reassigned until the return is confirmed.',
        ],
        'dashboard.Delivery_Failure_Recorded' => ['ar' => 'تم تسجيل تعذّر التوصيل.', 'en' => 'The delivery failure was recorded.'],

        // The required reason.
        'dashboard.Custody_Reason' => ['ar' => 'السبب', 'en' => 'Reason'],
        'dashboard.Custody_Reason_Required' => ['ar' => 'أدخل السبب.', 'en' => 'Enter the reason.'],
        'dashboard.Custody_Reason_Too_Vague' => [
            'ar' => 'اشرح ما حدث في 20 حرفًا و3 كلمات على الأقل.',
            'en' => 'Explain what happened in at least 20 characters and 3 words.',
        ],

        // The exceptional admin return confirmation with its evidence (D3).
        'dashboard.Return_Admin_Exception_Notice' => [
            'ar' => 'استخدم هذا فقط عندما يتعذّر على البائع تأكيد الإرجاع، فالبائع هو من يؤكده عادةً.',
            'en' => 'Use this only when the vendor cannot confirm the return. The vendor normally confirms it.',
        ],
        'dashboard.Return_Evidence_Type' => ['ar' => 'نوع الدليل', 'en' => 'Evidence type'],
        'dashboard.Return_Evidence_Vendor_Written' => ['ar' => 'تأكيد مكتوب من البائع', 'en' => 'Written confirmation from the vendor'],
        'dashboard.Return_Evidence_Signed_Receipt' => ['ar' => 'إيصال تسليم موقّع', 'en' => 'Signed handover receipt'],
        'dashboard.Return_Evidence_Photo' => ['ar' => 'صورة أو تسجيل كاميرا', 'en' => 'Photo or camera recording'],
        'dashboard.Return_Evidence_Joint_Statement' => ['ar' => 'إفادة مشتركة من البائع والمندوب', 'en' => 'Joint statement by the vendor and the driver'],
        'dashboard.Return_Evidence_Reference' => ['ar' => 'مرجع الدليل', 'en' => 'Evidence reference'],
        'dashboard.Return_Evidence_Reference_Help' => [
            'ar' => 'رقم الرسالة أو رقم الإيصال أو مكان حفظ السجل.',
            'en' => 'A message ID, receipt number or where the record is kept.',
        ],
        'dashboard.Return_Evidence_Reference_Same_As_Reason' => [
            'ar' => 'يجب أن يحدّد مرجع الدليل الدليلَ نفسه، لا أن يكرّر السبب.',
            'en' => 'The evidence reference must identify the evidence, not repeat the reason.',
        ],
        'dashboard.Return_Received_By' => ['ar' => 'المستلِم (لدى البائع)', 'en' => 'Received by (at the vendor)'],
        'dashboard.Return_Received_At' => ['ar' => 'وقت الاستلام', 'en' => 'Received at'],
        'dashboard.Return_Received_At_Invalid' => [
            'ar' => 'لا يمكن أن يكون وقت الاستلام في المستقبل أو قبل تعذّر التوصيل.',
            'en' => 'The time of receipt cannot be in the future or before the delivery failure.',
        ],
        'dashboard.Return_Confirmed' => ['ar' => 'تم تأكيد إرجاع البضاعة.', 'en' => 'The return of the goods was confirmed.'],

        // A refused custody action.
        'dashboard.Custody_Action_Not_Allowed' => [
            'ar' => 'هذا الإجراء غير متاح في حالة التوصيل الحالية للطلب.',
            'en' => 'This action is not available in the current delivery state of the order.',
        ],

        // The delivery history (order_status_histories events).
        'dashboard.Delivery_History' => ['ar' => 'سجل التوصيل', 'en' => 'Delivery history'],
        'dashboard.Delivery_History_Empty' => ['ar' => 'لا توجد أحداث توصيل بعد.', 'en' => 'No delivery events yet.'],
        'dashboard.Delivery_History_Driver_Assigned' => ['ar' => 'تم إسناد مندوب', 'en' => 'Driver assigned'],
        'dashboard.Delivery_History_Driver_Reassigned' => ['ar' => 'تمت إعادة إسناد المندوب', 'en' => 'Driver reassigned'],
        'dashboard.Delivery_History_Accepted' => ['ar' => 'قبِلها المندوب', 'en' => 'Accepted by the driver'],
        'dashboard.Delivery_History_Picked_Up' => ['ar' => 'استلمها المندوب', 'en' => 'Picked up by the driver'],
        'dashboard.Delivery_History_Delivered' => ['ar' => 'سلّمها المندوب', 'en' => 'Delivered by the driver'],
        'dashboard.Delivery_History_Failed_By_Driver' => ['ar' => 'أبلغ المندوب عن تعذّر التوصيل', 'en' => 'Delivery failure reported by the driver'],
        'dashboard.Delivery_History_Failure_Recorded' => ['ar' => 'سجّلت الإدارة تعذّر التوصيل', 'en' => 'Delivery failure recorded by administration'],
        'dashboard.Delivery_History_Return_By_Vendor' => ['ar' => 'أكّد البائع إرجاع البضاعة', 'en' => 'Return confirmed by the vendor'],
        'dashboard.Delivery_History_Return_By_Admin' => ['ar' => 'أكّدت الإدارة إرجاع البضاعة', 'en' => 'Return confirmed by administration'],
        'dashboard.Delivery_History_Previous_Driver' => ['ar' => 'المندوب السابق', 'en' => 'Previous driver'],
        'dashboard.Delivery_History_New_Driver' => ['ar' => 'المندوب الجديد', 'en' => 'New driver'],

        // Vendor order details page: the pending return and the confirmation of physical possession (D2, D3).
        'vendor.Order_Return_Pending' => ['ar' => 'بانتظار الإرجاع', 'en' => 'Return pending'],
        'vendor.Order_Return_Pending_Description' => [
            'ar' => 'تعذّر توصيل هذا الطلب. أكّد أدناه بعد أن تعود البضاعة فعليًا إلى متجرك.',
            'en' => 'Delivery of this order failed. Confirm below once the goods are physically back at your store.',
        ],
        'vendor.Order_Return_Driver' => ['ar' => 'المندوب', 'en' => 'Driver'],
        'vendor.Order_Return_Driver_Unknown' => ['ar' => 'غير معروف', 'en' => 'Unknown'],
        'vendor.Order_Return_Failed_At' => ['ar' => 'تاريخ تعذّر التوصيل', 'en' => 'Delivery failed on'],
        'vendor.Order_Return_Attestation' => ['ar' => 'البضاعة موجودة فعليًا في حوزتنا في المتجر.', 'en' => 'The goods are physically in our possession at the store.'],
        'vendor.Order_Return_Attestation_Required' => ['ar' => 'أكّد أن البضاعة موجودة فعليًا في حوزتك.', 'en' => 'Confirm that the goods are physically in your possession.'],
        'vendor.Order_Return_Note' => ['ar' => 'ملاحظة (اختيارية)', 'en' => 'Note (optional)'],
        'vendor.Order_Return_Confirm' => ['ar' => 'تأكيد استلام البضاعة', 'en' => 'Confirm goods received'],
        'vendor.Order_Return_Confirmed' => ['ar' => 'تم تأكيد استلام البضاعة المُعادة.', 'en' => 'Receipt of the returned goods was confirmed.'],
        'vendor.Order_Return_Confirmed_By_Admin' => ['ar' => 'أكّدت الإدارة إرجاع البضاعة بتاريخ :date', 'en' => 'Return confirmed by administration on :date'],
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

    /**
     * An Arabic row t() auto-created before this seeder ran holds exactly the English default (the en value here, the
     * same text as the default in the code) and was never changed since (created_at = updated_at; an edit through the
     * translations page saves a different value and moves updated_at). Only such a row is replaced by the Arabic text.
     * A row with any other value, or one updated since it was created, is kept.
     */
    private function repairArabicPlaceholders(): void
    {
        foreach (self::TRANSLATIONS as $key => $values) {
            // Compared in PHP: MySQL's collation is case-insensitive, so a SQL "=" would also match an edit that
            // differs only in case.
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
