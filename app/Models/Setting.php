<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'site_description',
        'logo',
        'favicon',
        'email',
        'phone',
        'address',
        'timezone',
        'default_locale',
        'default_currency',
    ];

    protected $hidden = ['singleton_key'];

    public static function defaults(): array
    {
        return [
            'timezone' => config('app.timezone', 'UTC'),
            'default_locale' => config('app.locale', 'en'),
            'default_currency' => 'USD',
        ];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['singleton_key' => 1],
            static::defaults()
        );
    }
}
