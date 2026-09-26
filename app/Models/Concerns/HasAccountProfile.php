<?php

namespace App\Models\Concerns;

trait HasAccountProfile
{
    /**
     * Declaring $fillable / $hidden here would clash with the properties
     * inherited from Model, so they are merged in on initialization instead.
     */
    public function initializeHasAccountProfile(): void
    {
        $this->mergeFillable([
            'name',
            'email',
            'phone',
            'password',
            'status',
            'avatar',
            'email_verified_at',
            'phone_verified_at',
            'last_login_at',
        ]);

        $this->makeHidden([
            'password',
            'remember_token',
        ]);
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