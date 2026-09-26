<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'governorate_id',
        'name',
        'slug',
        'status',
    ];

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }
}
