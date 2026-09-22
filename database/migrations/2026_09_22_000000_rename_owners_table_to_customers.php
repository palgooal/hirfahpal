<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owners') && ! Schema::hasTable('customers')) {
            Schema::rename('owners', 'customers');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasTable('owners')) {
            Schema::rename('customers', 'owners');
        }
    }
};
