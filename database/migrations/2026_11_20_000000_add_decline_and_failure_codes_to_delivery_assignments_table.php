<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-MVP batch 03: a driver may decline a task before accepting it (with a reason from a fixed list, "other" with a text),
 * and a failed delivery records a reason from a fixed list with an optional note. A declined attempt keeps the existing
 * "cancelled" status, marked by declined_at so it is told apart from the other cancellations (a reassignment, a return to
 * the vendor). failure_reason stays the free text: the optional note of a coded failure, or the text of an older one.
 *
 * E2-MVP custody fix: after a failure following pickup, the driver's "returned to the vendor" only declares the return
 * (return_declared_at); the custody is closed by an admin confirmation alone, recorded in returned_at (the confirmed
 * physical return) and return_confirmed_by.
 *
 * Nullable columns only: existing rows are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->string('failure_code', 40)->nullable()->after('failed_at');
            $table->timestamp('declined_at')->nullable()->after('returned_at');
            $table->string('decline_code', 40)->nullable()->after('declined_at');
            $table->text('decline_note')->nullable()->after('decline_code');
            $table->timestamp('return_declared_at')->nullable()->after('decline_note');
            $table->foreignId('return_confirmed_by')->nullable()->after('return_declared_at')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_confirmed_by');
            $table->dropColumn(['failure_code', 'declined_at', 'decline_code', 'decline_note', 'return_declared_at']);
        });
    }
};
