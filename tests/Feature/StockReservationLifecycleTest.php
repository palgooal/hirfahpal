<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Http\Requests\VendorDashboard\UpdateProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * VEN-BE-019 approved stock contract: checkout reserves, vendor reject
 * releases, vendor accept commits and decrements physical stock once.
 *
 * The suite runs on SQLite, where lockForUpdate is a no-op: these tests prove
 * the logical invariants (no over-reservation, single deduction, rollback),
 * not real row-lock concurrency.
 */
class StockReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // A1. Checkout reserves without touching physical stock.
    public function test_checkout_creates_a_linked_reserved_reservation_without_decrementing_stock(): void
    {
        $product = $this->product($this->vendor(), stock: 10);

        $this->checkout([$product->id => 3])->assertCreated();

        $reservation = StockReservation::sole();
        $order = Order::sole();
        $vendorOrder = VendorOrder::sole();
        $orderItem = OrderItem::sole();

        $this->assertSame('reserved', $reservation->status);
        $this->assertSame(3, (int) $reservation->quantity);
        $this->assertNotNull($reservation->reserved_at);
        $this->assertSame($product->id, (int) $reservation->product_id);
        $this->assertSame($order->id, (int) $reservation->order_id);
        $this->assertSame($vendorOrder->id, (int) $reservation->vendor_order_id);
        $this->assertSame($orderItem->id, (int) $reservation->order_item_id);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(7, $product->fresh()->availableStockQuantity());
    }

    // A2. Checkout refuses more than the available stock.
    public function test_checkout_refuses_quantity_above_available_stock(): void
    {
        $product = $this->product($this->vendor(), stock: 2);

        $this->checkout([$product->id => 3])->assertStatus(422);

        $this->assertSame(0, StockReservation::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    // A3. An outstanding reservation reduces what the next checkout can reserve.
    public function test_existing_reservations_reduce_what_another_checkout_can_reserve(): void
    {
        $product = $this->product($this->vendor(), stock: 5);

        $this->checkout([$product->id => 3])->assertCreated();
        $this->checkout([$product->id => 3])->assertStatus(422);
        $this->assertSame(1, StockReservation::count());

        $this->checkout([$product->id => 2])->assertCreated();

        $this->assertSame(5, (int) StockReservation::where('status', 'reserved')->sum('quantity'));
        $this->assertSame(0, $product->fresh()->availableStockQuantity());
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_cart_add_and_update_refuse_quantities_above_available_stock(): void
    {
        $product = $this->product($this->vendor(), stock: 5);
        $this->reservation($product, 3, 'reserved');
        // A signed-in customer's cart is keyed by customer, not by the per-request test session.
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])->assertStatus(422);
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();

        $item = CartItem::sole();
        $this->patchJson(route('customer.cart.items.update', $item), ['quantity' => 3])->assertStatus(422);
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockReservation::count());
    }

    public function test_checkout_with_several_products_in_any_cart_order_reserves_each_one(): void
    {
        $vendor = $this->vendor();
        $first = $this->product($vendor, stock: 5);
        $second = $this->product($vendor, stock: 5);

        // Cart lists the higher id first; locks are still taken in ascending id order.
        $this->checkout([$second->id => 2, $first->id => 1])->assertCreated();

        $this->assertSame(2, StockReservation::where('status', 'reserved')->count());
        $this->assertSame(5, $first->fresh()->stock_quantity);
        $this->assertSame(5, $second->fresh()->stock_quantity);
    }

    // B4. Reject releases without restoring stock.
    public function test_reject_releases_the_reservation_without_changing_physical_stock(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->checkout([$product->id => 4]);
        $vendorOrder = VendorOrder::sole();

        $this->reject($vendor, $vendorOrder)->assertOk()->assertJsonPath('order.status', 'rejected');

        $reservation = StockReservation::sole();
        $this->assertSame('released', $reservation->status);
        $this->assertNotNull($reservation->released_at);
        $this->assertNull($reservation->committed_at);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(10, $product->fresh()->availableStockQuantity());
    }

    // B5. Repeated or invalid reject never changes stock.
    public function test_repeated_or_invalid_reject_does_not_alter_stock(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->checkout([$product->id => 4]);
        $rejected = VendorOrder::sole();

        $this->reject($vendor, $rejected)->assertOk();
        $this->reject($vendor, $rejected)->assertStatus(422);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('released', StockReservation::sole()->status);

        // Reject after accept: refused, committed stock untouched.
        $this->checkout([$product->id => 2]);
        $accepted = VendorOrder::latest('id')->first();
        $this->accept($vendor, $accepted)->assertOk();
        $this->reject($vendor, $accepted)->assertStatus(422);

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('committed', StockReservation::where('vendor_order_id', $accepted->id)->sole()->status);
    }

    // C6. Accept commits and decrements once.
    public function test_accept_commits_the_reservation_and_decrements_stock_once(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->checkout([$product->id => 3]);
        $vendorOrder = VendorOrder::sole();

        $this->accept($vendor, $vendorOrder)->assertOk()->assertJsonPath('order.status', 'accepted');

        $reservation = StockReservation::sole();
        $this->assertSame('accepted', $vendorOrder->fresh()->status);
        $this->assertSame('committed', $reservation->status);
        $this->assertNotNull($reservation->committed_at);
        $this->assertNull($reservation->released_at);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(7, $product->fresh()->availableStockQuantity());
    }

    // C7. Several products commit together.
    public function test_multiple_product_reservations_commit_atomically(): void
    {
        $vendor = $this->vendor();
        $first = $this->product($vendor, stock: 10);
        $second = $this->product($vendor, stock: 6);
        $this->checkout([$second->id => 2, $first->id => 5]);

        $this->accept($vendor, VendorOrder::sole())->assertOk();

        $this->assertSame(2, StockReservation::where('status', 'committed')->count());
        $this->assertSame(5, $first->fresh()->stock_quantity);
        $this->assertSame(4, $second->fresh()->stock_quantity);
    }

    // C8. A repeated Accept cannot deduct twice.
    public function test_repeated_accept_cannot_decrement_twice(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->checkout([$product->id => 3]);
        $vendorOrder = VendorOrder::sole();

        $this->accept($vendor, $vendorOrder)->assertOk();
        $this->accept($vendor, $vendorOrder)->assertStatus(422);
        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockReservation::where('status', 'committed')->count());
    }

    // C9. A reservation that can no longer be committed rolls back the whole Accept.
    public function test_failed_commit_rolls_back_order_status_stock_and_reservations(): void
    {
        $vendor = $this->vendor();
        $first = $this->product($vendor, stock: 10);
        $second = $this->product($vendor, stock: 10);
        $this->checkout([$first->id => 2, $second->id => 3]);
        $vendorOrder = VendorOrder::sole();

        StockReservation::where('product_id', $second->id)->update(['status' => 'released', 'released_at' => now()]);

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertNull($vendorOrder->fresh()->accepted_at);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
        $this->assertSame('reserved', StockReservation::where('product_id', $first->id)->sole()->status);
        $this->assertNull(StockReservation::where('product_id', $first->id)->sole()->committed_at);
        $this->assertSame('released', StockReservation::where('product_id', $second->id)->sole()->status);
    }

    public function test_insufficient_physical_stock_rolls_back_the_whole_accept(): void
    {
        $vendor = $this->vendor();
        $first = $this->product($vendor, stock: 10);
        $second = $this->product($vendor, stock: 10);
        $this->checkout([$first->id => 2, $second->id => 3]);
        $vendorOrder = VendorOrder::sole();

        // Inconsistent legacy data: physical stock below the reserved quantity.
        $second->forceFill(['stock_quantity' => 1])->save();

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertSame(2, StockReservation::where('status', 'reserved')->count());
    }

    // C10. A vendor cannot commit another vendor's stock or reservations.
    public function test_vendor_cannot_accept_another_vendors_order(): void
    {
        $owner = $this->vendor();
        $product = $this->product($owner, stock: 10);
        $this->checkout([$product->id => 3]);

        $this->accept($this->vendor(), VendorOrder::sole())->assertNotFound();

        $this->assertSame('pending', VendorOrder::sole()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('reserved', StockReservation::sole()->status);
    }

    public function test_reservation_for_another_vendors_product_is_never_committed(): void
    {
        $vendor = $this->vendor();
        $foreignProduct = $this->product($this->vendor(), stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);
        // Complete item/reservation pair, so the ownership check is what refuses it.
        $item = $this->orderItem($vendorOrder, $foreignProduct, 2);
        $this->reserveItem($vendorOrder, $item, $foreignProduct, 2);

        $this->accept($vendor, $vendorOrder)->assertStatus(422)->assertJsonPath('message', 'Reserved stock does not belong to this vendor.');

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertSame(10, $foreignProduct->fresh()->stock_quantity);
        $this->assertSame('reserved', StockReservation::sole()->status);
    }

    // Header-only vendor order (Admin form): compatibility behavior, not a business contract.
    public function test_header_only_vendor_order_is_accepted_without_stock_changes(): void
    {
        $vendor = $this->vendor();
        $untouched = $this->product($vendor, stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);

        $this->accept($vendor, $vendorOrder)->assertOk();

        $this->assertSame('accepted', $vendorOrder->fresh()->status);
        $this->assertNotNull($vendorOrder->fresh()->accepted_at);
        $this->assertSame(0, $vendorOrder->items()->count());
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(10, $untouched->fresh()->stock_quantity);
    }

    // Completeness 1: a stock-backed item without a reservation.
    public function test_accept_refuses_a_stock_backed_item_without_a_reservation(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);
        $this->orderItem($vendorOrder, $product, 2);

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertNull($vendorOrder->fresh()->accepted_at);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockReservation::count());
    }

    // Completeness 2: two stock-backed items, only one reserved.
    public function test_accept_refuses_when_only_some_items_are_reserved(): void
    {
        $vendor = $this->vendor();
        $reservedProduct = $this->product($vendor, stock: 10);
        $missingProduct = $this->product($vendor, stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);
        $reservedItem = $this->orderItem($vendorOrder, $reservedProduct, 2);
        $this->orderItem($vendorOrder, $missingProduct, 3);
        $reservation = $this->reserveItem($vendorOrder, $reservedItem, $reservedProduct, 2);

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertSame(10, $reservedProduct->fresh()->stock_quantity);
        $this->assertSame(10, $missingProduct->fresh()->stock_quantity);
        $this->assertSame('reserved', $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->committed_at);
    }

    // Completeness 3: the reservation points at another order item.
    public function test_accept_refuses_a_reservation_with_the_wrong_order_item(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);
        $this->orderItem($vendorOrder, $product, 2);
        $otherItem = $this->orderItem($this->vendorOrderWithReservation($vendor, null, 0), $product, 2);
        $reservation = $this->reserveItem($vendorOrder, $otherItem, $product, 2);

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('reserved', $reservation->fresh()->status);
    }

    // Completeness 4: the reservation is for another product than its item.
    public function test_accept_refuses_a_reservation_with_the_wrong_product(): void
    {
        $vendor = $this->vendor();
        $orderedProduct = $this->product($vendor, stock: 10);
        $otherProduct = $this->product($vendor, stock: 10);
        $vendorOrder = $this->vendorOrderWithReservation($vendor, null, 0);
        $item = $this->orderItem($vendorOrder, $orderedProduct, 2);
        $reservation = $this->reserveItem($vendorOrder, $item, $otherProduct, 2);

        $this->accept($vendor, $vendorOrder)->assertStatus(422);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertSame(10, $orderedProduct->fresh()->stock_quantity);
        $this->assertSame(10, $otherProduct->fresh()->stock_quantity);
        $this->assertSame('reserved', $reservation->fresh()->status);
    }

    // Rollback after a mutation: a SQLite test-only trigger makes the reservation
    // commit fail after the stock decrements already ran (no production hook).
    public function test_failure_after_stock_was_decremented_rolls_everything_back(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Uses a SQLite trigger to fail the commit step.');
        }

        $vendor = $this->vendor();
        $first = $this->product($vendor, stock: 10);
        $second = $this->product($vendor, stock: 10);
        $this->checkout([$first->id => 2, $second->id => 3]);
        $vendorOrder = VendorOrder::sole();

        DB::statement("CREATE TRIGGER fail_reservation_commit BEFORE UPDATE OF status ON stock_reservations WHEN NEW.status = 'committed' BEGIN SELECT RAISE(ABORT, 'forced commit failure'); END");

        $this->accept($vendor, $vendorOrder)->assertStatus(500);

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertNull($vendorOrder->fresh()->accepted_at);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
        $this->assertSame(2, StockReservation::where('status', 'reserved')->whereNull('committed_at')->count());
    }

    // D11. Stock cannot go below the outstanding reserved quantity.
    public function test_vendor_cannot_lower_stock_below_outstanding_reservations(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->reservation($product, 5, 'reserved');

        $this->updateStock($vendor, $product, 4)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_quantity']);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    // D12 + D13. Equal to or above the reserved quantity is allowed.
    public function test_vendor_can_set_stock_equal_to_or_above_outstanding_reservations(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->reservation($product, 5, 'reserved');

        $this->updateStock($vendor, $product, 5)->assertOk();
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $this->updateStock($vendor, $product, 12)->assertOk();
        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    // D14 + D15. Released and committed reservations do not block edits.
    public function test_released_and_committed_reservations_do_not_block_stock_edits(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $this->reservation($product, 6, 'released');
        $this->reservation($product, 4, 'committed');

        $this->updateStock($vendor, $product, 0)->assertOk();

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    /**
     * Simulates a checkout committing a reservation between the FormRequest
     * validation (which saw nothing reserved) and the product write. On SQLite
     * this proves the write-boundary recheck logic, not MySQL row-lock behavior.
     */
    public function test_stock_update_rechecks_reservations_at_the_write_boundary(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor, stock: 10);
        $validatedBeforeInterleave = null;

        // afterResolving runs after the FormRequest's own validation callback.
        $this->app->afterResolving(UpdateProductRequest::class, function (UpdateProductRequest $request) use ($product, &$validatedBeforeInterleave): void {
            $validatedBeforeInterleave = (fn () => $this->validator !== null)->call($request);
            $this->reservation($product, 5, 'reserved');
        });

        $this->updateStock($vendor, $product, 2)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_quantity' => UpdateProductRequest::reservedStockMessage(5)]);

        $this->assertTrue($validatedBeforeInterleave, 'The FormRequest had already passed validation when the reservation appeared.');
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(5, $product->fresh()->reservedStockQuantity());
    }

    public function test_stock_rule_does_not_reveal_reservations_of_another_vendors_product(): void
    {
        $foreignProduct = $this->product($this->vendor(), stock: 10);
        $this->reservation($foreignProduct, 5, 'reserved');

        $this->updateStock($this->vendor(), $foreignProduct, 1)->assertNotFound();

        $this->assertSame(10, $foreignProduct->fresh()->stock_quantity);
    }

    // E16 + E17. Only reserved reservations are outstanding.
    public function test_available_stock_subtracts_only_reserved_reservations(): void
    {
        $product = $this->product($this->vendor(), stock: 10);
        $this->reservation($product, 3, 'reserved');
        $this->reservation($product, 4, 'released');
        $this->reservation($product, 5, 'committed');

        $this->assertSame(3, $product->reservedStockQuantity());
        $this->assertSame(7, $product->availableStockQuantity());
    }

    public function test_available_stock_is_floored_at_zero(): void
    {
        $product = $this->product($this->vendor(), stock: 2);
        $this->reservation($product, 3, 'reserved');

        $this->assertSame(0, $product->availableStockQuantity());
    }

    // E18 + E19 + E20. Dashboard low stock uses available stock, boundary included.
    public function test_dashboard_low_stock_count_and_list_use_available_stock(): void
    {
        $vendor = $this->vendor();
        $boundary = $this->product($vendor, stock: 10, threshold: 2);   // available 2 == threshold → low
        $this->reservation($boundary, 8, 'reserved');
        $settled = $this->product($vendor, stock: 10, threshold: 2);    // only committed/released → available 10
        $this->reservation($settled, 8, 'committed');
        $this->reservation($settled, 5, 'released');
        $healthy = $this->product($vendor, stock: 5, threshold: 1);     // available 5 → not low
        $physicalLow = $this->product($vendor, stock: 3, threshold: 5); // available 3 → low
        $exhausted = $this->product($vendor, stock: 4, threshold: 1);   // available 0 → low
        $this->reservation($exhausted, 4, 'reserved');
        $oversold = $this->product($vendor, stock: 2, threshold: 0);    // legacy: reserved > stock → available 0 → low
        $this->reservation($oversold, 3, 'reserved');
        $this->product($this->vendor(), stock: 0, threshold: 5);         // another vendor's product is never counted

        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.overview'))->assertOk();

        $response->assertJsonPath('stats.products_low_stock', 4);

        $listed = collect($response->json('low_stock_products'))->pluck('id');
        $this->assertEqualsCanonicalizing(
            [$boundary->id, $physicalLow->id, $exhausted->id, $oversold->id],
            $listed->all()
        );
        $this->assertNotContains($settled->id, $listed);
        $this->assertNotContains($healthy->id, $listed);

        $available = $listed->map(fn ($id) => Product::find($id)->availableStockQuantity())->all();
        $sorted = $available;
        sort($sorted);
        $this->assertSame($sorted, $available, 'Low-stock list is ordered by available stock, lowest first.');
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create();
    }

    private function product(Vendor $vendor, int $stock, int $threshold = 1): Product
    {
        static $sequence = 0;
        $sequence++;

        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => "Product $sequence",
            'slug' => "product-$sequence-".uniqid(),
            'price' => 10,
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
            'status' => 'active',
        ]);
    }

    /**
     * Checks out a fresh customer cart through the real endpoint.
     *
     * @param  array<int, int>  $quantities  product id => quantity
     */
    private function checkout(array $quantities): TestResponse
    {
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);

        foreach ($quantities as $productId => $quantity) {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => Product::find($productId)->price,
            ]);
        }

        return $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), ['payment_method' => 'cod']);
    }

    private function accept(Vendor $vendor, VendorOrder $vendorOrder): TestResponse
    {
        return $this->actingAs($vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.accept', $vendorOrder));
    }

    private function reject(Vendor $vendor, VendorOrder $vendorOrder): TestResponse
    {
        return $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.reject', $vendorOrder), ['rejection_reason' => 'Unavailable.']);
    }

    private function updateStock(Vendor $vendor, Product $product, int $stock): TestResponse
    {
        return $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), [
            'name' => $product->name,
            'price' => 10,
            'stock_quantity' => $stock,
            'status' => 'active',
        ]);
    }

    private function reservation(Product $product, int $quantity, string $status): StockReservation
    {
        $vendorOrder = $this->vendorOrderWithReservation($product->vendor, null, 0);

        return StockReservation::query()->create([
            'product_id' => $product->id,
            'order_id' => $vendorOrder->order_id,
            'vendor_order_id' => $vendorOrder->id,
            'quantity' => $quantity,
            'status' => $status,
            'reserved_at' => now(),
            'released_at' => $status === 'released' ? now() : null,
            'committed_at' => $status === 'committed' ? now() : null,
        ]);
    }

    private function orderItem(VendorOrder $vendorOrder, Product $product, int $quantity): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $vendorOrder->order_id,
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 10,
            'line_total' => 10 * $quantity,
        ]);
    }

    private function reserveItem(VendorOrder $vendorOrder, OrderItem $item, Product $product, int $quantity): StockReservation
    {
        return StockReservation::query()->create([
            'product_id' => $product->id,
            'order_id' => $vendorOrder->order_id,
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'status' => 'reserved',
            'reserved_at' => now(),
        ]);
    }

    /**
     * A pending vendor order for $vendor, optionally holding one reserved
     * reservation for $product (which may belong to another vendor).
     */
    private function vendorOrderWithReservation(Vendor $vendor, ?Product $product, int $quantity): VendorOrder
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'delivery_total' => 0,
            'grand_total' => 10,
        ]);

        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(),
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'subtotal' => 10,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 10,
        ]);

        if ($product !== null) {
            StockReservation::query()->create([
                'product_id' => $product->id,
                'order_id' => $order->id,
                'vendor_order_id' => $vendorOrder->id,
                'quantity' => $quantity,
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);
        }

        return $vendorOrder;
    }
}
