<?php

namespace App\Support\Orders;

use App\Models\DeliveryAssignment;
use App\Models\DriverCashSettlement;
use App\Models\Order;

/**
 * E2-MVP batch 05: the driver's cash custody, in integer cents (no float comparison). The balance is never stored: it is
 * the cash collected on delivered cash-on-delivery tasks, minus the settlements an admin recorded, plus the reversals
 * that cancelled some of them. It is independent of the goods custody (the delivery assignment's status).
 */
final class DriverCash
{
    /** An amount typed by a person: digits, optionally a dot and one or two decimals. */
    public const AMOUNT_PATTERN = '/^\d{1,8}(\.\d{1,2})?$/';

    /** A stored or typed decimal amount as integer cents. */
    public static function cents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_string($amount) && preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $parts)) {
            return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '0', 2, '0');
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $cents): string
    {
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }

    /** Whether the order is paid in cash on delivery (the driver collects); anything else is not collected by a driver. */
    public static function isCashOnDelivery(?Order $order): bool
    {
        return $order?->payment_method === 'cod';
    }

    /** E2-MVP decision: an online order is prepaid only when the admin marked it paid (no gateway records payments). */
    public static function isPrepaid(?Order $order): bool
    {
        return $order?->payment_method === 'online' && $order->payment_status === 'paid';
    }

    /** @return array{collected: int, settled: int, reversed: int, balance: int} in cents */
    public static function summary(int $driverId): array
    {
        $collected = self::cents(DeliveryAssignment::query()->where('delivery_driver_id', $driverId)->whereNotNull('cod_collected_at')->sum('cod_collected_amount'));
        $settled = self::cents(DriverCashSettlement::query()->where('delivery_driver_id', $driverId)->where('kind', DriverCashSettlement::SETTLEMENT)->sum('amount'));
        $reversed = self::cents(DriverCashSettlement::query()->where('delivery_driver_id', $driverId)->where('kind', DriverCashSettlement::REVERSAL)->sum('amount'));

        return ['collected' => $collected, 'settled' => $settled, 'reversed' => $reversed, 'balance' => $collected - $settled + $reversed];
    }
}
