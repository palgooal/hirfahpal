<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * AD-UI-PROD-M1 (OPEN-PROD-09, DEC-PROD-03, DEC-PROD-10, OPEN-PROD-04A/04C): the log of administrative actions on
 * products: who (admins.id), which product, what (suspended, unsuspended, archived, restored), why (reason) and
 * when (created_at). Append-only in the application (App\Models\ProductAdminAction).
 *
 * Both references are nullable and SET NULL on delete, with text snapshots beside them, so an entry outlives its
 * product (a vendor can still delete a product permanently, C-15) and its admin (admins can be deleted). Review
 * requests and internal notes are deferred and get no table here.
 *
 * An existing table is checked instead of recreated (re-run after a partial run); any other shape is refused.
 * down() refuses while the log has entries: dropping it would erase the audit trail.
 */
return new class extends Migration
{
    private const TABLE = 'product_admin_actions';

    private const COLUMNS = ['id', 'product_id', 'product_name', 'product_sku', 'admin_id', 'admin_name', 'action', 'reason', 'changes', 'created_at'];

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            $this->assertTargetState();

            return; // Already migrated.
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('admin_name');
            $table->enum('action', ['suspended', 'unsuspended', 'archived', 'restored']);
            $table->text('reason');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at'], 'product_admin_actions_product_created_index');
        });

        $this->assertTargetState();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $entries = DB::table(self::TABLE)->count();

        if ($entries > 0) {
            throw new RuntimeException(sprintf('product_admin_actions holds %d entr%s; rolling back would erase the audit trail. Nothing was changed.', $entries, $entries === 1 ? 'y' : 'ies'));
        }

        Schema::drop(self::TABLE);
    }

    private function assertTargetState(): void
    {
        $columns = collect(Schema::getColumns(self::TABLE))->pluck('name')->sort()->values()->all();
        $expected = collect(self::COLUMNS)->sort()->values()->all();

        $keys = collect(Schema::getForeignKeys(self::TABLE))
            ->mapWithKeys(fn (array $fk) => [implode(',', $fk['columns']) => [$fk['foreign_table'], $fk['on_delete']]])
            ->all();

        $valid = $columns === $expected
            && ($keys['product_id'] ?? null) === ['products', 'set null']
            && ($keys['admin_id'] ?? null) === ['admins', 'set null']
            && collect(Schema::getIndexes(self::TABLE))->contains(fn (array $index) => $index['columns'] === ['product_id', 'created_at']);

        if (! $valid) {
            throw new RuntimeException('product_admin_actions exists but does not have the expected columns, keys and index. Nothing was changed.');
        }
    }
};
