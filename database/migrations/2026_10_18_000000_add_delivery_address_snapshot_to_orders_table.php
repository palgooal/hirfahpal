<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * ORD-BE-001 (CUSTOMER-ACCOUNT-A1, docs/reviews/CUSTOMER-ACCOUNT-A1-DESIGN.md): the delivery address of an order,
 * copied from the customer's saved address inside the checkout transaction, so later edits or deletion of the
 * saved address, its city or its governorate never change it. All columns are nullable and no existing row is
 * written: orders created before this migration keep delivery_address_source NULL (legacy, no backfill).
 * orders.customer_address_id is unchanged and stays an optional reference.
 *
 * delivery_address_source: 'checkout' (snapshot captured), 'none' (order placed without an address, JSON checkout),
 * NULL (legacy). The governorate and city ids reference their tables ON DELETE SET NULL; their names are copied
 * beside them and are what is displayed.
 *
 * MySQL: every column, index and key is added by ONE "ALTER TABLE", applied atomically. SQLite (test suite):
 * adding a foreign key makes Laravel rebuild the table, which is only safe outside a transaction (see
 * 2026_10_10_000000_restrict_category_foreign_keys): orders has cascading dependents. The target state is checked
 * before (an already migrated table is skipped, a partial or different state is refused) and after: nullability,
 * column types (MySQL: exact type with length/precision; SQLite: type family), both indexes and both keys.
 *
 * down() refuses while any order has a delivery_address_source (a snapshot or the "none" marker): dropping the
 * columns would erase delivery history.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const TABLE = 'orders';

    /** column => MySQL definition, in table order after customer_address_id. */
    private const COLUMNS = [
        'delivery_address_source' => 'VARCHAR(20) NULL',
        'delivery_recipient_name' => 'VARCHAR(255) NULL',
        'delivery_phone' => 'VARCHAR(32) NULL',
        'delivery_governorate_id' => 'BIGINT UNSIGNED NULL',
        'delivery_governorate_name' => 'VARCHAR(255) NULL',
        'delivery_city_id' => 'BIGINT UNSIGNED NULL',
        'delivery_city_name' => 'VARCHAR(255) NULL',
        'delivery_address_line' => 'VARCHAR(500) NULL',
        'delivery_notes' => 'TEXT NULL',
        'delivery_latitude' => 'DECIMAL(10,7) NULL',
        'delivery_longitude' => 'DECIMAL(10,7) NULL',
    ];

    /** column => expected type as reported by Schema::getColumns(): [MySQL "type", SQLite "type_name"] */
    private const TYPES = [
        'delivery_address_source' => ['varchar(20)', 'varchar'],
        'delivery_recipient_name' => ['varchar(255)', 'varchar'],
        'delivery_phone' => ['varchar(32)', 'varchar'],
        'delivery_governorate_id' => ['bigint unsigned', 'integer'],
        'delivery_governorate_name' => ['varchar(255)', 'varchar'],
        'delivery_city_id' => ['bigint unsigned', 'integer'],
        'delivery_city_name' => ['varchar(255)', 'varchar'],
        'delivery_address_line' => ['varchar(500)', 'varchar'],
        'delivery_notes' => ['text', 'text'],
        'delivery_latitude' => ['decimal(10,7)', 'numeric'],
        'delivery_longitude' => ['decimal(10,7)', 'numeric'],
    ];

    /** column => [referenced table, index name, foreign key name] */
    private const REFERENCES = [
        'delivery_governorate_id' => ['governorates', 'orders_delivery_governorate_id_index', 'orders_delivery_governorate_id_foreign'],
        'delivery_city_id' => ['cities', 'orders_delivery_city_id_index', 'orders_delivery_city_id_foreign'],
    ];

    public function up(): void
    {
        $present = $this->presentColumns();

        if ($present === array_keys(self::COLUMNS)) {
            $this->assertTargetState();

            return; // Already migrated.
        }

        if ($present !== []) {
            throw new RuntimeException('orders has only part of the delivery address snapshot columns ('.implode(', ', $present).'). Nothing was changed.');
        }

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();
            $parts = [];
            $after = 'customer_address_id';

            foreach (self::COLUMNS as $column => $definition) {
                $parts[] = sprintf('ADD COLUMN %s %s AFTER %s', $grammar->wrap($column), $definition, $grammar->wrap($after));
                $after = $column;
            }

            foreach (self::REFERENCES as $column => [$table, $index, $foreign]) {
                $parts[] = sprintf('ADD INDEX %s (%s)', $grammar->wrap($index), $grammar->wrap($column));
                $parts[] = sprintf(
                    'ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE SET NULL',
                    $grammar->wrap($foreign), $grammar->wrap($column), $grammar->wrapTable($table), $grammar->wrap('id')
                );
            }

            DB::statement('ALTER TABLE '.$grammar->wrapTable(self::TABLE).' '.implode(', ', $parts));
        } else {
            $this->refuseInsideTransaction();

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('delivery_address_source', 20)->nullable();
                $table->string('delivery_recipient_name')->nullable();
                $table->string('delivery_phone', 32)->nullable();
                $table->unsignedBigInteger('delivery_governorate_id')->nullable();
                $table->string('delivery_governorate_name')->nullable();
                $table->unsignedBigInteger('delivery_city_id')->nullable();
                $table->string('delivery_city_name')->nullable();
                $table->string('delivery_address_line', 500)->nullable();
                $table->text('delivery_notes')->nullable();
                $table->decimal('delivery_latitude', 10, 7)->nullable();
                $table->decimal('delivery_longitude', 10, 7)->nullable();

                foreach (self::REFERENCES as $column => [$referenced, $index, $foreign]) {
                    $table->index($column, $index);
                    $table->foreign($column, $foreign)->references('id')->on($referenced)->nullOnDelete();
                }
            });

            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('SQLite foreign key check failed after adding the orders delivery address snapshot.');
            }
        }

        $this->assertTargetState();
    }

    public function down(): void
    {
        $present = $this->presentColumns();

        if ($present === []) {
            return;
        }

        // Any recorded source (a snapshot, or the "none" marker of an order placed without an address) is delivery
        // history that a rollback would erase or turn into a legacy order.
        $recorded = in_array('delivery_address_source', $present, true)
            ? DB::table(self::TABLE)->whereNotNull('delivery_address_source')->count()
            : 0;

        if ($recorded > 0) {
            throw new RuntimeException(sprintf('%d order(s) hold a recorded delivery address (snapshot or "none"); rolling back would erase it. Nothing was changed.', $recorded));
        }

        $keys = collect(Schema::getForeignKeys(self::TABLE))->map(fn (array $fk) => $fk['columns'])->all();
        $indexes = collect(Schema::getIndexes(self::TABLE))->pluck('name')->all();

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();
            $parts = [];

            foreach (self::REFERENCES as $column => [, $index, $foreign]) {
                if (in_array([$column], $keys, true)) {
                    $parts[] = 'DROP FOREIGN KEY '.$grammar->wrap($foreign);
                }
                if (in_array($index, $indexes, true)) {
                    $parts[] = 'DROP INDEX '.$grammar->wrap($index);
                }
            }

            foreach ($present as $column) {
                $parts[] = 'DROP COLUMN '.$grammar->wrap($column);
            }

            DB::statement('ALTER TABLE '.$grammar->wrapTable(self::TABLE).' '.implode(', ', $parts));

            return;
        }

        $this->refuseInsideTransaction();

        Schema::table(self::TABLE, function (Blueprint $table) use ($present, $keys, $indexes) {
            foreach (self::REFERENCES as $column => [, $index]) {
                if (in_array([$column], $keys, true)) {
                    $table->dropForeign([$column]); // SQLite keys have no name; dropped by column.
                }
                if (in_array($index, $indexes, true)) {
                    $table->dropIndex($index);
                }
            }

            $table->dropColumn($present);
        });
    }

    /** @return list<string> the snapshot columns that exist, in COLUMNS order */
    private function presentColumns(): array
    {
        return array_values(array_filter(array_keys(self::COLUMNS), fn (string $column) => Schema::hasColumn(self::TABLE, $column)));
    }

    private function assertTargetState(): void
    {
        $columns = collect(Schema::getColumns(self::TABLE))->keyBy('name');
        $mysql = DB::getDriverName() === 'mysql';

        foreach (self::TYPES as $column => [$mysqlType, $sqliteType]) {
            $definition = $columns->get($column);

            if ($definition === null || $definition['nullable'] !== true) {
                throw new RuntimeException("orders.{$column} is missing or not nullable. Nothing was changed.");
            }

            // MySQL reports the full type (length, precision, unsigned); SQLite only the type family.
            $actual = strtolower($mysql ? (string) $definition['type'] : (string) $definition['type_name']);
            $expected = $mysql ? $mysqlType : $sqliteType;

            if (! ($mysql ? $this->mysqlTypeMatches($actual, $expected) : $actual === $expected)) {
                throw new RuntimeException("orders.{$column} has type {$actual}, expected {$expected}. Nothing was changed.");
            }
        }

        $indexes = collect(Schema::getIndexes(self::TABLE))->keyBy('name');

        foreach (self::REFERENCES as $column => [, $index]) {
            if (($indexes->get($index)['columns'] ?? null) !== [$column]) {
                throw new RuntimeException("orders is missing the index {$index} on {$column}. Nothing was changed.");
            }
        }

        $keys = collect(Schema::getForeignKeys(self::TABLE));

        foreach (self::REFERENCES as $column => [$table, , $foreign]) {
            $key = $keys->first(fn (array $fk) => $fk['columns'] === [$column]);

            $valid = $key !== null
                && $key['foreign_table'] === $table
                && $key['foreign_columns'] === ['id']
                && $key['on_delete'] === 'set null'
                && (DB::getDriverName() !== 'mysql' || $key['name'] === $foreign);

            if (! $valid) {
                throw new RuntimeException("orders.{$column} exists but is not the expected nullable reference to {$table}.id ON DELETE SET NULL. Nothing was changed.");
            }
        }
    }

    /**
     * MariaDB (and MySQL before 8.0.19) still report the integer display width: BIGINT UNSIGNED is
     * "bigint(20) unsigned" there and "bigint unsigned" on MySQL 8.0.19+. Only that default width is accepted
     * for BIGINT UNSIGNED; every other type must match exactly.
     */
    private function mysqlTypeMatches(string $actual, string $expected): bool
    {
        if ($expected === 'bigint unsigned') {
            return in_array($actual, ['bigint unsigned', 'bigint(20) unsigned'], true);
        }

        return $actual === $expected;
    }

    private function refuseInsideTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Refusing to rebuild orders on SQLite inside a transaction: foreign key enforcement cannot be suspended there, so dependent rows could be changed.');
        }
    }
};
