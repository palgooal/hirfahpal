<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerFavorite;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\Storefront\CustomerFavoriteJson;
use App\Support\Storefront\CustomerOrderJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JSON-EXPOSURE-01B: the customer JSON responses (favorite store, orders index / show, confirm-receipt, checkout and
 * review store) publish only their allowed fields: every response's complete set of key paths is pinned, no planted
 * secret value appears anywhere, and the serializers ignore attributes and relations they do not know. The existing
 * keys, envelopes and the guest / ownership refusals are unchanged.
 */
class CustomerJsonExposureTest extends TestCase
{
    use RefreshDatabase;

    /** Values planted in the internal records; none may appear in any customer JSON. */
    private const SECRETS = ['INT-SKU-7731', 'Internal street 9', 'STORE-REJECTION-NOTE', 'VO-REJECTION-NOTE', 'GATEWAY-X',
        'REF-SECRET-77', 'PAYLOAD-SECRET-77', 'DISPATCH-NOTE-77', 'FAILURE-NOTE-77', '12.75', '3.21'];

    /** Keys that may never appear at any depth (outside the customer's own delivery address, see FORBIDDEN_OUTSIDE_ADDRESS). */
    private const FORBIDDEN_KEYS = ['email', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'password', 'remember_token',
        'commission_rate', 'commission_amount', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'provider',
        'provider_reference', 'payload', 'assigned_by', 'failure_reason', 'vendor_response_due_at', 'delivery_driver_id', 'sku',
        'product_sku', 'stock_quantity', 'low_stock_threshold', 'status_reason', 'is_featured', 'highlight_tags', 'craft_tags',
        'suspended_at', 'archived_at', 'cover_image', 'logo', 'governorate_id', 'city_id'];

    /** The customer's own recipient phone, street and note are allowed in the delivery address snapshot only. */
    private const FORBIDDEN_OUTSIDE_ADDRESS = ['phone', 'address_line', 'governorate_id', 'city_id'];

    private const ORDER = ['id', 'number', 'customer_id', 'customer_address_id', 'status', 'payment_method', 'payment_status',
        'subtotal', 'delivery_total', 'discount_total', 'grand_total', 'notes', 'completed_at', 'created_at', 'updated_at'];

    private const VENDOR_ORDER = ['id', 'number', 'order_id', 'vendor_id', 'status', 'subtotal', 'delivery_fee', 'total', 'accepted_at',
        'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at', 'created_at', 'updated_at'];

    private const ITEM = ['id', 'order_id', 'vendor_order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total',
        'created_at', 'updated_at'];

    private const PAYMENT = ['id', 'order_id', 'method', 'status', 'amount', 'refunded_amount', 'paid_at', 'created_at', 'updated_at'];

    private const ITEM_PRODUCT = ['id', 'name', 'slug', 'primary_image.id', 'primary_image.url', 'primary_image.path', 'primary_image.alt_text'];

    private const STORE = ['id', 'profile.store_name', 'profile.slug'];

    private const ASSIGNMENT = ['status', 'assigned_at', 'picked_up_at', 'delivered_at', 'failed_at'];

    private const REVIEW = ['id', 'order_id', 'vendor_order_id', 'product_id', 'reviewable_type', 'rating', 'comment', 'status',
        'created_at', 'updated_at'];

    private const ADDRESS = ['recipient_name', 'phone', 'governorate_id', 'governorate_name', 'city_id', 'city_name', 'address_line',
        'notes', 'latitude', 'longitude', 'source', 'is_historical'];

    private Customer $alice;

    private Customer $bob;

    private Vendor $vendor;

    private Product $product;

    private Order $order;

    private VendorOrder $vendorOrder;

    private DeliveryDriver $driver;

    /** The checkout's JSON response for Alice's order. */
    private array $checkoutResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->withProfile('approved', 'STORE-REJECTION-NOTE')->create(['status' => 'active']);
        $this->vendor->profile()->update(['commission_rate' => 12.75, 'address_line' => 'Internal street 9',
            'approved_by' => Admin::factory()->create()->id, 'logo' => 'vendors/media/1/logo.png']);
        $this->product = Product::query()->create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 25,
            'stock_quantity' => 9, 'low_stock_threshold' => 3, 'sku' => 'INT-SKU-7731', 'status' => 'active', 'is_featured' => true,
            'highlight_tags' => ['New'], 'craft_tags' => ['Glazed']]);
        ProductImage::query()->create(['product_id' => $this->product->id, 'path' => 'products/jug.jpg', 'alt_text' => 'Jug',
            'is_primary' => true, 'sort_order' => 0]);
        $this->alice = Customer::factory()->create();
        $this->bob = Customer::factory()->create();

        // Alice's order, through the real checkout.
        $address = CustomerAddress::query()->create(['customer_id' => $this->alice->id, 'label' => 'home', 'recipient_name' => 'Alice',
            'phone' => '0590000001', 'address_line' => 'Alice street 1']);
        $cart = Cart::query()->create(['customer_id' => $this->alice->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 25]);
        $this->checkoutResponse = $this->actingAs($this->alice, 'customer')
            ->postJson(route('customer.checkout.store'), ['customer_address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertCreated()->json();
        $this->order = Order::query()->findOrFail($this->checkoutResponse['id']);
        $this->vendorOrder = $this->order->vendorOrders()->firstOrFail();

        // The internal records an order gathers afterwards: commission, deadline and rejection note, gateway data, dispatch.
        $driver = DeliveryDriver::factory()->create();
        $this->vendorOrder->update(['commission_amount' => 3.21, 'vendor_response_due_at' => now()->addDay(),
            'rejection_reason' => 'VO-REJECTION-NOTE', 'delivery_driver_id' => $driver->id, 'status' => 'delivered', 'delivered_at' => now()]);
        $this->order->payment()->update(['provider' => 'GATEWAY-X', 'provider_reference' => 'REF-SECRET-77',
            'payload' => ['token' => 'PAYLOAD-SECRET-77']]);
        DeliveryAssignment::query()->create(['vendor_order_id' => $this->vendorOrder->id, 'delivery_driver_id' => $driver->id,
            'assigned_by' => Admin::factory()->create()->id, 'status' => 'delivered', 'assigned_at' => now(), 'delivered_at' => now(),
            'notes' => 'DISPATCH-NOTE-77', 'failure_reason' => 'FAILURE-NOTE-77']);
        $this->driver = $driver;
    }

    public function test_guests_get_401_from_every_customer_json_endpoint(): void
    {
        $this->app['auth']->forgetGuards();
        $calls = [
            ['postJson', route('customer.favorites.store'), ['product_id' => $this->product->id]],
            ['getJson', route('customer.orders.index'), []],
            ['getJson', route('customer.orders.show', $this->order), []],
            ['patchJson', route('customer.orders.vendor-orders.confirm-receipt', [$this->order, $this->vendorOrder]), []],
            ['postJson', route('customer.checkout.store'), ['payment_method' => 'cod']],
            ['postJson', route('customer.orders.reviews.store', $this->order), ['vendor_order_id' => $this->vendorOrder->id, 'vendor_rating' => 5]],
        ];

        foreach ($calls as [$method, $url, $data]) {
            $this->{$method}($url, $data)->assertUnauthorized();
        }
        $this->assertSame('delivered', $this->vendorOrder->fresh()->status);
    }

    public function test_another_customer_is_refused_and_sees_none_of_the_orders(): void
    {
        $favorite = CustomerFavorite::query()->create(['customer_id' => $this->alice->id, 'product_id' => $this->product->id]);

        $this->actingAs($this->bob, 'customer')->getJson(route('customer.orders.show', $this->order))->assertForbidden();
        $this->actingAs($this->bob, 'customer')
            ->patchJson(route('customer.orders.vendor-orders.confirm-receipt', [$this->order, $this->vendorOrder]))->assertForbidden();
        $this->actingAs($this->bob, 'customer')->postJson(route('customer.orders.reviews.store', $this->order),
            ['vendor_order_id' => $this->vendorOrder->id, 'vendor_rating' => 5])->assertForbidden();
        $this->actingAs($this->bob, 'customer')->deleteJson(route('customer.favorites.destroy', $favorite))->assertForbidden();
        $this->actingAs($this->bob, 'customer')->getJson(route('customer.orders.index'))->assertOk()->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');

        $this->assertSame('delivered', $this->vendorOrder->fresh()->status);
        $this->assertModelExists($favorite);
    }

    public function test_the_favorite_response_is_its_identifiers_and_a_public_product_summary(): void
    {
        $response = $this->actingAs($this->alice, 'customer')
            ->postJson(route('customer.favorites.store'), ['product_id' => $this->product->id])->assertCreated();

        // The home page's favorite toggle reads "id".
        $this->assertSame(CustomerFavorite::query()->sole()->id, $response->json('id'));
        $response->assertJsonPath('product_id', $this->product->id)->assertJsonPath('product.name', 'Jug')
            ->assertJsonPath('product.selling_price', '25.00')->assertJsonPath('product.vendor.profile.store_name', $this->vendor->profile->store_name);
        $this->assertPaths(['id', 'customer_id', 'product_id', 'created_at', 'updated_at',
            ...$this->prefixed('product', ['id', 'name', 'slug', 'price', 'compare_at_price', 'selling_price', 'has_discount',
                'primary_image.id', 'primary_image.url', 'primary_image.path', 'primary_image.alt_text', ...$this->prefixed('vendor', self::STORE)]),
        ], $response->json());
        $this->assertNothingInternal($response->json());

        // Saving it again keeps the same shape (200).
        $this->actingAs($this->alice, 'customer')->postJson(route('customer.favorites.store'), ['product_id' => $this->product->id])
            ->assertOk()->assertJsonPath('id', $response->json('id'));
    }

    public function test_the_checkout_response_keeps_its_totals_without_internal_fields(): void
    {
        $json = $this->checkoutResponse;

        $this->assertSame('50.00', $json['subtotal']);
        $this->assertSame('50.00', $json['vendor_orders'][0]['subtotal']);
        $this->assertSame(2, $json['vendor_orders'][0]['items'][0]['quantity'], 'The purchased quantity, not the stock.');
        $this->assertSame('25.00', $json['vendor_orders'][0]['items'][0]['unit_price']);
        $this->assertSame('50.00', $json['vendor_orders'][0]['items'][0]['line_total']);
        $this->assertSame('pending', $json['payment']['status']);
        // The newly created order carries the attributes it was created with (no discount_total / completed_at), as before.
        $this->assertPaths([...array_diff(self::ORDER, ['discount_total', 'completed_at']), ...$this->prefixed('payment', self::PAYMENT),
            ...$this->prefixed('vendor_orders.*', [...self::VENDOR_ORDER, ...$this->prefixed('items.*', self::ITEM)])], $json);
        $this->assertNothingInternal($json);
    }

    public function test_the_order_list_keeps_its_pagination_envelope_without_internal_fields(): void
    {
        $json = $this->actingAs($this->alice, 'customer')->getJson(route('customer.orders.index'))->assertOk()->json();

        $this->assertSame($this->order->id, $json['data'][0]['id']);
        $this->assertSame(1, $json['total']);
        $this->assertPaths(['current_page', 'first_page_url', 'from', 'last_page', 'last_page_url', 'links.*.url', 'links.*.label',
            'links.*.page', 'links.*.active', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total',
            ...$this->prefixed('data.*', [...self::ORDER, ...$this->prefixed('items.*', self::ITEM), 'reviews[]',
                ...$this->prefixed('vendor_orders.*', [...self::VENDOR_ORDER, ...$this->prefixed('vendor', self::STORE),
                    ...$this->prefixed('items.*', self::ITEM)])]),
        ], $json);
        $this->assertNothingInternal($json);
    }

    public function test_the_order_detail_keeps_its_delivery_address_contract_without_internal_fields(): void
    {
        $json = $this->actingAs($this->alice, 'customer')->getJson(route('customer.orders.show', $this->order))->assertOk()->json();

        // ORD-BE-001: the snapshot, and the deprecated "address" key with the two still-valid identifiers.
        $this->assertSame('Alice street 1', $json['delivery_address']['address_line']);
        $this->assertSame(['id' => $this->order->customer_address_id, 'customer_id' => $this->alice->id] + $json['delivery_address'], $json['address']);
        // The assigned driver's display name only.
        $this->assertSame(['name' => $this->driver->name], $json['vendor_orders'][0]['delivery_driver']);
        $this->assertSame('delivered', $json['vendor_orders'][0]['delivery_assignment']['status']);
        $this->assertPaths([...self::ORDER, ...$this->prefixed('delivery_address', self::ADDRESS),
            ...$this->prefixed('address', ['id', 'customer_id', ...self::ADDRESS]),
            ...$this->prefixed('items.*', [...self::ITEM, ...$this->prefixed('product', self::ITEM_PRODUCT)]),
            ...$this->prefixed('payment', self::PAYMENT), 'reviews[]',
            ...$this->prefixed('vendor_orders.*', [...self::VENDOR_ORDER, ...$this->prefixed('vendor', self::STORE), 'delivery_driver.name',
                ...$this->prefixed('delivery_assignment', self::ASSIGNMENT),
                ...$this->prefixed('items.*', [...self::ITEM, ...$this->prefixed('product', self::ITEM_PRODUCT)])]),
        ], $json);
        $this->assertNothingInternal($json);
    }

    public function test_confirming_receipt_keeps_the_order_and_payment_status_without_internal_fields(): void
    {
        $json = $this->actingAs($this->alice, 'customer')
            ->patchJson(route('customer.orders.vendor-orders.confirm-receipt', [$this->order, $this->vendorOrder]))->assertOk()->json();

        $this->assertSame('Receipt confirmed.', $json['message']);
        $this->assertSame('completed', $json['order']['status']);
        $this->assertSame('pending', $json['order']['payment']['status']);
        $this->assertPaths(['message', ...$this->prefixed('order', [...self::ORDER, ...$this->prefixed('payment', self::PAYMENT), 'reviews[]',
            ...$this->prefixed('vendor_orders.*', [...self::VENDOR_ORDER, ...$this->prefixed('delivery_assignment', self::ASSIGNMENT),
                ...$this->prefixed('items.*', [...self::ITEM, ...$this->prefixed('product', self::ITEM_PRODUCT)])])])], $json);
        $this->assertNothingInternal($json);
    }

    public function test_the_review_response_and_the_reviews_in_the_order_are_the_customers_own_fields(): void
    {
        $this->vendorOrder->update(['status' => 'completed']);

        $json = $this->actingAs($this->alice, 'customer')->postJson(route('customer.orders.reviews.store', $this->order), [
            'vendor_order_id' => $this->vendorOrder->id, 'product_id' => $this->product->id, 'product_rating' => 5, 'product_comment' => 'Lovely',
            'vendor_rating' => 4, 'driver_rating' => 3,
        ])->assertCreated()->json();

        $this->assertCount(3, $json);
        $this->assertSame(['product', 'vendor', 'delivery_driver'], array_column($json, 'reviewable_type'));
        $this->assertSame([5, 4, 3], array_column($json, 'rating'));
        $this->assertPaths($this->prefixed('*', array_diff(self::REVIEW, ['status'])), $json, 'A new review has no loaded status, as before.');
        $this->assertNothingInternal($json);

        $order = $this->actingAs($this->alice, 'customer')->getJson(route('customer.orders.show', $this->order))->assertOk()->json();
        foreach ($order['reviews'] as $review) {
            $this->assertSame(self::REVIEW, array_keys($review));
        }
        $this->assertNothingInternal($order);
    }

    public function test_the_serializers_ignore_attributes_and_relations_they_do_not_know(): void
    {
        $order = Order::query()->with(['customer', 'vendorOrders.vendor.profile', 'items.product.vendor.profile', 'payment'])
            ->whereKey($this->order->id)->firstOrFail();
        // A column added to a model later, and relations an endpoint might load in the future.
        $order->setAttribute('internal_margin', 'MARGIN-SECRET');
        $order->vendorOrders->first()->setAttribute('internal_score', 'SCORE-SECRET');
        $order->items->first()->product->setAttribute('cost_price', 'COST-SECRET');
        $order->items->first()->product->vendor->setRelation('vendorOrders', collect());

        $json = json_encode(CustomerOrderJson::order($order));
        foreach (['MARGIN-SECRET', 'SCORE-SECRET', 'COST-SECRET', '"customer"', $this->alice->email, '"vendor_orders":[]}'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertNothingInternal(json_decode($json, true));

        $favorite = CustomerFavorite::query()->create(['customer_id' => $this->alice->id, 'product_id' => $this->product->id]);
        $favorite->load(['customer', 'product.vendor.profile', 'product.primaryImage', 'product.reviews']);
        $favorite->product->setAttribute('cost_price', 'COST-SECRET');
        $favoriteJson = json_encode(CustomerFavoriteJson::favorite($favorite));
        $this->assertStringNotContainsString('COST-SECRET', $favoriteJson);
        $this->assertStringNotContainsString($this->alice->email, $favoriteJson);
        $this->assertStringNotContainsString('"reviews"', $favoriteJson);
    }

    /** No planted secret value, vendor or driver contact, or internal key anywhere in a response. */
    private function assertNothingInternal(array $json): void
    {
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ([...self::SECRETS, $this->vendor->email, $this->vendor->phone, $this->driver->email, $this->driver->phone] as $secret) {
            $this->assertStringNotContainsString((string) $secret, $encoded);
        }

        $walk = function (array $node, bool $inAddress) use (&$walk): void {
            foreach ($node as $key => $value) {
                if (is_string($key)) {
                    $this->assertNotContains($key, $inAddress ? array_diff(self::FORBIDDEN_KEYS, self::FORBIDDEN_OUTSIDE_ADDRESS) : self::FORBIDDEN_KEYS,
                        "Internal key {$key}");
                    if (! $inAddress) {
                        $this->assertNotContains($key, self::FORBIDDEN_OUTSIDE_ADDRESS, "Address key {$key} outside the delivery address");
                    }
                }
                if (is_array($value)) {
                    $walk($value, $inAddress || in_array($key, ['delivery_address', 'address'], true));
                }
            }
        };
        $walk($json, false);
    }

    /** The response's complete set of key paths (lists by their first item, "*"; an empty list as "[]"). */
    private function assertPaths(array $expected, array $json, string $message = ''): void
    {
        $paths = [];
        $walk = function ($value, string $prefix) use (&$walk, &$paths): void {
            if (is_array($value) && array_is_list($value)) {
                if ($value === []) {
                    $paths[] = $prefix.'[]';
                } else {
                    $walk($value[0], $prefix === '' ? '*' : $prefix.'.*');
                }

                return;
            }
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $walk($child, $prefix === '' ? (string) $key : $prefix.'.'.$key);
                }

                return;
            }
            $paths[] = $prefix;
        };
        $walk($json, '');

        $expected = array_values(array_unique($expected));
        sort($expected);
        sort($paths);
        $this->assertSame($expected, $paths, $message);
    }

    /** @return list<string> */
    private function prefixed(string $prefix, array $paths): array
    {
        return array_map(fn (string $path) => str_starts_with($path, '[]') ? $prefix.$path : $prefix.'.'.$path, $paths);
    }
}
