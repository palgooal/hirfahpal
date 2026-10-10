<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\VendorOrder;

class OrderStatusLifecycle
{
    private const CANCELLED_VENDOR_STATUSES = ['rejected', 'cancelled'];

    private const ACTIVE_VENDOR_STATUSES = [
        'accepted',
        'preparing',
        'ready_for_delivery',
        'assigned',
        'out_for_delivery',
        'delivered',
    ];

    public function syncParentForVendorOrder(VendorOrder $vendorOrder): void
    {
        $order = Order::query()
            ->with('vendorOrders')
            ->whereKey($vendorOrder->order_id)
            ->lockForUpdate()
            ->first();

        if (! $order) {
            return;
        }

        $statuses = $order->vendorOrders->pluck('status');

        if ($statuses->isEmpty() || $statuses->every(fn (string $status) => $status === 'pending')) {
            $this->setStatus($order, 'pending');

            return;
        }

        if ($statuses->every(fn (string $status) => in_array($status, self::CANCELLED_VENDOR_STATUSES, true))) {
            $this->setStatus($order, 'cancelled');

            return;
        }

        if ($statuses->every(fn (string $status) => $status === 'completed')) {
            $this->setStatus($order, 'completed', completed: true);

            return;
        }

        if ($statuses->contains(fn (string $status) => in_array($status, self::CANCELLED_VENDOR_STATUSES, true))) {
            $this->setStatus($order, 'partially_cancelled');

            return;
        }

        if ($statuses->contains(fn (string $status) => in_array($status, self::ACTIVE_VENDOR_STATUSES, true))) {
            $this->setStatus($order, 'processing');
        }
    }

    private function setStatus(Order $order, string $status, bool $completed = false): void
    {
        $order->forceFill([
            'status' => $status,
            'completed_at' => $completed ? ($order->completed_at ?? now()) : null,
        ])->save();
    }
}
