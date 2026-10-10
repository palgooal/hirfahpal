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
        'dimensions',
        'status',
        'is_featured',
        'published_at',
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
     * Storefront sellability requires both an active product and a vendor that
     * is currently allowed to sell. Checkout reuses this contract at write time.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('vendor', fn (Builder $vendor) => $vendor->where('status', 'active'))
            ->whereHas('vendor.profile', fn (Builder $profile) => $profile->where('approval_status', 'approved'));
    }

    public function isSellable(): bool
    {
        $this->loadMissing('vendor.profile');

        return $this->status === 'active'
            && $this->vendor?->status === 'active'
            && $this->vendor?->isApproved();
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
