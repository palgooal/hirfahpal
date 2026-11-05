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
            // MYSTORE-MEDIA-01B: image paths are never taken from this request. The store logo is chosen from the
            // vendor's own media library (vendor.dashboard.profile.logo.update), the cover through its upload endpoint,
            // and the avatar by an admin (vendor management). "missing" refuses the key with any value, null and ""
            // included, so a saved image is never replaced or cleared here (the controller keeps the current values
            // of omitted keys).
            'avatar' => ['missing'],
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
            'preparation_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'logo' => ['missing'],
            'cover_image' => ['missing'],
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
