<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StorefrontContentPage extends Model
{
    protected $fillable = ['slug', 'title', 'excerpt', 'body', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
