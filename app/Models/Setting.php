<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'home_content',
    ];

    protected $hidden = ['singleton_key'];

    public static function current(): ?self
    {
        return Schema::hasTable('settings') ? static::query()->first() : null;
    }

    protected function casts(): array
    {
        return ['home_content' => 'array'];
    }

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
