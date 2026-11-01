<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('material')->nullable()->after('dimensions');
            $table->string('place_of_manufacture')->nullable()->after('material');
            $table->unsignedSmallInteger('preparation_days')->nullable()->after('place_of_manufacture');
            $table->text('care_instructions')->nullable()->after('preparation_days');
            $table->text('shipping_details')->nullable()->after('care_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'material',
                'place_of_manufacture',
                'preparation_days',
                'care_instructions',
                'shipping_details',
            ]);
        });
    }
};
