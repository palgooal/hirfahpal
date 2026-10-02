<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'product_id',
        'order_id',
        'vendor_order_id',
        'order_item_id',
        'quantity',
        'status',
        'reserved_at',
        'released_at',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'released_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
