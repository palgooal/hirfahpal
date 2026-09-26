<?php

namespace App\Models;

use App\Models\Concerns\HasAccountProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class DeliveryDriver extends Authenticatable
{
    use HasAccountProfile;
    use HasFactory;
    use Notifiable;

    public function profile()
    {
        return $this->hasOne(DeliveryDriverProfile::class);
    }

    public function vendorOrders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function deliveryAssignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }
}
