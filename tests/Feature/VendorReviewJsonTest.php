<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E2-MVP (VEN-BE-023, GET vendor/dashboard/reviews): each review goes through the VendorReviewJson allowlist. The list
 * columns are there, a review hidden by the admin stays visible to the vendor, the store isolation, the filters, the
 * order and the paginator are unchanged, and the customer's account, the parent order's totals, notes and payment data
 * and the review's internal references appear nowhere.
 */
class VendorReviewJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['review-customer@example.test', '0595444555', 'REVIEW-ORDER-NOTE', '654.32', 'OTHER-STORE-REVIEW', 'DRIVER-REVIEW-COMMENT'];

    private const EXCLUDED_KEYS = ['email', 'phone', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'avatar', 'customer_id', 'order_id',
        'vendor_id', 'delivery_driver_id', 'grand_total', 'subtotal', 'delivery_total', 'discount_total', 'notes', 'payment_method', 'payment_status',
        'customer_address_id', 'stock_quantity', 'price'];

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
    }

    public function test_each_review_carries_the_list_columns_and_nothing_sensitive(): void
    {
        [$productReview, $storeReview] = $this->fixture();

        $response = $this->list()->assertOk();
        $json = $response->json();

        $this->assertSame(['reviews', 'summary'], array_keys($json));
        $rows = collect($json['reviews']['data'])->keyBy('id');
        $this->assertEqualsCanonicalizing([$productReview->id, $storeReview->id], $rows->keys()->all());

        // What vendor-resource-list.js reads: rating, product.name, customer.name, status, comment (and id).
        $product = $rows[$productReview->id];
        $this->assertSame([5, 'Clay Lamp', 'Huda', 'published', 'Lovely glaze.'],
            [$product['rating'], $product['product']['name'], $product['customer']['name'], $product['status'], $product['comment']]);
        $this->assertEqualsCanonicalizing(['id', 'reviewable_type', 'rating', 'comment', 'status', 'product_id', 'vendor_order_id', 'created_at', 'updated_at',
            'customer', 'order', 'product'], array_keys($product));
        $this->assertSame(['name'], array_keys($product['customer']));
        $this->assertSame(['id', 'number'], array_keys($product['order']));
        $this->assertSame(['id', 'name', 'slug', 'primary_image'], array_keys($product['product']));
        $this->assertSame('products/lamp.jpg', $product['product']['primary_image']['path']);

        // A store review hidden by the admin stays visible, with its status and comment.
        $store = $rows[$storeReview->id];
        $this->assertSame(['vendor', 'hidden', 'Slow reply.', null], [$store['reviewable_type'], $store['status'], $store['comment'], $store['product']]);

        // The summary is unchanged: the store's published reviews only.
        $this->assertSame(['average_rating' => 0, 'published_count' => 0], $json['summary']);

        foreach (self::EXCLUDED_KEYS as $key) {
            $this->assertNotContains($key, $this->keys($json), $key);
        }
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent(), $secret);
        }
    }

    public function test_other_stores_reviews_and_driver_reviews_never_appear(): void
    {
        [$productReview, $storeReview] = $this->fixture();

        $ids = array_column($this->list()->json('reviews.data'), 'id');

        $this->assertEqualsCanonicalizing([$productReview->id, $storeReview->id], $ids);
        $this->assertSame(2, $this->list()->json('reviews.total'));
    }

    public function test_the_filters_and_the_paginator_are_unchanged(): void
    {
        [$productReview, $storeReview] = $this->fixture();

        $this->assertSame([$productReview->id], array_column($this->list(['rating' => 5])->json('reviews.data'), 'id'));
        $this->assertSame([$storeReview->id], array_column($this->list(['status' => 'hidden'])->json('reviews.data'), 'id'));
        $this->assertSame([$storeReview->id], array_column($this->list(['type' => 'vendor'])->json('reviews.data'), 'id'));
        $this->assertSame([$productReview->id], array_column($this->list(['type' => 'product'])->json('reviews.data'), 'id'));

        $first = $this->list(['per_page' => 1]);
        $second = $this->list(['per_page' => 1, 'page' => 2]);
        $this->assertSame([1, 2, 2, 1], [$first->json('reviews.current_page'), $first->json('reviews.last_page'), $first->json('reviews.total'), count($first->json('reviews.data'))]);
        // Newest first, unchanged.
        $this->assertSame([$storeReview->id, $productReview->id], [$first->json('reviews.data.0.id'), $second->json('reviews.data.0.id')]);
        foreach (['first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'links', 'path', 'per_page', 'from', 'to'] as $key) {
            $this->assertArrayHasKey($key, $first->json('reviews'), $key);
        }
        $this->assertStringContainsString('per_page=1', $first->json('reviews.next_page_url'));
    }

    public function test_the_access_rules_are_unchanged(): void
    {
        $pending = Vendor::factory()->withProfile('pending')->create();
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.reviews.index'))->assertForbidden()->assertJsonPath('approval_status', 'pending');

        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.reviews.index'))->assertUnauthorized();
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.reviews.index', $query));
    }

    /** @return list<string> every key of the payload, at any depth */
    private function keys(array $data): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                $keys = [...$keys, ...$this->keys($value)];
            }
        }

        return $keys;
    }

    /** @return array{0: Review, 1: Review} this store's product review and its hidden store review, among other stores' and a driver's */
    private function fixture(): array
    {
        $customer = Customer::factory()->create(['name' => 'Huda', 'email' => 'review-customer@example.test', 'phone' => '0595444555']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'completed', 'payment_method' => 'cod',
            'payment_status' => 'paid', 'subtotal' => 654.32, 'delivery_total' => 0, 'grand_total' => 654.32, 'notes' => 'REVIEW-ORDER-NOTE']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'status' => 'completed', 'subtotal' => 54.32, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 54.32]);
        $product = Product::query()->create(['vendor_id' => $this->vendor->id, 'name' => 'Clay Lamp', 'slug' => 'clay-lamp-'.uniqid(), 'price' => 54.32,
            'stock_quantity' => 4, 'low_stock_threshold' => 1, 'status' => 'active']);
        $product->images()->create(['path' => 'products/lamp.jpg', 'is_primary' => true, 'sort_order' => 0]);

        Carbon::setTestNow(now()->subMinutes(10));
        $productReview = Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id, 'reviewable_type' => 'product', 'rating' => 5, 'comment' => 'Lovely glaze.', 'status' => 'published']);
        Carbon::setTestNow(now()->addMinutes(5));
        $storeReview = Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $this->vendor->id, 'reviewable_type' => 'vendor', 'rating' => 2, 'comment' => 'Slow reply.', 'status' => 'hidden']);
        Carbon::setTestNow();

        // A driver review on this vendor's own order, and another store's reviews: never listed.
        $driver = DeliveryDriver::factory()->create();
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id,
            'delivery_driver_id' => $driver->id, 'reviewable_type' => 'delivery_driver', 'rating' => 4, 'comment' => 'DRIVER-REVIEW-COMMENT', 'status' => 'published']);
        $other = Vendor::factory()->approved()->create();
        $otherProduct = Product::query()->create(['vendor_id' => $other->id, 'name' => 'Other Pot', 'slug' => 'other-pot-'.uniqid(), 'price' => 5,
            'stock_quantity' => 1, 'low_stock_threshold' => 1, 'status' => 'active']);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'product_id' => $otherProduct->id, 'reviewable_type' => 'product',
            'rating' => 5, 'comment' => 'OTHER-STORE-REVIEW', 'status' => 'published']);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'vendor_id' => $other->id, 'reviewable_type' => 'vendor',
            'rating' => 5, 'comment' => 'OTHER-STORE-REVIEW', 'status' => 'published']);

        return [$productReview, $storeReview];
    }
}
