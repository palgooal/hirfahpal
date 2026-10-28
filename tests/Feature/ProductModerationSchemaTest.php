<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductAdminAction;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * AD-UI-PROD-M1: the data of administrative suspension and archiving (products.suspended_at, products.archived_at)
 * and the product_admin_actions log. Schema only: no controller or view sets the state yet, and sellability is
 * unchanged until M2.
 *
 * SQLite (>= 3.35) adds and drops these columns natively, without rebuilding products, so up()/down() can run inside
 * RefreshDatabase's transaction and are rolled back with it. The MySQL path (one atomic ALTER TABLE) is not run
 * here: no isolated MySQL test database is available (Pending MySQL verification, C-22).
 */
class ProductModerationSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS_MIGRATION = 'migrations/2026_10_14_000000_add_moderation_columns_to_products.php';

    private const LOG_MIGRATION = 'migrations/2026_10_14_000001_create_product_admin_actions_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');
    }

    // ---------------------------------------------------------------- products.suspended_at / archived_at

    public function test_the_columns_are_nullable_indexed_timestamps_without_default(): void
    {
        $columns = collect(Schema::getColumns('products'))->keyBy('name');
        $indexes = collect(Schema::getIndexes('products'))->keyBy('name');

        foreach (['suspended_at' => 'products_suspended_at_index', 'archived_at' => 'products_archived_at_index'] as $column => $index) {
            $this->assertTrue($columns->has($column), $column);
            $this->assertTrue($columns[$column]['nullable']);
            $this->assertContains(strtolower($columns[$column]['type_name']), ['timestamp', 'datetime']);
            $this->assertNull($columns[$column]['default']);
            $this->assertSame([$column], $indexes[$index]['columns'] ?? null);
        }

        // No key was added to products, and products.status is untouched.
        $this->assertSame([], collect(Schema::getForeignKeys('products'))->filter(fn ($fk) => array_intersect($fk['columns'], ['suspended_at', 'archived_at']) !== [])->all());
        $this->assertTrue(Schema::hasColumn('products', 'status'));
    }

    public function test_rollback_and_reapply_write_no_existing_row_and_leave_everything_null(): void
    {
        $product = $this->product('Jug', 'active');
        $this->product('Bowl', 'draft');
        $before = $this->productsSnapshot();
        $migration = $this->migration(self::COLUMNS_MIGRATION);

        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'suspended_at'));
        $this->assertFalse(Schema::hasColumn('products', 'archived_at'));
        $this->assertSame($before, $this->productsSnapshot(), 'down() changes no row.');

        $migration->up();
        $this->assertSame($before, $this->productsSnapshot(), 'up() changes no row.');
        $this->assertSame(0, DB::table('products')->whereNotNull('suspended_at')->orWhereNotNull('archived_at')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // Running again on the final state changes nothing.
        $migration->up();
        $this->assertSame($before, $this->productsSnapshot());
        $this->assertFalse($product->fresh()->isSuspended());
        $this->assertFalse($product->fresh()->isArchived());
    }

    public function test_a_partial_or_unexpected_state_is_refused_without_changing_anything(): void
    {
        $migration = $this->migration(self::COLUMNS_MIGRATION);
        $migration->down();

        // Only one of the two columns.
        Schema::table('products', fn ($table) => $table->timestamp('suspended_at')->nullable());
        $this->assertRefused(fn () => $migration->up(), 'only part of the moderation columns');
        $this->assertFalse(Schema::hasColumn('products', 'archived_at'));

        // Both columns, but of the wrong type and without their indexes.
        Schema::table('products', fn ($table) => $table->dropColumn('suspended_at'));
        Schema::table('products', function ($table) {
            $table->string('suspended_at')->nullable();
            $table->string('archived_at')->nullable();
        });
        $this->assertRefused(fn () => $migration->up(), 'not the expected nullable, indexed timestamp');
        $this->assertNotContains('products_suspended_at_index', collect(Schema::getIndexes('products'))->pluck('name')->all());
    }

    public function test_rollback_is_refused_while_a_product_is_suspended_or_archived(): void
    {
        $migration = $this->migration(self::COLUMNS_MIGRATION);

        foreach (['suspended_at', 'archived_at'] as $column) {
            $product = $this->product("Kept {$column}", 'active');
            $product->forceFill([$column => now()])->save();

            $this->assertRefused(fn () => $migration->down(), 'suspended or archived');
            $this->assertTrue(Schema::hasColumn('products', $column));
            $this->assertNotNull($product->fresh()->{$column}, 'The decision is not lifted.');

            $product->forceFill([$column => null])->save();
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'suspended_at'));
    }

    // ---------------------------------------------------------------- product_admin_actions

    public function test_the_log_table_has_its_columns_keys_and_index(): void
    {
        $columns = collect(Schema::getColumns('product_admin_actions'))->keyBy('name');
        $this->assertSame(
            ['action', 'admin_id', 'admin_name', 'changes', 'created_at', 'id', 'product_id', 'product_name', 'product_sku', 'reason'],
            $columns->keys()->sort()->values()->all()
        );
        $this->assertTrue($columns['product_id']['nullable']);
        $this->assertTrue($columns['admin_id']['nullable']);
        $this->assertFalse($columns['reason']['nullable']);
        $this->assertFalse($columns['product_name']['nullable']);
        $this->assertFalse($columns['admin_name']['nullable']);
        $this->assertFalse($columns->has('updated_at'));

        $keys = collect(Schema::getForeignKeys('product_admin_actions'))->mapWithKeys(fn ($fk) => [implode(',', $fk['columns']) => [$fk['foreign_table'], $fk['foreign_columns'], $fk['on_delete']]]);
        $this->assertSame(['products', ['id'], 'set null'], $keys['product_id']);
        $this->assertSame(['admins', ['id'], 'set null'], $keys['admin_id']);

        $this->assertTrue(collect(Schema::getIndexes('product_admin_actions'))->contains(fn ($index) => $index['columns'] === ['product_id', 'created_at']));
    }

    public function test_the_action_column_accepts_only_the_four_actions(): void
    {
        $product = $this->product('Jug', 'active');
        $admin = $this->admin();

        foreach (ProductAdminAction::ACTIONS as $action) {
            $this->logEntry($product, $admin, $action);
        }
        $this->assertSame(4, ProductAdminAction::count());

        // Also at the database level (enum / check constraint), not only in the model.
        $this->expectException(QueryException::class);
        DB::table('product_admin_actions')->insert(['product_id' => $product->id, 'product_name' => 'Jug', 'admin_name' => 'A', 'action' => 'deleted', 'reason' => 'x']);
    }

    public function test_an_entry_survives_the_deletion_of_its_product_and_of_its_admin(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product('Clay jug', 'active', $vendor, 'JUG-1');
        $admin = $this->admin();
        $entry = $this->logEntry($product, $admin, 'suspended', ['status' => ['active', 'active']]);

        // The vendor's own delete endpoint (still a permanent delete, C-15) and an admin deletion.
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.destroy', $product))->assertOk();
        $admin->delete();

        $row = DB::table('product_admin_actions')->where('id', $entry->id)->first();
        $this->assertNull($row->product_id);
        $this->assertNull($row->admin_id);
        $this->assertSame('Clay jug', $row->product_name);
        $this->assertSame('JUG-1', $row->product_sku);
        $this->assertSame('Moderator', $row->admin_name);
        $this->assertSame('suspended', $row->action);
        $this->assertSame('Misleading photos', $row->reason);
        $this->assertSame(['status' => ['active', 'active']], ProductAdminAction::find($entry->id)->changes);
    }

    public function test_the_rollback_of_the_log_is_refused_while_it_has_entries(): void
    {
        $migration = $this->migration(self::LOG_MIGRATION);
        $entry = $this->logEntry($this->product('Jug', 'active'), $this->admin(), 'archived');

        $this->assertRefused(fn () => $migration->down(), 'erase the audit trail');
        $this->assertTrue(Schema::hasTable('product_admin_actions'));
        $this->assertSame(1, DB::table('product_admin_actions')->count());

        DB::table('product_admin_actions')->where('id', $entry->id)->delete();
        $migration->down();
        $this->assertFalse(Schema::hasTable('product_admin_actions'));

        $migration->up();
        $migration->up(); // Re-run on the final state.
        $this->assertTrue(Schema::hasTable('product_admin_actions'));
    }

    public function test_an_unexpected_log_table_is_refused(): void
    {
        $migration = $this->migration(self::LOG_MIGRATION);
        $migration->down();
        Schema::create('product_admin_actions', function ($table) {
            $table->id();
            $table->string('note');
        });

        $this->assertRefused(fn () => $migration->up(), 'does not have the expected columns');
        $this->assertFalse(Schema::hasColumn('product_admin_actions', 'reason'));
    }

    // ---------------------------------------------------------------- ProductAdminAction model

    public function test_an_entry_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $entry = $this->logEntry($this->product('Jug', 'active'), $this->admin(), 'suspended');

        try {
            $entry->update(['reason' => 'Rewritten']);
            $this->fail('An entry must not be updated.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('An entry must not be deleted.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame('Misleading photos', DB::table('product_admin_actions')->value('reason'));
        $this->assertSame(1, DB::table('product_admin_actions')->count());
    }

    public function test_an_entry_needs_a_known_action_and_a_reason(): void
    {
        $product = $this->product('Jug', 'active');
        $admin = $this->admin();

        foreach ([['action' => 'deleted', 'reason' => 'x'], ['action' => 'suspended', 'reason' => '   '], ['action' => 'suspended', 'reason' => null]] as $values) {
            try {
                ProductAdminAction::create($values + ['product_id' => $product->id, 'product_name' => 'Jug', 'admin_id' => $admin->id, 'admin_name' => 'Moderator']);
                $this->fail('Refused: '.json_encode($values));
            } catch (LogicException) {
                // Expected.
            }
        }

        $this->assertSame(0, ProductAdminAction::count());
    }

    public function test_the_product_exposes_its_actions_newest_first(): void
    {
        $product = $this->product('Jug', 'active');
        $admin = $this->admin();
        $first = $this->logEntry($product, $admin, 'suspended');
        $this->travel(1)->minutes();
        $second = $this->logEntry($product, $admin, 'unsuspended');

        $this->assertSame([$second->id, $first->id], $product->adminActions()->pluck('id')->all());
        $this->assertSame($product->id, $second->product->id);
        $this->assertSame($admin->id, $second->admin->id);
    }

    // ---------------------------------------------------------------- Product: not fillable, hidden, helpers

    public function test_the_state_is_readable_but_not_fillable_and_hidden_from_json(): void
    {
        $product = $this->product('Jug', 'active');
        $product->fill(['suspended_at' => now(), 'archived_at' => now()])->save();
        $this->assertNull($product->fresh()->suspended_at, 'Not mass assignable.');
        $this->assertNull($product->fresh()->archived_at);

        $product->forceFill(['suspended_at' => now(), 'archived_at' => now()])->save();
        $product = $product->fresh();
        $this->assertTrue($product->isSuspended());
        $this->assertTrue($product->isArchived());
        $this->assertInstanceOf(CarbonInterface::class, $product->suspended_at);

        $array = $product->toArray();
        $this->assertArrayNotHasKey('suspended_at', $array);
        $this->assertArrayNotHasKey('archived_at', $array);
        $this->assertArrayHasKey('status', $array);
    }

    public function test_vendor_writes_cannot_set_or_clear_the_state(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $input = ['name' => 'Jug', 'price' => 10, 'stock_quantity' => 5, 'status' => 'active', 'suspended_at' => '2026-01-01 00:00:00', 'archived_at' => '2026-01-01 00:00:00'];

        $created = $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), $input)->assertCreated()->json('product.id');
        $product = Product::findOrFail($created);
        $this->assertNull($product->suspended_at);
        $this->assertNull($product->archived_at);

        // A suspended and archived product stays so whatever the vendor's update sends.
        $product->forceFill(['suspended_at' => now(), 'archived_at' => now()])->save();
        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.products.update', $product), ['suspended_at' => null, 'archived_at' => null, 'status' => 'active'] + $input)
            ->assertOk();
        $this->assertTrue($product->fresh()->isSuspended());
        $this->assertTrue($product->fresh()->isArchived());
    }

    public function test_admin_form_writes_cannot_set_or_clear_the_state(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $admin = Admin::create(['name' => 'Super', 'email' => 'super@example.com', 'phone' => '0599000009', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        $form = ['vendor_id' => $vendor->id, 'category_id' => $this->fixtureCategory(), 'name' => 'Jug', 'price' => '10', 'stock_quantity' => '5', 'status' => 'active', 'suspended_at' => '2026-01-01 00:00:00', 'archived_at' => '2026-01-01 00:00:00'];

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.store'), $form + ['slug' => 'admin-jug'])->assertSessionHasNoErrors();
        $product = Product::where('slug', 'admin-jug')->sole();
        $this->assertNull($product->suspended_at);
        $this->assertNull($product->archived_at);

        $product->forceFill(['suspended_at' => now(), 'archived_at' => now()])->save();
        $product = $product->fresh();
        $this->actingAs($admin, 'admin')->put(route('dashboard.products.update', $product), [
            'suspended_at' => '', 'archived_at' => '', 'slug' => 'admin-jug', 'price' => '11',
            'stock_quantity_original' => (string) $product->stock_quantity,
            'stock_version' => ProductManagementController::stockVersion($product),
        ] + $form)->assertSessionHasNoErrors();

        $product = $product->fresh();
        $this->assertSame('11.00', $product->price);
        $this->assertTrue($product->isSuspended());
        $this->assertTrue($product->isArchived());
    }

    public function test_vendor_and_storefront_json_keep_their_shape(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product('Jug', 'active', $vendor);
        $product->forceFill(['suspended_at' => now()])->save();

        // The vendor still sees its suspended product, without the state in the JSON.
        $show = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.products.show', $product))->assertOk()->json('product');
        $list = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.products.index'))->assertOk()->json('products.data.0');

        // Since M2 the storefront does not serve a suspended product at all, so its JSON is checked on the same
        // product with the state cleared (the columns still exist on the row and stay hidden).
        $product->forceFill(['suspended_at' => null])->save();
        $shop = $this->getJson(route('shop.products.index'))->assertOk()->json('data.0');
        $shopShow = $this->getJson(route('shop.products.show', $product))->assertOk()->json();

        foreach ([$show, $list, $shop, $shopShow] as $payload) {
            $this->assertIsArray($payload);
            $this->assertArrayNotHasKey('suspended_at', $payload);
            $this->assertArrayNotHasKey('archived_at', $payload);
            $this->assertSame($product->id, $payload['id']);
        }
    }

    public function test_the_state_columns_take_part_in_sellability_since_m2(): void
    {
        // In M1 this test pinned that the columns did not affect sale yet. Since AD-UI-PROD-M2 a suspended or
        // archived product is not sellable; the full coverage is in ProductModerationSellabilityTest.
        $product = $this->product('Jug', 'active');
        $this->assertTrue($product->fresh()->isSellable());

        $product->forceFill(['suspended_at' => now(), 'archived_at' => now()])->save();

        $this->assertFalse($product->fresh()->isSellable());
        $this->assertSame([], Product::query()->sellable()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- helpers

    private function migration(string $path): object
    {
        return require database_path($path);
    }

    private function assertRefused(callable $run, string $message): void
    {
        try {
            $run();
            $this->fail('Expected a refusal containing: '.$message);
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    private function product(string $name, string $status, ?Vendor $vendor = null, ?string $sku = null): Product
    {
        return Product::create([
            'vendor_id' => ($vendor ?? Vendor::factory()->approved()->create())->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'sku' => $sku,
            'price' => 10,
            'stock_quantity' => 5,
            'status' => $status,
        ]);
    }

    private function admin(): Admin
    {
        static $sequence = 0;
        $sequence++;

        return Admin::create(['name' => 'Moderator', 'email' => "moderator{$sequence}@example.com", 'phone' => '05990001'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => false]);
    }

    private function logEntry(Product $product, Admin $admin, string $action, ?array $changes = null): ProductAdminAction
    {
        return ProductAdminAction::create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => $action,
            'reason' => 'Misleading photos',
            'changes' => $changes,
        ]);
    }

    private function productsSnapshot(): array
    {
        return DB::table('products')->orderBy('id')->get(['id', 'name', 'slug', 'status', 'price', 'stock_quantity', 'published_at', 'updated_at'])
            ->map(fn ($row) => (array) $row)->all();
    }

    /** ADMIN-PRODUCT-FORM-02: a category is required on admin saves, so fixtures share one active category. */
    private function fixtureCategory(): int
    {
        return Category::query()->firstOrCreate(['slug' => 'fixture-category'], ['name' => 'Pottery', 'is_active' => true])->id;
    }
}
