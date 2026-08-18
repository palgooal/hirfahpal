<?php

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GuardLoginService
{
    public function authenticate(
        string $modelClass,
        string $login,
        string $password,
        string $errorField = 'login',
        ?callable $constraint = null,
    ): Authenticatable {
        /** @var Builder $query */
        $query = $modelClass::query();

        if ($constraint) {
            $query = $constraint($query) ?? $query;
        }

        $user = $query
            ->where(function (Builder $builder) use ($login): void {
                $builder->where('email', $login)
                    ->orWhere('phone', $login);
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                $errorField => __('auth.failed'),
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                $errorField => __('auth.blocked'),
            ]);
        }

        if ($user->status === 'pending') {
            throw ValidationException::withMessages([
                $errorField => __('auth.pending'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return $user;
    }
}
