<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    // CONTACT-ADMIN-DELETE-02: a deletion only sets deleted_at; deleted messages are left out of every Eloquent query.
    use SoftDeletes;

    protected $fillable = ['customer_id', 'name', 'email', 'subject', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
