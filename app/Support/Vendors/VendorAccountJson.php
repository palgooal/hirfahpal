<?php

namespace App\Support\Vendors;

use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * E2-MVP (VEN-BE-023, phase 1: the vendor dashboard overview and the profile read and update): the vendor's own account
 * and store profile as their JSON publishes them, built field by field. What the dashboard and My Store use: the owner's
 * name, email and phone, the store's editable texts, slug, location (with the governorate and city names) and logo path.
 * Never the commission rate, the approval or review records (status, dates, the reviewing admin, the rejection reason),
 * the cover path (published as media.cover_url), the account status, avatar or verification and login times.
 */
class VendorAccountJson
{
    private const VENDOR = ['id', 'name', 'email', 'phone'];

    private const PROFILE = ['id', 'store_name', 'slug', 'short_description', 'description', 'address_line', 'preparation_days',
        'governorate_id', 'city_id', 'logo'];

    /** A low-stock product of the vendor's own: what restocking needs. */
    private const PRODUCT = ['id', 'name', 'slug', 'sku', 'status', 'stock_quantity', 'low_stock_threshold'];

    private const IMAGE = ['id', 'product_id', 'path', 'alt_text', 'sort_order', 'is_primary'];

    /** @return array<string, mixed> the vendor with their profile (null when they have none), as loaded */
    public static function vendor(Vendor $vendor): array
    {
        $data = self::only($vendor, self::VENDOR);

        if ($vendor->relationLoaded('profile')) {
            $data['profile'] = $vendor->profile ? self::profile($vendor->profile) : null;
        }

        return $data;
    }

    /** @return array<string, mixed> a low-stock product with its category and primary image, as loaded */
    public static function lowStockProduct(Product $product): array
    {
        $data = self::only($product, self::PRODUCT);

        if ($product->relationLoaded('category')) {
            $category = $product->category;
            $data['category'] = $category instanceof Category ? ['id' => $category->id, 'name' => $category->name] : null;
        }
        if ($product->relationLoaded('primaryImage')) {
            $image = $product->primaryImage;
            $data['primary_image'] = $image instanceof ProductImage ? self::only($image, self::IMAGE) : null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function profile(VendorProfile $profile): array
    {
        $data = self::only($profile, self::PROFILE);

        // The stored pair, even inactive or legacy, with the names My Store shows and the city's own governorate.
        if ($profile->relationLoaded('governorate')) {
            $governorate = $profile->governorate;
            $data['governorate'] = $governorate instanceof Governorate ? ['id' => $governorate->id, 'name' => $governorate->name] : null;
        }
        if ($profile->relationLoaded('city')) {
            $city = $profile->city;
            $data['city'] = $city instanceof City ? ['id' => $city->id, 'name' => $city->name, 'governorate_id' => $city->governorate_id] : null;
        }

        return $data;
    }

    /** @return array<string, mixed> the allowed attributes, as the model serializes them (casts and date format kept) */
    private static function only(Model $model, array $keys): array
    {
        return Arr::only($model->attributesToArray(), $keys);
    }
}
