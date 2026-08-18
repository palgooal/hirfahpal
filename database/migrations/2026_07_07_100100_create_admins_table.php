<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'pending', 'blocked'])->default('active');
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $admins = DB::table('users')
            ->where('type', 'admin')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'password',
                'status',
                'avatar',
                'email_verified_at',
                'phone_verified_at',
                'last_login_at',
                'remember_token',
                'created_at',
                'updated_at',
            ]);

        if ($admins->isNotEmpty()) {
            DB::table('admins')->insert($admins->map(fn ($admin) => (array) $admin)->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
