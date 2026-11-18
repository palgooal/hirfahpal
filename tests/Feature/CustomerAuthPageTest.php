<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_page_contains_real_login_and_register_forms(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertViewIs('pages.customer-login')
            ->assertSee('method="POST" action="'.route('customer.login.store').'"', false)
            ->assertSee('method="POST" action="'.route('customer.register.store').'"', false)
            ->assertSee('name="full_name"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="terms"', false);
    }

    public function test_customer_register_route_opens_the_storefront_register_tab(): void
    {
        $this->get(route('customer.register'))
            ->assertOk()
            ->assertViewIs('pages.customer-login')
            ->assertSee('data-initial-auth-tab="register"', false)
            ->assertSee('id="registerPanel" role="tabpanel" aria-labelledby="registerTabButton" class="mt-6 flex flex-col gap-4"', false);
    }

    public function test_customer_can_register_from_the_storefront_page_and_reach_dashboard(): void
    {
        $this->from(route('customer.login'))
            ->post(route('customer.register.store'), [
                '_auth_panel' => 'register',
                'full_name' => 'Customer One',
                'phone' => '0594999000',
                'email' => 'customer-one@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'terms' => '1',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $customer = Customer::where('email', 'customer-one@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_register_validation_returns_to_register_tab(): void
    {
        $this->from(route('customer.login'))
            ->post(route('customer.register.store'), [
                '_auth_panel' => 'register',
                'full_name' => '',
                'phone' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors(['full_name', 'phone', 'email', 'password', 'terms']);

        $this->get(route('customer.login'))
            ->assertSee('data-initial-auth-tab="register"', false);
    }

    // ---------------------------------------------------------------- each form's errors in its own panel

    public function test_each_registration_error_is_shown_in_the_register_panel_only_and_creates_nothing(): void
    {
        foreach ([
            'customer email taken' => ['email', [], fn () => Customer::factory()->create(['email' => 'new-customer@example.com'])],
            'email of another account type' => ['email', [], fn () => Admin::factory()->create(['email' => 'new-customer@example.com'])],
            'customer phone taken' => ['phone', [], fn () => Customer::factory()->create(['phone' => '0594999111'])],
            'phone of another account type' => ['phone', [], fn () => Vendor::factory()->create(['phone' => '0594999111'])],
            'password under 8 characters' => ['password', ['password' => 'short12', 'password_confirmation' => 'short12'], null],
            'confirmation mismatch' => ['password', ['password_confirmation' => 'other-password'], null],
            'terms not accepted' => ['terms', ['terms' => null], null],
        ] as $label => [$field, $overrides, $existing]) {
            $account = $existing ? $existing() : null;
            $customers = Customer::count();
            $input = array_filter(array_merge($this->registration(), $overrides), fn ($value) => $value !== null);

            $this->from(route('customer.login'))->post(route('customer.register.store'), $input)
                ->assertRedirect(route('customer.login'))
                ->assertSessionHasErrors($field)
                ->assertSessionHasInput(['full_name' => $input['full_name'], 'email' => $input['email'], 'phone' => $input['phone']])
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $message = session('errors')->first($field);
            $this->assertNotSame('', $message, $label);
            $this->assertSame($customers, Customer::count(), "{$label}: no customer is created.");
            $this->assertGuest('customer');

            $page = $this->get(route('customer.login'))->assertOk()
                ->assertSee('data-initial-auth-tab="register"', false)
                ->assertSee('value="'.e($input['email']).'"', false)
                ->getContent();
            [$login, $register] = $this->panelAlerts($page);
            $this->assertSame(['visible', $message], $register, "{$label}: the register panel shows the error.");
            $this->assertSame(['hidden', ''], $login, "{$label}: the login panel stays empty.");
            $account?->delete();
        }
    }

    public function test_a_valid_registration_still_redirects_to_the_dashboard_with_no_error(): void
    {
        $this->from(route('customer.login'))->post(route('customer.register.store'), $this->registration())
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(Customer::where('email', 'new-customer@example.com')->sole(), 'customer');
    }

    public function test_login_errors_stay_in_the_login_panel(): void
    {
        Customer::factory()->create(['email' => 'known@example.com']);

        foreach ([
            'wrong password' => [['login' => 'known@example.com', 'password' => 'wrong-password'], 'login'],
            'missing password' => [['login' => 'known@example.com'], 'password'],
        ] as $label => [$input, $field]) {
            $this->from(route('customer.login'))->post(route('customer.login.store'), $input)
                ->assertRedirect(route('customer.login'))
                ->assertSessionHasErrors($field);
            $message = session('errors')->first($field);
            $this->assertGuest('customer');

            $page = $this->get(route('customer.login'))->assertOk()->assertSee('data-initial-auth-tab="login"', false)->getContent();
            [$login, $register] = $this->panelAlerts($page);
            $this->assertSame(['visible', $message], $login, "{$label}: the login panel shows the error.");
            $this->assertSame(['hidden', ''], $register, "{$label}: the register panel stays empty.");
        }
    }

    /**
     * @return array<string, string>
     */
    private function registration(): array
    {
        return [
            '_auth_panel' => 'register',
            'full_name' => 'New Customer',
            'phone' => '0594999111',
            'email' => 'new-customer@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
        ];
    }

    /**
     * The alert of the login panel and of the register panel: [visible|hidden, text].
     *
     * @return array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}}
     */
    private function panelAlerts(string $html): array
    {
        return array_map(function (string $panel) use ($html): array {
            $this->assertMatchesRegularExpression('#<form id="'.$panel.'".*?</form>#s', $html);
            preg_match('#<form id="'.$panel.'".*?</form>#s', $html, $form);
            $this->assertSame(1, preg_match_all('#<p data-form-error class="([^"]*)"\s+role="alert">([^<]*)</p>#u', $form[0], $alerts, PREG_SET_ORDER), $panel);

            return [str_starts_with($alerts[0][1], 'hidden ') ? 'hidden' : 'visible', html_entity_decode(trim($alerts[0][2]), ENT_QUOTES)];
        }, ['loginPanel', 'registerPanel']);
    }
}
