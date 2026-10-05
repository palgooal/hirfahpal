<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_order_id',
        'customer_id',
        'status',
        'reason',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
