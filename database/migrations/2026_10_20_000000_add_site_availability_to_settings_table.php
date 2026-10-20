<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * SITE-AVAILABILITY (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §6.1): the stored availability state on the
 * settings singleton. site_availability_mode is NOT NULL with the safe default 'open' (an existing installation keeps
 * its behaviour), limited by a named CHECK to exactly 'open', 'coming_soon' or 'maintenance'. The change date and the
 * admin who made it are nullable; the admin reference is cleared if that admin is deleted. No existing row is
 * otherwise written.
 *
 * Exact values (SITE-AVAILABILITY-MIGRATION-FIX-01): the table collation (utf8mb4_unicode_ci) is case-insensitive and
 * ignores trailing spaces, so on MySQL/MariaDB the CHECK compares the bytes: CAST(column AS BINARY) IN (...). That
 * rejects 'OPEN' and 'maintenance ' without depending on a version-specific column collation. SQLite compares text
 * exactly by default (and CAST AS BINARY would give a number there), so its CHECK is a plain IN.
 *
 * MySQL/MariaDB: every column, the foreign key and the CHECK are added by ONE "ALTER TABLE", which either completes or
 * changes nothing. SQLite: the foreign key rebuilds the table, so the mode column and its column CHECK are added after
 * it (a rebuild would drop a CHECK added before).
 *
 * Partial states (MySQL verification): up() does nothing on a fully applied schema (after checking it) and refuses a
 * partial one without changing anything. down() removes only what this migration owns and only what exists, so it
 * also clears a partial state; unrelated columns, constraints and data are never touched.
 */
return new class extends Migration
{
    private const TABLE = 'settings';

    private const MODE = 'site_availability_mode';

    private const CHANGED_AT = 'site_availability_changed_at';

    private const CHANGED_BY = 'site_availability_changed_by';

    /** The owned columns, in table order. */
    private const COLUMNS = [self::MODE, self::CHANGED_AT, self::CHANGED_BY];

    private const CHECK = 'settings_site_availability_mode_check';

    private const FOREIGN = 'settings_site_availability_changed_by_foreign';

    private const STATES = ['open', 'coming_soon', 'maintenance'];

    public function up(): void
    {
        $present = $this->presentColumns();

        if ($present === self::COLUMNS) {
            $this->assertTargetState();

            return; // Already applied.
        }

        if ($present !== [] || $this->checkExists() || $this->foreignKeyExists()) {
            throw new RuntimeException(sprintf(
                'settings is partly migrated for site availability (columns: %s; check: %s; foreign key: %s). Nothing was changed. Roll this migration back (its down() removes only what exists), then migrate again.',
                $present === [] ? 'none' : implode(', ', $present),
                $this->checkExists() ? 'yes' : 'no',
                $this->foreignKeyExists() ? 'yes' : 'no',
            ));
        }

        $grammar = DB::getQueryGrammar();
        $mode = $grammar->wrap(self::MODE);
        $states = "'".implode("', '", self::STATES)."'";

        if ($this->isMySqlFamily()) {
            DB::statement(sprintf(
                "ALTER TABLE %s ADD COLUMN %s VARCHAR(20) NOT NULL DEFAULT 'open', ADD COLUMN %s TIMESTAMP NULL, ADD COLUMN %s BIGINT UNSIGNED NULL, "
                .'ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE SET NULL, ADD CONSTRAINT %s CHECK (CAST(%s AS BINARY) IN (%s))',
                $grammar->wrapTable(self::TABLE), $mode, $grammar->wrap(self::CHANGED_AT), $grammar->wrap(self::CHANGED_BY),
                $grammar->wrap(self::FOREIGN), $grammar->wrap(self::CHANGED_BY), $grammar->wrapTable('admins'), $grammar->wrap('id'),
                $grammar->wrap(self::CHECK), $mode, $states,
            ));
        } else {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->timestamp(self::CHANGED_AT)->nullable();
                $table->foreignId(self::CHANGED_BY)->nullable()->constrained('admins')->nullOnDelete();
            });

            DB::statement(sprintf(
                "ALTER TABLE %s ADD COLUMN %s VARCHAR(20) NOT NULL DEFAULT 'open' CONSTRAINT %s CHECK (%s IN (%s))",
                $grammar->wrapTable(self::TABLE), $mode, $grammar->wrap(self::CHECK), $mode, $states,
            ));
        }

        $this->assertTargetState();
    }

    public function down(): void
    {
        $present = $this->presentColumns();
        $check = $this->checkExists();
        $foreign = $this->foreignKeyExists();

        if ($this->isMySqlFamily()) {
            $grammar = DB::getQueryGrammar();
            $parts = [];

            if ($check) {
                $parts[] = 'DROP CONSTRAINT '.$grammar->wrap(self::CHECK);
            }
            if ($foreign) {
                $parts[] = 'DROP FOREIGN KEY '.$grammar->wrap(self::FOREIGN);
            }
            if ($this->indexExists() && ! in_array(self::CHANGED_BY, $present, true)) {
                // An orphaned index of ours whose column is already gone (dropping the column removes it otherwise).
                $parts[] = 'DROP INDEX '.$grammar->wrap(self::FOREIGN);
            }
            foreach ($present as $column) {
                $parts[] = 'DROP COLUMN '.$grammar->wrap($column);
            }

            if ($parts !== []) {
                DB::statement('ALTER TABLE '.$grammar->wrapTable(self::TABLE).' '.implode(', ', $parts));
            }

            return;
        }

        // SQLite: the mode column (with its column CHECK) first, natively, before any rebuild; then the foreign key
        // (rebuild), then the remaining columns.
        if (in_array(self::MODE, $present, true)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn(self::MODE));
        }
        if ($foreign) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropForeign([self::CHANGED_BY]));
        }
        $rest = array_values(array_intersect([self::CHANGED_BY, self::CHANGED_AT], $present));
        if ($rest !== []) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($rest));
        }
    }

    private function isMySqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** @return list<string> the owned columns that exist, in COLUMNS order */
    private function presentColumns(): array
    {
        return array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn(self::TABLE, $column)));
    }

    private function checkExists(): bool
    {
        if ($this->isMySqlFamily()) {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', self::TABLE)
                ->where('CONSTRAINT_NAME', self::CHECK)
                ->where('CONSTRAINT_TYPE', 'CHECK')
                ->exists();
        }

        $sql = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', self::TABLE)->value('sql');

        return str_contains($sql, self::CHECK);
    }

    private function foreignKeyExists(): bool
    {
        return collect(Schema::getForeignKeys(self::TABLE))->contains(fn (array $key) => $key['columns'] === [self::CHANGED_BY]);
    }

    private function indexExists(): bool
    {
        return collect(Schema::getIndexes(self::TABLE))->contains(fn (array $index) => $index['name'] === self::FOREIGN);
    }

    /** Verifies the applied schema exactly; throws (without changing anything) on any difference. */
    private function assertTargetState(): void
    {
        $columns = collect(Schema::getColumns(self::TABLE))->keyBy('name');
        $mysql = $this->isMySqlFamily();
        $expected = [
            self::MODE => [$mysql ? 'varchar(20)' : 'varchar', false],
            self::CHANGED_AT => [$mysql ? 'timestamp' : 'datetime', true],
            self::CHANGED_BY => [$mysql ? 'bigint unsigned' : 'integer', true],
        ];

        foreach ($expected as $column => [$type, $nullable]) {
            $definition = $columns->get($column);
            $actual = strtolower((string) ($mysql ? ($definition['type'] ?? '') : ($definition['type_name'] ?? '')));

            if ($definition === null || $actual !== $type || $definition['nullable'] !== $nullable) {
                throw new RuntimeException("settings.{$column} is missing or not {$type}".($nullable ? ' NULL' : ' NOT NULL').'. Nothing was changed.');
            }
        }

        if (! str_contains((string) $columns->get(self::MODE)['default'], 'open')) {
            throw new RuntimeException('settings.site_availability_mode does not default to open. Nothing was changed.');
        }

        $key = collect(Schema::getForeignKeys(self::TABLE))->first(fn (array $fk) => $fk['columns'] === [self::CHANGED_BY]);
        if ($key === null || $key['foreign_table'] !== 'admins' || $key['foreign_columns'] !== ['id'] || $key['on_delete'] !== 'set null'
            || ($mysql && $key['name'] !== self::FOREIGN)) {
            throw new RuntimeException('settings.site_availability_changed_by is not the expected reference to admins.id ON DELETE SET NULL. Nothing was changed.');
        }

        if (! $this->checkExists()) {
            throw new RuntimeException('settings is missing the '.self::CHECK.' constraint. Nothing was changed.');
        }
    }
};
