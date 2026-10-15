<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHomeContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('edit', Setting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'home_content' => ['required', 'array:'.implode(',', array_keys(config('homepage')))],
            'home_content.*' => ['required', 'string', 'max:5000'],
        ];
    }
}
