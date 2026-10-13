<?php

namespace App\Models;

use App\Models\Concerns\HasAccountProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasAccountProfile;
    use HasFactory;
    use Notifiable;

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function favorites()
    {
        return $this->hasMany(CustomerFavorite::class);
    }

    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'customer_favorites')->withTimestamps();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
