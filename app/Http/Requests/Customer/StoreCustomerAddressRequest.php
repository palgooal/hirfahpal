<?php

namespace App\Http\Requests\Customer;

use App\Models\CustomerAddress;
use App\Support\Locations\LocationPair;
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

    /**
     * The pair the address will hold follows Governorate → City: a new address has no stored pair, an update keeps the
     * stored values of omitted fields. Another customer's address is never read here; the controller refuses it (403).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $address = $this->route('address');
                $address = $address instanceof CustomerAddress ? $address : null;

                if ($address !== null && (int) $address->customer_id !== (int) $this->user('customer')->id) {
                    return;
                }

                LocationPair::validate($validator, $this->all(), $address?->governorate_id, $address?->city_id);
            },
        ];
    }
}
