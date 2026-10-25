<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY DEC-SA-01 (§5.5): during coming_soon, existing vendors reach exactly the allowlisted vendor routes
 * (sign in and out, password recovery, approval status, dashboard), with every existing check kept; registration and
 * the public storefront stay closed to them; during maintenance the whole vendor area is closed. The allowlist is
 * exactly the documented set, and every vendor route is classified.
 */
class SiteAvailabilityVendorAllowlistTest extends TestCase
{
    use RefreshDatabase;

    /** §5.5, exactly. */
    private const ALLOWED_NAMES = [
        'vendor.login', 'vendor.login.store', 'vendor.logout',
        'vendor.password.request', 'vendor.password.email', 'vendor.password.reset', 'vendor.password.update',
        'vendor.approval-status', 'vendor.dashboard', 'vendor.entry',
    ];

    /** Vendor routes deliberately closed in every closed state. */
    private const GATED_NAMES = ['vendor.register', 'vendor.register.store'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_the_allowlist_is_exactly_the_documented_set(): void
    {
        $this->assertSame(self::ALLOWED_NAMES, config('site-availability.coming_soon_allowed_routes.names'));
        $this->assertSame(['vendor.dashboard.'], config('site-availability.coming_soon_allowed_routes.prefixes'));
    }

    public function test_every_vendor_route_is_either_allowlisted_or_deliberately_gated(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter(fn ($name) => is_string($name) && Str::startsWith($name, 'vendor.'))->unique()->values();

        $this->assertNotEmpty($names);
        foreach ($names as $name) {
            $allowed = in_array($name, self::ALLOWED_NAMES, true) || Str::startsWith($name, 'vendor.dashboard.');
            $this->assertTrue($allowed || in_array($name, self::GATED_NAMES, true), "{$name} is not classified for site availability.");
            $this->assertFalse($allowed && in_array($name, self::GATED_NAMES, true), $name);
        }
        foreach ([...self::ALLOWED_NAMES, ...self::GATED_NAMES] as $name) {
            $this->assertTrue(Route::has($name), "{$name} no longer exists.");
        }
    }

    public function test_during_coming_soon_an_active_approved_vendor_uses_the_dashboard_with_every_check_kept(): void
    {
        $this->state('coming_soon');
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $own = $this->productFor($vendor);
        $foreign = $this->productFor($other);

        $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'))->assertOk()->assertDontSee('data-site-availability', false);
        $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.products.index'))->assertOk();

        // A mutation reaches its controller, and ownership is still enforced exactly as when the site is open.
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.destroy', $own))->assertOk();
        $this->assertNull(Product::query()->find($own->id));

        $closed = $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.destroy', $foreign))->status();
        $this->state('open');
        $open = $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.destroy', $foreign))->status();
        $this->assertContains($closed, [403, 404]);
        $this->assertSame($open, $closed, 'Same ownership answer as with the site open.');
        $this->assertNotNull(Product::query()->find($foreign->id));
        $this->state('coming_soon');

        $this->actingAs($vendor, 'vendor')->post(route('vendor.logout'))->assertRedirect();
        $this->assertGuest('vendor');
    }

    public function test_during_coming_soon_vendors_never_bypass_the_storefront(): void
    {
        $this->state('coming_soon');
        $vendor = Vendor::factory()->approved()->create();

        foreach (['/', route('product'), route('vendors'), route('categories'), route('contact.create')] as $url) {
            $this->actingAs($vendor, 'vendor')->get($url)->assertOk()->assertSee('data-site-availability="coming_soon"', false);
        }
        $this->actingAs($vendor, 'vendor')->post(route('contact.store'), ['name' => 'V', 'email' => 'v@example.com', 'message' => 'Hi'])->assertStatus(503);
    }

    public function test_during_coming_soon_the_existing_vendor_checks_still_apply(): void
    {
        $this->state('coming_soon');

        // Unapproved store: vendor.approved sends it to the approval status page, which is allowlisted.
        $pending = Vendor::factory()->withProfile('pending')->create();
        $this->actingAs($pending, 'vendor')->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
        $this->actingAs($pending, 'vendor')->get(route('vendor.approval-status'))->assertOk()->assertDontSee('data-site-availability', false);

        // A blocked vendor account is signed out by EnsureAccountIsActive.
        $blocked = Vendor::factory()->approved()->create(['status' => 'blocked']);
        $this->actingAs($blocked, 'vendor')->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));
        $this->assertGuest('vendor');
    }

    public function test_during_coming_soon_guests_reach_only_vendor_sign_in_and_recovery_never_registration(): void
    {
        $this->state('coming_soon');

        $this->get(route('vendor.login'))->assertOk()->assertDontSee('data-site-availability', false);
        $this->get(route('vendor.password.request'))->assertOk()->assertDontSee('data-site-availability', false);
        $this->get(route('vendor.password.reset', ['token' => 'token']))->assertOk()->assertDontSee('data-site-availability', false);
        $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));

        $this->get(route('vendor.register'))->assertOk()->assertSee('data-site-availability="coming_soon"', false);
        $this->post(route('vendor.register.store'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertStatus(503)->assertSee('data-site-availability="coming_soon"', false);
        $this->assertSame(0, Vendor::query()->where('email', 'new@example.com')->count());
    }

    public function test_an_existing_vendor_can_sign_in_during_coming_soon(): void
    {
        $this->state('coming_soon');
        $vendor = Vendor::factory()->approved()->create(['password' => 'password']);

        $this->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_during_maintenance_the_whole_vendor_area_is_closed(): void
    {
        $this->state('maintenance');
        $vendor = Vendor::factory()->approved()->create(['password' => 'password']);
        $product = $this->productFor($vendor);

        foreach ([route('vendor.login'), route('vendor.password.request'), route('vendor.register'), route('vendor.approval-status')] as $url) {
            $this->get($url)->assertStatus(503)->assertSee('data-site-availability="maintenance"', false);
        }
        $this->post(route('vendor.login.store'), ['login' => $vendor->email, 'password' => 'password'])->assertStatus(503);
        $this->assertGuest('vendor');

        $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'))->assertStatus(503);
        $this->actingAs($vendor, 'vendor')->deleteJson(route('vendor.dashboard.products.destroy', $product))->assertStatus(503)->assertJsonPath('state', 'maintenance');
        $this->assertNotNull(Product::query()->find($product->id), 'The blocked mutation never ran.');
        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_the_allowlist_does_nothing_for_customers_and_drivers_areas(): void
    {
        $this->state('coming_soon');

        foreach ([route('customer.login'), route('customer.register'), route('delivery-driver.login'), route('delivery-driver.register')] as $url) {
            $this->get($url)->assertOk()->assertSee('data-site-availability="coming_soon"', false);
        }
    }

    private function state(string $mode): void
    {
        config(['site-availability.override' => 'auto']);
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => $mode]);
    }

    private function productFor(Vendor $vendor): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Product '.$vendor->id,
            'slug' => 'product-'.$vendor->id,
            'price' => 10,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);
    }
}
