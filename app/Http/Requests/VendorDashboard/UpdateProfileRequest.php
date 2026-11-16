<?php

namespace App\Http\Requests\VendorDashboard;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Locations\LocationPair;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // vendor's own media library (vendor.dashboard.profile.logo.update); the cover has no vendor endpoint (its
            // upload was retired in MYSTORE-SEC-02B; a stored value is kept), and the avatar is set by an admin
            // (vendor management). "missing" refuses the key with any value, null and "" included, so a saved image is
            // never replaced or cleared here (the controller keeps the current values of omitted keys).
            'avatar' => ['missing'],
            'store_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('vendor_profiles', 'slug')->ignore($profile)],
            // Governorate → City: the pair the profile will hold is checked in after() (LocationPair).
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'preparation_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'logo' => ['missing'],
            'cover_image' => ['missing'],
        ];
    }

    /**
     * The store location the profile will hold (omitted fields keep the stored values) follows Governorate → City. The
     * stored pair is read from the database, not from a relation cached on the signed-in vendor.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $stored = $this->user('vendor')?->profile()->first(['governorate_id', 'city_id']);

                LocationPair::validate($validator, $this->all(), $stored?->governorate_id, $stored?->city_id, [
                    'mismatch' => self::cityGovernorateMismatchMessage(),
                ]);
            },
        ];
    }

    /** The My Store mismatch message, in the page language; also given to the page script (my-store.blade.php). */
    public static function cityGovernorateMismatchMessage(): string
    {
        return self::untranslated('vendor.My_Store_Error_City_Governorate', 'The selected city does not belong to the selected governorate.');
    }

    /** The label of a stored location value the active lists no longer offer (":name" is the value's name). */
    public static function locationUnavailableLabel(): string
    {
        return self::untranslated('vendor.My_Store_Location_Unavailable', ':name (unavailable)');
    }

    /** Reads a key (VendorDashboardTranslationSeeder) without creating translation rows when it is not seeded yet. */
    private static function untranslated(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
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
