<?php

namespace App\Http\Requests\DeliveryDriverDashboard;

use App\Support\Locations\LocationPair;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    /** The pair the driver's profile will hold (omitted fields keep the stored values) follows Governorate → City. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Read from the database, not a relation cached on the signed-in driver.
                $profile = $this->user('delivery_driver')->profile()->first(['governorate_id', 'city_id']);

                LocationPair::validate($validator, $this->all(), $profile?->governorate_id, $profile?->city_id);
            },
        ];
    }
}
