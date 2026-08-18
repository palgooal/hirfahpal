<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $keyword = trim((string) $this->input('q'));

            $this->merge(['q' => $keyword !== '' ? $keyword : null]);
        }

        if ($this->input('area') === '') {
            $this->merge(['area' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'area' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')->where('status', 'active'),
            ],
        ];
    }
}
