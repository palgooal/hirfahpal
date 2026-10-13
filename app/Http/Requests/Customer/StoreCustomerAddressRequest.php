<?php

namespace App\Http\Requests\Customer;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        return [
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'address_line' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled(['governorate_id', 'city_id'])) {
                    return;
                }

                $belongsToGovernorate = City::query()
                    ->whereKey($this->integer('city_id'))
                    ->where('governorate_id', $this->integer('governorate_id'))
                    ->exists();

                if (! $belongsToGovernorate) {
                    $validator->errors()->add('city_id', 'The selected city does not belong to the selected governorate.');
                }
            },
        ];
    }
}
