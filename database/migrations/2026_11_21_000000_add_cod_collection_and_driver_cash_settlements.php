<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-MVP batch 05: cash on delivery and the driver's cash custody.
 *
 * - delivery_assignments: cod_amount, the vendor order total captured when a cash-on-delivery order is assigned (what the
 *   driver must collect), and cod_collected_amount / cod_collected_at, recorded with the delivery (the full amount only).
 * - driver_cash_settlements: the cash an admin received from a driver ("settlement", partial allowed), and corrections as
 *   new "reversal" entries pointing at the settlement they cancel (reverses_id, unique: a settlement is reversed at most
 *   once). Entries are never updated or deleted. The driver's balance is computed: collected - settlements + reversals.
 *
 * Nullable columns and a new table only: existing rows are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->decimal('cod_amount', 10, 2)->nullable()->after('notes');
            $table->decimal('cod_collected_amount', 10, 2)->nullable()->after('cod_amount');
            $table->timestamp('cod_collected_at')->nullable()->after('cod_collected_amount');
        });

        Schema::create('driver_cash_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_driver_id')->constrained('delivery_drivers')->restrictOnDelete();
            $table->string('kind', 20);
            $table->decimal('amount', 10, 2);
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('driver_cash_settlements')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('admins')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['delivery_driver_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_cash_settlements');

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn(['cod_amount', 'cod_collected_amount', 'cod_collected_at']);
        });
    }
};
