<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'failure_reason']);
        });
    }
};
