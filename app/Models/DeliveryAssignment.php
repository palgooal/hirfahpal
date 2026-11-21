<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $fillable = [
        'vendor_order_id',
        'delivery_driver_id',
        'assigned_by',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'failure_code',
        'returned_at',
        'declined_at',
        'decline_code',
        'decline_note',
        'return_declared_at',
        'return_confirmed_by',
        'notes',
        'cod_amount',
        'cod_collected_amount',
        'cod_collected_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'returned_at' => 'datetime',
            'declined_at' => 'datetime',
            'return_declared_at' => 'datetime',
            'cod_amount' => 'decimal:2',
            'cod_collected_amount' => 'decimal:2',
            'cod_collected_at' => 'datetime',
        ];
    }

    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class);
    }
}
