<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    protected $fillable = [
        'vendor_id',
        'governorate_id',
        'city_id',
        'store_name',
        'slug',
        'short_description',
        'description',
        'address_line',
        'logo',
        'cover_image',
        'commission_rate',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function getStoreInitialsAttribute(): string
    {
        $words = preg_split('/\s+/u', trim($this->store_name), -1, PREG_SPLIT_NO_EMPTY);

        return collect($words)->take(2)->map(fn (string $word) => mb_substr($word, 0, 1))->implode('.');
    }

    public function getLocationNameAttribute(): string
    {
        return $this->city?->name ?: ($this->governorate?->name ?: 'غير محدد');
    }
}
