<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'super_admin',
        'status',
        'avatar',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'super_admin' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleUser::class, 'user_id');
    }

    /**
     * Super admin status comes only from the stored flag, never from
     * role_user rows or the Gate.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->super_admin;
    }

    public function isActiveSuperAdmin(): bool
    {
        return $this->isSuperAdmin() && $this->status === 'active';
    }

    /**
     * Ability names explicitly granted through role_user.
     *
     * @return array<int, string>
     */
    public function abilityNames(): array
    {
        return $this->roles()
            ->where('ability', 'allow')
            ->pluck('role_name')
            ->all();
    }

    public function hasAbility(string $ability): bool
    {
        return $this->roles()
            ->where('role_name', $ability)
            ->where('ability', 'allow')
            ->exists();
    }
}
