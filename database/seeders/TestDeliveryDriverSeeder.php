<?php

namespace Database\Seeders;

use App\Models\DeliveryDriver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDeliveryDriverSeeder extends Seeder
{
    public function run(): void
    {
        $driver = DeliveryDriver::query()->updateOrCreate(
            ['email' => 'driver.test@hirfah.local'],
            [
                'name' => 'Test Delivery Driver',
                'phone' => '0599999998',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $driver->profile()->updateOrCreate([], [
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'TEST-001',
            'is_available' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }
}
