<?php

namespace App\Models\Concerns;

trait HasAccountProfile
{
    public function initializeHasAccountProfile(): void
    {
        $this->fillable = [
            'name',
            'email',
            'phone',
            'password',
            'status',
            'avatar',
            'email_verified_at',
            'phone_verified_at',
            'last_login_at',
        ];

        $this->hidden = [
            'password',
            'remember_token',
        ];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
