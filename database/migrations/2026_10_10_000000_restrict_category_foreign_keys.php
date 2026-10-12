<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DEC-CAT-Q3 (AD-UI-CAT-01C-1D): a category that has products, or a parent that has children, can no
 * longer be deleted at database level. products.category_id and categories.parent_id change from
 * ON DELETE SET NULL to ON DELETE RESTRICT.
 *
 * MySQL: each key is replaced by ONE "ALTER TABLE … DROP FOREIGN KEY …, ADD CONSTRAINT …" statement, which
 * MySQL applies atomically, so the table is never left without a foreign key (verified on MySQL 8.4.3 in
 * AD-UI-CAT-01C-1C). The new constraints have explicit names. Each key is checked before and after, keys
 * already in the target state are skipped (the migration can be re-run after a partial run), and any other
 * schema state is refused instead of guessed. Foreign key checks are never disabled.
 *
 * SQLite (test suite): SQLite cannot alter a foreign key, so Laravel rebuilds the table. The rebuild relies on
 * Laravel switching SQLite foreign key enforcement off for its copy-and-swap, which SQLite ignores inside a
 * transaction; dropping the old table with enforcement on would fire ON DELETE actions on dependent tables.
 * The migration therefore runs outside a transaction and refuses to rebuild while one is open.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** @var list<array{table: string, column: string, legacy: string, restrict: string}> */
    private const KEYS = [
        ['table' => 'products', 'column' => 'category_id', 'legacy' => 'products_category_id_foreign', 'restrict' => 'products_category_restrict_fk'],
        ['table' => 'categories', 'column' => 'parent_id', 'legacy' => 'categories_parent_id_foreign', 'restrict' => 'categories_parent_restrict_fk'],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $this->replace($key, from: [$key['legacy'], 'set null'], to: [$key['restrict'], 'restrict']);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::KEYS) as $key) {
            $this->replace($key, from: [$key['restrict'], 'restrict'], to: [$key['legacy'], 'set null']);
        }
    }

    /**
     * @param  array{table: string, column: string, legacy: string, restrict: string}  $key
     * @param  array{0: string, 1: string}  $from  [constraint name, on delete]
     * @param  array{0: string, 1: string}  $to  [constraint name, on delete]
     */
    private function replace(array $key, array $from, array $to): void
    {
        $state = $this->state($key);

        if ($this->matches($state, $to)) {
            return; // Already replaced (re-run after a partial run).
        }

        if (! $this->matches($state, $from)) {
            throw new RuntimeException(sprintf(
                'Unexpected foreign key on %s.%s: expected [%s] or [%s], found [%s]. Nothing was changed.',
                $key['table'], $key['column'], $this->describe($from), $this->describe($to), $this->describe($state)
            ));
        }

        $this->assertNoOrphans($key);

        DB::getDriverName() === 'mysql'
            ? $this->replaceOnMySql($key, $from[0], $to)
            : $this->replaceOnSqlite($key, $to[1]);

        if (! $this->matches($this->state($key), $to)) {
            throw new RuntimeException(sprintf(
                'Foreign key on %s.%s is [%s] after the change, expected [%s].',
                $key['table'], $key['column'], $this->describe($this->state($key)), $this->describe($to)
            ));
        }
    }

    /**
     * @param  array{table: string, column: string}  $key
     * @param  array{0: string, 1: string}  $to
     */
    private function replaceOnMySql(array $key, string $current, array $to): void
    {
        $grammar = DB::getQueryGrammar();

        DB::statement(sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s, ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s',
            $grammar->wrapTable($key['table']),
            $grammar->wrap($current),
            $grammar->wrap($to[0]),
            $grammar->wrap($key['column']),
            $grammar->wrapTable('categories'),
            $grammar->wrap('id'),
            strtoupper($to[1])
        ));
    }

    /**
     * @param  array{table: string, column: string}  $key
     */
    private function replaceOnSqlite(array $key, string $onDelete): void
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to rebuild %s on SQLite inside a transaction: foreign key enforcement cannot be suspended there, so dependent rows could be changed.',
                $key['table']
            ));
        }

        Schema::table($key['table'], function (Blueprint $table) use ($key, $onDelete) {
            $table->dropForeign([$key['column']]);

            $foreign = $table->foreign($key['column'])->references('id')->on('categories');
            $onDelete === 'restrict' ? $foreign->restrictOnDelete() : $foreign->nullOnDelete();
        });

        $violations = DB::select('PRAGMA foreign_key_check');

        if ($violations !== []) {
            throw new RuntimeException('SQLite foreign key check failed after rebuilding '.$key['table'].'.');
        }
    }

    /**
     * The foreign key(s) on the column that reference categories.id, as [name, on delete].
     * SQLite foreign keys created by Laravel have no name, so only the rule is compared there.
     *
     * @param  array{table: string, column: string}  $key
     * @return list<array{0: ?string, 1: string}>
     */
    private function state(array $key): array
    {
        return collect(Schema::getForeignKeys($key['table']))
            ->filter(fn (array $fk) => $fk['columns'] === [$key['column']] && $fk['foreign_table'] === 'categories')
            ->map(fn (array $fk) => [DB::getDriverName() === 'mysql' ? $fk['name'] : null, $fk['on_delete']])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{0: ?string, 1: string}>  $state
     * @param  array{0: string, 1: string}  $expected
     */
    private function matches(array $state, array $expected): bool
    {
        if (count($state) !== 1) {
            return false;
        }

        [$name, $onDelete] = $state[0];

        return $onDelete === $expected[1] && ($name === null || $name === $expected[0]);
    }

    /**
     * @param  array{table: string, column: string}  $key
     */
    private function assertNoOrphans(array $key): void
    {
        $orphans = DB::table($key['table'].' as child')
            ->leftJoin('categories as parent', 'parent.id', '=', 'child.'.$key['column'])
            ->whereNotNull('child.'.$key['column'])
            ->whereNull('parent.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException(sprintf(
                '%d row(s) in %s reference a missing category through %s. Resolve them first; nothing was changed.',
                $orphans, $key['table'], $key['column']
            ));
        }
    }

    /**
     * @param  array<int, mixed>  $value
     */
    private function describe(array $value): string
    {
        if ($value === [] || is_array($value[0] ?? null)) {
            return $value === [] ? 'none' : implode('; ', array_map(fn ($fk) => $this->describe($fk), $value));
        }

        return ($value[0] ?? 'unnamed').': on delete '.$value[1];
    }
};
