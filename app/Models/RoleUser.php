<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleUser extends Model
{
    use HasFactory;

    protected $table = 'role_user';

    public $timestamps = false;

    protected $fillable = [
        'role_name',
        'user_id',
        'ability',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }
}
