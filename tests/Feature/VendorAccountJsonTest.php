<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-MVP (VEN-BE-023, phase 1): the vendor dashboard overview and the profile read and update publish the vendor's own
 * account and store profile, their recent orders and low-stock products through explicit allowlists. What the dashboard
 * and My Store read is there; the commission rate, the approval and review records, the account internals, the
 * customer's account and the parent order's totals are not, at any depth.
 */
class VendorAccountJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['REVIEW-REJECTION-SECRET', '17.35', 'covers/secret-cover.jpg', 'avatars/secret-avatar.jpg', 'acct-customer@example.test',
        '0596333444', 'ACCT-ORDER-NOTE', '888.88', 'ACCT-OTHER-VENDOR'];

    private const EXCLUDED_KEYS = ['commission_rate', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'cover_image', 'avatar',
        'status_reason', 'email_verified_at', 'phone_verified_at', 'last_login_at', 'password', 'remember_token', 'grand_total', 'delivery_total',
        'discount_total', 'notes', 'is_active', 'sort_order_category'];

    public function test_the_overview_publishes_the_dashboard_fields_and_nothing_sensitive(): void
    {
        [$vendor, $vendorOrder, $product] = $this->fixture();

        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.overview'))->assertOk();
        $json = $response->json();

        $this->assertSame(['vendor', 'stats', 'recent_orders', 'low_stock_products'], array_keys($json));
        $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'phone', 'profile'], array_keys($json['vendor']));
        $this->assertSame($vendor->id, $json['vendor']['id']);
        // What vendor-dashboard-home.js reads from the recent orders.
        $this->assertSame([$vendorOrder->id], array_column($json['recent_orders'], 'id'), 'Only this vendor\'s orders.');
        $recent = $json['recent_orders'][0];
        $this->assertSame([$vendorOrder->number, 'accepted', 2], [$recent['number'], $recent['status'], count($recent['items'])]);
        $this->assertIsString($recent['created_at']);
        $this->assertSame(['id', 'name'], array_keys($recent['order']['customer']));
        // The low-stock product: restocking fields, its category and primary image.
        $low = $json['low_stock_products'][0];
        $this->assertSame([$product->id, 'Clay Jug', 1], [$low['id'], $low['name'], $low['stock_quantity']]);
        $this->assertSame(['id', 'name'], array_keys($low['category']));
        $this->assertSame('products/jug.jpg', $low['primary_image']['path']);

        // A recent order's own rejection_reason (the vendor's reason for rejecting it) is part of the order allowlist;
        // the store review's rejection reason is not published anywhere (its text is among the secrets).
        $scanned = $json;
        foreach ($scanned['recent_orders'] as $index => $order) {
            unset($scanned['recent_orders'][$index]['rejection_reason']);
        }
        $this->assertNothingSensitive($response, $scanned);
    }

    public function test_the_profile_read_publishes_what_my_store_uses_and_nothing_sensitive(): void
    {
        [$vendor] = $this->fixture();

        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))->assertOk();
        $json = $response->json();

        $this->assertSame(['vendor', 'media', 'locations'], array_keys($json));
        $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'phone', 'profile'], array_keys($json['vendor']));
        $this->assertEqualsCanonicalizing(['id', 'store_name', 'slug', 'short_description', 'description', 'address_line', 'preparation_days',
            'governorate_id', 'city_id', 'logo', 'governorate', 'city'], array_keys($json['vendor']['profile']));
        $profile = $json['vendor']['profile'];
        $this->assertSame(['Acct Workshop', 'acct-workshop', 'logos/acct.png', 'Gaza Governorate', 'Gaza City'],
            [$profile['store_name'], $profile['slug'], $profile['logo'], $profile['governorate']['name'], $profile['city']['name']]);
        $this->assertSame($profile['governorate_id'], $profile['city']['governorate_id']);
        // The cover stays available as a URL, not as a stored path.
        $this->assertStringEndsWith('covers/secret-cover.jpg', (string) $json['media']['cover_url']);

        unset($json['media']);
        $this->assertNothingSensitive(null, $json);
        $this->assertStringNotContainsString('REVIEW-REJECTION-SECRET', $response->getContent());
        $this->assertStringNotContainsString('17.35', $response->getContent());
    }

    public function test_the_profile_update_saves_and_answers_with_the_same_allowlist(): void
    {
        [$vendor] = $this->fixture();
        $profile = $vendor->profile;

        $response = $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.profile.update'), [
            'name' => 'Renamed Owner',
            'store_name' => 'Renamed Workshop',
            'short_description' => 'Hand-thrown clay.',
            'governorate_id' => $profile->governorate_id,
            'city_id' => $profile->city_id,
        ])->assertOk();
        $json = $response->json();

        $this->assertSame(['message', 'vendor'], array_keys($json));
        $this->assertSame(['Renamed Owner', 'Renamed Workshop', 'Hand-thrown clay.'], [$json['vendor']['name'], $json['vendor']['profile']['store_name'], $json['vendor']['profile']['short_description']]);
        $this->assertSame(['Renamed Owner', 'Renamed Workshop'], [$vendor->fresh()->name, $profile->fresh()->store_name]);
        // The review records and the commission rate are kept as they were, and not published.
        $this->assertSame(['approved', '17.35', 'REVIEW-REJECTION-SECRET'], [$profile->fresh()->approval_status, $profile->fresh()->commission_rate, $profile->fresh()->rejection_reason]);
        $this->assertNothingSensitive(null, $json);
        $this->assertStringNotContainsString('REVIEW-REJECTION-SECRET', $response->getContent());
    }

    public function test_the_access_rules_are_unchanged(): void
    {
        $pending = Vendor::factory()->withProfile('pending')->create();
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.overview'))->assertForbidden()->assertJsonPath('approval_status', 'pending');
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.profile.show'))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.overview'))->assertUnauthorized();
        $this->getJson(route('vendor.dashboard.profile.show'))->assertUnauthorized();
        $this->putJson(route('vendor.dashboard.profile.update'), ['name' => 'x', 'store_name' => 'y'])->assertUnauthorized();
    }

    private function assertNothingSensitive($response, array $json): void
    {
        foreach (self::EXCLUDED_KEYS as $key) {
            $this->assertNotContains($key, $this->keys($json), $key);
        }
        if ($response !== null) {
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent(), $secret);
            }
        }
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

    /** @return array{0: Vendor, 1: VendorOrder, 2: Product} an approved vendor with every sensitive field filled */
    private function fixture(): array
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $governorate = Governorate::create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate', 'status' => 'active']);
        $city = City::create(['governorate_id' => $governorate->id, 'name' => 'Gaza City', 'slug' => 'gaza-city', 'status' => 'active']);
        $vendor = Vendor::factory()->create(['avatar' => 'avatars/secret-avatar.jpg', 'last_login_at' => now()]);
        $vendor->profile()->create([
            'store_name' => 'Acct Workshop', 'slug' => 'acct-workshop', 'approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => $admin->id,
            'rejection_reason' => 'REVIEW-REJECTION-SECRET', 'commission_rate' => 17.35, 'logo' => 'logos/acct.png', 'cover_image' => 'covers/secret-cover.jpg',
            'governorate_id' => $governorate->id, 'city_id' => $city->id, 'address_line' => 'Clay Road 3',
        ]);

        $customer = Customer::factory()->create(['email' => 'acct-customer@example.test', 'phone' => '0596333444']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'processing', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'subtotal' => 888.88, 'delivery_total' => 0, 'grand_total' => 888.88, 'notes' => 'ACCT-ORDER-NOTE']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => 'accepted',
            'subtotal' => 20, 'delivery_fee' => 0, 'commission_amount' => 3.47, 'total' => 20, 'accepted_at' => now()]);
        foreach (['Clay Jug', 'Clay Cup'] as $name) {
            $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_name' => $name, 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10]);
        }
        // Another vendor's order in the same parent order, never in this vendor's overview.
        VendorOrder::query()->create(['number' => 'ACCT-OTHER-VENDOR-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'accepted', 'subtotal' => 868.88, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 868.88]);

        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery-'.uniqid(), 'is_active' => true]);
        $product = Product::query()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Clay Jug', 'slug' => 'clay-jug-'.uniqid(),
            'price' => 10, 'stock_quantity' => 1, 'low_stock_threshold' => 3, 'status' => 'active']);
        $product->images()->create(['path' => 'products/jug.jpg', 'is_primary' => true, 'sort_order' => 0]);

        return [$vendor, $vendorOrder, $product];
    }
}
