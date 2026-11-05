<?php

namespace App\Models;

use App\Support\Media\StoragePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VendorProfile extends Model
{
    /** VP-DATA-01: image types a public store logo may have (those the media library and the old upload accepted). */
    private const PUBLIC_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    protected $fillable = [
        'vendor_id',
        'governorate_id',
        'city_id',
        'store_name',
        'slug',
        'short_description',
        'description',
        'address_line',
        'preparation_days',
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
            'preparation_days' => 'integer',
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

    /**
     * VP-DATA-01: the public URL of the store logo, or null when there is no usable one. The stored path is resolved
     * the way the dashboard resolves it (the public disk's URL), but only when it is a canonical relative path inside
     * this vendor's own logo locations — its media library folder (vendors/media/{id}/, MYSTORE-MEDIA-01) or the
     * retired upload folder (vendors/profile/logos/{id}/) — with an image extension, and the file exists. Anything
     * else (empty, traversal, absolute, URL, another vendor's or a missing file) falls back to the store's initial.
     */
    public function publicLogoUrl(): ?string
    {
        $path = is_string($this->logo) ? $this->logo : '';

        if ($path === '' || $this->vendor_id === null || StoragePath::normalize($path) !== $path) {
            return null;
        }

        $owned = str_starts_with($path, 'vendors/media/'.$this->vendor_id.'/')
            || str_starts_with($path, 'vendors/profile/logos/'.$this->vendor_id.'/');

        if (! $owned || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::PUBLIC_LOGO_EXTENSIONS, true)) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    /** VP-DATA-01: the logo fallback: the store name's first character (as in the vendor dashboard). */
    public function publicInitial(): string
    {
        return mb_strtoupper(mb_substr(trim((string) $this->store_name), 0, 1));
    }
}
