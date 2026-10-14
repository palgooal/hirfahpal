<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * AD-UI-PROD-M1: one administrative action on a product (DEC-PROD-03, DEC-PROD-10). Append-only: an entry is
 * created once and never updated or deleted through this model.
 *
 * Limits of that protection: it guards Eloquent model operations only. A raw query (DB::table() or a mass
 * ProductAdminAction::query()->update()/delete(), which fire no model events) is not stopped, and the database's
 * own ON DELETE SET NULL still clears product_id or admin_id when the product or the admin is deleted; the
 * product_* and admin_name snapshots keep the entry readable then.
 */
class ProductAdminAction extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['suspended', 'unsuspended', 'archived', 'restored'];

    protected $fillable = [
        'product_id',
        'product_name',
        'product_sku',
        'admin_id',
        'admin_name',
        'action',
        'reason',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductAdminAction $entry): void {
            if (! in_array($entry->action, self::ACTIONS, true)) {
                throw new LogicException("Unknown product admin action [{$entry->action}].");
            }

            if (trim((string) $entry->reason) === '') {
                throw new LogicException('A product admin action needs a reason.');
            }
        });

        static::updating(fn () => throw new LogicException('Product admin actions are append-only and cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Product admin actions are append-only and cannot be deleted.'));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
