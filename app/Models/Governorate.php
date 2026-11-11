<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'status',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /** The city this governorate belongs to for product availability. */
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
