<?php

namespace App\Http\Requests\VendorDashboard;

use Illuminate\Foundation\Http\FormRequest;

/** VPM-01D: one step of a product image: "up" (towards the main image) or "down". */
class MoveProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', 'in:up,down'],
        ];
    }
}
