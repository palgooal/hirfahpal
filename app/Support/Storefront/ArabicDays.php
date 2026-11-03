<?php

namespace App\Support\Storefront;

/**
 * PRODUCT-FIX-01: a number of days in Arabic with the noun form that number takes: يوم واحد، يومان، 3–10 أيام، 11–99
 * يومًا، and above 100 by the last two digits (e.g. 103 أيام، 115 يومًا، 200 يوم). Zero days is "the same day".
 */
class ArabicDays
{
    public static function label(?int $days): ?string
    {
        if ($days === null || $days < 0) {
            return null;
        }

        return match (true) {
            $days === 0 => 'في اليوم نفسه',
            $days === 1 => 'يوم واحد',
            $days === 2 => 'يومان',
            default => $days.' '.self::noun($days),
        };
    }

    private static function noun(int $days): string
    {
        $lastTwo = $days % 100;

        return match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => 'أيام',
            $lastTwo >= 11 => 'يومًا',
            default => 'يوم',
        };
    }
}
