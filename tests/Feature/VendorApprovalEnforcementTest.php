<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * VEN-BE-001: vendor approval enforcement, per the Approved Vendor Approval
 * Contract in docs/reviews/HIRFAH-REVIEW-FINDINGS.md.
 */
class VendorApprovalEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    // 1. Self-registration produces active + pending.
    public function test_self_registration_produces_an_active_account_with_a_pending_store(): void
    {
        $this->register();

        $vendor = Vendor::with('profile')->sole();
        $this->assertSame('active', $vendor->status);
        $this->assertSame('pending', $vendor->profile->approval_status);
    }

    // 2. The new vendor stays signed in but ends in the status flow.
    public function test_registration_keeps_the_session_and_ends_in_the_status_flow(): void
    {
        $this->register()->assertRedirect(route('vendor.dashboard'));

        $vendor = Vendor::sole();
        $this->assertAuthenticatedAs($vendor, 'vendor');

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertExactJson([
                'approval_status' => 'pending',
                'rejection_reason' => null,
                'store_name' => 'Store Owner',
                'dashboard_url' => null,
            ]);
        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    // 3. A pending vendor can sign in later, and lands on the status flow.
    public function test_pending_vendor_can_log_in_later(): void
    {
        $vendor = $this->vendor('pending');

        $this->login($vendor)->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor, 'vendor');

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.approval-status'))->assertOk()->assertJsonPath('approval_status', 'pending');
    }

    // 4. A pending vendor cannot open the operational dashboard.
    public function test_pending_vendor_cannot_open_the_operational_dashboard(): void
    {
        $this->actingAs($this->vendor('pending'), 'vendor');

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.dashboard'))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Your vendor account is not approved yet.',
                'approval_status' => 'pending',
                'status_url' => route('vendor.approval-status'),
            ]);
    }

    // 5. Pending: no product create / update / delete.
    public function test_pending_vendor_cannot_create_update_or_delete_products(): void
    {
        $vendor = $this->vendor('pending');
        $product = $this->productFor($vendor);
        $this->actingAs($vendor, 'vendor');

        $this->postJson(route('vendor.dashboard.products.store'), $this->productInput('New Product'))->assertForbidden();
        $this->putJson(route('vendor.dashboard.products.update', $product), $this->productInput('Changed'))->assertForbidden();
        $this->deleteJson(route('vendor.dashboard.products.destroy', $product))->assertForbidden();

        $this->post(route('vendor.dashboard.products.store'), $this->productInput('New Product'))->assertRedirect(route('vendor.approval-status'));
        $this->delete(route('vendor.dashboard.products.destroy', $product))->assertRedirect(route('vendor.approval-status'));

        $this->assertSame(1, Product::count());
        $this->assertSame('Own Product', $product->fresh()->name);
    }

    // 6. Pending: no product image mutations.
    public function test_pending_vendor_cannot_mutate_product_images(): void
    {
        $vendor = $this->vendor('pending');
        $product = $this->productFor($vendor);
        $image = $product->images()->create(['path' => 'products/a.jpg', 'alt_text' => 'A', 'sort_order' => 0, 'is_primary' => true]);
        $this->actingAs($vendor, 'vendor');

        $this->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => 'products/b.jpg'])->assertForbidden();
        $this->patchJson(route('vendor.dashboard.products.images.update', [$product, $image]), ['alt_text' => 'Changed'])->assertForbidden();
        $this->deleteJson(route('vendor.dashboard.products.images.destroy', [$product, $image]))->assertForbidden();

        $this->assertSame(1, $product->images()->count());
        $this->assertSame('A', $image->fresh()->alt_text);
    }

    // 7. Pending: no order mutations.
    public function test_pending_vendor_cannot_mutate_orders(): void
    {
        $vendor = $this->vendor('pending');
        $order = $this->vendorOrderFor($vendor);
        $this->actingAs($vendor, 'vendor');

        foreach (['accept', 'preparing', 'ready'] as $action) {
            $this->patchJson(route("vendor.dashboard.orders.$action", $order))->assertForbidden();
        }
        $this->patchJson(route('vendor.dashboard.orders.reject', $order), ['rejection_reason' => 'No'])->assertForbidden();
        $this->patch(route('vendor.dashboard.orders.accept', $order))->assertRedirect(route('vendor.approval-status'));

        $this->assertSame('pending', $order->fresh()->status);
    }

    // 8 + 18. Pending: every other operational route is gated, including direct URLs and non-existent ids.
    public function test_pending_vendor_cannot_reach_any_operational_route_directly(): void
    {
        $vendor = $this->vendor('pending');
        $this->actingAs($vendor, 'vendor');

        foreach ($this->operationalRequests($vendor) as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden()->assertJsonPath('approval_status', 'pending');
            $this->call($method, $uri)->assertRedirect(route('vendor.approval-status'));
        }
    }

    // 9 + 11. Rejected + active can log in and reads the status contract with the reason.
    public function test_rejected_vendor_can_log_in_and_read_the_rejection_reason(): void
    {
        $vendor = $this->vendor('rejected', 'Missing store documents.');

        $this->login($vendor)->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor, 'vendor');

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertJsonPath('approval_status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Missing store documents.')
            ->assertJsonPath('dashboard_url', null);
    }

    // 10 + 18. Rejected: no operational route, by URL or by direct mutation.
    public function test_rejected_vendor_cannot_reach_operational_routes(): void
    {
        $vendor = $this->vendor('rejected', 'Missing store documents.');
        $product = $this->productFor($vendor);
        $this->actingAs($vendor, 'vendor');

        foreach ($this->operationalRequests($vendor) as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden()->assertJsonPath('approval_status', 'rejected');
        }

        $this->assertSame('Own Product', $product->fresh()->name);
        $this->assertSame(1, Product::count());
    }

    // 12. Admin rejection keeps the account active and stores the reason.
    public function test_admin_rejection_does_not_block_the_account(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = $this->vendor('pending');

        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.reject', $vendor), ['rejection_reason' => 'Incomplete profile.'])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('active', $vendor->status);
        $this->assertSame('rejected', $vendor->profile->approval_status);
        $this->assertSame('Incomplete profile.', $vendor->profile->rejection_reason);

        $this->login($vendor)->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor, 'vendor');
        $this->getJson(route('vendor.approval-status'))->assertJsonPath('rejection_reason', 'Incomplete profile.');
    }

    public function test_admin_decision_takes_effect_on_the_next_request_without_re_login(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = $this->vendor('pending');
        $this->login($vendor);
        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));

        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertRedirect();
        Auth::forgetGuards();
        $this->get(route('vendor.dashboard'))->assertOk();

        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendors.reject', $vendor), ['rejection_reason' => 'Changed.'])->assertRedirect();
        Auth::forgetGuards();
        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->assertAuthenticatedAs($vendor->fresh(), 'vendor');
    }

    // 13. Approved + active keeps full current access.
    public function test_approved_vendor_can_log_in_and_use_the_dashboard(): void
    {
        $vendor = $this->vendor('approved');

        $this->login($vendor)->assertRedirect(route('vendor.dashboard'));
        // vendor.dashboard is the browser dashboard (VEN-BE-010); the overview data moved to vendor.dashboard.overview.
        $this->get(route('vendor.dashboard'))->assertOk()->assertViewIs('vendor-dashboard.home');
        $this->getJson(route('vendor.dashboard.overview'))->assertOk()->assertJsonPath('vendor.id', $vendor->id);

        $this->postJson(route('vendor.dashboard.products.store'), $this->productInput('Approved Product'))
            ->assertCreated()
            ->assertJsonPath('product.vendor_id', $vendor->id);

        $this->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertJsonPath('approval_status', 'approved')
            ->assertJsonPath('dashboard_url', route('vendor.dashboard'));
    }

    public function test_approved_vendor_reaches_every_operational_route(): void
    {
        $vendor = $this->vendor('approved');
        $this->actingAs($vendor, 'vendor');

        foreach ($this->operationalRequests($vendor, existingOnly: true) as [$method, $uri]) {
            $status = $this->json($method, $uri)->status();

            $this->assertNotSame(403, $status, "$method $uri");
            $this->assertNotSame(302, $status, "$method $uri");
        }
    }

    // 14. vendors.status=pending cannot log in even when approved.
    public function test_pending_account_cannot_log_in_even_when_approved(): void
    {
        $vendor = $this->vendor('approved', accountStatus: 'pending');

        $this->from(route('vendor.login'))->login($vendor)->assertSessionHasErrors(['login' => __('auth.pending')]);
        $this->assertGuest('vendor');
    }

    // 15. vendors.status=blocked cannot log in even when approved.
    public function test_blocked_account_cannot_log_in_even_when_approved(): void
    {
        $vendor = $this->vendor('approved', accountStatus: 'blocked');

        $this->from(route('vendor.login'))->login($vendor)->assertSessionHasErrors(['login' => __('auth.blocked')]);
        $this->assertGuest('vendor');
    }

    // 16. Account status takes precedence over approval status in an existing session.
    public function test_account_status_takes_precedence_over_approval_status(): void
    {
        foreach (['approved', 'pending', 'rejected'] as $approval) {
            $vendor = $this->vendor($approval, accountStatus: 'active', email: "$approval@example.com");
            $this->login($vendor);
            $vendor->forceFill(['status' => 'blocked'])->save();
            // Next request: drop the guard's cached user, as a real request would.
            Auth::forgetGuards();

            $this->get(route('vendor.dashboard'))
                ->assertRedirect(route('vendor.login'))
                ->assertSessionHasErrors(['login' => __('auth.inactive')]);
            $this->assertGuest('vendor');
        }

        $vendor = $this->vendor('pending', email: 'json@example.com');
        $this->login($vendor);
        $vendor->forceFill(['status' => 'blocked'])->save();
        Auth::forgetGuards();

        $this->getJson(route('vendor.dashboard.products.index'))->assertUnauthorized();
        $this->assertGuest('vendor');
    }

    // 17. Logout works for pending and rejected vendors.
    public function test_pending_and_rejected_vendors_can_log_out(): void
    {
        foreach (['pending', 'rejected'] as $approval) {
            $vendor = $this->vendor($approval, 'Reason.', email: "$approval@example.com");
            $this->login($vendor);
            $this->assertAuthenticatedAs($vendor, 'vendor');

            $this->post(route('vendor.logout'))->assertRedirect(route('vendor.login'));
            $this->assertGuest('vendor');
        }
    }

    public function test_status_route_supports_language_switching_for_unapproved_vendors(): void
    {
        $this->actingAs($this->vendor('pending'), 'vendor');

        $this->get(route('vendor.approval-status').'?change-locale=ar')->assertRedirect(route('vendor.approval-status'));
    }

    public function test_vendor_without_a_profile_is_not_approved(): void
    {
        $this->actingAs(Vendor::factory()->create(), 'vendor');

        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->getJson(route('vendor.approval-status'))->assertOk()->assertJsonPath('approval_status', null);
    }

    public function test_every_operational_vendor_route_is_behind_the_gate_and_the_rest_are_not(): void
    {
        $vendorRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('auth:vendor', $route->gatherMiddleware(), true));

        $gated = $vendorRoutes->filter(fn ($route) => in_array('vendor.approved', $route->gatherMiddleware(), true))
            ->map->getName()->sort()->values();
        $open = $vendorRoutes->reject(fn ($route) => in_array('vendor.approved', $route->gatherMiddleware(), true))
            ->map->getName()->sort()->values();

        $this->assertSame(['vendor.approval-status', 'vendor.logout'], $open->all());
        // Browser pages (vendor.dashboard, vendor.dashboard.my-store) plus 23 JSON endpoints, including vendor.dashboard.overview.
        $this->assertCount(25, $gated);
        $this->assertTrue($gated->every(fn (string $name) => $name === 'vendor.dashboard' || str_starts_with($name, 'vendor.dashboard.')));
    }

    /**
     * One request per operational route, using the vendor's own records; returns
     * and disputes use non-existent ids unless $existingOnly, to prove the gate
     * runs before route-model binding.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function operationalRequests(Vendor $vendor, bool $existingOnly = false): array
    {
        $product = $vendor->products()->first() ?? $this->productFor($vendor);
        $image = $product->images()->first() ?? $product->images()->create(['path' => 'products/x.jpg', 'alt_text' => 'X', 'sort_order' => 0, 'is_primary' => true]);
        $order = $this->vendorOrderFor($vendor);

        $requests = [
            ['GET', route('vendor.dashboard')],
            ['GET', route('vendor.dashboard.overview')],
            ['GET', route('vendor.dashboard.my-store')],
            ['GET', route('vendor.dashboard.profile.show')],
            ['PUT', route('vendor.dashboard.profile.update')],
            ['GET', route('vendor.dashboard.products.index')],
            ['POST', route('vendor.dashboard.products.store')],
            ['GET', route('vendor.dashboard.products.show', $product)],
            ['PUT', route('vendor.dashboard.products.update', $product)],
            ['PATCH', route('vendor.dashboard.products.update', $product)],
            ['DELETE', route('vendor.dashboard.products.destroy', $product)],
            ['POST', route('vendor.dashboard.products.images.store', $product)],
            ['PATCH', route('vendor.dashboard.products.images.update', [$product, $image])],
            ['DELETE', route('vendor.dashboard.products.images.destroy', [$product, $image])],
            ['GET', route('vendor.dashboard.orders.index')],
            ['GET', route('vendor.dashboard.orders.show', $order)],
            ['PATCH', route('vendor.dashboard.orders.accept', $order)],
            ['PATCH', route('vendor.dashboard.orders.reject', $order)],
            ['PATCH', route('vendor.dashboard.orders.preparing', $order)],
            ['PATCH', route('vendor.dashboard.orders.ready', $order)],
            ['GET', route('vendor.dashboard.reviews.index')],
            ['GET', route('vendor.dashboard.returns.index')],
            ['GET', route('vendor.dashboard.disputes.index')],
            ['GET', route('vendor.dashboard.commissions.index')],
        ];

        if (! $existingOnly) {
            $requests[] = ['GET', route('vendor.dashboard.returns.show', 999999)];
            $requests[] = ['GET', route('vendor.dashboard.disputes.show', 999999)];
            $requests[] = ['GET', route('vendor.dashboard.products.show', 999999)];
        }

        return $requests;
    }

    private function register(): TestResponse
    {
        return $this->post(route('vendor.register.store'), [
            'full_name' => 'Store Owner',
            'phone' => '0591000009',
            'email' => 'new-vendor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);
    }

    private function login(Vendor $vendor): TestResponse
    {
        return $this->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => self::PASSWORD]);
    }

    private function vendor(string $approval, ?string $reason = null, string $accountStatus = 'active', string $email = 'vendor@example.com'): Vendor
    {
        return Vendor::factory()
            ->withProfile($approval, $reason)
            ->create(['email' => $email, 'password' => self::PASSWORD, 'status' => $accountStatus])
            ->load('profile');
    }

    private function productFor(Vendor $vendor): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Own Product',
            'slug' => 'own-product-'.$vendor->id,
            'price' => 10,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);
    }

    private function productInput(string $name): array
    {
        return ['name' => $name, 'price' => 20, 'stock_quantity' => 3, 'status' => 'active'];
    }

    private function vendorOrderFor(Vendor $vendor): VendorOrder
    {
        $order = Order::query()->create([
            'number' => 'HF-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 20,
            'delivery_total' => 0,
            'grand_total' => 20,
        ]);

        return VendorOrder::query()->create([
            'number' => 'VO-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'subtotal' => 20,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 20,
        ]);
    }
}
