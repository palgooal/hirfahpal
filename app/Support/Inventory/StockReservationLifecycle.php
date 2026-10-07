<?php

namespace App\Support\Inventory;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\VendorOrder;
use Illuminate\Support\Collection;

/**
 * Stock reservation lifecycle for vendor orders (VEN-BE-019, approved stock
 * contract): checkout reserves, vendor reject releases, vendor accept commits
 * and deducts physical stock exactly once.
 *
 * Both methods must run inside the caller's database transaction, after the
 * caller has locked the vendor order row, so the order-state change and the
 * stock change are committed or rolled back together.
 */
class StockReservationLifecycle
{
    /**
     * Commits every reservation of the vendor order and decrements physical
     * stock once per product. Any failed check throws before stock is touched.
     *
     * @throws StockCommitmentException
     */
    public function commitForVendorOrder(VendorOrder $vendorOrder): void
    {
        // Stock-backed items: those still linked to a product. Items with a null
        // product_id are outside stock-reservation completeness; this does not
        // define their wider commercial meaning.
        $stockItems = OrderItem::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->whereNotNull('product_id')
            ->get(['id', 'product_id']);

        $productIds = $stockItems->pluck('product_id')
            ->merge(StockReservation::query()->where('vendor_order_id', $vendorOrder->id)->pluck('product_id'))
            ->unique()
            ->sort()
            ->values();

        // Header-only vendor order (no stock-backed items, no reservations), as the
        // Admin "create vendor order" form produces: nothing to commit. Kept for
        // compatibility with existing behavior, not as an approved business contract.
        if ($productIds->isEmpty()) {
            return;
        }

        // Product locks first, in ascending id order (same order as checkout).
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $reservations = StockReservation::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($reservations->contains(fn (StockReservation $reservation) => $reservation->status !== 'reserved')) {
            throw new StockCommitmentException('Only reserved stock can be committed for this order.');
        }

        $this->assertEveryStockItemIsReserved($stockItems, $reservations);

        $quantities = $reservations
            ->groupBy('product_id')
            ->map(fn ($productReservations) => (int) $productReservations->sum('quantity'));

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null || (int) $product->vendor_id !== (int) $vendorOrder->vendor_id) {
                throw new StockCommitmentException('Reserved stock does not belong to this vendor.');
            }

            if ($product->stock_quantity < $quantity) {
                throw new StockCommitmentException("Not enough physical stock to commit product [{$product->name}].");
            }
        }

        foreach ($quantities as $productId => $quantity) {
            Product::query()->whereKey($productId)->decrement('stock_quantity', $quantity);
        }

        $committed = StockReservation::query()
            ->whereIn('id', $reservations->pluck('id'))
            ->where('status', 'reserved')
            ->update(['status' => 'committed', 'committed_at' => now()]);

        if ($committed !== $reservations->count()) {
            throw new StockCommitmentException('Reserved stock changed while it was being committed.');
        }
    }

    /**
     * Every stock-backed item must have exactly one reservation of this vendor
     * order for that same item and product, and no reservation may be left
     * unmatched (one-to-one). Status is checked by the caller.
     *
     * @param  Collection<int, OrderItem>  $stockItems
     * @param  Collection<int, StockReservation>  $reservations
     *
     * @throws StockCommitmentException
     */
    private function assertEveryStockItemIsReserved(Collection $stockItems, Collection $reservations): void
    {
        if ($reservations->count() !== $stockItems->count()) {
            throw new StockCommitmentException('Every stock-backed item of this order must have exactly one reservation.');
        }

        foreach ($stockItems as $item) {
            $matches = $reservations->filter(fn (StockReservation $reservation) => (int) $reservation->order_item_id === (int) $item->id
                && (int) $reservation->product_id === (int) $item->product_id);

            if ($matches->count() !== 1) {
                throw new StockCommitmentException('Every stock-backed item of this order must have exactly one reservation.');
            }
        }
    }

    /**
     * Releases the vendor order's outstanding reservations. Physical stock is
     * not incremented: it was never decremented for a reserved reservation.
     */
    public function releaseForVendorOrder(VendorOrder $vendorOrder): int
    {
        return StockReservation::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->where('status', 'reserved')
            ->update(['status' => 'released', 'released_at' => now()]);
    }
}
