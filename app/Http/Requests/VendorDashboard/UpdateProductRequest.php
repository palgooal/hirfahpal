<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Governorate;
use App\Models\Product;
use App\Support\Products\VendorProductCategory;
use App\Support\Products\VendorProductLocation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('vendor');
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            // VPM-CATEGORY-REQUIRED-01: a category is required (an active one, or the one the product already has).
            'category_id' => VendorProductCategory::rules($product instanceof Product ? $product : null, $this->user('vendor')?->id),
            'city_id' => ['nullable', 'required_with:governorate_id', 'integer', 'exists:cities,id'],
            'governorate_id' => ['nullable', 'required_with:city_id', 'integer', 'exists:governorates,id', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $this->input('city_id') !== null
                    && ! Governorate::query()->whereKey($value)->where('city_id', $this->input('city_id'))->exists()) {
                    $fail(VendorProductLocation::governorateMismatchMessage());
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'stock_quantity' => ['required', 'integer', 'min:0', $this->notBelowReservedStock($product)],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'in:kg,g,t'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'material' => ['nullable', 'string', 'max:255'],
            'place_of_manufacture' => ['nullable', 'string', 'max:255'],
            'preparation_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'care_instructions' => ['nullable', 'string', 'max:5000'],
            'shipping_details' => ['nullable', 'string', 'max:5000'],
            // VENDOR-PRODUCT-PUBLISH-DECISION-01 (OPEN-PROD-19, 20; DETAIL-01): draft, active or inactive; omitted keeps
            // the stored status, a historical out_of_stock included. out_of_stock is never a manual choice.
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'is_featured' => ['nullable', 'boolean'],
            'is_limited_edition' => ['nullable', 'boolean'],
            'highlight_tags' => ['nullable', 'array', 'max:5'],
            'highlight_tags.*' => ['string', 'max:40'],
            'craft_tags' => ['nullable', 'array', 'max:5'],
            'craft_tags.*' => ['string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return VendorProductCategory::messages();
    }

    /**
     * Physical stock may not drop below the quantity still reserved for open
     * orders (VEN-BE-019). Only checked for the vendor's own product; for any
     * other product the controller answers 404 without revealing reservations.
     */
    private function notBelowReservedStock(mixed $product): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($product): void {
            if (! $product instanceof Product || (int) $product->vendor_id !== (int) $this->user('vendor')?->id) {
                return;
            }

            $reserved = $product->reservedStockQuantity();

            if (is_numeric($value) && (int) $value < $reserved) {
                $fail(self::reservedStockMessage($reserved));
            }
        };
    }

    /**
     * Shared with the controller's write-boundary recheck, so both report the same error.
     */
    public static function reservedStockMessage(int $reserved): string
    {
        return "The stock quantity cannot be lower than the {$reserved} units currently reserved for open orders.";
    }
}
