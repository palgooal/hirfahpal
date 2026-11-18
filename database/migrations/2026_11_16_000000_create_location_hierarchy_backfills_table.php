<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * H4: the record of the cities that 2026_11_16_000001_backfill_city_governorates_from_legacy_links linked to a
 * governorate (Governorate → City, from the legacy governorates.city_id). One row per changed city, with the values
 * needed to reverse exactly that change. No foreign keys: a row must outlive a later deletion of its city or
 * governorate, so the reversal can see the change and refuse it.
 *
 * Schema only. On MySQL a schema statement commits any open transaction, which is why this table is created by its
 * own migration and the data step runs separately, in one transaction of its own.
 */
return new class extends Migration
{
    private const TABLE = 'location_hierarchy_backfills';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->unique();
            $table->unsignedBigInteger('legacy_governorate_id');
            $table->unsignedBigInteger('previous_governorate_id')->nullable();
            $table->unsignedBigInteger('assigned_governorate_id');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // A row is a change still in place: dropping the table would lose the only way to reverse it.
        $remaining = DB::table(self::TABLE)->count();
        if ($remaining > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to drop %s: it still records %d backfilled %s. Roll back 2026_11_16_000001_backfill_city_governorates_from_legacy_links first. Nothing was changed.',
                self::TABLE, $remaining, $remaining === 1 ? 'city' : 'cities'
            ));
        }

        Schema::drop(self::TABLE);
    }
};
