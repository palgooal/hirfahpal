<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('preparation_days')->nullable()->after('address_line');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_limited_edition')->default(false)->after('is_featured');
            $table->json('highlight_tags')->nullable()->after('is_limited_edition');
            $table->json('craft_tags')->nullable()->after('highlight_tags');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['is_limited_edition', 'highlight_tags', 'craft_tags']);
        });

        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->dropColumn('preparation_days');
        });
    }
};
