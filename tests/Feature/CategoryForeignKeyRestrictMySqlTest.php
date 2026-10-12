<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * DEC-CAT-Q3 (AD-UI-CAT-01C-1D): MySQL path of 2026_10_10_000000_restrict_category_foreign_keys.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (connection settings otherwise come from the mysql connection). Skipped
 * otherwise, so the regular suite and the development database are never touched.
 */
class CategoryForeignKeyRestrictMySqlTest extends TestCase
{
    private const CONNECTION = 'category_fk_mysql';

    private const MIGRATION = 'migrations/2026_10_10_000000_restrict_category_foreign_keys.php';

    private ?string $previousConnection = null;

    /** @var list<string> */
    private array $statements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['database' => $database])]);
        DB::purge(self::CONNECTION);
        $this->assertSame($database, DB::connection(self::CONNECTION)->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        DB::connection(self::CONNECTION)->listen(function ($query) {
            if (str_starts_with(strtoupper(ltrim($query->sql)), 'ALTER TABLE')) {
                $this->statements[] = $query->sql;
            }
        });
        $this->clearCatalog();
    }

    protected function tearDown(): void
    {
        if ($this->previousConnection !== null) {
            $this->migration()->up(); // Leave the isolated database in the migrated state.
            $this->clearCatalog();
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_keys_have_the_new_names_and_restrict(): void
    {
        $this->assertSame([
            'categories.parent_id' => 'categories_parent_restrict_fk:RESTRICT',
            'products.category_id' => 'products_category_restrict_fk:RESTRICT',
        ], $this->keys());
    }

    public function test_deletes_are_refused_for_linked_categories_and_allowed_for_empty_ones(): void
    {
        $vendor = Vendor::factory()->create();
        $root = DB::table('categories')->insertGetId(['name' => 'Root', 'slug' => 'root']);
        DB::table('categories')->insert(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root]);
        $empty = DB::table('categories')->insertGetId(['name' => 'Empty', 'slug' => 'empty']);

        foreach (['draft', 'active', 'inactive', 'out_of_stock'] as $status) {
            $category = DB::table('categories')->insertGetId(['name' => $status, 'slug' => 'cat-'.$status]);
            DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => $category, 'name' => $status, 'slug' => 'p-'.$status, 'price' => 10, 'status' => $status]);
            $before = $this->snapshot();
            $this->assertErrorCode(1451, fn () => DB::table('categories')->where('id', $category)->delete(), $status);
            $this->assertSame($before, $this->snapshot(), $status);
        }

        $before = $this->snapshot();
        $this->assertErrorCode(1451, fn () => DB::table('categories')->where('id', $root)->delete(), 'parent with child');
        $this->assertSame($before, $this->snapshot());

        $this->assertSame(1, DB::table('categories')->where('id', $empty)->delete());
        $this->assertErrorCode(1452, fn () => DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => 999999, 'name' => 'X', 'slug' => 'x', 'price' => 1]), 'product -> missing category');
        $this->assertErrorCode(1452, fn () => DB::table('categories')->insert(['name' => 'Y', 'slug' => 'y', 'parent_id' => 999999]), 'child -> missing parent');
    }

    public function test_rollback_and_reapply_use_one_alter_per_key_and_keep_the_data(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();
        $migration = $this->migration();

        $this->statements = [];
        $migration->down();
        $this->assertSame([
            'categories.parent_id' => 'categories_parent_id_foreign:SET NULL',
            'products.category_id' => 'products_category_id_foreign:SET NULL',
        ], $this->keys());
        $this->assertSame($before, $this->snapshot());
        $this->assertOneAlterPerKey('categories_parent_restrict_fk', 'categories_parent_id_foreign', 'SET NULL');

        $this->statements = [];
        $migration->up();
        $this->assertSame([
            'categories.parent_id' => 'categories_parent_restrict_fk:RESTRICT',
            'products.category_id' => 'products_category_restrict_fk:RESTRICT',
        ], $this->keys());
        $this->assertSame($before, $this->snapshot());
        $this->assertOneAlterPerKey('products_category_id_foreign', 'products_category_restrict_fk', 'RESTRICT');
        $this->assertCount(2, $this->statements, 'Exactly one ALTER per key: '.implode(' || ', $this->statements));
    }

    public function test_a_re_run_after_only_one_key_was_replaced_completes_the_other(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();
        $migration = $this->migration();
        $migration->down();

        // Interrupted run: products already replaced, categories still legacy.
        DB::statement('ALTER TABLE `products` DROP FOREIGN KEY `products_category_id_foreign`, ADD CONSTRAINT `products_category_restrict_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT');

        $this->statements = [];
        $migration->up();
        $this->assertCount(1, $this->statements, 'Only the remaining key is altered.');
        $this->assertStringContainsString('categories_parent_restrict_fk', $this->statements[0]);
        $this->assertSame([
            'categories.parent_id' => 'categories_parent_restrict_fk:RESTRICT',
            'products.category_id' => 'products_category_restrict_fk:RESTRICT',
        ], $this->keys());

        $this->statements = [];
        $migration->up();
        $this->assertSame([], $this->statements, 'A completed migration re-runs without any DDL.');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_unexpected_key_is_refused_without_any_ddl(): void
    {
        $this->seedCatalog();
        $migration = $this->migration();
        $migration->down();
        // An unexpected key (other name, other rule). MySQL cannot drop and re-add the same name in one ALTER.
        DB::statement('ALTER TABLE `products` DROP FOREIGN KEY `products_category_id_foreign`, ADD CONSTRAINT `products_category_cascade_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE');
        $before = $this->snapshot();

        $this->statements = [];

        try {
            $migration->up();
            $this->fail('An unexpected cascade key must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unexpected foreign key on products.category_id', $e->getMessage());
        }

        $this->assertSame([], $this->statements, 'No DDL after a refused state.');
        $this->assertSame([
            'categories.parent_id' => 'categories_parent_id_foreign:SET NULL',
            'products.category_id' => 'products_category_cascade_fk:CASCADE',
        ], $this->keys());
        $this->assertSame($before, $this->snapshot());

        DB::statement('ALTER TABLE `products` DROP FOREIGN KEY `products_category_cascade_fk`, ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL');
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /** @return array<string, string> */
    private function keys(): array
    {
        return collect(DB::select("SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c, r.CONSTRAINT_NAME AS n, r.DELETE_RULE AS d FROM information_schema.REFERENTIAL_CONSTRAINTS r JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME WHERE r.CONSTRAINT_SCHEMA = DATABASE() AND r.REFERENCED_TABLE_NAME = 'categories' ORDER BY k.TABLE_NAME"))
            ->mapWithKeys(fn ($r) => ["{$r->t}.{$r->c}" => "{$r->n}:{$r->d}"])
            ->all();
    }

    private function assertOneAlterPerKey(string $dropped, string $added, string $rule): void
    {
        $matching = array_values(array_filter($this->statements, fn (string $sql) => str_contains($sql, "DROP FOREIGN KEY `{$dropped}`")));
        $this->assertCount(1, $matching, "One ALTER drops {$dropped}.");
        $this->assertStringContainsString("ADD CONSTRAINT `{$added}`", $matching[0], 'Drop and add are in the same statement.');
        $this->assertStringContainsString("ON DELETE {$rule}", $matching[0]);
    }

    private function assertErrorCode(int $code, callable $statement, string $label): void
    {
        try {
            $statement();
            $this->fail("{$label}: expected MySQL error {$code}.");
        } catch (QueryException $e) {
            $this->assertSame($code, $e->errorInfo[1] ?? null, $label.': '.$e->getMessage());
        }
    }

    private function seedCatalog(): void
    {
        $vendor = Vendor::factory()->create();
        $root = DB::table('categories')->insertGetId(['name' => 'Root', 'slug' => 'root']);
        $child = DB::table('categories')->insertGetId(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root]);
        $inChild = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $child, 'name' => 'P1', 'slug' => 'p1', 'price' => 10, 'status' => 'inactive']);
        DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => $root, 'name' => 'P2', 'slug' => 'p2', 'price' => 10, 'status' => 'active']);
        DB::table('product_images')->insert(['product_id' => $inChild, 'path' => 'a.jpg']);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('id')->get(['id', 'parent_id'])->map(fn ($r) => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->get(['id', 'category_id', 'status'])->map(fn ($r) => (array) $r)->all(),
            'product_images' => DB::table('product_images')->orderBy('id')->get(['id', 'product_id'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function clearCatalog(): void
    {
        DB::table('product_images')->delete();
        DB::table('products')->delete();
        DB::table('categories')->whereNotNull('parent_id')->delete();
        DB::table('categories')->delete();
        Vendor::query()->delete();
    }
}
