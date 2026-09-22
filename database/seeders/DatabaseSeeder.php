<?php

namespace Database\Seeders;

use App\Models\Admin;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        

        Admin::updateOrCreate(
            ['email' => 'palgooal@gmail.com'],
            [
                'name' => 'Admin',
                'phone' => '1234567890',
                'password' => Hash::make('Ha@1020300'),
                'status' => 'active',
                'avatar' => 'avatar.png',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'last_login_at' => now(),
            ]
        );

       
    }
}
