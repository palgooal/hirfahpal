<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Auth\AccountGuard;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
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

        Validator::make($input, [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', $this->uniqueAcrossAccountTables('phone')],
            'email' => ['required', 'string', 'email', 'max:255', $this->uniqueAcrossAccountTables('email')],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

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
            $user->profile()->create([
                'approval_status' => 'pending',
            ]);
        }

        return $user;
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

        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
