<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            // Existing address records use this legacy relationship. New dashboard cities do not need it.
            $table->foreignId('governorate_id')->nullable()->change();
        });

        Schema::table('governorates', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete()->after('category_id');
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete()->after('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->foreignId('governorate_id')->nullable(false)->change();
        });
    }
};
