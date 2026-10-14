<?php

namespace App\Http\Requests\DeliveryDriverDashboard;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('delivery_driver') !== null;
    }

    public function rules(): array
    {
        $driverId = $this->user('delivery_driver')->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique('delivery_drivers', 'phone')->ignore($driverId)],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'vehicle_plate' => ['nullable', 'string', 'max:255'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')],
            'city_id' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $governorateId = $this->input('governorate_id');

                    if ($value !== null && $governorateId !== null && ! City::query()->whereKey($value)->where('governorate_id', $governorateId)->exists()) {
                        $fail('The selected city does not belong to the selected governorate.');
                    }
                },
            ],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
