<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * AD-UI-CAT-M2-C (D5): SQLite path of 2026_10_11_000000_add_image_media_id_to_categories. DatabaseMigrations
 * (not RefreshDatabase) so up()/down() run outside a transaction, as they do under the migrator. The MySQL
 * path is covered by MediaDeletionConcurrencyMySqlTest::test_the_column_and_its_key_on_mysql.
 */
class CategoryImageMediaMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_10_11_000000_add_image_media_id_to_categories.php';

    public function test_the_column_is_a_nullable_indexed_restrict_reference_to_media(): void
    {
        $column = collect(Schema::getColumns('categories'))->firstWhere('name', 'image_media_id');
        $this->assertNotNull($column);
        $this->assertTrue($column['nullable']);
        $this->assertSame('integer', $column['type_name']);
        $this->assertNull($column['default']);

        $keys = collect(Schema::getForeignKeys('categories'))->filter(fn (array $fk) => $fk['columns'] === ['image_media_id'])->values();
        $this->assertCount(1, $keys);
        $this->assertSame(['media', ['id'], 'restrict'], [$keys[0]['foreign_table'], $keys[0]['foreign_columns'], $keys[0]['on_delete']]);

        $this->assertContains('categories_image_media_id_index', collect(Schema::getIndexes('categories'))->pluck('name')->all());

        // The legacy column and the category keys are untouched.
        $this->assertTrue(Schema::hasColumn('categories', 'image'));
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id', 'categories'));
        $this->assertSame('restrict', $this->onDelete('products', 'category_id', 'categories'));
    }

    public function test_rollback_and_reapply_change_no_existing_row(): void
    {
        $this->seedCatalog();
        $before = $this->snapshot();
        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('categories', 'image_media_id'));
        $this->assertSame($before, $this->snapshot(), 'down() must not change any row.');
        $this->assertSame('restrict', $this->onDelete('categories', 'parent_id', 'categories'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('categories', 'image_media_id'));
        $this->assertSame($before, $this->snapshot(), 'up() must not change any row, nor copy the legacy image path.');
        $this->assertSame(0, DB::table('categories')->whereNotNull('image_media_id')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // Running again on the final state changes nothing.
        $migration->up();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_unexpected_existing_column_is_refused_without_changing_anything(): void
    {
        $migration = $this->migration();
        $migration->down();
        Schema::table('categories', fn ($table) => $table->unsignedBigInteger('image_media_id')->nullable());

        try {
            $migration->up();
            $this->fail('A column without the expected key must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not the expected nullable reference', $e->getMessage());
        }

        $this->assertSame([], collect(Schema::getForeignKeys('categories'))->filter(fn ($fk) => $fk['columns'] === ['image_media_id'])->all());

        // Restore the expected state so the suite's rollback can run.
        Schema::table('categories', fn ($table) => $table->dropColumn('image_media_id'));
        $migration->up();
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

        $this->assertTrue(Schema::hasColumn('categories', 'image_media_id'));
        $this->assertSame($before, $this->snapshot());
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function onDelete(string $table, string $column, string $foreignTable): ?string
    {
        return collect(Schema::getForeignKeys($table))
            ->first(fn (array $fk) => $fk['columns'] === [$column] && $fk['foreign_table'] === $foreignTable)['on_delete'] ?? null;
    }

    private function seedCatalog(): void
    {
        $vendor = Vendor::factory()->create();
        $root = DB::table('categories')->insertGetId(['name' => 'Root', 'slug' => 'root', 'image' => 'legacy/path.png', 'is_active' => false]);
        $child = DB::table('categories')->insertGetId(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root, 'image' => 'Fugiat impedit face']);
        $product = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $child, 'name' => 'P', 'slug' => 'p', 'price' => 10, 'status' => 'active']);
        // product_images cascade from products: a rebuild that fired ON DELETE actions would remove them.
        DB::table('product_images')->insert(['product_id' => $product, 'path' => 'a.jpg']);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('id')->get(['id', 'parent_id', 'slug', 'image', 'is_active', 'sort_order'])->map(fn ($r) => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->get(['id', 'category_id', 'status'])->map(fn ($r) => (array) $r)->all(),
            'product_images' => DB::table('product_images')->orderBy('id')->get(['id', 'product_id'])->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
