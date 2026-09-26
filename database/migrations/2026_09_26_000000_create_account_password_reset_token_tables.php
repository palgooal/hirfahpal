<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each HIRFAH password broker its own token table so a token issued for
 * one account type can never be verified, replaced or throttled by another.
 * Rows in password_reset_tokens are left alone: they carry no broker identity.
 */
return new class extends Migration
{
    private const TABLES = [
        'admin_password_reset_tokens',
        'customer_password_reset_tokens',
        'vendor_password_reset_tokens',
        'delivery_driver_password_reset_tokens',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};
