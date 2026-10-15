<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_details_require_customer_authentication(): void
    {
        $this->get(route('customer.account-details'))->assertRedirect(route('customer.login'));
    }

    public function test_account_forms_render_escaped_current_values_and_save_changes(): void
    {
        $customer = Customer::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($customer, 'customer')->get(route('customer.account-details'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(route('customer.account-details.update'), false);
        $this->from(route('customer.account-details'))->patch(route('customer.account-details.update'), [
            'name' => 'Updated name', 'email' => 'updated@example.com', 'phone' => $customer->phone])->assertRedirect(route('customer.account-details'));
        $this->assertSame('Updated name', $customer->fresh()->name);
    }

    public function test_password_form_checks_current_password_and_updates_hash(): void
    {
        $customer = Customer::factory()->create(['password' => 'Old-password-1']);
        $this->actingAs($customer, 'customer')->from(route('customer.account-details'))->patch(route('customer.account-details.password.update'), [
            'current_password' => 'Old-password-1', 'password' => 'New-password-2', 'password_confirmation' => 'New-password-2'])->assertRedirect(route('customer.account-details'));
        $this->assertTrue(Hash::check('New-password-2', $customer->fresh()->password));
        $this->patchJson(route('customer.account-details.password.update'), ['current_password' => 'wrong', 'password' => 'Another-password-3',
            'password_confirmation' => 'Another-password-3'])->assertUnprocessable();
    }
}
