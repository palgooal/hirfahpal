<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Product;
use App\Rules\VendorLibraryImage;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->user('vendor');
        $product = $this->route('product');

        // G7-C2-1: another vendor's product is a 404 before any validation, so a refused path never reveals it.
        abort_unless($vendor && $product instanceof Product && (int) $product->vendor_id === (int) $vendor->id, 404);

        return true;
    }

    public function rules(): array
    {
        return [
            // G7-C2-1 (V2): only an image of the vendor's own media library.
            'path' => ['required', 'string', 'max:2048', new VendorLibraryImage((int) $this->user('vendor')->id)],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
