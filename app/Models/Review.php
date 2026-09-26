<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'vendor_order_id',
        'product_id',
        'vendor_id',
        'delivery_driver_id',
        'reviewable_type',
        'rating',
        'comment',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
