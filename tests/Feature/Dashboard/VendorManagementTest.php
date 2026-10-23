<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Review;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_vendor_from_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.vendors.store'), [
                'name' => 'Hirfah Vendor',
                'email' => 'vendor@example.com',
                'phone' => '0561234567',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'store_name' => 'Hirfah Store',
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        $vendor = Vendor::where('phone', '0561234567')->firstOrFail();

        $this->assertSame('Hirfah Vendor', $vendor->name);
        $this->assertSame('active', $vendor->status);
        $this->assertNotNull($vendor->profile);
        $this->assertSame('Hirfah Store', $vendor->profile->store_name);
        $this->assertSame('approved', $vendor->profile->approval_status);
        $this->assertSame($admin->id, $vendor->profile->approved_by);
        $this->assertNotNull($vendor->profile->approved_at);
    }

    public function test_approval_does_not_change_the_account_status(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach (['blocked', 'pending', 'active'] as $accountStatus) {
            $vendor = Vendor::factory()->withProfile('pending')->create(['status' => $accountStatus]);

            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.approve', $vendor))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $vendor->refresh();
            $this->assertSame($accountStatus, $vendor->status, "approval changed a {$accountStatus} account");
            $this->assertSame('approved', $vendor->profile->approval_status);
        }
    }

    public function test_approval_of_a_blocked_vendor_keeps_it_out_of_the_storefront(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('pending')->create(['status' => 'blocked']);

        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertRedirect();

        $this->assertSame('blocked', $vendor->fresh()->status);
        $this->assertFalse(Vendor::query()->storefrontVisible()->whereKey($vendor->id)->exists());
    }

    public function test_approval_stores_the_decision_data(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('rejected', 'Missing documents.')->create(['status' => 'active']);

        $this->travelTo(now()->startOfSecond());
        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.approve', $vendor))
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile = $vendor->fresh()->profile;
        $this->assertSame('approved', $profile->approval_status);
        $this->assertSame($admin->id, $profile->approved_by);
        $this->assertNotNull($profile->approved_at);
        $this->assertTrue($profile->approved_at->equalTo(now()));
        $this->assertNull($profile->rejection_reason);
    }

    public function test_admin_with_vendor_edit_ability_can_approve_and_without_it_cannot(): void
    {
        $vendor = Vendor::factory()->withProfile('pending')->create(['status' => 'blocked']);
        $plain = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);

        $this->actingAs($plain, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertForbidden();
        $this->assertSame('pending', $vendor->fresh()->profile->approval_status);

        $editor = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.edit', 'user_id' => $editor->id, 'ability' => 'allow']);

        $this->actingAs($editor, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertRedirect();
        $vendor->refresh();
        $this->assertSame('approved', $vendor->profile->approval_status);
        $this->assertSame($editor->id, $vendor->profile->approved_by);
        $this->assertSame('blocked', $vendor->status);
    }

    public function test_rejection_does_not_change_the_account_status(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach (['blocked', 'pending', 'active'] as $accountStatus) {
            $vendor = Vendor::factory()->withProfile('pending')->create(['status' => $accountStatus]);

            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.reject', $vendor), ['rejection_reason' => 'Incomplete profile.'])
                ->assertRedirect();

            $vendor->refresh();
            $this->assertSame($accountStatus, $vendor->status);
            $this->assertSame('rejected', $vendor->profile->approval_status);
            $this->assertSame('Incomplete profile.', $vendor->profile->rejection_reason);
        }
    }

    public function test_super_admin_updates_the_commission_rate_without_touching_status_or_approval(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $approver = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'blocked']);
        $vendor->profile->update(['approved_by' => $approver->id, 'commission_rate' => 10]);
        $before = $vendor->profile->fresh()->only(['approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'store_name', 'slug']);

        $this->actingAs($admin, 'admin')
            ->from(route('dashboard.vendors.show', $vendor))
            ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '12.5'])
            ->assertRedirect(route('dashboard.vendors.show', $vendor))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $vendor->refresh();
        $this->assertSame('12.50', $vendor->profile->commission_rate);
        $this->assertSame('blocked', $vendor->status);
        $this->assertEquals($before, $vendor->profile->only(array_keys($before)));
    }

    public function test_commission_rate_accepts_the_bounds_and_can_be_cleared(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('pending')->create();

        foreach (['0' => '0.00', '100' => '100.00', '7.25' => '7.25'] as $input => $stored) {
            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => $input])
                ->assertSessionHasNoErrors();
            $this->assertSame($stored, $vendor->profile->fresh()->commission_rate);
        }

        // Same rule as at creation: the rate is optional, so an empty value clears it.
        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($vendor->profile->fresh()->commission_rate);
        $this->assertSame('pending', $vendor->profile->fresh()->approval_status);
    }

    public function test_invalid_commission_rates_are_rejected_without_changes(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $vendor->profile->update(['commission_rate' => 10]);

        foreach (['-0.01', '100.01', 'abc', '1e3'] as $input) {
            $this->actingAs($admin, 'admin')
                ->from(route('dashboard.vendors.show', $vendor))
                ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => $input])
                ->assertRedirect(route('dashboard.vendors.show', $vendor))
                ->assertSessionHasErrors('commission_rate');
            $this->assertSame('10.00', $vendor->profile->fresh()->commission_rate, "rate changed for input {$input}");
        }
    }

    public function test_commission_rate_update_requires_the_vendor_edit_ability(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $vendor->profile->update(['commission_rate' => 10]);

        $plain = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $viewer = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.view', 'user_id' => $viewer->id, 'ability' => 'allow']);
        $editor = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.edit', 'user_id' => $editor->id, 'ability' => 'allow']);

        foreach ([$plain, $viewer] as $admin) {
            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '20'])
                ->assertForbidden();
        }
        $this->assertSame('10.00', $vendor->profile->fresh()->commission_rate);

        $this->actingAs($editor, 'admin')
            ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '20'])
            ->assertRedirect();
        $this->assertSame('20.00', $vendor->profile->fresh()->commission_rate);
    }

    public function test_guest_cannot_update_the_commission_rate(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();

        $this->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '20'])->assertRedirect();
        $this->assertNull($vendor->profile->fresh()->commission_rate);
    }

    public function test_commission_rate_of_a_vendor_without_profile_is_not_found(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '20'])
            ->assertNotFound();
        $this->assertNull($vendor->fresh()->profile);
    }

    public function test_show_page_displays_the_rate_and_the_form_only_to_editors(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $vendor->profile->update(['commission_rate' => 12.5]);
        $formAction = route('dashboard.vendors.commission-rate', $vendor);

        $viewer = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.view', 'user_id' => $viewer->id, 'ability' => 'allow']);
        $this->actingAs($viewer, 'admin')
            ->get(route('dashboard.vendors.show', $vendor))
            ->assertOk()
            ->assertSee('12.50%')
            ->assertDontSee($formAction);

        $editor = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.view', 'user_id' => $editor->id, 'ability' => 'allow']);
        RoleUser::create(['role_name' => 'vendors.edit', 'user_id' => $editor->id, 'ability' => 'allow']);
        $this->actingAs($editor, 'admin')
            ->get(route('dashboard.vendors.show', $vendor))
            ->assertOk()
            ->assertSee($formAction)
            ->assertSee('name="commission_rate"', false);

        $vendor->profile->update(['commission_rate' => null]);
        $this->actingAs($editor, 'admin')
            ->get(route('dashboard.vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Not set');
    }

    public function test_create_accepts_up_to_two_decimal_places_in_the_commission_rate(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        $n = 0;
        foreach (['0' => '0.00', '100' => '100.00', '12.50' => '12.50', '7.5' => '7.50'] as $input => $stored) {
            $phone = '0590000'.str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
            $this->actingAs($admin, 'admin')
                ->post(route('dashboard.vendors.store'), $this->vendorInput($phone, (string) $input))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame($stored, Vendor::where('phone', $phone)->firstOrFail()->profile->commission_rate, "input {$input}");
        }
    }

    public function test_create_rejects_more_than_two_decimal_places_without_creating_a_vendor(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach (['12.345', '0.001', '99.999'] as $input) {
            $this->actingAs($admin, 'admin')
                ->post(route('dashboard.vendors.store'), $this->vendorInput('0591111111', $input))
                ->assertSessionHasErrors('commission_rate');
        }

        $this->assertSame(0, Vendor::count());
    }

    public function test_update_accepts_two_decimal_places_and_rejects_more_without_rounding(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('approved')->create();

        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => '12.50'])
            ->assertSessionHasNoErrors();
        $this->assertSame('12.50', $vendor->profile->fresh()->commission_rate);

        foreach (['12.345', '12.505', '0.001'] as $input) {
            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.commission-rate', $vendor), ['commission_rate' => $input])
                ->assertSessionHasErrors('commission_rate');
            $this->assertSame('12.50', $vendor->profile->fresh()->commission_rate, "input {$input} must not be stored or rounded");
        }
    }

    public function test_reviews_card_shows_only_this_stores_own_reviews(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $other = Vendor::factory()->withProfile('approved')->create();
        $own = $this->review($vendor, ['comment' => 'OWN-STORE-REVIEW', 'rating' => 4]);
        $this->review($vendor, ['reviewable_type' => 'product', 'comment' => 'OWN-PRODUCT-REVIEW']);
        $this->review($other, ['comment' => 'OTHER-STORE-REVIEW']);
        $this->review(null, ['reviewable_type' => 'delivery_driver', 'comment' => 'DRIVER-REVIEW']);

        $response = $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.vendors.show', $vendor))->assertOk();

        $this->assertSame([$own->id], $response->viewData('recentReviews')->pluck('id')->all());
        $response->assertSee('data-vendor-reviews', false)
            ->assertSee('OWN-STORE-REVIEW')
            ->assertSee('4/5')
            ->assertSee($own->customer->name)
            ->assertDontSee('OWN-PRODUCT-REVIEW')
            ->assertDontSee('OTHER-STORE-REVIEW')
            ->assertDontSee('DRIVER-REVIEW');
    }

    public function test_reviews_card_shows_every_status_with_its_label(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        foreach (['pending', 'published', 'hidden'] as $status) {
            $this->review($vendor, ['status' => $status, 'comment' => 'REVIEW-'.strtoupper($status)]);
        }

        $response = $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.vendors.show', $vendor))->assertOk();

        $this->assertEqualsCanonicalizing(['pending', 'published', 'hidden'], $response->viewData('recentReviews')->pluck('status')->all());
        $response->assertSee('REVIEW-PENDING')->assertSee('REVIEW-PUBLISHED')->assertSee('REVIEW-HIDDEN')
            ->assertSee('bg-light-warning text-warning">Pending', false)
            ->assertSee('bg-light-success text-success">Published', false)
            ->assertSee('bg-light-secondary text-secondary">Hidden', false);
    }

    public function test_reviews_card_shows_the_ten_newest_newest_first(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $this->travelTo(now()->addMinute());
            $ids[$i] = $this->review($vendor, ['comment' => sprintf('REVIEW-NO-%02d', $i)])->id;
        }

        $response = $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.vendors.show', $vendor))->assertOk();

        $this->assertSame(array_reverse(array_slice($ids, 2)), $response->viewData('recentReviews')->pluck('id')->all());
        $response->assertSeeInOrder(['REVIEW-NO-12', 'REVIEW-NO-11', 'REVIEW-NO-03'])
            ->assertDontSee('REVIEW-NO-01')
            ->assertDontSee('REVIEW-NO-02');
    }

    public function test_reviews_card_needs_both_vendors_view_and_reviews_view(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $this->review($vendor, ['comment' => 'SECRET-STORE-REVIEW']);

        $vendorsOnly = $this->adminWith(['vendors.view']);
        $response = $this->actingAs($vendorsOnly, 'admin')->get(route('dashboard.vendors.show', $vendor))->assertOk();
        $this->assertNull($response->viewData('recentReviews'));
        $response->assertDontSee('data-vendor-reviews', false)->assertDontSee('SECRET-STORE-REVIEW');

        $reviewsOnly = $this->adminWith(['reviews.view']);
        $this->actingAs($reviewsOnly, 'admin')->get(route('dashboard.vendors.show', $vendor))->assertForbidden();

        $both = $this->adminWith(['vendors.view', 'reviews.view']);
        $this->actingAs($both, 'admin')->get(route('dashboard.vendors.show', $vendor))
            ->assertOk()
            ->assertSee('data-vendor-reviews', false)
            ->assertSee('SECRET-STORE-REVIEW');
    }

    public function test_reviews_card_shows_an_empty_state_without_reviews(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $this->review(Vendor::factory()->withProfile('approved')->create(), ['comment' => 'OTHER-STORE-REVIEW']);

        $response = $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.vendors.show', $vendor))->assertOk();

        $this->assertCount(0, $response->viewData('recentReviews'));
        $response->assertSee('data-vendor-reviews', false)->assertSee('No reviews found')->assertDontSee('OTHER-STORE-REVIEW');
    }

    public function test_reviews_card_is_read_only(): void
    {
        $vendor = Vendor::factory()->withProfile('approved')->create();
        $review = $this->review($vendor, ['comment' => 'READ-ONLY-REVIEW']);

        $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.vendors.show', $vendor))
            ->assertOk()
            ->assertSee('READ-ONLY-REVIEW')
            ->assertDontSee(route('dashboard.reviews.update-status', $review))
            ->assertDontSee(route('dashboard.reviews.destroy', $review));
    }

    public function test_admin_without_vendor_create_ability_cannot_create_vendor(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => false,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.vendors.create'))
            ->assertForbidden();
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    /** @param list<string> $abilities */
    private function adminWith(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    /** A review with its own customer and order; a store review of $vendor unless the attributes say otherwise. */
    private function review(?Vendor $vendor, array $attributes = []): Review
    {
        $customer = Customer::factory()->create();
        $order = Order::query()->create(['number' => 'REV-'.Str::upper(Str::random(10)), 'customer_id' => $customer->id, 'payment_method' => 'cod']);

        return Review::query()->create(array_merge([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor?->id,
            'reviewable_type' => 'vendor',
            'rating' => 5,
            'comment' => 'A review',
            'status' => 'published',
        ], $attributes));
    }

    /** @return array<string, string> a valid create payload with the given phone and commission rate */
    private function vendorInput(string $phone, string $commissionRate): array
    {
        return [
            'name' => 'Rate Vendor',
            'phone' => $phone,
            'password' => 'password',
            'password_confirmation' => 'password',
            'status' => 'active',
            'store_name' => 'Rate Store '.$phone,
            'approval_status' => 'pending',
            'commission_rate' => $commissionRate,
        ];
    }
}
