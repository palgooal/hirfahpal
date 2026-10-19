<?php

namespace App\Support\Orders;

use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * ORD-BE-001: the delivery address of an order, read-only. For orders placed since the snapshot exists it is the
 * copy taken at checkout (source "checkout", or "none" when the order was placed without an address). For legacy
 * orders (no snapshot) it falls back to the saved address as it is now, marked is_historical = false
 * (source "legacy_saved_address"), or to an empty address when that row is gone ("legacy_missing").
 *
 * @implements Arrayable<string, mixed>
 */
final class DeliveryAddress implements Arrayable, JsonSerializable
{
    public const SOURCE_CHECKOUT = 'checkout';

    public const SOURCE_NONE = 'none';

    public const SOURCE_LEGACY_SAVED_ADDRESS = 'legacy_saved_address';

    public const SOURCE_LEGACY_MISSING = 'legacy_missing';

    public function __construct(
        public readonly ?string $recipientName,
        public readonly ?string $phone,
        public readonly ?int $governorateId,
        public readonly ?string $governorateName,
        public readonly ?int $cityId,
        public readonly ?string $cityName,
        public readonly ?string $addressLine,
        public readonly ?string $notes,
        public readonly ?string $latitude,
        public readonly ?string $longitude,
        public readonly string $source,
        public readonly bool $isHistorical,
    ) {}

    /**
     * The order columns copied from a saved address at checkout. The governorate and city names are read from
     * their relations as they are now (load them before calling).
     *
     * @return array<string, mixed>
     */
    public static function snapshotColumns(CustomerAddress $address): array
    {
        return [
            'delivery_address_source' => self::SOURCE_CHECKOUT,
            'delivery_recipient_name' => $address->recipient_name,
            'delivery_phone' => $address->phone,
            'delivery_governorate_id' => $address->governorate_id,
            'delivery_governorate_name' => $address->governorate?->name,
            'delivery_city_id' => $address->city_id,
            'delivery_city_name' => $address->city?->name,
            'delivery_address_line' => $address->address_line,
            'delivery_notes' => $address->notes,
            'delivery_latitude' => $address->latitude,
            'delivery_longitude' => $address->longitude,
        ];
    }

    /** @return array<string, mixed> the order columns for an order placed without an address */
    public static function noAddressColumns(): array
    {
        return ['delivery_address_source' => self::SOURCE_NONE];
    }

    public static function fromOrder(Order $order): self
    {
        $source = $order->delivery_address_source;

        if ($source !== null) {
            return new self(
                $order->delivery_recipient_name,
                $order->delivery_phone,
                self::id($order->delivery_governorate_id),
                $order->delivery_governorate_name,
                self::id($order->delivery_city_id),
                $order->delivery_city_name,
                $order->delivery_address_line,
                $order->delivery_notes,
                self::coordinate($order->delivery_latitude),
                self::coordinate($order->delivery_longitude),
                $source,
                true,
            );
        }

        // Legacy order (created before the snapshot): the saved address as it is now, if it still exists. Loaded
        // relations are used as they are; otherwise plain queries that set no relation on the models, so
        // resolving the fallback has no side effect on later serialization.
        $saved = match (true) {
            $order->customer_address_id === null => null,
            $order->relationLoaded('address') => $order->getRelation('address'),
            default => $order->address()->first(),
        };

        if (! $saved instanceof CustomerAddress) {
            return self::empty(self::SOURCE_LEGACY_MISSING, false);
        }

        return new self(
            $saved->recipient_name,
            $saved->phone,
            self::id($saved->governorate_id),
            self::relatedName($saved, 'governorate'),
            self::id($saved->city_id),
            self::relatedName($saved, 'city'),
            $saved->address_line,
            $saved->notes,
            self::coordinate($saved->latitude),
            self::coordinate($saved->longitude),
            self::SOURCE_LEGACY_SAVED_ADDRESS,
            false,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'phone' => $this->phone,
            'governorate_id' => $this->governorateId,
            'governorate_name' => $this->governorateName,
            'city_id' => $this->cityId,
            'city_name' => $this->cityName,
            'address_line' => $this->addressLine,
            'notes' => $this->notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => $this->source,
            'is_historical' => $this->isHistorical,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function empty(string $source, bool $isHistorical): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, $source, $isHistorical);
    }

    /** The name of the saved address's governorate or city, without loading the relation onto the model. */
    private static function relatedName(CustomerAddress $saved, string $relation): ?string
    {
        if ($saved->relationLoaded($relation)) {
            return $saved->getRelation($relation)?->name;
        }

        return $saved->{$relation.'_id'} === null ? null : $saved->{$relation}()->value('name');
    }

    private static function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function coordinate(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
