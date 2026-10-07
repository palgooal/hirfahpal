<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Product;
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'stock_quantity' => ['required', 'integer', 'min:0', $this->notBelowReservedStock($product)],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'out_of_stock'])],
            'is_featured' => ['nullable', 'boolean'],
        ];
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
