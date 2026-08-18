<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds two default languages: English (LTR) and Arabic (RTL).
 * Run with: php artisan db:seed --class=LanguageSeeder
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('languages')->insert([
            [
                'name'       => 'English',
                'native'     => 'الإنجليزية',
                'code'       => 'en',
                'flag'       => 'flags/en.png',
                'is_rtl'     => false,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Arabic',
                'native'     => 'العربية',
                'code'       => 'ar',
                'flag'       => 'flags/ar.png',
                'is_rtl'     => true,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
