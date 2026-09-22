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
}
