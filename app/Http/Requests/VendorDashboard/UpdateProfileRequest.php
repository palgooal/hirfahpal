<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('vendor');
    }

    public function rules(): array
    {
        $vendor = $this->user('vendor');
        $profile = $vendor?->profile;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', $this->uniqueAcrossAccountTables('email', $vendor?->id)],
            'phone' => ['sometimes', 'required', 'string', 'max:30', $this->uniqueAcrossAccountTables('phone', $vendor?->id)],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'store_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('vendor_profiles', 'slug')->ignore($profile)],
            'governorate_id' => ['nullable', 'required_with:city_id', 'integer', 'exists:governorates,id'],
            'city_id' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('governorate_id', $this->input('governorate_id'))),
            ],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
        ];
    }

    private function uniqueAcrossAccountTables(string $column, ?int $currentVendorId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $currentVendorId): void {
            if ($value === null || $value === '') {
                return;
            }

            if (
                User::where($column, $value)->exists()
                || Admin::where($column, $value)->exists()
                || Customer::where($column, $value)->exists()
                || Vendor::where($column, $value)->when($currentVendorId, fn ($query) => $query->whereKeyNot($currentVendorId))->exists()
                || DeliveryDriver::where($column, $value)->exists()
            ) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        };
    }
}
