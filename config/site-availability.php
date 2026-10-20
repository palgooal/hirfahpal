<?php

/*
|--------------------------------------------------------------------------
| Site availability (open / coming_soon / maintenance)
|--------------------------------------------------------------------------
|
| docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md. The business-level availability switch, separate from Laravel's
| `php artisan down` (SA-12). This file is the only place that reads the environment for it.
|
*/

return [

    /*
     | Environment override (§6.2, §6.3). `auto` uses the state saved in settings.site_availability_mode; `open`,
     | `coming_soon` or `maintenance` force that state without reading the database, so a forced state works before
     | the migrations run (DEC-SA-08). Any other value is treated as `auto` and logged.
     */
    'override' => env('HIRFAH_SITE_AVAILABILITY', 'auto'),

    // Seconds sent in Retry-After with maintenance responses (§5.3).
    'retry_after' => (int) env('HIRFAH_SITE_AVAILABILITY_RETRY_AFTER', 3600),

    /*
     | Paths never gated, in every state (§5.1): admin authentication, the admin dashboard (protected by auth:admin and
     | its abilities), the health check and the language switch (DEC-SA-05). Matched with Request::is().
     */
    'exempt' => [
        'admin',
        'admin/*',
        'dashboard',
        'dashboard/*',
        'up',
        'change-locale/*',
    ],

    /*
     | D1 (§5.6): unmatched requests for missing static resources keep Laravel's normal 404 in every state. Matched by
     | whole path segments: a prefix covers itself and everything below it (`build`, `build/…`, never `builder`); a path
     | must match exactly. Applies only to requests no route matched; it never exempts a route from the gate.
     */
    'unmatched_static' => [
        'prefixes' => [
            'build',
            'assets',
            'assets-dashboard',
            'images',
            'storage',
            'vendor',
        ],
        'paths' => [
            'favicon.ico',
            'robots.txt',
        ],
    ],

    /*
     | DEC-SA-01 (§5.5): vendor routes allowed during coming_soon only, by route name (never by path). Each route keeps
     | its own middleware (auth:vendor, guest:vendor, vendor.approved, throttles, ownership checks). Vendor registration
     | is deliberately absent.
     */
    'coming_soon_allowed_routes' => [
        'names' => [
            'vendor.login',
            'vendor.login.store',
            'vendor.logout',
            'vendor.password.request',
            'vendor.password.email',
            'vendor.password.reset',
            'vendor.password.update',
            'vendor.approval-status',
            'vendor.dashboard',
        ],
        'prefixes' => [
            'vendor.dashboard.',
        ],
    ],

    // Fixed JSON messages (no database, no t()).
    'messages' => [
        'coming_soon' => 'متجر حِرفة قريبًا بين أيديكم.',
        'maintenance' => 'الموقع تحت الصيانة حاليًا، وسنعود قريبًا.',
    ],

];
