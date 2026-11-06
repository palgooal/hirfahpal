<?php

namespace App\Support\Storefront;

use App\Models\CustomerFavorite;
use Illuminate\Support\Arr;

/**
 * JSON-EXPOSURE-01B: a customer's favorite as its store response publishes it (customer.favorites.store): the
 * favorite's own identifiers (the home page reads "id") and a minimal public summary of the product and its store,
 * built field by field (CustomerProductJson). Never the raw product, vendor or store records.
 */
class CustomerFavoriteJson
{
    /** @return array<string, mixed> */
    public static function favorite(CustomerFavorite $favorite): array
    {
        return Arr::only($favorite->attributesToArray(), ['id', 'customer_id', 'product_id', 'created_at', 'updated_at'])
            + ['product' => $favorite->product ? CustomerProductJson::summary($favorite->product) : null];
    }
}
