<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * CONTACT-ADMIN-DELETE-02: soft delete for the storefront contact messages. A super admin's deletion only sets
 * deleted_at; the row stays in the database. No existing row is written.
 *
 * down() is refused while any message is soft-deleted: dropping the column would bring those messages back into the
 * admin list silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $deleted = DB::table('contact_messages')->whereNotNull('deleted_at')->count();

        if ($deleted > 0) {
            throw new RuntimeException(sprintf('%d contact message(s) are soft-deleted; rolling back would restore them to the admin list. Nothing was changed.', $deleted));
        }

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
