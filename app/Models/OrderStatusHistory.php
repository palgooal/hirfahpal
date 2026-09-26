<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_order_id',
        'status',
        'actor_type',
        'actor_id',
        'note',
    ];
}
