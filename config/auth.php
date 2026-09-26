<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
        'vendor' => [
            'driver' => 'session',
            'provider' => 'vendors',
        ],
        'delivery_driver' => [
            'driver' => 'session',
            'provider' => 'delivery_drivers',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Customer::class,
        ],
        'vendors' => [
            'driver' => 'eloquent',
            'model' => App\Models\Vendor::class,
        ],
        'delivery_drivers' => [
            'driver' => 'eloquent',
            'model' => App\Models\DeliveryDriver::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        // Each HIRFAH broker keeps its own token table so tokens never cross account types.
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'vendors' => [
            'provider' => 'vendors',
            'table' => 'vendor_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'delivery_drivers' => [
            'provider' => 'delivery_drivers',
            'table' => 'delivery_driver_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'admins' => [
            'provider' => 'admins',
            'table' => 'admin_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
