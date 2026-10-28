<?php

namespace App\Http\Requests\VendorDashboard;

use App\Rules\VendorLibraryImage;
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            // Vendors do not choose publication status. New products always start as drafts for review.
            'status' => ['nullable', 'in:draft,active,inactive,out_of_stock'],
            'is_featured' => ['nullable', 'boolean'],
            'is_limited_edition' => ['nullable', 'boolean'],
            'highlight_tags' => ['nullable', 'array', 'max:5'],
            'highlight_tags.*' => ['string', 'max:40'],
            'craft_tags' => ['nullable', 'array', 'max:5'],
            'craft_tags.*' => ['string', 'max:40'],
            'images' => ['nullable', 'array'],
            // G7-C2-1 (V2): every image must be one of the vendor's own media library images.
            'images.*.path' => ['required_with:images', 'string', 'max:2048', new VendorLibraryImage((int) $this->user('vendor')->id)],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
