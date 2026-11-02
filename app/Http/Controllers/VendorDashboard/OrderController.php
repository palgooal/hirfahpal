<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\RejectVendorOrderRequest;
use App\Models\VendorOrder;
use App\Support\Inventory\StockCommitmentException;
use App\Support\Inventory\StockReservationLifecycle;
use App\Support\Orders\OrderStatusLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * ORD-02A: the lifecycle timestamps the details page shows, each only when its column exists in the schema
     * (customer_receipt_confirmed_at is missing from some databases, see VEN-BE-020). There is no preparing timestamp.
     */
    private const DETAIL_TIMESTAMPS = ['accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at'];

    /** ORD-02A: allowedActions() keys and the action endpoints they open (orders/{vendorOrder}/{action}). */
    private const DETAIL_ACTIONS = ['accept' => 'accept', 'reject' => 'reject', 'mark_preparing' => 'preparing', 'mark_ready' => 'ready'];

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $orders = VendorOrder::query()
            ->with(['order.customer', 'order.address.governorate', 'order.address.city', 'items.product.primaryImage', 'deliveryAssignment.deliveryDriver.profile'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('number', 'like', '%'.$search.'%'))
                        ->orWhereHas('order.customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->boolean('needs_response'), fn ($query) => $query->where('status', 'pending'))
            // VEN-BE-032 (ORD-01A): newest first, the id breaking ties, so orders created in the same second keep one
            // order and every page holds its own slice (none twice, none missing), as in the vendor products list.
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        // ORD-BE-001: the order's delivery address is the checkout snapshot.
        $orders->getCollection()->each(fn (VendorOrder $vendorOrder) => $vendorOrder->order?->exposeDeliveryAddress());

        return response()->json([
            'orders' => $orders,
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        $vendorOrder->load([
            'order.customer',
            'order.address.governorate',
            'order.address.city',
            'items.product.images',
            'deliveryAssignment.deliveryDriver.profile',
            'deliveryDriver.profile',
            'stockReservations.product',
        ]);
        // ORD-BE-001: the order's delivery address is the checkout snapshot.
        $vendorOrder->order?->exposeDeliveryAddress();

        return response()->json([
            'order' => $vendorOrder,
            'allowed_actions' => $this->allowedActions($vendorOrder),
        ]);
    }

    /**
     * ORD-02A: the vendor order details page (VEN-BE-023, this page only): the owner's order, another vendor's or an
     * unknown one is 404. The page and its JSON refresh (a JSON request to the same URL) carry the same minimal data,
     * built field by field in details(); no model or relation is serialized, and the other order endpoints are unchanged.
     */
    public function page(Request $request, VendorOrder $vendorOrder): View|JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);
        $details = $this->details($vendorOrder);

        return $request->expectsJson()
            ? response()->json(['order' => $details])
            : view('vendor-dashboard.order-details', ['details' => $details]);
    }

    /**
     * Vendor Accept is the stock commitment point (VEN-BE-019): the order
     * state change and the stock commitment succeed or roll back together.
     */
    public function accept(Request $request, VendorOrder $vendorOrder, StockReservationLifecycle $stock, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        try {
            DB::transaction(function () use ($vendorOrder, $stock, $orders): void {
                // Re-read under lock so a repeated or concurrent Accept sees the new state.
                $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->status === 'pending', 422, 'Only pending orders can be accepted.');

                $stock->commitForVendorOrder($lockedOrder);

                $lockedOrder->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);

                $orders->syncParentForVendorOrder($lockedOrder);
            });
        } catch (StockCommitmentException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->orderResponse($vendorOrder, 'Order accepted successfully.');
    }

    public function reject(RejectVendorOrderRequest $request, VendorOrder $vendorOrder, StockReservationLifecycle $stock, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($request, $vendorOrder, $stock, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === 'pending', 422, 'Only pending orders can be rejected.');

            $lockedOrder->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $request->validated('rejection_reason'),
            ]);

            // Release only: physical stock was never decremented for these reservations.
            $stock->releaseForVendorOrder($lockedOrder);
            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order rejected successfully.');
    }

    public function markPreparing(Request $request, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($vendorOrder, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['accepted', 'preparing'], true), 422, 'Only accepted orders can move to preparing.');

            $lockedOrder->update([
                'status' => 'preparing',
                'accepted_at' => $lockedOrder->accepted_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order marked as preparing.');
    }

    public function markReady(Request $request, VendorOrder $vendorOrder, OrderStatusLifecycle $orders): JsonResponse
    {
        $this->authorizeOrder($request, $vendorOrder);

        DB::transaction(function () use ($vendorOrder, $orders): void {
            $lockedOrder = VendorOrder::query()->whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['accepted', 'preparing', 'ready_for_delivery'], true), 422, 'Only accepted or preparing orders can be marked ready.');

            $lockedOrder->update([
                'status' => 'ready_for_delivery',
                'accepted_at' => $lockedOrder->accepted_at ?? now(),
                'ready_at' => $lockedOrder->ready_at ?? now(),
            ]);

            $orders->syncParentForVendorOrder($lockedOrder);
        });

        return $this->orderResponse($vendorOrder, 'Order marked as ready for delivery.');
    }

    private function authorizeOrder(Request $request, VendorOrder $vendorOrder): void
    {
        abort_unless((int) $vendorOrder->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    /**
     * ORD-02A: the details page's data, an explicit whitelist: the vendor order's own number, status, dates, rejection
     * reason and amounts as stored, its items' snapshot fields, and the actions allowedActions() permits. Never the
     * customer, the address, the driver, the parent order, the commission, the response deadline or stock reservations.
     *
     * @return array<string, mixed>
     */
    private function details(VendorOrder $vendorOrder): array
    {
        $columns = Schema::getColumnListing($vendorOrder->getTable());
        $timeline = [];
        foreach (self::DETAIL_TIMESTAMPS as $column) {
            if (in_array($column, $columns, true)) {
                $timeline[$column] = $vendorOrder->getAttribute($column)?->toJSON();
            }
        }

        $items = $vendorOrder->items()
            ->orderBy('id')
            ->get(['id', 'product_name', 'product_sku', 'quantity', 'unit_price', 'line_total'])
            ->map(fn ($item) => [
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])
            ->all();

        return [
            'id' => $vendorOrder->id,
            'number' => $vendorOrder->number,
            'status' => $vendorOrder->status,
            'created_at' => $vendorOrder->created_at?->toJSON(),
            'timeline' => $timeline,
            'rejection_reason' => $vendorOrder->rejection_reason,
            'subtotal' => $vendorOrder->subtotal,
            'delivery_fee' => $vendorOrder->delivery_fee,
            'total' => $vendorOrder->total,
            'items' => $items,
            'actions' => array_values(array_map(
                fn (string $key) => self::DETAIL_ACTIONS[$key],
                array_keys(array_filter($this->allowedActions($vendorOrder)))
            )),
        ];
    }

    private function orderResponse(VendorOrder $vendorOrder, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'order' => $vendorOrder->fresh(['order.customer', 'items.product.primaryImage']),
            'allowed_actions' => $this->allowedActions($vendorOrder->fresh()),
        ]);
    }

    private function allowedActions(VendorOrder $vendorOrder): array
    {
        return [
            'accept' => $vendorOrder->status === 'pending',
            'reject' => $vendorOrder->status === 'pending',
            'mark_preparing' => in_array($vendorOrder->status, ['accepted', 'preparing'], true),
            'mark_ready' => in_array($vendorOrder->status, ['accepted', 'preparing', 'ready_for_delivery'], true),
        ];
    }

    private function statuses(): array
    {
        return [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'preparing' => 'Preparing',
            'ready_for_delivery' => 'Ready for delivery',
            'assigned' => 'Assigned',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
    }
}
