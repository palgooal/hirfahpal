<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Palgoal\MediaLibrary\Models\Media;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /** AD-UI-CAT-M2-C: the category image from the media library (image_media_id, ON DELETE RESTRICT). */
    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /**
     * Products of any status linked to this category directly or, for a main category, through any of its
     * subcategories (two levels, so the direct children are all the descendants). DEC-CAT-Q15: such a
     * category cannot be deactivated.
     */
    public function hasAssociatedProducts(): bool
    {
        $categoryIds = $this->parent_id === null
            ? $this->children()->pluck('id')->push($this->getKey())
            : collect([$this->getKey()]);

        return Product::query()->whereIn('category_id', $categoryIds)->exists();
    }
}
