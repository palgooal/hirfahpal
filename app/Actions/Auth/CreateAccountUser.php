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

        return $modelClass::create([
            'name' => $input['full_name'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'status' => 'active',
        ]);
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
}
