<?php

namespace App\Support\Storefront;

use Illuminate\Support\Facades\Storage;

class HomepageMedia
{
    public static function url(?string $path, string $fallback = 'assets/storefront/imgs/mcp/hero-main.png'): string
    {
        if (! $path) {
            return asset($fallback);
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if (str_starts_with($path, 'assets/') || str_starts_with($path, '/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}
