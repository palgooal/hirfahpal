<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'vendor_order_id',
        'vendor_id',
        'rate',
        'base_amount',
        'amount',
        'status',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }
}
