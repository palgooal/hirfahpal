<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiGuardAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_login_pages_are_available(): void
    {
        $this->get(route('customer.login'))->assertOk();
        $this->get(route('vendor.login'))->assertOk();
        $this->get(route('delivery-driver.login'))->assertOk();
    }

    public function test_customer_can_login_with_customer_guard(): void
    {
        $customer = Customer::create($this->accountData('customer@example.com', '0591000001'));

        $this->post(route('customer.login.store'), [
            'login' => $customer->email,
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_vendor_can_login_with_vendor_guard(): void
    {
        $vendor = Vendor::create($this->accountData('vendor@example.com', '0591000002'));

        $this->post(route('vendor.login.store'), [
            'login' => $vendor->email,
            'password' => 'password',
        ])->assertRedirect(route('vendor.dashboard'));

        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_delivery_driver_can_login_with_delivery_driver_guard(): void
    {
        $driver = DeliveryDriver::create($this->accountData('driver@example.com', '0591000003'));

        $this->post(route('delivery-driver.login.store'), [
            'login' => $driver->email,
            'password' => 'password',
        ])->assertRedirect(route('delivery-driver.dashboard'));

        $this->assertAuthenticatedAs($driver, 'delivery_driver');
    }

    private function accountData(string $email, string $phone): array
    {
        return [
            'name' => 'Test Account',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'status' => 'active',
        ];
    }
}
