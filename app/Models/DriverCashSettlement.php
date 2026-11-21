<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * E2-MVP batch 05: an entry of a driver's cash custody, never updated or deleted. "settlement": cash an admin received
 * from the driver; "reversal": a correction cancelling one settlement (reverses_id, at most once).
 */
class DriverCashSettlement extends Model
{
    public const SETTLEMENT = 'settlement';

    public const REVERSAL = 'reversal';

    protected $fillable = [
        'delivery_driver_id',
        'kind',
        'amount',
        'reverses_id',
        'recorded_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class);
    }

    public function recorder()
    {
        return $this->belongsTo(Admin::class, 'recorded_by');
    }

    /** The settlement this reversal cancels. */
    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /** The reversal that cancelled this settlement, if any. */
    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_id');
    }
}
