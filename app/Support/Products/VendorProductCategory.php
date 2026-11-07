<?php

namespace App\Support\Products;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * VPM-CATEGORY-REQUIRED-01: a vendor product always has a category, on create and on update, with the admin product
 * form's eligibility rule (ADMIN-PRODUCT-FORM-02 D1, D3): an active category, or, for an existing product of this
 * vendor, the category it already has even if that one is now inactive. Nothing is assigned by default; a product
 * stored without a category must be given one before it can be saved again.
 */
final class VendorProductCategory
{
    /** @return array<int, mixed> */
    public static function rules(?Product $product = null, ?int $vendorId = null): array
    {
        // Only the vendor's own product keeps an inactive category; for any other product the controller answers 404.
        $current = $product?->exists && (int) $product->vendor_id === (int) $vendorId ? $product->category_id : null;

        return ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $current !== null
            ? $query->where(fn ($eligible) => $eligible->where('is_active', true)->orWhere('id', $current))
            : $query->where('is_active', true))];
    }

    /**
     * The messages in the page language (VendorDashboardTranslationSeeder), read without creating translation rows.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $required = self::text('vendor.Product_Category_Required', 'Please choose a category for the product.');
        $invalid = self::text('vendor.Product_Category_Invalid', 'Choose an available category.');

        return [
            'category_id.required' => $required,
            'category_id.integer' => $invalid,
            'category_id.exists' => $invalid,
        ];
    }

    private static function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
