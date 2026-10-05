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

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class);
    }
}
