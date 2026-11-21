<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Auth\AccountGuard;
use App\Support\Locations\LocationPair;
use Closure;
use Database\Seeders\DeliveryDriverAuthTranslationSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAccountUser
{
    use PasswordValidationRules;

    public function create(string $account, array $input): Authenticatable
    {
        $accountConfig = AccountGuard::get($account);
        $modelClass = $accountConfig['model'];

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', $this->uniqueAcrossAccountTables('phone')],
            'email' => ['required', 'string', 'email', 'max:255', $this->uniqueAcrossAccountTables('email')],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ];
        $isDriver = $modelClass === DeliveryDriver::class;

        // E2-MVP batch 01: a delivery driver applies with the service area and vehicle the review and assignment need.
        // The governorate and city follow the approved hierarchy (LocationPair: active values, the city in the governorate).
        if ($isDriver) {
            $rules += [
                LocationPair::GOVERNORATE => ['required', 'integer'],
                LocationPair::CITY => ['required', 'integer'],
                'vehicle_type' => ['required', 'string', 'max:255'],
            ];
        }

        $validator = Validator::make($input, $rules, [], $isDriver ? [
            'vehicle_type' => $this->driverText('driver.Vehicle_Type'),
        ] : []);

        if ($isDriver) {
            $validator->after(function ($validator) use ($input): void {
                $invalid = $this->driverText('driver.Location_Invalid');

                LocationPair::validate($validator, $input, null, null, [
                    'governorate_missing' => $invalid,
                    'governorate_invalid' => $invalid,
                    'governorate_inactive' => $invalid,
                    'city_invalid' => $invalid,
                    'city_inactive' => $invalid,
                    'mismatch' => $this->driverText('driver.City_Mismatch'),
                ]);
            });
        }

        $validator->validate();

        $user = $modelClass::create([
            'name' => $input['full_name'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'status' => 'active',
        ]);

        if ($user instanceof Vendor) {
            $user->profile()->create([
                'store_name' => $input['store_name'] ?? $input['full_name'],
                'slug' => $this->uniqueSlug('vendor_profiles', $input['store_name'] ?? $input['full_name']),
                'approval_status' => 'pending',
            ]);
        }

        if ($user instanceof DeliveryDriver) {
            // Still pending and unavailable: no delivery operation or assignment before the platform approves it.
            $user->profile()->create([
                'approval_status' => 'pending',
                'governorate_id' => (int) $input[LocationPair::GOVERNORATE],
                'city_id' => (int) $input[LocationPair::CITY],
                'vehicle_type' => trim((string) $input['vehicle_type']),
            ]);
        }

        return $user;
    }

    /** A delivery driver registration string, seeded by DeliveryDriverAuthTranslationSeeder (its text until it runs). */
    private function driverText(string $key): string
    {
        return t($key, DeliveryDriverAuthTranslationSeeder::fallback($key));
    }

    private function uniqueAcrossAccountTables(string $column): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column): void {
            if (
                User::where($column, $value)->exists()
                || Admin::where($column, $value)->exists()
                || Customer::where($column, $value)->exists()
                || Vendor::where($column, $value)->exists()
                || DeliveryDriver::where($column, $value)->exists()
            ) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        };
    }

    private function uniqueSlug(string $table, string $value): string
    {
        $base = Str::slug($value) ?: 'account';
        $slug = $base;
        $counter = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
