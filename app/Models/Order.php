<?php

namespace App\Models;

use App\Support\Orders\DeliveryAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Fluent;
use LogicException;

class Order extends Model
{
    /** ORD-BE-001: the delivery address copied at checkout (see DeliveryAddress). */
    public const DELIVERY_COLUMNS = [
        'delivery_address_source',
        'delivery_recipient_name',
        'delivery_phone',
        'delivery_governorate_id',
        'delivery_governorate_name',
        'delivery_city_id',
        'delivery_city_name',
        'delivery_address_line',
        'delivery_notes',
        'delivery_latitude',
        'delivery_longitude',
    ];

    protected $fillable = [
        'number',
        'customer_id',
        'customer_address_id',
        'status',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_total',
        'discount_total',
        'grand_total',
        'notes',
        'completed_at',
        ...self::DELIVERY_COLUMNS,
    ];

    /** ORD-BE-001: the raw snapshot columns never serialize; exposeDeliveryAddress() opts a response in. */
    protected $hidden = self::DELIVERY_COLUMNS;

    /** The delivery address resolved by exposeDeliveryAddress(), kept for this instance's serialization. */
    protected ?DeliveryAddress $exposedDeliveryAddress = null;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'completed_at' => 'datetime',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
        ];
    }

    /**
     * ORD-BE-001: the delivery address is written once, at checkout. Limits of this protection: it guards Eloquent
     * model updates only; a raw query (DB::table(), a mass query()->update()) is not stopped, and the database's
     * ON DELETE SET NULL still clears delivery_governorate_id / delivery_city_id when a governorate or city is
     * deleted (their names stay).
     */
    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if ($order->isDirty(self::DELIVERY_COLUMNS)) {
                throw new LogicException('The delivery address of an order is a checkout snapshot and cannot be changed.');
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The saved address that was used at checkout (optional reference, mutable). It is NOT the delivery address
     * of the order: use deliveryAddress() (ORD-BE-001).
     */
    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /** ORD-BE-001: informational; the displayed names come from the snapshot. */
    public function deliveryGovernorate()
    {
        return $this->belongsTo(Governorate::class, 'delivery_governorate_id');
    }

    /** ORD-BE-001: informational; the displayed names come from the snapshot. */
    public function deliveryCity()
    {
        return $this->belongsTo(City::class, 'delivery_city_id');
    }

    public function hasDeliverySnapshot(): bool
    {
        return $this->delivery_address_source !== null;
    }

    /** ORD-BE-001: the authoritative delivery address (the checkout snapshot, or the marked legacy fallback). */
    public function deliveryAddress(): DeliveryAddress
    {
        return $this->exposedDeliveryAddress ?? DeliveryAddress::fromOrder($this);
    }

    /**
     * ORD-BE-001: opts THIS order instance into serializing its delivery address, for the responses that already
     * exposed the order's address (vendor orders, delivery-driver assignments, the customer's order). It adds
     * "delivery_address" and replaces the deprecated "address" key with the same delivery address plus the two
     * still-valid identifiers of the old payload: "id" (the saved address used, customer_address_id, null when it
     * is gone) and "customer_id". The mutable saved address is never serialized. Load "address" (and
     * "address.governorate", "address.city") beforehand so the legacy fallback needs no query.
     */
    public function exposeDeliveryAddress(): static
    {
        $this->exposedDeliveryAddress = DeliveryAddress::fromOrder($this);

        $this->append('delivery_address');
        $this->setRelation('address', new Fluent([
            'id' => $this->customer_address_id,
            'customer_id' => $this->customer_id,
            ...$this->exposedDeliveryAddress->toArray(),
        ]));

        return $this;
    }

    /** @return array<string, mixed> the "delivery_address" key, appended by exposeDeliveryAddress() only */
    public function getDeliveryAddressAttribute(): array
    {
        return $this->deliveryAddress()->toArray();
    }

    public function vendorOrders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }
}
