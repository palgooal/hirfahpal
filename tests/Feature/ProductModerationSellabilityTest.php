<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use App\Models\Language;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AD-UI-PROD-M2: a product suspended (suspended_at) or archived (archived_at) by the administration is not sellable,
 * through the one shared contract (Product::scopeSellable() / isSellable()) that the storefront list and page, the
 * cart, the favorites and checkout already use. The previous conditions (active product, active and approved
 * vendor) are unchanged. No route sets the state yet (M5, M6): the tests set it directly.
 */
class ProductModerationSellabilityTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        app()->setLocale('en');
        $this->vendor = Vendor::factory()->approved()->create();
        $this->category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
    }

    /** @return array<string, array{list<string>}> */
    public static function states(): array
    {
        return [
            'suspended' => [['suspended_at']],
            'archived' => [['archived_at']],
            'suspended and archived' => [['suspended_at', 'archived_at']],
        ];
    }

    // ---------------------------------------------------------------- storefront

    #[DataProvider('states')]
    public function test_the_storefront_list_and_its_filters_leave_it_out(array $state): void
    {
        $normal = $this->product('Normal jug');
        $moderated = $this->moderate($this->product('Moderated jug'), $state);

        // The price_min / price_max filters are left out: they fail with a 500 before and after M2
        // (ProductCatalogController calls Request::decimal(), which does not exist); reported, not fixed here.
        foreach ([[], ['category' => 'pottery'], ['vendor' => $this->vendor->profile->slug], ['sort' => 'newest']] as $filters) {
            $ids = collect($this->getJson(route('shop.products.index', $filters))->assertOk()->json('data'))->pluck('id')->all();

            $this->assertSame([$normal->id], $ids, 'filters: '.json_encode($filters));
            $this->assertNotContains($moderated->id, $ids);
        }
    }

    #[DataProvider('states')]
    public function test_the_product_page_by_direct_link_is_not_found(array $state): void
    {
        $normal = $this->product('Normal jug');
        $moderated = $this->moderate($this->product('Moderated jug'), $state);

        $this->getJson(route('shop.products.show', $moderated))->assertNotFound();
        $this->getJson(route('shop.products.show', $normal))->assertOk()->assertJsonPath('id', $normal->id);
    }

    // ---------------------------------------------------------------- cart and favorites

    #[DataProvider('states')]
    public function test_it_cannot_be_added_to_the_cart_by_a_direct_request(array $state): void
    {
        $moderated = $this->moderate($this->product('Moderated jug'), $state);

        $this->postJson(route('customer.cart.items.store'), ['product_id' => $moderated->id, 'quantity' => 1])->assertNotFound();
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->postJson(route('customer.cart.items.store'), ['product_id' => $moderated->id, 'quantity' => 1])->assertNotFound();

        $this->assertSame(0, CartItem::count());
    }

    #[DataProvider('states')]
    public function test_its_quantity_in_an_existing_cart_cannot_be_changed(array $state): void
    {
        $product = $this->product('Jug');
        $this->actingAs(Customer::factory()->create(), 'customer');
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $item = CartItem::sole();

        $this->moderate($product, $state);

        $this->patchJson(route('customer.cart.items.update', $item), ['quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This product is no longer available for sale.');
        $this->assertSame(1, (int) $item->fresh()->quantity);
    }

    #[DataProvider('states')]
    public function test_it_cannot_be_added_to_the_favorites(array $state): void
    {
        $moderated = $this->moderate($this->product('Moderated jug'), $state);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->postJson(route('customer.favorites.store'), ['product_id' => $moderated->id])
            ->assertNotFound();

        $this->assertSame(0, CustomerFavorite::count());
    }

    // ---------------------------------------------------------------- checkout

    #[DataProvider('states')]
    public function test_checkout_is_refused_when_the_state_changed_after_the_product_entered_the_cart(array $state): void
    {
        $product = $this->product('Jug', stock: 5);
        $customer = $this->customerWithCart([$product->id => 2]);

        $this->moderate($product, $state);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Product [Jug] is no longer available for sale.');

        $this->assertNothingWasSold($product, 5);
    }

    #[DataProvider('states')]
    public function test_checkout_is_refused_when_the_state_changes_between_the_first_check_and_the_lock(array $state): void
    {
        $product = $this->product('Jug', stock: 5);
        $customer = $this->customerWithCart([$product->id => 2]);

        // The product is sellable when checkout first checks the cart (before its transaction). The state is set
        // just before that transaction starts, as a concurrent, committed administrative action would be; only
        // the re-check on the locked row can refuse it.
        $set = false;
        DB::connection()->beforeStartingTransaction(function () use ($product, $state, &$set) {
            if (! $set) {
                $set = true;
                DB::table('products')->where('id', $product->id)->update(array_fill_keys($state, now()));
            }
        });

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Product [Jug] is no longer available for sale.');

        $this->assertTrue($set, 'The state changed after the first check.');
        $this->assertNothingWasSold($product, 5);
    }

    public function test_a_normal_product_next_to_a_moderated_one_still_sells_on_its_own(): void
    {
        $normal = $this->product('Normal jug', stock: 5);
        $moderated = $this->moderate($this->product('Moderated jug', stock: 5), ['suspended_at']);

        // A cart holding both is refused as a whole.
        $customer = $this->customerWithCart([$normal->id => 1, $moderated->id => 1]);
        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])->assertStatus(422);
        $this->assertNothingWasSold($normal, 5);

        // The normal product alone sells exactly as before.
        $this->checkoutAndAssertSold($normal, 2);
    }

    // ---------------------------------------------------------------- unchanged conditions

    public function test_a_normal_product_is_listed_shown_added_favorited_and_sold(): void
    {
        $product = $this->product('Jug', stock: 5);

        $this->assertSame([$product->id], collect($this->getJson(route('shop.products.index'))->json('data'))->pluck('id')->all());
        $this->getJson(route('shop.products.show', $product))->assertOk();

        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->postJson(route('customer.favorites.store'), ['product_id' => $product->id])->assertCreated();
        $this->actingAs($customer, 'customer')->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $this->checkoutAndAssertSold($product, 2);
    }

    public function test_the_previous_conditions_still_apply_with_both_columns_null(): void
    {
        $inactive = $this->product('Inactive', status: 'inactive');
        $draft = $this->product('Draft', status: 'draft');
        $blockedVendor = $this->product('Blocked vendor', vendor: Vendor::factory()->approved()->create(['status' => 'blocked']));
        $pendingProfile = $this->product('Pending profile', vendor: Vendor::factory()->withProfile('pending')->create());
        $rejectedProfile = $this->product('Rejected profile', vendor: Vendor::factory()->withProfile('rejected', 'No')->create());
        $sellable = $this->product('Sellable');

        $this->assertSame([$sellable->id], Product::query()->sellable()->pluck('id')->all());

        foreach ([$inactive, $draft, $blockedVendor, $pendingProfile, $rejectedProfile] as $product) {
            $fresh = $product->fresh();
            $this->assertNull($fresh->suspended_at);
            $this->assertNull($fresh->archived_at);
            $this->assertFalse($fresh->isSellable(), $product->name);
        }

        $this->assertTrue($sellable->fresh()->isSellable());
    }

    public function test_clearing_the_state_does_not_bypass_the_other_conditions(): void
    {
        $product = $this->moderate($this->product('Jug', status: 'inactive'), ['suspended_at', 'archived_at']);

        $product->forceFill(['suspended_at' => null, 'archived_at' => null])->save();

        $this->assertFalse($product->fresh()->isSellable(), 'Still inactive.');
        $this->assertSame([], Product::query()->sellable()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- lifting the state

    #[DataProvider('states')]
    public function test_it_sells_again_once_the_state_is_cleared(array $state): void
    {
        $product = $this->moderate($this->product('Jug', stock: 5), $state);
        $this->assertFalse($product->fresh()->isSellable());

        $product->forceFill(array_fill_keys($state, null))->save();

        $this->assertTrue($product->fresh()->isSellable());
        $this->getJson(route('shop.products.show', $product))->assertOk();
        $this->checkoutAndAssertSold($product, 1);
    }

    public function test_clearing_only_one_of_two_states_keeps_it_off_sale(): void
    {
        $product = $this->moderate($this->product('Jug'), ['suspended_at', 'archived_at']);

        $product->forceFill(['suspended_at' => null])->save(); // Unsuspended, still archived.
        $this->assertFalse($product->fresh()->isSellable());

        $product->forceFill(['suspended_at' => now(), 'archived_at' => null])->save(); // Restored, still suspended.
        $this->assertFalse($product->fresh()->isSellable());
        $this->assertSame([], Product::query()->sellable()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- fail-closed

    public function test_a_product_loaded_without_the_state_columns_is_not_sellable(): void
    {
        $product = $this->product('Jug');

        $full = Product::query()->find($product->id);
        $this->assertTrue($full->isSellable());

        foreach ([['id', 'vendor_id', 'status'], ['id', 'vendor_id', 'status', 'suspended_at'], ['id', 'vendor_id', 'status', 'archived_at']] as $columns) {
            $partial = Product::query()->select($columns)->find($product->id);
            $this->assertFalse($partial->isSellable(), 'Loaded with: '.implode(', ', $columns));
        }

        $this->assertTrue(Product::query()->select(['id', 'vendor_id', 'status', 'suspended_at', 'archived_at'])->find($product->id)->isSellable());
    }

    public function test_the_check_reads_the_model_values_not_its_json(): void
    {
        $product = $this->moderate($this->product('Jug'), ['suspended_at']);
        $fresh = $product->fresh();

        $this->assertArrayNotHasKey('suspended_at', $fresh->toArray(), 'Hidden from JSON (M1).');
        $this->assertFalse($fresh->isSellable(), 'Still read by the contract.');
    }

    // ---------------------------------------------------------------- helpers

    private function product(string $name, int $stock = 10, string $status = 'active', ?Vendor $vendor = null): Product
    {
        return Product::create([
            'vendor_id' => ($vendor ?? $this->vendor)->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'price' => 10,
            'stock_quantity' => $stock,
            'status' => $status,
        ]);
    }

    /** Sets the given state columns, as the M5 / M6 actions will (they are not mass assignable). */
    private function moderate(Product $product, array $state): Product
    {
        $product->forceFill(array_fill_keys($state, now()))->save();

        return $product;
    }

    private function customerWithCart(array $quantities): Customer
    {
        $customer = Customer::factory()->create();
        $cart = Cart::create(['customer_id' => $customer->id, 'status' => 'active']);

        foreach ($quantities as $productId => $quantity) {
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $productId, 'quantity' => $quantity, 'unit_price' => 10]);
        }

        return $customer;
    }

    private function checkoutAndAssertSold(Product $product, int $quantity): void
    {
        $customer = $this->customerWithCart([$product->id => $quantity]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertCreated();

        $this->assertSame(1, OrderItem::where('product_id', $product->id)->count());
        $this->assertSame($quantity, (int) StockReservation::where('product_id', $product->id)->where('status', 'reserved')->sum('quantity'));
    }

    private function assertNothingWasSold(Product $product, int $stock): void
    {
        $this->assertSame(0, Order::count());
        $this->assertSame(0, VendorOrder::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockReservation::count());
        $this->assertSame($stock, $product->fresh()->stock_quantity);
    }
}
