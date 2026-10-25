<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'sku',
        'stock_quantity',
        'low_stock_threshold',
        'weight',
        'weight_unit',
        'dimensions',
        'status',
        'is_featured',
        'published_at',
    ];

    /**
     * AD-UI-PROD-M1: the administrative state (suspended_at, archived_at) is not fillable, so no vendor or admin
     * form write can change it; only the moderation actions set it (forceFill under the product lock). It is
     * hidden from JSON so the vendor and storefront responses keep their current shape; the application still
     * reads it as attributes.
     */
    protected $hidden = [
        'suspended_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'dimensions' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('reviewable_type', 'product');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /** AD-UI-PROD-M1: the administrative actions on this product, newest first. */
    public function adminActions()
    {
        return $this->hasMany(ProductAdminAction::class)->latest('created_at')->latest('id');
    }

    /** AD-UI-PROD-M1: an administrative suspension (DEC-PROD-01). Not yet part of sellability (M2). */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** AD-UI-PROD-M1: archived instead of deleted (OPEN-PROD-04). Not yet part of sellability (M2). */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function reservedStockQuantity(): int
    {
        return (int) $this->stockReservations()
            ->where('status', 'reserved')
            ->sum('quantity');
    }

    public function availableStockQuantity(): int
    {
        return max(0, $this->stock_quantity - $this->reservedStockQuantity());
    }

    /**
     * The catalogue stores the regular price in price and, when a promotion is
     * running, the lower selling price in compare_at_price.  Keeping this in
     * one place prevents carts and checkout from charging the regular price.
     */
    public function sellingPrice(): float
    {
        $salePrice = $this->compare_at_price;

        return $salePrice !== null && (float) $salePrice < (float) $this->price
            ? (float) $salePrice
            : (float) $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price < (float) $this->price;
    }

    /**
     * Storefront sellability requires both an active product and a vendor that
     * is currently allowed to sell. Checkout reuses this contract at write time.
     * AD-UI-PROD-M2: and a product that is neither suspended nor archived by the
     * administration (DEC-PROD-01, OPEN-PROD-04).
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNull('products.suspended_at')
            ->whereNull('products.archived_at')
            ->whereHas('vendor', fn (Builder $vendor) => $vendor->where('status', 'active'))
            ->whereHas('vendor.profile', fn (Builder $profile) => $profile->where('approval_status', 'approved'));
    }

    public function isSellable(): bool
    {
        $this->loadMissing('vendor.profile');

        return $this->status === 'active'
            && $this->isClearedByAdministration()
            && $this->vendor?->status === 'active'
            && $this->vendor?->isApproved();
    }

    /**
     * AD-UI-PROD-M2: read from the model's raw attributes (as loaded from the row, independent of $hidden and of
     * any serialization). Fail-closed: a product loaded without suspended_at or archived_at (a partial select)
     * cannot show it is cleared, so it is not sellable.
     */
    private function isClearedByAdministration(): bool
    {
        $attributes = $this->getAttributes();

        return array_key_exists('suspended_at', $attributes)
            && array_key_exists('archived_at', $attributes)
            && $attributes['suspended_at'] === null
            && $attributes['archived_at'] === null;
    }

    /**
     * Products whose available stock (physical stock minus outstanding
     * reservations, floored at 0) is at or below their threshold (VEN-BE-019).
     * Written as `stock <= threshold + reserved` so the unsigned stock column
     * is never subtracted from, which would overflow on MySQL.
     */
    public function scopeLowAvailableStock(Builder $query): Builder
    {
        return $query->whereRaw('products.stock_quantity <= products.low_stock_threshold + ('.self::reservedStockSql().')');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('products.stock_quantity > ('.self::reservedStockSql().')');
    }

    /**
     * Orders by available stock, lowest first.
     */
    public function scopeOrderByAvailableStock(Builder $query): Builder
    {
        return $query->orderByRaw('CAST(products.stock_quantity AS SIGNED) - ('.self::reservedStockSql().')');
    }

    /**
     * Outstanding (reserved) quantity of the current product row; released and
     * committed reservations are not outstanding.
     */
    private static function reservedStockSql(): string
    {
        return "select coalesce(sum(stock_reservations.quantity), 0) from stock_reservations where stock_reservations.product_id = products.id and stock_reservations.status = 'reserved'";
    }
}
