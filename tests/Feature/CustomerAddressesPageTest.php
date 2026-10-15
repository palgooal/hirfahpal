<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class CustomerAddressesPageTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_addresses_require_customer_authentication(): void
    {
        $this->get(route('customer.addresses'))->assertRedirect(route('customer.login'));
    }

    public function test_browser_address_form_creates_edits_and_deletes_owned_addresses(): void
    {
        $customer = Customer::factory()->create();
        $data = ['label' => 'Home', 'recipient_name' => 'Recipient', 'phone' => '0500000000', 'address_line' => 'Actual street'];
        $this->actingAs($customer, 'customer')->post(route('customer.addresses.store'), $data)->assertRedirect(route('customer.addresses'));
        $address = $customer->addresses()->firstOrFail();
        $this->assertTrue($address->is_default);
        $this->get(route('customer.addresses', ['edit' => $address->id]))->assertOk()->assertSee('Actual street')
            ->assertSee(route('customer.addresses.update', $address), false);
        $data['address_line'] = 'Updated street';
        $this->patch(route('customer.addresses.update', $address), $data)->assertRedirect(route('customer.addresses'));
        $this->assertSame('Updated street', $address->fresh()->address_line);
        $this->from(route('customer.addresses'))->delete(route('customer.addresses.destroy', $address))->assertRedirect(route('customer.addresses'));
        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_customer_cannot_open_or_modify_foreign_addresses(): void
    {
        $address = $this->storefrontAddress(Customer::factory()->create(), ['address_line' => 'Foreign street']);
        $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.addresses'))->assertOk()->assertDontSee('Foreign street');
        $this->get(route('customer.addresses', ['edit' => $address->id]))->assertNotFound();
        $this->deleteJson(route('customer.addresses.destroy', $address))->assertForbidden();
    }
}
