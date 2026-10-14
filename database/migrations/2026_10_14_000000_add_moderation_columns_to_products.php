<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * AD-UI-PROD-M1 (OPEN-PROD-09, OPEN-PROD-04): the current administrative state of a product, kept apart from
 * products.status (which the vendor controls). products.suspended_at (an administrative suspension, DEC-PROD-01,
 * open-ended per DEC-PROD-07) and products.archived_at (archiving instead of deletion, OPEN-PROD-04), both nullable
 * timestamps, NULL for every existing product, each indexed for the admin list filters. The reason and the actor
 * of every change live in product_admin_actions, not here.
 *
 * The columns sit on the product row on purpose (C-13): checkout re-checks sellability on the products rows it
 * has locked FOR UPDATE, so the state it reads is the locked row's own. No foreign key is added to products.
 *
 * MySQL: both columns and both indexes are added by ONE "ALTER TABLE" (atomic DDL on MySQL 8). SQLite: plain
 * "ALTER TABLE ADD COLUMN", no table rebuild. The state is checked before (an already migrated table is skipped,
 * any other state of these columns is refused) and after. No existing row is written.
 *
 * down() refuses while any product is suspended or archived: dropping the columns would silently lift those
 * decisions. On SQLite it also refuses versions without native DROP COLUMN (< 3.35), which would rebuild the table.
 */
return new class extends Migration
{
    private const COLUMNS = ['suspended_at', 'archived_at'];

    private const INDEXES = ['suspended_at' => 'products_suspended_at_index', 'archived_at' => 'products_archived_at_index'];

    public function up(): void
    {
        $present = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('products', $column)));

        if ($present === self::COLUMNS) {
            $this->assertTargetState();

            return; // Already migrated.
        }

        if ($present !== []) {
            throw new RuntimeException('products has only part of the moderation columns ('.implode(', ', $present).'). Nothing was changed.');
        }

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();

            DB::statement(sprintf(
                'ALTER TABLE %1$s ADD COLUMN %2$s TIMESTAMP NULL DEFAULT NULL AFTER %3$s, ADD COLUMN %4$s TIMESTAMP NULL DEFAULT NULL AFTER %2$s, ADD INDEX %5$s (%2$s), ADD INDEX %6$s (%4$s)',
                $grammar->wrapTable('products'),
                $grammar->wrap('suspended_at'),
                $grammar->wrap('published_at'),
                $grammar->wrap('archived_at'),
                $grammar->wrap(self::INDEXES['suspended_at']),
                $grammar->wrap(self::INDEXES['archived_at'])
            ));
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('suspended_at')->nullable()->after('published_at');
                $table->timestamp('archived_at')->nullable()->after('suspended_at');
                $table->index('suspended_at', self::INDEXES['suspended_at']);
                $table->index('archived_at', self::INDEXES['archived_at']);
            });
        }

        $this->assertTargetState();
    }

    public function down(): void
    {
        $present = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('products', $column)));

        if ($present === []) {
            return;
        }

        $kept = DB::table('products')->where(fn ($query) => $query->whereNotNull('suspended_at')->orWhereNotNull('archived_at'))->count();

        if ($kept > 0) {
            throw new RuntimeException(sprintf('%d product(s) are suspended or archived; rolling back would lift those decisions. Nothing was changed.', $kept));
        }

        $indexes = collect(Schema::getIndexes('products'))->pluck('name')->all();

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();
            $parts = [];

            foreach (self::INDEXES as $column => $index) {
                if (in_array($index, $indexes, true)) {
                    $parts[] = 'DROP INDEX '.$grammar->wrap($index);
                }
            }

            foreach ($present as $column) {
                $parts[] = 'DROP COLUMN '.$grammar->wrap($column);
            }

            DB::statement('ALTER TABLE '.$grammar->wrapTable('products').' '.implode(', ', $parts));

            return;
        }

        if (version_compare((string) DB::connection()->getPdo()->query('select sqlite_version()')->fetchColumn(), '3.35', '<')) {
            throw new RuntimeException('This SQLite version has no native DROP COLUMN; rolling back would rebuild products. Nothing was changed.');
        }

        Schema::table('products', function (Blueprint $table) use ($present, $indexes) {
            foreach (self::INDEXES as $index) {
                if (in_array($index, $indexes, true)) {
                    $table->dropIndex($index);
                }
            }

            $table->dropColumn($present);
        });
    }

    private function assertTargetState(): void
    {
        $columns = collect(Schema::getColumns('products'))->keyBy('name');
        $indexes = collect(Schema::getIndexes('products'))->keyBy('name');

        foreach (self::COLUMNS as $column) {
            $definition = $columns->get($column);
            $index = $indexes->get(self::INDEXES[$column]);

            $valid = $definition !== null
                && $definition['nullable'] === true
                && in_array(strtolower($definition['type_name']), ['timestamp', 'datetime'], true)
                && ($definition['default'] === null || strtoupper((string) $definition['default']) === 'NULL')
                && $index !== null
                && $index['columns'] === [$column];

            if (! $valid) {
                throw new RuntimeException("products.{$column} exists but is not the expected nullable, indexed timestamp. Nothing was changed.");
            }
        }
    }
};
