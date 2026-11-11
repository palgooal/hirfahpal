<?php

namespace App\Support\Products;

/**
 * A vendor product's optional city and governorate: the message when the chosen governorate does not belong to the
 * chosen city, in the page language (VendorDashboardTranslationSeeder), read without creating translation rows.
 */
final class VendorProductLocation
{
    public static function governorateMismatchMessage(): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('vendor.Product_Governorate_Mismatch', 'The selected governorate does not belong to the selected city.');
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
