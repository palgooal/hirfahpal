<?php

namespace App\Support\Vendors;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Support\Storefront\CustomerProductJson;
use Illuminate\Support\Arr;

/**
 * E2-MVP (VEN-BE-023, the vendor reviews list): a review of the vendor's store or of one of their products, as the
 * vendor's JSON publishes it. The review itself (its moderation status included: a review hidden by the admin stays
 * visible to the vendor), the reviewer's name, the parent order's id and number, and the product's reference. Never the
 * customer's account (email, phone, ...), the parent order's totals, notes or payment data, or the review's internal
 * references (customer, order, vendor or delivery driver ids).
 */
class VendorReviewJson
{
    private const REVIEW = ['id', 'reviewable_type', 'rating', 'comment', 'status', 'product_id', 'vendor_order_id', 'created_at', 'updated_at'];

    /** @return array<string, mixed> */
    public static function review(Review $review): array
    {
        $data = Arr::only($review->attributesToArray(), self::REVIEW);

        if ($review->relationLoaded('customer')) {
            $data['customer'] = $review->customer instanceof Customer ? ['name' => $review->customer->name] : null;
        }
        if ($review->relationLoaded('order')) {
            $data['order'] = $review->order instanceof Order ? ['id' => $review->order->id, 'number' => $review->order->number] : null;
        }
        if ($review->relationLoaded('product')) {
            $data['product'] = $review->product instanceof Product ? CustomerProductJson::reference($review->product) : null;
        }

        return $data;
    }
}
