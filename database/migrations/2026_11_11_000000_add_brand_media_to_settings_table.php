<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * SETTINGS-MEDIA-01: the site logo and favicon become references to the media library, as categories.image_media_id
 * (AD-UI-CAT-M2-C). settings.logo_media_id and settings.favicon_media_id are nullable unsigned big integers (the type
 * of media.id), indexed, each with a foreign key to media.id ON DELETE RESTRICT: a media row used as the logo or the
 * favicon cannot be deleted at database level (MediaUsageGuard checks them first). The legacy path columns
 * settings.logo and settings.favicon are left untouched and nothing is copied from them.
 *
 * MySQL/MariaDB: both columns, their indexes and keys are added by ONE "ALTER TABLE", which either completes or changes
 * nothing. The target state is checked before (an already migrated table is skipped, any other state is refused) and
 * after.
 *
 * SQLite (test suite): Laravel would add a foreign key by rebuilding the table, and a rebuild drops the named CHECK of
 * settings.site_availability_mode (2026_10_20_000000). So each column is added natively ("ADD COLUMN ... REFERENCES",
 * no rebuild) and its index with CREATE INDEX. SQLite cannot drop a column that carries a foreign key, so down()
 * rebuilds the table itself from its stored definition with exactly the two appended column definitions removed,
 * keeping every other column, constraint, index and row.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const TABLE = 'settings';

    /** column => [index, foreign key name (MySQL), column it follows] */
    private const COLUMNS = [
        'logo_media_id' => ['settings_logo_media_id_index', 'settings_logo_media_restrict_fk', 'favicon'],
        'favicon_media_id' => ['settings_favicon_media_id_index', 'settings_favicon_media_restrict_fk', 'logo_media_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            throw new RuntimeException('The media table must exist before settings can reference it.');
        }

        $present = array_values(array_filter(array_keys(self::COLUMNS), fn (string $column) => Schema::hasColumn(self::TABLE, $column)));

        if ($present !== []) {
            if (count($present) !== count(self::COLUMNS)) {
                throw new RuntimeException('settings is partly migrated for the brand media (columns: '.implode(', ', $present).'). Nothing was changed.');
            }

            $this->assertTargetState();

            return; // Already migrated.
        }

        $grammar = DB::getQueryGrammar();

        if ($this->isMySqlFamily()) {
            $parts = [];
            foreach (self::COLUMNS as $column => [$index, $foreign, $after]) {
                $parts[] = sprintf('ADD COLUMN %s BIGINT UNSIGNED NULL AFTER %s', $grammar->wrap($column), $grammar->wrap($after));
                $parts[] = sprintf('ADD INDEX %s (%s)', $grammar->wrap($index), $grammar->wrap($column));
                $parts[] = sprintf('ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE RESTRICT',
                    $grammar->wrap($foreign), $grammar->wrap($column), $grammar->wrapTable('media'), $grammar->wrap('id'));
            }

            DB::statement('ALTER TABLE '.$grammar->wrapTable(self::TABLE).' '.implode(', ', $parts));
        } else {
            foreach (self::COLUMNS as $column => [$index]) {
                DB::statement('alter table '.$grammar->wrapTable(self::TABLE).' add column '.$this->sqliteDefinition($column));
                DB::statement(sprintf('create index %s on %s (%s)', $grammar->wrap($index), $grammar->wrapTable(self::TABLE), $grammar->wrap($column)));
            }

            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('SQLite foreign key check failed after adding the settings brand media columns.');
            }
        }

        $this->assertTargetState();
    }

    public function down(): void
    {
        $present = array_values(array_filter(array_keys(self::COLUMNS), fn (string $column) => Schema::hasColumn(self::TABLE, $column)));

        if ($present === []) {
            return;
        }

        $grammar = DB::getQueryGrammar();

        if ($this->isMySqlFamily()) {
            $parts = [];
            foreach ($present as $column) {
                [$index, $foreign] = self::COLUMNS[$column];
                if ($this->mySqlForeignKeyExists($foreign)) {
                    $parts[] = 'DROP FOREIGN KEY '.$grammar->wrap($foreign);
                }
                if (Schema::hasIndex(self::TABLE, $index)) {
                    $parts[] = 'DROP INDEX '.$grammar->wrap($index);
                }
                $parts[] = 'DROP COLUMN '.$grammar->wrap($column);
            }

            DB::statement('ALTER TABLE '.$grammar->wrapTable(self::TABLE).' '.implode(', ', $parts));

            return;
        }

        $this->sqliteRebuildWithout($present);
    }

    /** The SQLite column definition, as up() adds it and as SQLite stores it in the table's CREATE statement. */
    private function sqliteDefinition(string $column): string
    {
        $grammar = DB::getQueryGrammar();

        return sprintf('%s integer null references %s (%s) on delete restrict', $grammar->wrap($column), $grammar->wrapTable('media'), $grammar->wrap('id'));
    }

    /**
     * Rebuilds settings without $columns: the stored CREATE statement minus exactly their appended definitions (refused
     * when they are not found as added), the rows copied column by column, the other indexes recreated. Foreign keys are
     * off during the rebuild (only possible outside a transaction) and checked afterwards.
     *
     * @param  list<string>  $columns
     */
    private function sqliteRebuildWithout(array $columns): void
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Refusing to rebuild settings on SQLite inside a transaction: foreign key enforcement cannot be suspended there.');
        }

        $grammar = DB::getQueryGrammar();
        $sql = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', self::TABLE)->value('sql');
        $stripped = $sql;

        foreach ($columns as $column) {
            $definition = ', '.$this->sqliteDefinition($column);

            if (substr_count($stripped, $definition) !== 1) {
                throw new RuntimeException("settings.{$column} is not defined as this migration adds it. Nothing was changed.");
            }

            $stripped = str_replace($definition, '', $stripped);
        }

        $temporary = self::TABLE.'__brand_media_down';
        $create = preg_replace('/^CREATE TABLE\s+("?)'.self::TABLE.'\1/i', 'CREATE TABLE '.$grammar->wrapTable($temporary), $stripped, 1, $replaced);

        if ($replaced !== 1) {
            throw new RuntimeException('Unexpected CREATE statement for settings. Nothing was changed.');
        }

        $kept = implode(', ', array_map(fn (string $column) => $grammar->wrap($column),
            array_values(array_diff(Schema::getColumnListing(self::TABLE), $columns))));
        $ownIndexes = array_map(fn (string $column) => self::COLUMNS[$column][0], array_keys(self::COLUMNS));
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', self::TABLE)
            ->whereNotNull('sql')->whereNotIn('name', $ownIndexes)->pluck('sql')->all();

        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::transaction(function () use ($create, $temporary, $kept, $indexes, $grammar): void {
                DB::statement($create);
                DB::statement(sprintf('insert into %s (%s) select %s from %s', $grammar->wrapTable($temporary), $kept, $kept, $grammar->wrapTable(self::TABLE)));
                DB::statement('drop table '.$grammar->wrapTable(self::TABLE));
                DB::statement(sprintf('alter table %s rename to %s', $grammar->wrapTable($temporary), $grammar->wrapTable(self::TABLE)));

                foreach ($indexes as $index) {
                    DB::statement($index);
                }

                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('SQLite foreign key check failed after removing the settings brand media columns.');
                }
            });
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function assertTargetState(): void
    {
        $keys = collect(Schema::getForeignKeys(self::TABLE));
        $columns = collect(Schema::getColumns(self::TABLE))->keyBy('name');

        foreach (self::COLUMNS as $column => [$index, $foreign]) {
            $matching = $keys->filter(fn (array $fk) => $fk['columns'] === [$column])->values();

            $valid = $matching->count() === 1
                && $matching[0]['foreign_table'] === 'media'
                && $matching[0]['foreign_columns'] === ['id']
                && $matching[0]['on_delete'] === 'restrict'
                && (! $this->isMySqlFamily() || $matching[0]['name'] === $foreign)
                && ($columns[$column]['nullable'] ?? false)
                && Schema::hasIndex(self::TABLE, $index);

            if (! $valid) {
                throw new RuntimeException("settings.{$column} exists but is not the expected nullable, indexed reference to media.id ON DELETE RESTRICT. Nothing was changed.");
            }
        }
    }

    private function mySqlForeignKeyExists(string $name): bool
    {
        return collect(Schema::getForeignKeys(self::TABLE))->contains(fn (array $fk) => $fk['name'] === $name);
    }

    private function isMySqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
