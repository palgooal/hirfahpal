<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('super_admin')->default(false)->after('password');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->string('role_name');
            $table->foreignId('user_id')->constrained('admins')->cascadeOnDelete();
            $table->enum('ability', ['allow', 'deny'])->default('deny');
            $table->primary(['role_name', 'user_id']);
        });

        DB::table('admins')
            ->where('id', DB::table('admins')->min('id'))
            ->update(['super_admin' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('super_admin');
        });
    }
};
