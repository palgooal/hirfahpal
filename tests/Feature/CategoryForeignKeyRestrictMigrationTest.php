<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * DEC-CAT-Q3 (AD-UI-CAT-01C-1D): products.category_id and categories.parent_id restrict deletes.
 * SQLite path of 2026_10_10_000000_restrict_category_foreign_keys. DatabaseMigrations (not RefreshDatabase)
 * so up()/down() run outside a transaction, as they do under the migrator.
 */
class CategoryForeignKeyRestrictMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_10_10_000000_restrict_category_foreign_keys.php';

    public function test_category_keys_restrict_deletes_after_migrating(): void
    {
        $this->assertSame('restrict', $this->onDelete('products', 'category_id'));
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id'));
    }

    public function test_a_category_with_products_of_any_status_cannot_be_deleted(): void
    {
        $vendor = Vendor::factory()->create();

        foreach (['draft', 'active', 'inactive', 'out_of_stock'] as $status) {
            $category = DB::table('categories')->insertGetId(['name' => $status, 'slug' => 'cat-'.$status]);
            DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => $category, 'name' => $status, 'slug' => 'p-'.$status, 'price' => 10, 'status' => $status]);
            $before = $this->snapshot();

            $this->assertDeleteRefused($category, $status);
            $this->assertSame($before, $this->snapshot(), $status);
        }
    }

    public function test_a_parent_with_children_cannot_be_deleted_but_an_empty_category_can(): void
    {
        $root = DB::table('categories')->insertGetId(['name' => 'Root', 'slug' => 'root']);
        DB::table('categories')->insert(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root]);
        $empty = DB::table('categories')->insertGetId(['name' => 'Empty', 'slug' => 'empty']);
        $before = $this->snapshot();

        $this->assertDeleteRefused($root, 'parent with child');
        $this->assertSame($before, $this->snapshot());

        $this->assertSame(1, DB::table('categories')->where('id', $empty)->delete());
        $this->assertFalse(DB::table('categories')->where('id', $empty)->exists());
    }

    public function test_rows_pointing_at_a_missing_category_are_refused(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertInsertRefused('products', ['vendor_id' => $vendor->id, 'category_id' => 999, 'name' => 'X', 'slug' => 'x', 'price' => 1]);
        $this->assertInsertRefused('categories', ['name' => 'Y', 'slug' => 'y', 'parent_id' => 999]);
    }

    public function test_rollback_restores_set_null_and_reapplying_restricts_again_without_changing_data(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();
        $migration = $this->migration();

        $migration->down();
        $this->assertSame('set null', $this->onDelete('products', 'category_id'));
        $this->assertSame('set null', $this->onDelete('categories', 'parent_id'));
        $this->assertSame($before, $this->snapshot(), 'down() must not change any row, including product images.');

        $migration->up();
        $this->assertSame('restrict', $this->onDelete('products', 'category_id'));
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id'));
        $this->assertSame($before, $this->snapshot(), 'up() must not change any row, including product images.');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_a_re_run_after_only_one_key_was_replaced_completes_the_other(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();
        $migration = $this->migration();

        $migration->down();
        // Simulate an interrupted run: products already replaced, categories not yet.
        Schema::table('products', function ($table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
        $this->assertSame('set null', $this->onDelete('categories', 'parent_id'));

        $migration->up();
        $this->assertSame('restrict', $this->onDelete('products', 'category_id'));
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id'));

        // Running again on the final state changes nothing.
        $migration->up();
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_unexpected_foreign_key_is_refused_without_changing_anything(): void
    {
        $this->seedCatalog();
        $migration = $this->migration();
        $migration->down();
        Schema::table('products', function ($table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
        $before = $this->snapshot();

        try {
            $migration->up();
            $this->fail('An unexpected cascade key must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unexpected foreign key on products.category_id', $e->getMessage());
        }

        $this->assertSame('cascade', $this->onDelete('products', 'category_id'));
        $this->assertSame('set null', $this->onDelete('categories', 'parent_id'), 'The second key is not touched after the first is refused.');
        $this->assertSame($before, $this->snapshot());

        // Restore the expected state so the suite's rollback can run.
        Schema::table('products', function ($table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
        $migration->up();
        $this->assertSame('restrict', $this->onDelete('products', 'category_id'));
    }

    public function test_the_sqlite_rebuild_is_refused_inside_a_transaction(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();

        DB::beginTransaction();

        try {
            $this->migration()->down();
            $this->fail('Rebuilding inside a transaction must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inside a transaction', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id'));
        $this->assertSame($before, $this->snapshot());
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function onDelete(string $table, string $column): ?string
    {
        $keys = collect(Schema::getForeignKeys($table))->filter(fn (array $fk) => $fk['columns'] === [$column] && $fk['foreign_table'] === 'categories');
        $this->assertCount(1, $keys, "{$table}.{$column} must have exactly one key to categories.");

        return $keys->first()['on_delete'];
    }

    private function seedCatalog(): void
    {
        $vendor = Vendor::factory()->create();
        $root = DB::table('categories')->insertGetId(['name' => 'Root', 'slug' => 'root']);
        $child = DB::table('categories')->insertGetId(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root]);
        DB::table('categories')->insert(['name' => 'Empty', 'slug' => 'empty']);
        $inChild = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $child, 'name' => 'P1', 'slug' => 'p1', 'price' => 10, 'status' => 'inactive']);
        $inRoot = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $root, 'name' => 'P2', 'slug' => 'p2', 'price' => 10, 'status' => 'active']);
        // Dependent rows: product_images cascade from products, so a rebuild that fired ON DELETE actions would remove them.
        DB::table('product_images')->insert([['product_id' => $inChild, 'path' => 'a.jpg'], ['product_id' => $inRoot, 'path' => 'b.jpg']]);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('id')->get(['id', 'parent_id', 'slug'])->map(fn ($r) => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->get(['id', 'category_id', 'status'])->map(fn ($r) => (array) $r)->all(),
            'product_images' => DB::table('product_images')->orderBy('id')->get(['id', 'product_id'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function assertDeleteRefused(int $categoryId, string $label): void
    {
        try {
            DB::table('categories')->where('id', $categoryId)->delete();
            $this->fail("Deleting category ({$label}) must be refused by the database.");
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $e->getMessage(), $label);
        }
    }

    private function assertInsertRefused(string $table, array $row): void
    {
        try {
            DB::table($table)->insert($row);
            $this->fail("Inserting into {$table} with a missing category must be refused.");
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $e->getMessage());
        }
    }
}
