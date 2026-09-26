<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDriverProfile extends Model
{
    protected $fillable = [
        'delivery_driver_id',
        'governorate_id',
        'city_id',
        'vehicle_type',
        'vehicle_plate',
        'is_available',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class);
    }
}
