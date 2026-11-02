<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * VEN-BE-020 (slice 2A): Arabic and English texts the admin vendor order page shows instead of the driver assignment
 * form, for an order not ready for delivery yet and for a delivered order. The page's older dashboard.* labels are not
 * seeded here. Only missing (key, locale) rows are inserted; edited values are kept; nothing is deleted. Not called
 * from DatabaseSeeder.
 *
 * The admin page reads these keys with auto-create on: opened in Arabic before this seeder runs, t() inserts an Arabic
 * row holding the English default. Such a placeholder is repaired (see repairArabicPlaceholders()); any other row is kept.
 */
class AdminVendorOrderTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Vendor_Order_Assignment_Not_Ready' => [
            'ar' => 'يمكن إسناد مندوب توصيل بعد أن يحدّد البائع هذا الطلب كجاهز للتسليم.',
            'en' => 'A driver can be assigned once the vendor marks this order ready for delivery.',
        ],
        'dashboard.Vendor_Order_Assignment_Delivered' => [
            'ar' => 'تم تسليم هذا الطلب، لذلك لم يعد بالإمكان إسناد مندوب توصيل له.',
            'en' => 'This order has been delivered, so a driver can no longer be assigned.',
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

    /**
     * An Arabic row t() auto-created before this seeder ran holds exactly the English default (the en value here, the
     * same text as the page's default) and was never changed since (created_at = updated_at; an edit through the
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
