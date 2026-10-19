<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * ORD-BE-001: SQLite path of 2026_10_18_000000_add_delivery_address_snapshot_to_orders_table. DatabaseMigrations
 * (not RefreshDatabase) so up()/down() run outside a transaction, as they do under the migrator: adding the two
 * foreign keys rebuilds orders, whose dependents (vendor orders, items, payments, reviews) must not be touched.
 */
class OrderDeliveryAddressSnapshotMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_10_18_000000_add_delivery_address_snapshot_to_orders_table.php';

    private const COLUMNS = [
        'delivery_address_source', 'delivery_recipient_name', 'delivery_phone', 'delivery_governorate_id', 'delivery_governorate_name',
        'delivery_city_id', 'delivery_city_name', 'delivery_address_line', 'delivery_notes', 'delivery_latitude', 'delivery_longitude',
    ];

    public function test_the_columns_are_nullable_and_the_ids_reference_their_tables_on_delete_set_null(): void
    {
        $columns = collect(Schema::getColumns('orders'))->keyBy('name');

        foreach (self::COLUMNS as $column) {
            $this->assertTrue($columns->has($column), $column);
            $this->assertTrue($columns->get($column)['nullable'], "{$column} is nullable");
        }

        $this->assertSame(['governorates', 'set null'], $this->reference('delivery_governorate_id'));
        $this->assertSame(['cities', 'set null'], $this->reference('delivery_city_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'customer_address_id'), 'The reference column is kept.');
    }

    public function test_rollback_and_reapply_change_no_existing_row(): void
    {
        $orderId = $this->legacyOrderWithDependents();
        $before = $this->rows();
        $migration = $this->migration();

        $migration->down();
        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column), $column);
        }
        $this->assertSame($before, $this->rows(), 'down() must not change any row, nor cascade to dependents.');

        $migration->up();
        $this->assertSame($before, $this->rows(), 'up() must not change any row.');
        $this->assertNull(DB::table('orders')->where('id', $orderId)->value('delivery_address_source'), 'No backfill.');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // Running again on the final state changes nothing.
        $migration->up();
        $this->assertSame($before, $this->rows());
    }

    public function test_rollback_is_refused_while_any_order_has_a_recorded_delivery_address_source(): void
    {
        $orderId = $this->legacyOrderWithDependents();

        // A checkout snapshot and the "none" marker both block the rollback.
        foreach (['checkout' => 'Kept street', 'none' => null] as $source => $line) {
            DB::table('orders')->where('id', $orderId)->update(['delivery_address_source' => $source, 'delivery_address_line' => $line]);

            try {
                $this->migration()->down();
                $this->fail("The rollback must be refused ({$source}).");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('hold a recorded delivery address', $e->getMessage(), $source);
            }

            $this->assertSame($source, DB::table('orders')->where('id', $orderId)->value('delivery_address_source'));
            $this->assertSame($line, DB::table('orders')->where('id', $orderId)->value('delivery_address_line'));
        }

        // Only legacy orders (NULL source) left: the rollback is allowed.
        DB::table('orders')->where('id', $orderId)->update(['delivery_address_source' => null, 'delivery_address_line' => null]);
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('orders', 'delivery_address_source'));
        $this->migration()->up();
    }

    public function test_a_column_with_the_wrong_type_is_refused(): void
    {
        $migration = $this->migration();
        $migration->down();

        // Every column, index and key exists, but delivery_latitude is a string instead of a decimal.
        Schema::table('orders', function ($table) {
            $table->string('delivery_address_source', 20)->nullable();
            $table->string('delivery_recipient_name')->nullable();
            $table->string('delivery_phone', 32)->nullable();
            $table->unsignedBigInteger('delivery_governorate_id')->nullable();
            $table->string('delivery_governorate_name')->nullable();
            $table->unsignedBigInteger('delivery_city_id')->nullable();
            $table->string('delivery_city_name')->nullable();
            $table->string('delivery_address_line', 500)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('delivery_latitude')->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->index('delivery_governorate_id', 'orders_delivery_governorate_id_index');
            $table->foreign('delivery_governorate_id', 'orders_delivery_governorate_id_foreign')->references('id')->on('governorates')->nullOnDelete();
            $table->index('delivery_city_id', 'orders_delivery_city_id_index');
            $table->foreign('delivery_city_id', 'orders_delivery_city_id_foreign')->references('id')->on('cities')->nullOnDelete();
        });

        try {
            $migration->up();
            $this->fail('A wrong column type must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('delivery_latitude has type varchar, expected numeric', $e->getMessage());
        }

        // Restore the expected state so the suite's rollback can run.
        $migration->down();
        $migration->up();
        $this->assertSame('numeric', collect(Schema::getColumns('orders'))->firstWhere('name', 'delivery_latitude')['type_name']);
    }

    public function test_a_partial_or_unexpected_state_is_refused_without_changing_anything(): void
    {
        $migration = $this->migration();
        $migration->down();

        Schema::table('orders', fn ($table) => $table->string('delivery_phone', 32)->nullable());

        try {
            $migration->up();
            $this->fail('A partial state must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only part of the delivery address snapshot columns', $e->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('orders', 'delivery_address_source'), 'Nothing was added.');

        // Restore the expected state so the suite's rollback can run.
        Schema::table('orders', fn ($table) => $table->dropColumn('delivery_phone'));
        $migration->up();
        $this->assertSame(['cities', 'set null'], $this->reference('delivery_city_id'));
    }

    public function test_the_sqlite_rebuild_is_refused_inside_a_transaction(): void
    {
        $this->legacyOrderWithDependents();
        $before = $this->rows();

        DB::beginTransaction();

        try {
            $this->migration()->down();
            $this->fail('Rebuilding inside a transaction must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inside a transaction', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertTrue(Schema::hasColumn('orders', 'delivery_address_source'));
        $this->assertSame($before, $this->rows());
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /** @return array{0: ?string, 1: ?string} [referenced table, on delete] */
    private function reference(string $column): array
    {
        $key = collect(Schema::getForeignKeys('orders'))->first(fn (array $fk) => $fk['columns'] === [$column]);

        return [$key['foreign_table'] ?? null, $key['on_delete'] ?? null];
    }

    /** An order created before the snapshot, with a vendor order and an item depending on it. */
    private function legacyOrderWithDependents(): int
    {
        $customer = Customer::factory()->create();
        $vendor = Vendor::factory()->approved()->create();
        $address = DB::table('customer_addresses')->insertGetId([
            'customer_id' => $customer->id, 'label' => 'Home', 'recipient_name' => 'R', 'phone' => '0599000000',
            'address_line' => 'Street', 'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'number' => 'LEGACY-1', 'customer_id' => $customer->id, 'customer_address_id' => $address, 'status' => 'pending',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vendorOrderId = DB::table('vendor_orders')->insertGetId([
            'number' => 'LEGACY-1-V1', 'order_id' => $orderId, 'vendor_id' => $vendor->id, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId, 'vendor_order_id' => $vendorOrderId, 'product_name' => 'Item', 'quantity' => 1,
            'unit_price' => 10, 'line_total' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $orderId;
    }

    /** @return array<string, mixed> the rows the migration must never change */
    private function rows(): array
    {
        $orderColumns = ['id', 'number', 'customer_id', 'customer_address_id', 'status', 'payment_method', 'payment_status', 'grand_total'];

        return [
            'orders' => DB::table('orders')->orderBy('id')->get($orderColumns)->map(fn ($row) => (array) $row)->all(),
            'vendor_orders' => DB::table('vendor_orders')->orderBy('id')->get(['id', 'order_id', 'status'])->map(fn ($row) => (array) $row)->all(),
            'order_items' => DB::table('order_items')->orderBy('id')->get(['id', 'order_id', 'vendor_order_id', 'quantity'])->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
