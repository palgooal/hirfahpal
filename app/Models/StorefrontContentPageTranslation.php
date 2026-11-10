<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PAGES-02: one language of a storefront content page: title, excerpt (plain text), body (HTML, cleaned by
 * PageContentSanitizer when saved and again when shown) and SEO texts.
 */
class StorefrontContentPageTranslation extends Model
{
    protected $fillable = ['locale', 'title', 'excerpt', 'body_html', 'meta_title', 'meta_description'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontContentPage::class, 'page_id');
    }
}
