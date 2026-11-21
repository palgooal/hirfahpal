<?php

namespace App\Support\Orders;

use App\Models\DeliveryAssignment;
use App\Models\OrderItem;

/**
 * E2-MVP batch 02 (FR-DEL-006): a delivery task as the driver's browser dashboard shows it, built field by field so the
 * page never receives a model (SRS §2.3, E1): the recipient and the delivery address from the order's checkout snapshot,
 * the pickup store with its address and phone (vendors.phone, the store's contact phone kept in My Store), the products
 * and quantities (no options are stored on order items) and the payment method. Never an email, a price, a total, a
 * commission or payment internals.
 *
 * The recipient and the store are given only while the task needs them: the customer for an active task, the store for
 * an active task and for a failed one still in the driver's custody (to return it). Finished tasks keep the number,
 * store name, products and status only.
 */
final class DeliveryDriverAssignmentCard
{
    /** The assignment statuses of a task the driver is carrying out. */
    public const ACTIVE_STATUSES = ['assigned', 'accepted', 'picked_up', 'out_for_delivery'];

    private const PAYMENT_METHODS = [
        'cod' => 'الدفع عند الاستلام',
        'online' => 'دفع إلكتروني',
    ];

    public static function isActive(DeliveryAssignment $assignment): bool
    {
        return in_array($assignment->status, self::ACTIVE_STATUSES, true);
    }

    /** A failed delivery after pickup: the package stays with the driver until it is returned to the vendor. */
    public static function isInCustody(DeliveryAssignment $assignment): bool
    {
        return $assignment->status === 'failed' && $assignment->vendorOrder?->status === 'out_for_delivery';
    }

    /**
     * Load on the assignments before calling: vendorOrder.order (and its address.governorate / address.city for legacy
     * orders without a snapshot), vendorOrder.vendor.profile.governorate / .city, vendorOrder.items.
     *
     * @return array<string, mixed>
     */
    public static function for(DeliveryAssignment $assignment): array
    {
        $vendorOrder = $assignment->vendorOrder;
        $order = $vendorOrder?->order;
        $profile = $vendorOrder?->vendor?->profile;

        return [
            'number' => $vendorOrder?->number,
            'status' => $assignment->status,
            'store_name' => $profile?->store_name ?? $vendorOrder?->vendor?->name,
            'payment_method' => $order ? (self::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method) : null,
            'items' => $vendorOrder
                ? $vendorOrder->items->map(fn (OrderItem $item) => ['name' => $item->product_name, 'quantity' => (int) $item->quantity])->values()->all()
                : [],
            // E2-MVP batch 05: the cash to collect on an active cash-on-delivery task (the amount captured at assignment;
            // an older attempt without it uses the vendor order total), and the cash collected on a delivered one.
            'cod_amount' => $vendorOrder && DriverCash::isCashOnDelivery($order) && self::isActive($assignment)
                ? DriverCash::format(DriverCash::cents($assignment->cod_amount ?? $vendorOrder->total))
                : null,
            'cod_collected' => $assignment->cod_collected_at !== null ? DriverCash::format(DriverCash::cents($assignment->cod_collected_amount)) : null,
            // E2-MVP batch 03: why the driver declined the task, or why the delivery failed.
            'declined' => $assignment->declined_at !== null,
            // E2-MVP custody fix: the driver declared the return; the custody stays open until the admin confirms it.
            'return_declared' => $assignment->return_declared_at !== null,
            'reason' => match (true) {
                $assignment->declined_at !== null => self::reason(DeliveryReasons::label(DeliveryReasons::DECLINE, $assignment->decline_code), $assignment->decline_note),
                $assignment->status === 'failed' => self::reason(DeliveryReasons::label(DeliveryReasons::FAILURE, $assignment->failure_code), $assignment->failure_reason),
                default => null,
            },
            'customer' => $order && self::isActive($assignment) ? self::customer(DeliveryAddress::fromOrder($order), $order->customer?->name) : null,
            'vendor' => $vendorOrder && (self::isActive($assignment) || self::isInCustody($assignment)) ? self::vendor($vendorOrder) : null,
        ];
    }

    /** @return array<string, mixed> the recipient and the delivery address, as copied at checkout */
    private static function customer(DeliveryAddress $address, ?string $customerName): array
    {
        $text = self::join([$address->addressLine, $address->cityName, $address->governorateName]);

        return [
            'name' => filled($address->recipientName) ? $address->recipientName : $customerName,
            'phone' => $address->phone,
            'tel' => self::tel($address->phone),
            'address' => $text,
            'notes' => filled($address->notes) ? $address->notes : null,
            'maps_url' => self::mapsUrl($address->latitude, $address->longitude, filled($address->addressLine) ? $text : null),
        ];
    }

    /** @return array<string, mixed> the pickup store, its address and its contact phone */
    private static function vendor($vendorOrder): array
    {
        $vendor = $vendorOrder->vendor;
        $profile = $vendor?->profile;
        $text = self::join([$profile?->address_line, $profile?->city?->name, $profile?->governorate?->name]);

        return [
            'name' => $profile?->store_name ?? $vendor?->name,
            'phone' => $vendor?->phone,
            'tel' => self::tel($vendor?->phone),
            'address' => $text,
            // The store has no coordinates: directions use its written address, when it has one.
            'maps_url' => self::mapsUrl(null, null, filled($profile?->address_line) ? self::join([$profile->store_name, $text]) : null),
        ];
    }

    /** "label: text", the label alone, or the text alone (an older failure has only its free text). */
    private static function reason(?string $label, ?string $text): ?string
    {
        $text = filled($text) ? $text : null;

        return match (true) {
            $label !== null && $text !== null => $label.': '.$text,
            default => $label ?? $text,
        };
    }

    /** A dialable tel: URI (digits and a leading +), or null when the number is too short to call. */
    private static function tel(?string $phone): ?string
    {
        $number = preg_replace('/[^\d+]/', '', (string) $phone);
        $number = ($number[0] ?? '') === '+' ? '+'.str_replace('+', '', $number) : str_replace('+', '', $number);

        return strlen(ltrim($number, '+')) >= 6 ? 'tel:'.$number : null;
    }

    /** Google Maps directions to valid coordinates, otherwise to the written address, otherwise none. */
    private static function mapsUrl(?string $latitude, ?string $longitude, ?string $text): ?string
    {
        if (is_numeric($latitude) && is_numeric($longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180
            && ((float) $latitude !== 0.0 || (float) $longitude !== 0.0)) {
            $destination = ((float) $latitude).','.((float) $longitude);
        } elseif (filled($text)) {
            $destination = $text;
        } else {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination);
    }

    /** @param  array<int, string|null>  $parts */
    private static function join(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(fn ($part) => is_string($part) ? trim($part) : null, $parts), fn ($part) => filled($part)));

        return $parts === [] ? null : implode('، ', $parts);
    }
}
