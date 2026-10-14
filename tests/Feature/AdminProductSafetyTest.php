<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Inventory\StockReservationLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AD-UI-PROD-M0: data safety of the admin products module.
 *  - PRD-01 / OPEN-PROD-17 (A): permanent deletion is refused, even when the route is called directly, and
 *    nothing related changes; the delete button is gone.
 *  - PRD-02: the stock cannot go below the quantity reserved for open orders (checked under the product lock).
 *  - PRD-03: no redirect follows an external Referer.
 *  - PRD-06: numeric bounds of the columns and compare-at price above the price.
 */
class AdminProductSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    // ---------------------------------------------------------------- PRD-01: no permanent deletion

    public function test_a_super_admin_cannot_delete_a_product_and_nothing_related_changes(): void
    {
        $product = $this->product();
        $related = $this->withOrderReservationReviewAndCart($product);

        $this->actingAs($this->superAdmin(), 'admin')
            ->delete(route('dashboard.products.destroy', $product))
            ->assertRedirect(route('dashboard.products.index'))
            ->assertSessionHas('danger');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Clay jug', 'stock_quantity' => 10, 'status' => 'active']);
        $this->assertSame($related, $this->relatedSnapshot($product));
    }

    public function test_deletion_is_refused_for_an_admin_holding_products_delete(): void
    {
        $product = $this->product();
        $related = $this->withOrderReservationReviewAndCart($product);

        $this->actingAs($this->adminWith(['products.view', 'products.delete']), 'admin')
            ->delete(route('dashboard.products.destroy', $product))
            ->assertRedirect(route('dashboard.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame($related, $this->relatedSnapshot($product));
    }

    public function test_a_direct_json_delete_call_is_refused_with_409(): void
    {
        $product = $this->product();

        $this->actingAs($this->superAdmin(), 'admin')
            ->deleteJson(route('dashboard.products.destroy', $product))
            ->assertStatus(409);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_deletion_still_requires_the_delete_ability(): void
    {
        $product = $this->product();

        $this->actingAs($this->adminWith(['products.view', 'products.edit']), 'admin')
            ->delete(route('dashboard.products.destroy', $product))
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_a_guest_cannot_reach_the_delete_route(): void
    {
        $product = $this->product();

        $this->delete(route('dashboard.products.destroy', $product))->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_the_list_has_no_delete_action_and_shows_the_refusal(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->get(route('dashboard.products.index'))
            ->assertOk()
            ->assertSee('Clay jug')
            ->assertSee(route('dashboard.products.edit', $product), false)
            ->assertDontSee('action="'.route('dashboard.products.destroy', $product).'"', false)
            ->assertDontSee('name="_method" value="DELETE"', false);

        $this->actingAs($admin, 'admin')->followingRedirects()
            ->delete(route('dashboard.products.destroy', $product))
            ->assertOk()
            ->assertSee('Permanent deletion of products is disabled.');
    }

    // ---------------------------------------------------------------- PRD-02: stock and reservations

    public function test_the_stock_cannot_go_below_the_reserved_quantity(): void
    {
        $product = $this->product();
        $this->reserve($product, 6);

        $this->actingAs($this->superAdmin(), 'admin')
            ->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => 5, 'name' => 'Renamed']))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors(['stock_quantity' => 'The stock quantity cannot be lower than the 6 units currently reserved for open orders.']);

        $product->refresh();
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame('Clay jug', $product->name, 'Nothing of the rejected update is written.');
    }

    public function test_the_stock_can_be_set_to_exactly_the_reserved_quantity_or_above(): void
    {
        $product = $this->product();
        $this->reserve($product, 6);
        $admin = $this->superAdmin();

        foreach ([6, 25] as $stock) {
            $this->actingAs($admin, 'admin')
                ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => $stock]))
                ->assertRedirect(route('dashboard.products.index'))
                ->assertSessionHasNoErrors();

            $this->assertSame($stock, $product->fresh()->stock_quantity);
        }
    }

    public function test_released_and_committed_reservations_do_not_hold_stock(): void
    {
        $product = $this->product();
        $this->reserve($product, 4, 'released');
        $this->reserve($product, 3, 'committed');

        $this->actingAs($this->superAdmin(), 'admin')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_the_update_reads_the_reserved_quantity_under_the_product_lock(): void
    {
        $product = $this->product();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [DB::transactionLevel(), $query->sql];
        });

        $this->actingAs($this->superAdmin(), 'admin')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => 3]))
            ->assertSessionHasNoErrors();

        // RefreshDatabase holds level 1; the update's own transaction is level 2. The product row is selected
        // again, then the reservations are summed, then the row is written, all inside that transaction.
        $inside = array_values(array_filter($queries, fn ($q) => $q[0] >= 2));
        $sql = array_column($inside, 1);
        $this->assertNotEmpty($sql);
        $lock = $this->firstIndex($sql, fn ($s) => str_contains($s, 'from "products"') && str_contains($s, '"products"."id" ='));
        $reserved = $this->firstIndex($sql, fn ($s) => str_contains($s, 'from "stock_reservations"'));
        $write = $this->firstIndex($sql, fn ($s) => str_starts_with($s, 'update "products"'));
        $this->assertNotNull($lock);
        $this->assertNotNull($reserved);
        $this->assertNotNull($write);
        $this->assertTrue($lock < $reserved && $reserved < $write, 'lock, then reserved quantity, then write');
    }

    public function test_an_editor_without_products_edit_cannot_update(): void
    {
        $product = $this->product();

        $this->actingAs($this->adminWith(['products.view']), 'admin')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => 1]))
            ->assertForbidden();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    // ---------------------------------------------------------------- M0-FIX: lost update of the stock

    public function test_saving_an_old_form_without_touching_the_stock_keeps_a_committed_decrement(): void
    {
        $product = $this->product();
        $vendorOrder = $this->reserve($product, 6);
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);

        // The order is fulfilled after the form was opened: physical stock drops from 10 to 4.
        $this->travel(5)->seconds();
        app(StockReservationLifecycle::class)->commitForVendorOrder($vendorOrder);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        // The admin changes only the price; the form still carries stock_quantity = 10.
        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['price' => '12']))
            ->assertRedirect(route('dashboard.products.index'))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('12.00', $product->price);
        $this->assertSame(4, $product->stock_quantity, 'The shipped units are not restored.');
    }

    public function test_a_price_only_edit_never_writes_the_stock_column(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (str_starts_with($query->sql, 'update "products"')) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['price' => '15']))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $writes);
        $this->assertStringNotContainsString('"stock_quantity"', $writes[0]);
        $this->assertSame('15.00', $product->fresh()->price);
    }

    public function test_an_intended_stock_change_after_a_concurrent_decrement_is_refused(): void
    {
        $product = $this->product();
        $vendorOrder = $this->reserve($product, 6);
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);

        // Same second as the form: only the stock value tells the change apart, and it is enough.
        app(StockReservationLifecycle::class)->commitForVendorOrder($vendorOrder);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['stock_quantity' => '12', 'price' => '20', 'name' => 'Renamed']))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors(['stock_quantity' => 'The stock changed while this page was open (it is now 4). Review it and save again.'])
            ->assertSessionHasInput('name', 'Renamed')
            ->assertSessionMissing('_old_input.stock_quantity')
            ->assertSessionMissing('_old_input.stock_quantity_original')
            ->assertSessionMissing('_old_input.stock_version');

        $product->refresh();
        $this->assertSame(4, $product->stock_quantity);
        $this->assertSame('10.00', $product->price, 'Nothing of the refused update is written.');
        $this->assertSame('Clay jug', $product->name);

        // The form shown next carries the current stock and a fresh basis, so a reviewed save goes through.
        $this->actingAs($admin, 'admin')->get(route('dashboard.products.edit', $product))
            ->assertOk()
            ->assertSee('name="stock_quantity_original" value="4"', false);
        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => '12']))
            ->assertSessionHasNoErrors();
        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    public function test_a_change_and_its_reversal_meanwhile_still_refuse_the_stock_change(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);

        // Someone else sets the stock to 4 and back to 10: same value, newer row.
        $this->travel(5)->seconds();
        $product->update(['stock_quantity' => 4]);
        $product->update(['stock_quantity' => 10]);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['stock_quantity' => '30']))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors('stock_quantity');

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_a_stock_change_without_conflict_is_saved(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['stock_quantity' => '7', 'price' => '11']))
            ->assertRedirect(route('dashboard.products.index'))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(7, $product->stock_quantity);
        $this->assertSame('11.00', $product->price);
    }

    public function test_an_unconflicted_stock_change_below_the_reserved_quantity_is_still_refused(): void
    {
        $product = $this->product();
        $this->reserve($product, 6);
        $admin = $this->superAdmin();
        $form = $this->openEditForm($admin, $product);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.products.update', $product), array_merge($form, ['stock_quantity' => '5', 'price' => '30']))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors(['stock_quantity' => 'The stock quantity cannot be lower than the 6 units currently reserved for open orders.']);

        $product->refresh();
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame('10.00', $product->price);
    }

    public function test_an_update_without_the_stock_basis_is_refused_and_writes_nothing(): void
    {
        $product = $this->product();

        $admin = $this->superAdmin();
        $complete = $this->updatePayload($product, ['price' => '99']);
        $cases = [
            'no original stock' => array_diff_key($complete, ['stock_quantity_original' => 1]),
            'no version field' => array_diff_key($complete, ['stock_version' => 1]),
            'original stock not a number' => ['stock_quantity_original' => 'x'] + $complete,
        ];

        foreach ($cases as $payload) {
            $this->actingAs($admin, 'admin')
                ->put(route('dashboard.products.update', $product), $payload)
                ->assertRedirect(route('dashboard.products.edit', $product))
                ->assertSessionHasErrors(['stock_quantity' => 'This form is out of date. Reload the page and save again.']);
        }

        $product->refresh();
        $this->assertSame('10.00', $product->price);
        $this->assertSame(10, $product->stock_quantity);
    }

    public function test_the_edit_form_carries_the_displayed_stock_and_its_version(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->get(route('dashboard.products.edit', $product))
            ->assertOk()
            ->assertSee('name="stock_quantity_original" value="10"', false)
            ->assertSee('name="stock_version" value="'.ProductManagementController::stockVersion($product->fresh()).'"', false);

        $this->actingAs($admin, 'admin')->get(route('dashboard.products.create'))
            ->assertOk()
            ->assertDontSee('name="stock_quantity_original"', false);
    }

    // ---------------------------------------------------------------- PRD-03: no external redirect

    public function test_validation_errors_never_follow_an_external_referer(): void
    {
        $product = $this->product();
        $admin = $this->superAdmin();

        foreach (['https://evil.example/dashboard/products', '//evil.example/x', 'javascript:alert(1)'] as $referer) {
            $this->actingAs($admin, 'admin')->withHeader('referer', $referer)
                ->post(route('dashboard.products.store'), $this->payload(['price' => -1]))
                ->assertRedirect(route('dashboard.products.create'));

            $this->actingAs($admin, 'admin')->withHeader('referer', $referer)
                ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['price' => -1]))
                ->assertRedirect(route('dashboard.products.edit', $product));

            $this->actingAs($admin, 'admin')->withHeader('referer', $referer)
                ->delete(route('dashboard.products.destroy', $product))
                ->assertRedirect(route('dashboard.products.index'));
        }
    }

    public function test_the_reserved_stock_error_does_not_follow_an_external_referer(): void
    {
        $product = $this->product();
        $this->reserve($product, 6);

        $this->actingAs($this->superAdmin(), 'admin')->withHeader('referer', 'https://evil.example/')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, ['stock_quantity' => 1]))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors('stock_quantity');
    }

    public function test_successful_saves_go_to_the_products_list(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->withHeader('referer', 'https://evil.example/')
            ->post(route('dashboard.products.store'), $this->payload(['name' => 'New jug', 'slug' => 'new-jug']))
            ->assertRedirect(route('dashboard.products.index'));

        $this->assertDatabaseHas('products', ['slug' => 'new-jug']);
    }

    // ---------------------------------------------------------------- PRD-06: numeric bounds and relations

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidValues(): array
    {
        return [
            'price above DECIMAL(10,2)' => [['price' => '100000000'], 'price'],
            'price with three decimals' => [['price' => '10.555'], 'price'],
            'price in exponent form' => [['price' => '1e3'], 'price'],
            'negative price' => [['price' => '-0.01'], 'price'],
            'compare-at equal to the price' => [['price' => '50', 'compare_at_price' => '50'], 'compare_at_price'],
            'compare-at below the price' => [['price' => '50', 'compare_at_price' => '20'], 'compare_at_price'],
            'compare-at above DECIMAL(10,2)' => [['price' => '50', 'compare_at_price' => '100000000'], 'compare_at_price'],
            'stock above UNSIGNED INT' => [['stock_quantity' => '4294967296'], 'stock_quantity'],
            'negative stock' => [['stock_quantity' => '-1'], 'stock_quantity'],
            'fractional stock' => [['stock_quantity' => '1.5'], 'stock_quantity'],
            'threshold above UNSIGNED INT' => [['low_stock_threshold' => '4294967296'], 'low_stock_threshold'],
            'weight above DECIMAL(8,2)' => [['weight' => '1000000'], 'weight'],
            'weight with three decimals' => [['weight' => '1.255'], 'weight'],
            'slug with a slash' => [['slug' => 'clay/jug'], 'slug'],
            'slug with a space' => [['slug' => 'clay jug'], 'slug'],
            'slug with a query mark' => [['slug' => 'clay?jug'], 'slug'],
        ];
    }

    /** @param  array<string, mixed>  $values */
    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_field_errors_on_create(array $values, string $field): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('dashboard.products.store'), $this->payload($values + ['slug' => 'created']))
            ->assertRedirect(route('dashboard.products.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Product::count());
    }

    /** @param  array<string, mixed>  $values */
    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_field_errors_on_update(array $values, string $field): void
    {
        $product = $this->product();

        $this->actingAs($this->superAdmin(), 'admin')
            ->put(route('dashboard.products.update', $product), $this->updatePayload($product, $values))
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors($field);

        $product->refresh();
        $this->assertSame('10.00', $product->price);
        $this->assertNull($product->compare_at_price);
        $this->assertSame(10, $product->stock_quantity);
    }

    public function test_values_at_the_bounds_are_accepted(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('dashboard.products.store'), $this->payload([
                'slug' => 'bounds',
                'price' => '99999999.98',
                'compare_at_price' => '99999999.99',
                'stock_quantity' => '4294967295',
                'low_stock_threshold' => '4294967295',
                'weight' => '999999.99',
            ]))
            ->assertRedirect(route('dashboard.products.index'))
            ->assertSessionHasNoErrors();

        $product = Product::where('slug', 'bounds')->sole();
        $this->assertSame('99999999.98', $product->price);
        $this->assertSame('99999999.99', $product->compare_at_price);
        $this->assertSame(4294967295, $product->stock_quantity);
    }

    public function test_a_compare_at_price_above_the_price_and_existing_slug_rules_still_work(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.products.store'), $this->payload(['slug' => 'jug-ok', 'price' => '40', 'compare_at_price' => '55.5']))
            ->assertSessionHasNoErrors();
        $this->assertSame('55.50', Product::where('slug', 'jug-ok')->sole()->compare_at_price);

        // An empty slug is still generated from the name, as before.
        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.products.store'), $this->payload(['name' => 'Copper Pot', 'slug' => '']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['slug' => 'copper-pot']);
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $overrides = []): Product
    {
        return Product::create($overrides + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'price' => 10,
            'stock_quantity' => 10,
            'low_stock_threshold' => 1,
            'status' => 'active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'price' => '10',
            'stock_quantity' => '10',
            'low_stock_threshold' => '1',
            'status' => 'active',
        ];
    }

    /** An update as the edit form sends it: the fields plus the stock basis the form displayed. */
    private function updatePayload(Product $product, array $overrides = [], ?Product $displayed = null): array
    {
        return $this->payload($overrides) + $this->basis($displayed ?? $product->fresh());
    }

    /** The hidden fields of the edit form rendered for $product as it is now. */
    private function basis(Product $product): array
    {
        return [
            'stock_quantity_original' => (string) $product->stock_quantity,
            'stock_version' => ProductManagementController::stockVersion($product),
        ];
    }

    private function reserve(Product $product, int $quantity, string $status = 'reserved'): VendorOrder
    {
        [$orderId, $vendorOrderId, $itemId] = $this->order($product, $quantity);

        DB::table('stock_reservations')->insert([
            'product_id' => $product->id, 'order_id' => $orderId, 'vendor_order_id' => $vendorOrderId, 'order_item_id' => $itemId,
            'quantity' => $quantity, 'status' => $status, 'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return VendorOrder::findOrFail($vendorOrderId);
    }

    /** Opens the real edit page and returns what its form would submit unchanged (fields and hidden basis). */
    private function openEditForm(Admin $admin, Product $product): array
    {
        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.products.edit', $product))->assertOk()->getContent();
        $hidden = [];

        foreach (['stock_quantity_original', 'stock_version'] as $name) {
            $this->assertMatchesRegularExpression('/name="'.$name.'" value="([^"]*)"/', $html);
            preg_match('/name="'.$name.'" value="([^"]*)"/', $html, $match);
            $hidden[$name] = html_entity_decode($match[1]);
        }

        return $this->payload(['stock_quantity' => (string) $product->fresh()->stock_quantity]) + $hidden;
    }

    /** @return array{int, int, int} */
    private function order(Product $product, int $quantity): array
    {
        static $sequence = 0;
        $sequence++;
        $customer = Customer::factory()->create();
        $orderId = DB::table('orders')->insertGetId(['number' => "O-{$sequence}", 'customer_id' => $customer->id, 'payment_method' => 'cod', 'created_at' => now(), 'updated_at' => now()]);
        $vendorOrderId = DB::table('vendor_orders')->insertGetId(['number' => "O-{$sequence}-V", 'order_id' => $orderId, 'vendor_id' => $product->vendor_id, 'created_at' => now(), 'updated_at' => now()]);
        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'vendor_order_id' => $vendorOrderId, 'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => $quantity, 'unit_price' => 10, 'line_total' => 10 * $quantity, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$orderId, $vendorOrderId, $itemId];
    }

    /** An open order with a reservation, a product review and a cart item, as a deletion would cascade to. */
    private function withOrderReservationReviewAndCart(Product $product): array
    {
        $this->reserve($product, 6);
        $customer = Customer::factory()->create();
        $orderId = (int) DB::table('orders')->value('id');
        DB::table('reviews')->insert(['customer_id' => $customer->id, 'order_id' => $orderId, 'product_id' => $product->id, 'reviewable_type' => 'product', 'rating' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $cartId = DB::table('carts')->insertGetId(['customer_id' => $customer->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'created_at' => now(), 'updated_at' => now()]);

        return $this->relatedSnapshot($product);
    }

    private function relatedSnapshot(Product $product): array
    {
        return [
            'reservations' => DB::table('stock_reservations')->where('product_id', $product->id)->orderBy('id')->get(['id', 'quantity', 'status'])->map(fn ($r) => (array) $r)->all(),
            'reviews' => DB::table('reviews')->where('product_id', $product->id)->count(),
            'cart_items' => DB::table('cart_items')->where('product_id', $product->id)->count(),
            'order_items' => DB::table('order_items')->where('product_id', $product->id)->count(),
        ];
    }

    private function firstIndex(array $items, callable $match): ?int
    {
        foreach ($items as $index => $item) {
            if ($match($item)) {
                return $index;
            }
        }

        return null;
    }

    private function superAdmin(): Admin
    {
        return Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
    }

    private function adminWith(array $abilities): Admin
    {
        $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'phone' => '0599000002', 'password' => 'password', 'status' => 'active', 'super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
