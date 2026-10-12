<?php

namespace Tests\Feature;

use App\Http\Requests\VendorDashboard\StoreProductRequest;
use App\Http\Requests\VendorDashboard\UpdateProductRequest;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AD-UI-CAT-01C-1F: a category deleted between validation and saving a product is reported as a
 * category_id validation error (no 500, no partial product); other database errors stay visible.
 *
 * Limits: a real race needs two database connections. These tests delete the category at the exact point
 * between validation and the write instead (vendor: after the FormRequest has validated, before the
 * controller's transaction; admin: in the model's creating/updating event, the admin write has no
 * transaction), so the write itself hits the foreign key, as it would in the race.
 */
class ProductCategoryForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');
    }

    public function test_admin_create_with_a_category_deleted_meanwhile_returns_a_field_error(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::create(['name' => 'Vanishing', 'slug' => 'vanishing']);
        Product::creating(fn () => DB::table('categories')->where('id', $category->id)->delete());

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('dashboard.products.create'))
            ->post(route('dashboard.products.store'), $this->adminPayload($vendor, $category))
            ->assertRedirect(route('dashboard.products.create'))
            ->assertSessionHasErrors('category_id')
            ->assertSessionHasInput('name', 'Clay jug');

        $this->assertSame(0, Product::count());
    }

    public function test_admin_update_with_a_category_deleted_meanwhile_returns_a_field_error(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $current = Category::create(['name' => 'Current', 'slug' => 'current']);
        $target = Category::create(['name' => 'Target', 'slug' => 'target']);
        $product = $this->product($vendor, $current, 'Original');
        Product::updating(fn () => DB::table('categories')->where('id', $target->id)->delete());

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $this->adminPayload($vendor, $target, 'Renamed'))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors('category_id');

        $product->refresh();
        $this->assertSame('Original', $product->name);
        $this->assertSame($current->id, $product->category_id);
    }

    public function test_vendor_create_with_a_category_deleted_meanwhile_returns_422_and_leaves_no_product_or_image(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::create(['name' => 'Vanishing', 'slug' => 'vanishing']);
        $this->deleteAfterValidation(StoreProductRequest::class, $category);

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.store'), $this->vendorPayload($category) + ['images' => [['path' => 'a.jpg'], ['path' => 'b.jpg']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->assertSame(0, Product::count());
        $this->assertSame(0, DB::table('product_images')->count(), 'The transaction rolled back the images too.');
    }

    public function test_vendor_update_with_a_category_deleted_meanwhile_returns_422_and_keeps_the_product(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $current = Category::create(['name' => 'Current', 'slug' => 'current']);
        $target = Category::create(['name' => 'Target', 'slug' => 'target']);
        $product = $this->product($vendor, $current, 'Original');
        DB::table('product_images')->insert(['product_id' => $product->id, 'path' => 'kept.jpg']);
        $this->deleteAfterValidation(UpdateProductRequest::class, $target);

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.products.update', $product), $this->vendorPayload($target, 'Renamed'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $product->refresh();
        $this->assertSame('Original', $product->name);
        $this->assertSame($current->id, $product->category_id);
        $this->assertSame(1, DB::table('product_images')->where('product_id', $product->id)->count());
    }

    public function test_an_unrelated_database_error_is_rethrown_by_the_vendor_controller(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::create(['name' => 'Kept', 'slug' => 'kept']);
        Product::creating(fn () => DB::select('select * from table_that_does_not_exist'));

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/table_that_does_not_exist/');

        $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), $this->vendorPayload($category));
    }

    public function test_a_foreign_key_failure_that_is_not_the_category_is_rethrown_by_the_admin_controller(): void
    {
        // The vendor disappears between validation and insert: a foreign key failure, but the category still
        // exists, so it must not be reported as a category error.
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::create(['name' => 'Kept', 'slug' => 'kept']);
        Product::creating(fn () => DB::table('vendors')->where('id', $vendor->id)->delete());

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->superAdmin(), 'admin')->post(route('dashboard.products.store'), $this->adminPayload($vendor, $category));
            $this->fail('A non-category foreign key failure must not be turned into a validation error.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertSame(0, Product::count());
    }

    public function test_sellability_contract_is_unchanged(): void
    {
        $category = Category::create(['name' => 'Any', 'slug' => 'any']);
        $sellable = $this->product(Vendor::factory()->approved()->create(), $category, 'Sellable', 'active');
        $this->product(Vendor::factory()->approved()->create(), $category, 'Inactive', 'inactive');
        $this->product(Vendor::factory()->withProfile('pending')->create(), $category, 'Pending vendor', 'active');

        $this->assertSame([$sellable->id], Product::query()->sellable()->pluck('id')->all());
        $this->assertTrue($sellable->fresh()->isSellable());
    }

    /** Deletes the category right after the FormRequest has validated, before the controller writes. */
    private function deleteAfterValidation(string $requestClass, Category $category): void
    {
        $this->app->afterResolving($requestClass, function ($request) use ($category) {
            $validator = (fn () => $this->validator)->call($request);
            $this->assertNotNull($validator, 'The category must disappear after validation, not before.');
            $this->assertTrue($validator->passes());

            DB::table('categories')->where('id', $category->id)->delete();
        });
    }

    private function product(Vendor $vendor, Category $category, string $name, string $status = 'draft'): Product
    {
        return Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.$vendor->id,
            'price' => 10,
            'stock_quantity' => 5,
            'status' => $status,
        ]);
    }

    private function adminPayload(Vendor $vendor, Category $category, string $name = 'Clay jug'): array
    {
        return ['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => $name, 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft'];
    }

    private function vendorPayload(Category $category, string $name = 'Clay jug'): array
    {
        return ['category_id' => $category->id, 'name' => $name, 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft'];
    }

    private function superAdmin(): Admin
    {
        return Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
    }
}
