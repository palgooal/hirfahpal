<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Product;
use App\Models\ProductImage;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->user('vendor');
        $product = $this->route('product');
        $image = $this->route('productImage');

        // G7-C2-1: another vendor's product, or an image of another product, is a 404 before any validation.
        abort_unless(
            $vendor && $product instanceof Product && $image instanceof ProductImage
                && (int) $product->vendor_id === (int) $vendor->id && (int) $image->product_id === (int) $product->id,
            404
        );

        return true;
    }

    public function rules(): array
    {
        return [
            // G7-C2-1 (V2): a new path must be an image of the vendor's own media library; the image's current path
            // (a legacy one included) may always be sent back unchanged.
            'path' => ['sometimes', 'required', 'string', 'max:2048', new VendorLibraryImage((int) $this->user('vendor')->id, $this->route('productImage')->path)],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
