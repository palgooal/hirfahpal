<?php

namespace App\Http\Requests\VendorDashboard;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Rules\VendorLibraryImage;
use App\Support\Products\VendorProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('vendor');
    }

    public function rules(): array
    {
        return [
            // VPM-CATEGORY-REQUIRED-01: an active category is required.
            'category_id' => VendorProductCategory::rules(),
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
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
            // VENDOR-PRODUCT-PUBLISH-DECISION-01 (OPEN-PROD-19, 20): the vendor chooses draft, active or inactive;
            // omitted means draft. out_of_stock is never a manual choice.
            'status' => ['nullable', 'in:draft,active,inactive'],
            'is_featured' => ['nullable', 'boolean'],
            'is_limited_edition' => ['nullable', 'boolean'],
            'highlight_tags' => ['nullable', 'array', 'max:5'],
            'highlight_tags.*' => ['string', 'max:40'],
            'craft_tags' => ['nullable', 'array', 'max:5'],
            'craft_tags.*' => ['string', 'max:40'],
            // G7-C2-2 (V3): at most six images per product.
            'images' => ['nullable', 'array', 'max:'.ProductImageController::MAX_IMAGES],
            // G7-C2-1 (V2): every image must be one of the vendor's own media library images.
            // VPM-01C: and each image at most once (exact paths), checked again inside the creating transaction.
            'images.*.path' => ['required_with:images', 'string', 'max:2048', 'distinct:strict', new VendorLibraryImage((int) $this->user('vendor')->id)],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => ProductImageController::MAX_IMAGES_MESSAGE,
            'images.*.path.distinct' => ProductImageController::DUPLICATE_MESSAGE,
            ...VendorProductCategory::messages(),
        ];
    }
}
