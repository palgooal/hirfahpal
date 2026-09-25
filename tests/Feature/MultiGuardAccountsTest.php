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

    public function test_account_models_share_the_profile_fillable_and_hidden_contract(): void
    {
        foreach ([Customer::class, Vendor::class, DeliveryDriver::class] as $modelClass) {
            $model = new $modelClass;

            $this->assertSame([
                'name',
                'email',
                'phone',
                'password',
                'status',
                'avatar',
                'email_verified_at',
                'phone_verified_at',
                'last_login_at',
            ], $model->getFillable(), $modelClass);

            $this->assertSame(['password', 'remember_token'], $model->getHidden(), $modelClass);
        }
    }

    public function test_account_models_keep_their_casts_and_hash_passwords(): void
    {
        foreach ([Customer::class, Vendor::class, DeliveryDriver::class] as $index => $modelClass) {
            $casts = (new $modelClass)->getCasts();

            $this->assertSame('datetime', $casts['email_verified_at'], $modelClass);
            $this->assertSame('datetime', $casts['phone_verified_at'], $modelClass);
            $this->assertSame('datetime', $casts['last_login_at'], $modelClass);
            $this->assertSame('hashed', $casts['password'], $modelClass);

            $account = $modelClass::create([
                'name' => 'Cast Account',
                'email' => "cast{$index}@example.com",
                'phone' => "059200000{$index}",
                'password' => 'plain-password',
                'status' => 'active',
                'last_login_at' => '2026-01-01 10:00:00',
            ]);

            $this->assertNotSame('plain-password', $account->password, $modelClass);
            $this->assertTrue(Hash::check('plain-password', $account->password), $modelClass);
            $this->assertInstanceOf(\DateTimeInterface::class, $account->fresh()->last_login_at, $modelClass);
        }
    }

    public function test_account_models_reject_mass_assignment_outside_the_contract_and_hide_secrets(): void
    {
        foreach ([Customer::class, Vendor::class, DeliveryDriver::class] as $index => $modelClass) {
            $account = $modelClass::create([
                ...$this->accountData("guarded{$index}@example.com", "059300000{$index}"),
                'super_admin' => true,
                'remember_token' => 'injected-token',
            ]);

            $this->assertArrayNotHasKey('super_admin', $account->getAttributes(), $modelClass);
            $this->assertNull($account->fresh()->remember_token, $modelClass);

            $account->forceFill(['remember_token' => 'real-token'])->save();
            $serialized = $account->fresh()->toArray();

            $this->assertArrayNotHasKey('password', $serialized, $modelClass);
            $this->assertArrayNotHasKey('remember_token', $serialized, $modelClass);
            $this->assertSame("guarded{$index}@example.com", $serialized['email'], $modelClass);
        }
    }

    public function test_customer_can_register_with_customer_guard(): void
    {
        $this->post(route('customer.register.store'), [
            'full_name' => 'New Customer',
            'phone' => '0594000001',
            'email' => 'new-customer@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
        ])->assertRedirect(route('customer.dashboard'));

        $customer = Customer::where('email', 'new-customer@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertSame('active', $customer->status);
        $this->assertTrue(Hash::check('secret-password', $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');
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
