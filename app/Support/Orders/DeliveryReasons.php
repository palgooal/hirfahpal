<?php

namespace App\Support\Orders;

/**
 * E2-MVP batch 03: the fixed reasons a driver gives when declining a task before accepting it, or when a delivery fails.
 * The codes are stored (delivery_assignments.decline_code / failure_code); the labels are what the driver and the admin
 * read. "other" always needs a text. payment_failed covers a cash-on-delivery amount not collected in full.
 */
final class DeliveryReasons
{
    public const OTHER = 'other';

    public const DECLINE = [
        'out_of_area' => 'خارج منطقة عملي',
        'vehicle_unavailable' => 'المركبة غير متاحة',
        'schedule_conflict' => 'لدي التزام آخر في هذا الوقت',
        'personal_emergency' => 'ظرف طارئ',
        self::OTHER => 'أخرى',
    ];

    public const FAILURE = [
        'customer_unreachable' => 'تعذّر التواصل مع المستلم',
        'customer_absent' => 'المستلم غير موجود في العنوان',
        'wrong_address' => 'العنوان غير صحيح أو غير مكتمل',
        'customer_refused' => 'رفض المستلم الاستلام',
        'payment_failed' => 'تعذّر الدفع (لم يُحصَّل المبلغ كاملًا)',
        'package_issue' => 'مشكلة في الشحنة',
        'vehicle_issue' => 'عطل في المركبة',
        self::OTHER => 'أخرى',
    ];

    /** The label of a stored code, or the code itself when it is not (or no longer) in the list. */
    public static function label(array $reasons, ?string $code): ?string
    {
        return $code === null ? null : ($reasons[$code] ?? $code);
    }
}
