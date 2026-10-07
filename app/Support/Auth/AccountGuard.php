<?php

namespace App\Support\Auth;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use InvalidArgumentException;

class AccountGuard
{
    public static function all(): array
    {
        return [
            'customer' => [
                'guard' => 'customer',
                'broker' => 'customers',
                'model' => Customer::class,
                'prefix' => 'customer',
                'route' => 'customer',
                'dashboard_route' => 'customer.dashboard',
                'login_view' => 'pages.customer-login',
                'dashboard_view' => 'pages.customer-dashboard',
                'label' => 'Customer',
            ],
            'vendor' => [
                'guard' => 'vendor',
                'broker' => 'vendors',
                'model' => Vendor::class,
                'prefix' => 'vendor',
                'route' => 'vendor',
                'dashboard_route' => 'vendor.dashboard',
                'login_view' => 'auth.vendor.login',
                'register_view' => 'auth.vendor.register',
                'forgot_password_view' => 'auth.vendor.forgot-password',
                'reset_password_view' => 'auth.vendor.reset-password',
                'dashboard_view' => 'accounts.dashboard',
                'label' => 'Vendor',
            ],
            'delivery_driver' => [
                'guard' => 'delivery_driver',
                'broker' => 'delivery_drivers',
                'model' => DeliveryDriver::class,
                'prefix' => 'delivery-driver',
                'route' => 'delivery-driver',
                'dashboard_route' => 'delivery-driver.dashboard',
                'login_view' => 'auth.accounts.login',
                'dashboard_view' => 'accounts.dashboard',
                'label' => 'Delivery Driver',
            ],
        ];
    }

    public static function get(string $account): array
    {
        $config = self::all()[$account] ?? null;

        if (! $config) {
            throw new InvalidArgumentException("Unsupported account guard [{$account}].");
        }

        return $config;
    }
}
