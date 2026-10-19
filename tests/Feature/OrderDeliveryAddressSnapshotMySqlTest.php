<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

/**
 * ORD-BE-001: MySQL path of 2026_10_18_000000_add_delivery_address_snapshot_to_orders_table (one ALTER TABLE, exact
 * column types, named indexes and keys ON DELETE SET NULL, rollback guard), and the checkout snapshot on MySQL.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the project's MySQL test variable; connection settings otherwise come from the mysql
 * connection, whose database is never used). Skipped otherwise, so the regular suite and the development database
 * are never touched.
 */
class OrderDeliveryAddressSnapshotMySqlTest extends TestCase
{
    use InteractsWithStorefront;

    private const CONNECTION = 'order_snapshot_mysql';

    private const MIGRATION = 'migrations/2026_10_18_000000_add_delivery_address_snapshot_to_orders_table.php';

    /** column => exact MySQL COLUMN_TYPE, in table order after customer_address_id */
    private const COLUMNS = [
        'delivery_address_source' => 'varchar(20)',
        'delivery_recipient_name' => 'varchar(255)',
        'delivery_phone' => 'varchar(32)',
        'delivery_governorate_id' => 'bigint unsigned',
        'delivery_governorate_name' => 'varchar(255)',
        'delivery_city_id' => 'bigint unsigned',
        'delivery_city_name' => 'varchar(255)',
        'delivery_address_line' => 'varchar(500)',
        'delivery_notes' => 'text',
        'delivery_latitude' => 'decimal(10,7)',
        'delivery_longitude' => 'decimal(10,7)',
    ];

    /** Tables this test writes to, emptied before and after each test (isolated database only). */
    private const TOUCHED_TABLES = [
        'order_status_histories', 'stock_reservations', 'payments', 'order_items', 'vendor_orders', 'orders',
        'cart_items', 'carts', 'customer_addresses', 'product_images', 'products', 'categories',
        'vendor_profiles', 'vendors', 'customers', 'cities', 'governorates',
    ];

    private ?string $previousConnection = null;

    private string $database = '';

    /** @var list<string> */
    private array $statements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($this->database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($this->database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['url' => null, 'database' => $this->database])]);
        DB::purge(self::CONNECTION);
        // The connection must be on the isolated database before anything is written.
        $this->assertSame($this->database, DB::connection(self::CONNECTION)->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        $this->assertSame($this->database, DB::selectOne('SELECT DATABASE() AS d')->d, 'The default connection is the isolated database.');

        DB::connection(self::CONNECTION)->listen(function ($query) {
            if (str_starts_with(strtoupper(ltrim($query->sql)), 'ALTER TABLE')) {
                $this->statements[] = $query->sql;
            }
        });
        $this->emptyTouchedTables();
    }

    protected function tearDown(): void
    {
        if ($this->previousConnection !== null) {
            $this->emptyTouchedTables();
            $this->migration()->up(); // Leave the isolated database in the migrated state.
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_the_columns_indexes_and_keys_are_exactly_as_designed(): void
    {
        $columns = collect(DB::select(
            'SELECT COLUMN_NAME AS c, COLUMN_TYPE AS t, IS_NULLABLE AS n, COLUMN_DEFAULT AS d, ORDINAL_POSITION AS p
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', ['orders']
        ));

        $delivery = $columns->filter(fn ($c) => str_starts_with($c->c, 'delivery_') && $c->c !== 'delivery_total')->values();
        $this->assertSame(self::COLUMNS, $delivery->mapWithKeys(fn ($c) => [$c->c => $c->t])->all(), 'Exact types and order.');
        foreach ($delivery as $column) {
            $this->assertSame('YES', $column->n, "{$column->c} is nullable");
            $this->assertNull($column->d, "{$column->c} has no default");
        }
        $reference = $columns->firstWhere('c', 'customer_address_id');
        $this->assertSame($reference->p + 1, $delivery->first()->p, 'The snapshot follows customer_address_id.');

        $indexes = collect(DB::select(
            "SELECT INDEX_NAME AS i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME LIKE 'orders\\_delivery\\_%' GROUP BY INDEX_NAME ORDER BY INDEX_NAME"
        ))->mapWithKeys(fn ($r) => [$r->i => $r->c])->all();
        $this->assertSame([
            'orders_delivery_city_id_index' => 'delivery_city_id',
            'orders_delivery_governorate_id_index' => 'delivery_governorate_id',
        ], $indexes);

        $this->assertSame([
            'orders.delivery_city_id' => 'orders_delivery_city_id_foreign:cities.id:SET NULL',
            'orders.delivery_governorate_id' => 'orders_delivery_governorate_id_foreign:governorates.id:SET NULL',
        ], $this->deliveryKeys());
    }

    public function test_a_mysql_checkout_snapshot_survives_city_and_governorate_deletion(): void
    {
        $governorate = Governorate::query()->forceCreate(['name' => 'الخليل', 'slug' => 'hebron', 'status' => 'active']);
        $city = City::query()->forceCreate(['governorate_id' => $governorate->id, 'name' => 'دورا', 'slug' => 'dura', 'status' => 'active']);
        $other = City::query()->forceCreate(['governorate_id' => $governorate->id, 'name' => 'يطا', 'slug' => 'yatta', 'status' => 'active']);
        $customer = Customer::factory()->create();
        $first = $this->checkout($customer, $this->address($customer, $city->id), 'first');
        $second = $this->checkout($customer, $this->address($customer, $other->id), 'second');

        $row = DB::table('orders')->where('id', $first->id)->first();
        $this->assertSame(['checkout', 'ليلى أحمد', $governorate->id, 'الخليل', $city->id, 'دورا', '31.5326000', '35.0998000'], [
            $row->delivery_address_source, $row->delivery_recipient_name, (int) $row->delivery_governorate_id, $row->delivery_governorate_name,
            (int) $row->delivery_city_id, $row->delivery_city_name, $row->delivery_latitude, $row->delivery_longitude,
        ]);

        $city->delete();
        $first->refresh();
        $this->assertNull($first->delivery_city_id, 'ON DELETE SET NULL on MySQL.');
        $this->assertSame('دورا', $first->delivery_city_name);
        $this->assertSame($governorate->id, (int) $first->delivery_governorate_id);

        $governorate->delete(); // cascades to its remaining city
        $second->refresh();
        $this->assertNull($second->delivery_governorate_id);
        $this->assertNull($second->delivery_city_id);
        $this->assertSame(['الخليل', 'يطا'], [$second->delivery_governorate_name, $second->delivery_city_name]);
        $this->assertSame('شارع الصف، بناية 4', $second->deliveryAddress()->addressLine);
    }

    public function test_rollback_is_refused_for_any_recorded_source_and_up_is_one_alter_then_idempotent(): void
    {
        $customer = Customer::factory()->create();
        $orderId = DB::table('orders')->insertGetId([
            'number' => 'MYSQL-1', 'customer_id' => $customer->id, 'status' => 'pending', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = $this->migration();

        foreach (['checkout', 'none'] as $source) {
            DB::table('orders')->where('id', $orderId)->update(['delivery_address_source' => $source]);
            $this->statements = [];

            try {
                $migration->down();
                $this->fail("The rollback must be refused ({$source}).");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('hold a recorded delivery address', $e->getMessage());
            }

            $this->assertSame([], $this->statements, "{$source}: no DDL when refused.");
            $this->assertSame($source, DB::table('orders')->where('id', $orderId)->value('delivery_address_source'));
        }

        DB::table('orders')->where('id', $orderId)->update(['delivery_address_source' => null]);
        $this->statements = [];
        $migration->down();
        $this->assertCount(1, $this->statements, 'down() is one ALTER: '.implode(' || ', $this->statements));
        $this->assertSame([], $this->deliveryKeys());
        $this->assertSame(0, $this->countDeliveryColumns());
        $this->assertSame(1, DB::table('orders')->where('id', $orderId)->count(), 'The order row is kept.');

        $this->statements = [];
        $migration->up();
        $this->assertCount(1, $this->statements, 'up() is one ALTER: '.implode(' || ', $this->statements));
        $this->assertSame(count(self::COLUMNS), $this->countDeliveryColumns());
        $this->assertCount(2, $this->deliveryKeys());

        $this->statements = [];
        $migration->up();
        $this->assertSame([], $this->statements, 'A completed migration re-runs without any DDL.');
    }

    public function test_partial_and_mistyped_states_are_refused_without_ddl(): void
    {
        $migration = $this->migration();
        $migration->down();

        // Partial: one column only.
        DB::statement('ALTER TABLE `orders` ADD COLUMN `delivery_phone` VARCHAR(32) NULL');
        $this->statements = [];

        try {
            $migration->up();
            $this->fail('A partial state must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only part of the delivery address snapshot columns', $e->getMessage());
        }

        $this->assertSame([], $this->statements, 'No DDL after a refused partial state.');
        DB::statement('ALTER TABLE `orders` DROP COLUMN `delivery_phone`');

        // Mistyped: every column, index and key present, but delivery_phone is VARCHAR(64).
        $migration->up();
        DB::statement('ALTER TABLE `orders` MODIFY `delivery_phone` VARCHAR(64) NULL');
        $this->statements = [];

        try {
            $migration->up();
            $this->fail('A wrong column type must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('delivery_phone has type varchar(64), expected varchar(32)', $e->getMessage());
        }

        $this->assertSame([], $this->statements, 'No DDL after a refused type.');
        DB::statement('ALTER TABLE `orders` MODIFY `delivery_phone` VARCHAR(32) NULL');
        $migration->up(); // verifies the restored state
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /** @return array<string, string> "table.column" => "name:referenced.column:DELETE_RULE" */
    private function deliveryKeys(): array
    {
        return collect(DB::select(
            "SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c, r.CONSTRAINT_NAME AS n, k.REFERENCED_TABLE_NAME AS rt, k.REFERENCED_COLUMN_NAME AS rc, r.DELETE_RULE AS d
             FROM information_schema.REFERENTIAL_CONSTRAINTS r
             JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
             WHERE r.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'orders' AND k.COLUMN_NAME LIKE 'delivery\\_%' ORDER BY k.COLUMN_NAME"
        ))->mapWithKeys(fn ($r) => ["{$r->t}.{$r->c}" => "{$r->n}:{$r->rt}.{$r->rc}:{$r->d}"])->all();
    }

    private function countDeliveryColumns(): int
    {
        return (int) DB::selectOne(
            "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME LIKE 'delivery\\_%' AND COLUMN_NAME <> 'delivery_total'"
        )->n;
    }

    private function address(Customer $customer, int $cityId)
    {
        return $customer->addresses()->create([
            'label' => 'Home', 'recipient_name' => 'ليلى أحمد', 'phone' => '0599123456',
            'governorate_id' => City::query()->findOrFail($cityId)->governorate_id, 'city_id' => $cityId,
            'address_line' => 'شارع الصف، بناية 4', 'latitude' => 31.5326, 'longitude' => 35.0998,
        ]);
    }

    private function checkout(Customer $customer, $address, string $slug): Order
    {
        $product = $this->storefrontProduct($slug);
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $id = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['customer_address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated()->json('id');

        return Order::query()->findOrFail($id);
    }

    private function emptyTouchedTables(): void
    {
        $connection = DB::connection(self::CONNECTION);
        // Isolated database only (asserted in setUp); foreign key checks are off for this session's cleanup only.
        $this->assertSame($this->database, $connection->selectOne('SELECT DATABASE() AS d')->d);
        $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::TOUCHED_TABLES as $table) {
                $connection->table($table)->truncate();
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
