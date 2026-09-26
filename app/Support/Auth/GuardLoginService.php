<?php

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardLoginService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(private readonly Request $request) {}

    /**
     * Passing a $throttleScope (the account type) opts the call into the
     * per-account failure limiter; Fortify's /login does not pass one.
     */
    public function authenticate(
        string $modelClass,
        string $login,
        string $password,
        string $errorField = 'login',
        ?callable $constraint = null,
        ?string $throttleScope = null,
    ): Authenticatable {
        $throttleKey = $throttleScope !== null ? $this->throttleKey($throttleScope, $login) : null;

        // Checked before any lookup so a throttled response never reveals account state.
        if ($throttleKey !== null && RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                $errorField => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        try {
            $user = $this->resolveUser($modelClass, $login, $password, $errorField, $constraint);
        } catch (ValidationException $exception) {
            if ($throttleKey !== null) {
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            }

            throw $exception;
        }

        if ($throttleKey !== null) {
            RateLimiter::clear($throttleKey);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * The identifier is normalized like Fortify's limiter and hashed so the
     * raw email or phone never ends up in the cache key.
     */
    public function throttleKey(string $scope, string $login): string
    {
        $identifier = hash('sha256', Str::transliterate(Str::lower($login)));

        return 'account-login:'.$scope.':'.$identifier.'|'.$this->request->ip();
    }

    private function resolveUser(
        string $modelClass,
        string $login,
        string $password,
        string $errorField,
        ?callable $constraint,
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

        return $user;
    }
}
