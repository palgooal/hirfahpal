<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('edit', Setting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'timezone' => ['required', 'timezone'],
            'default_locale' => ['required', 'string', 'max:10'],
            'default_currency' => ['required', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_currency')) {
            $this->merge([
                'default_currency' => strtoupper((string) $this->input('default_currency')),
            ]);
        }
    }
}
