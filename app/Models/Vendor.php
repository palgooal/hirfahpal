<?php

namespace App\Models;

use App\Models\Concerns\HasAccountProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Vendor extends Authenticatable
{
    use HasAccountProfile;
    use HasFactory;
    use Notifiable;

    public function profile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function vendorOrders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function commissionRules()
    {
        return $this->hasMany(CommissionRule::class);
    }
}
