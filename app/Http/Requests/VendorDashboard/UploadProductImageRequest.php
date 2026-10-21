<?php

namespace App\Http\Requests\VendorDashboard;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('vendor');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
