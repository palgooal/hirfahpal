<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * FR-ADM-009: Arabic and English texts of the vendor commission rate card on the admin vendor details page
 * (dashboard/vendors/show) and of the update message of VendorManagementController::updateCommissionRate.
 * FR-ADM-015: the title and the empty state of the store reviews card on the same page.
 * Shared keys already used on the page (Commission_Rate, …) are not touched.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; the English defaults that t() auto-creates
 * in Arabic (listed in CORRECTIONS) are replaced only while unchanged. Not called from DatabaseSeeder.
 */
class AdminVendorTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Commission_rate_updated_successfully' => ['ar' => 'تم تحديث نسبة العمولة بنجاح', 'en' => 'Commission rate updated successfully.'],
        'dashboard.Current_commission_rate' => ['ar' => 'نسبة العمولة الحالية', 'en' => 'Current rate'],
        'dashboard.Not_set' => ['ar' => 'غير محددة', 'en' => 'Not set'],
        'dashboard.Update_commission_rate' => ['ar' => 'تحديث نسبة العمولة', 'en' => 'Update Commission Rate'],
        // FR-ADM-015: the store reviews card (shared keys Customer, Rating, Comment, Status, Pending, … are reused).
        'dashboard.Recent_Reviews' => ['ar' => 'آخر التقييمات', 'en' => 'Recent Reviews'],
        'dashboard.No_reviews_found' => ['ar' => 'لا توجد تقييمات لهذا المتجر', 'en' => 'No reviews found'],
    ];

    /**
     * Arabic rows auto-created by t() with the English default. Each row is replaced only while it still holds that
     * exact default; any other value is an edit and is kept (same mechanism as AdminCustomerTranslationSeeder).
     *
     * @var list<array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        ['key' => 'dashboard.Commission_rate_updated_successfully', 'locale' => 'ar', 'from' => 'Commission rate updated successfully.', 'to' => 'تم تحديث نسبة العمولة بنجاح'],
        ['key' => 'dashboard.Current_commission_rate', 'locale' => 'ar', 'from' => 'Current rate', 'to' => 'نسبة العمولة الحالية'],
        ['key' => 'dashboard.Not_set', 'locale' => 'ar', 'from' => 'Not set', 'to' => 'غير محددة'],
        ['key' => 'dashboard.Update_commission_rate', 'locale' => 'ar', 'from' => 'Update Commission Rate', 'to' => 'تحديث نسبة العمولة'],
        ['key' => 'dashboard.Recent_Reviews', 'locale' => 'ar', 'from' => 'Recent Reviews', 'to' => 'آخر التقييمات'],
        ['key' => 'dashboard.No_reviews_found', 'locale' => 'ar', 'from' => 'No reviews found', 'to' => 'لا توجد تقييمات لهذا المتجر'],
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
