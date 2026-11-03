<?php

namespace Tests\Unit;

use App\Support\Storefront\ArabicDays;
use PHPUnit\Framework\TestCase;

/** PRODUCT-FIX-01: a number of days with the Arabic noun form that number takes. */
class ArabicDaysTest extends TestCase
{
    public function test_each_number_takes_its_arabic_form(): void
    {
        $cases = [
            0 => 'في اليوم نفسه',
            1 => 'يوم واحد',
            2 => 'يومان',
            3 => '3 أيام',
            10 => '10 أيام',
            11 => '11 يومًا',
            30 => '30 يومًا',
            99 => '99 يومًا',
            100 => '100 يوم',
            101 => '101 يوم',
            102 => '102 يوم',
            103 => '103 أيام',
            110 => '110 أيام',
            115 => '115 يومًا',
            200 => '200 يوم',
            3650 => '3650 يومًا',
        ];

        foreach ($cases as $days => $label) {
            $this->assertSame($label, ArabicDays::label($days), (string) $days);
        }
    }

    public function test_no_value_has_no_label(): void
    {
        $this->assertNull(ArabicDays::label(null));
        $this->assertNull(ArabicDays::label(-1));
    }
}
