<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VEN-BE-001: vendor approval enforcement, per the Approved Vendor Approval
 * Contract in docs/reviews/HIRFAH-REVIEW-FINDINGS.md.
 */
class VendorApprovalEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // 1. Self-registration produces active + pending.
    public function test_self_registration_produces_an_active_account_with_a_pending_store(): void
    {
        $this->register();

        $vendor = Vendor::with('profile')->sole();
        $this->assertSame('active', $vendor->status);
        $this->assertSame('pending', $vendor->profile->approval_status);
    }

    // 2. The new vendor application is created for admin review, without signing the vendor in.
    public function test_registration_creates_a_pending_request_for_admin_review_without_login(): void
    {
        $this->register()->assertRedirect(route('vendor.login'));

        $vendor = Vendor::sole();
        $this->assertSame('pending', $vendor->profile->approval_status);
        $this->assertGuest('vendor');

        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.vendors.index', ['approval_status' => 'pending']))
            ->assertOk()
            ->assertSee('Store Owner');
    }

    // 3. A pending vendor cannot sign in until an admin approves the request.
    public function test_pending_vendor_cannot_log_in_later(): void
    {
        $vendor = $this->vendor('pending');

        $this->from(route('vendor.login'))
            ->login($vendor)
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');
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

    // 9 + 11. Rejected + active cannot log in.
    public function test_rejected_vendor_cannot_log_in(): void
    {
        $vendor = $this->vendor('rejected', 'Missing store documents.');

        $this->from(route('vendor.login'))
            ->login($vendor)
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');
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

        $this->from(route('vendor.login'))
            ->login($vendor)
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');
    }

    public function test_admin_decision_takes_effect_on_the_next_request_without_re_login(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = $this->vendor('pending');
        Auth::guard('vendor')->login($vendor);
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
            Auth::guard('vendor')->login($vendor);
            $vendor->forceFill(['status' => 'blocked'])->save();
            // Next request: drop the guard's cached user, as a real request would.
            Auth::forgetGuards();

            $this->get(route('vendor.dashboard'))
                ->assertRedirect(route('vendor.login'))
                ->assertSessionHasErrors(['login' => __('auth.inactive')]);
            $this->assertGuest('vendor');
        }

        $vendor = $this->vendor('pending', email: 'json@example.com');
        Auth::guard('vendor')->login($vendor);
        $vendor->forceFill(['status' => 'blocked'])->save();
        Auth::forgetGuards();

        $this->getJson(route('vendor.dashboard.products.index'))->assertUnauthorized();
        $this->assertGuest('vendor');
    }

    // 17. Logout still works for existing pending and rejected vendor sessions.
    public function test_pending_and_rejected_vendor_sessions_can_log_out(): void
    {
        foreach (['pending', 'rejected'] as $approval) {
            $vendor = $this->vendor($approval, 'Reason.', email: "$approval@example.com");
            $this->actingAs($vendor, 'vendor');
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

    /**
     * VENDOR-TEST-CLEANUP-01A: no fixed route count (it broke on every new, correctly gated route). Every vendor
     * dashboard route, found by name OR by URL (so a route registered outside the vendor auth group is still checked),
     * must have the vendor login and the approval gate in its effective middleware; the only routes open to an
     * unapproved vendor are the approval status page and logout; and the approval-gated routes are exactly the
     * dashboard routes. The HTTP tests below then exercise every method of every one of them.
     */
    public function test_every_vendor_dashboard_route_is_behind_the_login_and_the_approval_gate(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $has = fn ($route, string $middleware) => in_array($middleware, $route->gatherMiddleware(), true);

        $dashboard = $routes->filter(fn ($route) => str_starts_with((string) $route->getName(), 'vendor.dashboard') || str_starts_with($route->uri(), 'vendor/dashboard'));
        $this->assertNotEmpty($dashboard);
        foreach ($dashboard as $route) {
            $this->assertTrue($has($route, 'auth:vendor'), "{$route->uri()} needs auth:vendor");
            $this->assertTrue($has($route, 'vendor.approved'), "{$route->uri()} needs vendor.approved");
            $this->assertTrue($route->getName() === 'vendor.dashboard' || str_starts_with((string) $route->getName(), 'vendor.dashboard.'), "{$route->uri()} is named under vendor.dashboard");
        }

        $vendorLogin = $routes->filter(fn ($route) => $has($route, 'auth:vendor'));
        $open = $vendorLogin->reject(fn ($route) => $has($route, 'vendor.approved'))->map->getName()->sort()->values();
        $this->assertSame(['vendor.approval-status', 'vendor.logout'], $open->all(), 'The only routes open to an unapproved vendor.');

        $gated = $vendorLogin->filter(fn ($route) => $has($route, 'vendor.approved'))->map->getName()->sort()->values();
        $this->assertSame($dashboard->map->getName()->sort()->values()->all(), $gated->all(), 'The approval gate covers the dashboard routes, and only those.');
    }

    /** VENDOR-TEST-CLEANUP-01A: a new gated route (or a new method on one) fails here until the HTTP checks request it. */
    public function test_the_http_checks_request_every_method_of_every_gated_route(): void
    {
        $vendor = $this->vendor('approved');

        $expected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('vendor.approved', $route->gatherMiddleware(), true))
            ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->map(fn ($method) => "{$method} {$route->getName()}"))
            ->unique()->sort()->values();

        $requested = collect($this->operationalRequests($vendor))
            ->map(fn (array $request) => $request[0].' '.Route::getRoutes()->match(Request::create($request[1], $request[0]))->getName())
            ->unique()->sort()->values();

        $this->assertSame($expected->all(), $requested->all());
    }

    /** VENDOR-TEST-CLEANUP-01A: without a vendor session, every gated route answers 401 (JSON) or sends to the vendor login. */
    public function test_guests_cannot_reach_any_operational_route(): void
    {
        $requests = $this->operationalRequests($this->vendor('approved'));
        $products = Product::count();

        foreach ($requests as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
            $this->call($method, $uri)->assertRedirect(route('vendor.login'));
        }

        $this->assertSame($products, Product::count());
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
        // VENDOR-TEST-CLEANUP-01A: an image of the vendor's own media library, for the library routes.
        Storage::fake('public');
        $media = Media::query()->create([
            'file_name' => 'gate.png', 'file_original_name' => 'gate.png', 'file_path' => "vendors/media/{$vendor->id}/gate.png", 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        $requests = [
            ['GET', route('vendor.dashboard')],
            ['GET', route('vendor.dashboard.overview')],
            ['GET', route('vendor.dashboard.my-store')],
            ['GET', route('vendor.dashboard.profile.show')],
            ['PUT', route('vendor.dashboard.profile.update')],
            // MYSTORE-MEDIA-01 (the direct profile media upload was retired in MYSTORE-SEC-02B).
            ['PUT', route('vendor.dashboard.profile.logo.update')],
            ['DELETE', route('vendor.dashboard.profile.logo.destroy')],
            ['GET', route('vendor.dashboard.products.index')],
            ['POST', route('vendor.dashboard.products.store')],
            ['GET', route('vendor.dashboard.products.show', $product)],
            ['PUT', route('vendor.dashboard.products.update', $product)],
            ['PATCH', route('vendor.dashboard.products.update', $product)],
            ['DELETE', route('vendor.dashboard.products.destroy', $product)],
            ['POST', route('vendor.dashboard.products.images.store', $product)],
            ['PATCH', route('vendor.dashboard.products.images.update', [$product, $image])],
            // VENDOR-TEST-CLEANUP-01A: the routes added after the original 27 (browser pages, media library, image moves).
            ['PATCH', route('vendor.dashboard.products.images.move', [$product, $image])],
            ['GET', route('vendor.dashboard.products.media.library')],
            ['POST', route('vendor.dashboard.products.media.store')],
            ['DELETE', route('vendor.dashboard.products.media.destroy', $media)],
            ['GET', route('vendor.dashboard.media.page')],
            ['GET', route('vendor.dashboard.products.page')],
            ['GET', route('vendor.dashboard.orders.page')],
            ['GET', route('vendor.dashboard.reviews.page')],
            ['GET', route('vendor.dashboard.returns.page')],
            ['GET', route('vendor.dashboard.disputes.page')],
            ['GET', route('vendor.dashboard.commissions.page')],
            ['DELETE', route('vendor.dashboard.products.images.destroy', [$product, $image])],
            ['GET', route('vendor.dashboard.orders.index')],
            ['GET', route('vendor.dashboard.orders.show', $order)],
            // ORD-02A: the vendor order details page (and its JSON refresh at the same URL).
            ['GET', route('vendor.dashboard.orders.details', $order)],
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
