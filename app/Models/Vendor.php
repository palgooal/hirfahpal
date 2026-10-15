<?php

namespace App\Models;

use App\Models\Concerns\HasAccountProfile;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeStorefrontVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('profile', fn (Builder $profile) => $profile->where('approval_status', 'approved'));
    }

    public function scopeWithStorefrontSummary(Builder $query): Builder
    {
        return $query->with(['profile.city', 'profile.governorate'])
            ->withCount(['products as available_products_count' => fn (Builder $products) => $products->sellable()->inStock()])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $reviews) => $reviews->where('status', 'published')->where('reviewable_type', 'vendor')])
            ->withAvg(['reviews as published_rating' => fn (Builder $reviews) => $reviews->where('status', 'published')->where('reviewable_type', 'vendor')], 'rating');
    }

    /**
     * Store approval lives on the profile; a vendor without a profile is not approved.
     */
    public function isApproved(): bool
    {
        return $this->profile?->approval_status === 'approved';
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function vendorOrders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function commissionRules()
    {
        return $this->hasMany(CommissionRule::class);
    }
}
