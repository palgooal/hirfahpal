<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\City;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminAbilityTranslationSeeder;
use Database\Seeders\AdminCustomerTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHomeTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CUS-UI-06: the admin customer details page (/dashboard/customers/{customer}). Access, the status form only for an
 * admin who may change the status (customers.edit or super admin), the PATCH and its messages, the orders (links by
 * orders.view), addresses and reviews, the empty states, both languages, escaping, no translation row written by
 * the view, and the page band hidden by its own CSS rule. CUS-BE-003 (fixed): the PATCH returns to this customer's
 * details page on success and on a validation failure, never to the Referer. CUS-BE-006 (fixed): the validation
 * message names the field by its translated label.
 */
class AdminCustomerShowTest extends TestCase
{
    use RefreshDatabase;

    /** The shared keys the controller read before CUS-BE-004. */
    private const SHARED_STATUS_KEYS = ['dashboard.Active', 'dashboard.Pending', 'dashboard.Blocked'];

    /** The keys the controller reads now (without auto-create). */
    private const CUSTOMER_STATUS_KEYS = ['dashboard.Customers_Status_Active', 'dashboard.Customers_Status_Pending', 'dashboard.Customers_Status_Blocked', 'dashboard.Customer_status_updated'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- access

    public function test_an_admin_with_customers_view_sees_the_details(): void
    {
        $customer = $this->customer(['name' => 'Layla']);

        $this->page($this->admin(['customers.view']), $customer)
            ->assertSee('id="admin-customer-show"', false)
            ->assertSee('<bdi>Layla</bdi>', false);
    }

    public function test_guests_and_admins_without_customers_view_are_refused(): void
    {
        $customer = $this->customer();

        $this->get(route('dashboard.customers.show', $customer))->assertRedirect();
        foreach ([[], ['customers.edit'], ['orders.view']] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')->get(route('dashboard.customers.show', $customer))->assertForbidden();
        }
        $this->actingAs($this->admin(['customers.view']), 'admin')->get(route('dashboard.customers.show', 999999))->assertNotFound();
    }

    // ---------------------------------------------------------------- status form

    public function test_the_status_form_is_shown_only_to_admins_who_may_change_the_status(): void
    {
        $customer = $this->customer(['status' => 'pending']);
        $action = 'action="'.route('dashboard.customers.update-status', $customer).'"';

        foreach ([$this->admin(super: true), $this->admin(['customers.view', 'customers.edit'])] as $editor) {
            $html = $this->page($editor, $customer)->getContent();
            $this->assertStringContainsString($action, $html);
            $this->assertStringContainsString('name="_method" value="PATCH"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('<label class="form-label" for="customer-status">New status</label>', $html);
            $this->assertMatchesRegularExpression('#id="customer-status"[^>]*aria-describedby="customer-status-hint"#', $html);
            $this->assertStringContainsString('id="customer-status-hint">A blocked or pending customer cannot sign in and is signed out of the current session.</p>', $html);
            $this->assertStringContainsString('<option value="pending" selected>', $html);
        }

        $viewer = $this->page($this->admin(['customers.view']), $customer)->getContent();
        $this->assertStringNotContainsString($action, $viewer);
        $this->assertStringNotContainsString('name="_method"', $viewer);
        $this->assertSame(0, $this->xpath($viewer)->query('//*[@id="admin-customer-show"]//select|//*[@id="admin-customer-show"]//form')->length);
    }

    public function test_the_three_statuses_and_their_labels(): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'blocked']);
        $html = $this->page($this->admin(super: true), $customer, 'ar')->getContent();

        $this->assertSame(['active', 'pending', 'blocked'], array_map(fn ($o) => $o->getAttribute('value'), iterator_to_array($this->xpath($html)->query('//select[@id="customer-status"]/option'))));
        foreach (['نشط', 'بانتظار التفعيل', 'محظور'] as $label) {
            $this->assertStringContainsString('>'.$label.'</option>', $html, $label);
        }
        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">محظور</span>', $html);
    }

    public function test_a_valid_status_is_saved_and_the_success_message_is_shown(): void
    {
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);
        $page = route('dashboard.customers.show', $customer);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from($page)
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked'])
            ->assertRedirect($page);

        $this->assertSame('blocked', $customer->fresh()->status);
        $html = $this->page($admin, $customer)->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-success" role="status">.*?<p>Customer status updated successfully\.</p>#s', $html);
        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">Blocked</span>', $html);
    }

    public function test_an_invalid_status_is_refused_with_the_error_at_the_field_and_nothing_changes(): void
    {
        $customer = $this->customer(['status' => 'pending']);
        $admin = $this->admin(super: true);
        $page = route('dashboard.customers.show', $customer);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from($page)
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'deleted'])
            ->assertRedirect($page)->assertSessionHasErrors('status');

        $this->assertSame('pending', $customer->fresh()->status);
        $html = $this->page($admin, $customer)->getContent();
        $this->assertMatchesRegularExpression('#<p class="admin-product-moderation-error" id="customer-status-error" role="alert">[^<]+</p>#', $html);
        $this->assertMatchesRegularExpression('#id="customer-status"[^>]*aria-describedby="customer-status-hint customer-status-error"#', $html);
        $this->assertMatchesRegularExpression('#id="customer-status"[^>]*aria-invalid="true"#s', $html);
        $this->assertStringContainsString('<option value="pending" selected>', $html, 'The current status stays selected after an invalid value.');
    }

    public function test_an_admin_without_customers_edit_cannot_change_the_status(): void
    {
        $customer = $this->customer(['status' => 'active']);

        $this->actingAs($this->admin(['customers.view']), 'admin')
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked'])
            ->assertForbidden();

        $this->assertSame('active', $customer->fresh()->status);
    }

    // ---------------------------------------------------------------- CUS-BE-003: the redirect after the PATCH

    // Both the success and the validation failure return to the named route of this customer's details page; neither
    // reads the Referer header nor the previous URL in the session. The test client never follows a redirect, so
    // the Location header checked here is the one the server sent.

    /** @return array<string, array{?string}> */
    public static function referers(): array
    {
        return [
            'external' => ['https://evil.example/phish?next=/dashboard'],
            'protocol-relative' => ['//evil.example/phish'],
            'none' => [null],
            'another internal page' => ['/dashboard/customers?search=Ali&status=blocked'],
        ];
    }

    /** @dataProvider referers */
    public function test_cus_be_003_a_saved_status_returns_to_the_customer_page(?string $referer): void
    {
        $customer = $this->customer(['status' => 'active']);

        $response = $this->patchStatus($this->admin(super: true), $customer, ['status' => 'blocked'], $referer);

        $this->assertRedirectsToTheDetailsPage($response, $customer);
        $response->assertSessionHas('success', 'Customer status updated successfully.')->assertSessionHasNoErrors();
        $this->assertSame('blocked', $customer->fresh()->status);
    }

    public function test_cus_be_003_the_previous_url_in_the_session_is_not_used(): void
    {
        $customer = $this->customer(['status' => 'pending']);
        $other = $this->customer();
        $admin = $this->admin(['customers.view', 'customers.edit']);

        foreach (['https://evil.example/stored', route('dashboard.customers.show', $other)] as $previous) {
            $session = ['locale' => 'en', '_previous' => ['url' => $previous]];

            $this->assertRedirectsToTheDetailsPage($this->actingAs($admin, 'admin')->withSession($session)
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'active']), $customer);
            $this->assertRedirectsToTheDetailsPage($this->actingAs($admin, 'admin')->withSession($session)
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'deleted']), $customer);
        }
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_cus_be_003_the_details_page_shows_the_message_and_the_new_status_after_the_redirect(): void
    {
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);

        $location = $this->patchStatus($admin, $customer, ['status' => 'pending'], 'https://evil.example/')->headers->get('Location');
        $html = $this->actingAs($admin, 'admin')->get($location)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-success" role="status">.*?<p>Customer status updated successfully\.</p>#s', $html);
        $this->assertStringContainsString('<span class="admin-products-badge is-low">Pending activation</span>', $html);
        $this->assertStringContainsString('<option value="pending" selected>', $html);
    }

    public function test_cus_be_003_the_success_message_stays_translated(): void
    {
        $this->seedTranslations();
        $approved = AdminCustomerTranslationSeeder::TRANSLATIONS['dashboard.Customer_status_updated']['ar'];
        $customer = $this->customer(['status' => 'active']);

        $response = $this->actingAs($this->admin(super: true), 'admin')->withSession(['locale' => 'ar'])
            ->withHeader('Referer', 'https://evil.example/')
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked']);

        $this->assertRedirectsToTheDetailsPage($response, $customer);
        $response->assertSessionHas('success', $approved);
    }

    /** @return array<string, array{?string, array<string, mixed>}> */
    public static function invalidStatuses(): array
    {
        $cases = [];
        foreach (self::referers() as $name => [$referer]) {
            $cases["unknown, {$name}"] = [$referer, ['status' => 'deleted']];
        }
        $cases['missing, external'] = ['https://evil.example/', []];
        $cases['empty, none'] = [null, ['status' => '']];
        $cases['array, external'] = ['https://evil.example/', ['status' => ['blocked']]];

        return $cases;
    }

    /** @dataProvider invalidStatuses */
    public function test_cus_be_003_a_refused_status_returns_to_the_customer_page_with_its_error(?string $referer, array $data): void
    {
        $customer = $this->customer(['status' => 'pending']);

        $response = $this->patchStatus($this->admin(super: true), $customer, $data, $referer);

        $this->assertRedirectsToTheDetailsPage($response, $customer);
        $response->assertSessionHasErrors('status')->assertSessionMissing('success');
        $this->assertSame('pending', $customer->fresh()->status);
    }

    public function test_cus_be_003_the_error_is_shown_at_the_field_after_the_redirect(): void
    {
        $customer = $this->customer(['status' => 'pending']);
        $admin = $this->admin(super: true);

        $location = $this->patchStatus($admin, $customer, ['status' => 'deleted'], 'https://evil.example/')->headers->get('Location');
        $html = $this->actingAs($admin, 'admin')->get($location)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<p class="admin-product-moderation-error" id="customer-status-error" role="alert">[^<]+</p>#', $html);
        $this->assertMatchesRegularExpression('#id="customer-status"[^>]*aria-invalid="true"#s', $html);
        $this->assertStringContainsString('<option value="pending" selected>', $html);
    }

    public function test_cus_be_003_a_json_request_gets_422_without_a_redirect(): void
    {
        $customer = $this->customer(['status' => 'pending']);

        $this->actingAs($this->admin(super: true), 'admin')->withHeader('Referer', 'https://evil.example/')
            ->patchJson(route('dashboard.customers.update-status', $customer), ['status' => 'deleted'])
            ->assertUnprocessable()->assertJsonValidationErrors('status')->assertHeaderMissing('Location');

        $this->assertSame('pending', $customer->fresh()->status);
    }

    public function test_cus_be_003_access_is_unchanged(): void
    {
        $customer = $this->customer(['status' => 'active']);

        // A guest goes to the admin sign-in page, never to the Referer.
        $guest = $this->withHeader('Referer', 'https://evil.example/')->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked']);
        $guest->assertRedirect(route('admin.login'));

        // Without customers.edit: refused before validation, with a valid or an invalid value, and no redirect.
        foreach ([[], ['customers.view'], ['customers.view', 'orders.view']] as $abilities) {
            foreach ([['status' => 'blocked'], ['status' => 'deleted']] as $data) {
                $this->patchStatus($this->admin($abilities), $customer, $data, 'https://evil.example/')
                    ->assertForbidden()->assertHeaderMissing('Location')->assertSessionHasNoErrors();
            }
        }
        $this->assertSame('active', $customer->fresh()->status);

        $this->assertRedirectsToTheDetailsPage($this->patchStatus($this->admin(['customers.edit']), $customer, ['status' => 'blocked'], 'https://evil.example/'), $customer);
        $this->assertSame('blocked', $customer->fresh()->status);
    }

    // ---------------------------------------------------------------- CUS-BE-004: no translation row written

    // The status labels and the success message are read without auto-creating rows, from the customer-specific keys;
    // the validation uses the fixed list of values. On an empty translations table the admin shell (layout, partials,
    // language switcher) still auto-creates its own keys on any dashboard page; those are told apart by their source.

    public function test_cus_be_004_the_details_page_writes_no_translation_row_of_its_own(): void
    {
        $customer = $this->customerWithHistory(['status' => 'pending']);
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $this->assertShellKeysOnly($this->writtenKeys(fn () => $this->page($admin, $customer, $locale)), $locale);
        }
        $this->assertSame([], TranslationValue::whereIn('key', [...self::SHARED_STATUS_KEYS, ...self::CUSTOMER_STATUS_KEYS])->pluck('key')->all());
    }

    public function test_cus_be_004_the_status_patch_writes_no_translation_row(): void
    {
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);
        $before = TranslationValue::count();

        foreach (['ar', 'en'] as $locale) {
            foreach (['pending', 'blocked', 'active'] as $status) {
                $response = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->withHeader('Referer', 'https://evil.example/')
                    ->patch(route('dashboard.customers.update-status', $customer), ['status' => $status]);
                $this->assertRedirectsToTheDetailsPage($response, $customer);
                // No translation yet: the English default, in both languages.
                $response->assertSessionHas('success', 'Customer status updated successfully.');
                $this->assertSame($status, $customer->fresh()->status);
            }
            foreach ([['status' => 'deleted'], ['status' => ''], [], ['status' => ['blocked']]] as $data) {
                $response = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->withHeader('Referer', 'https://evil.example/')
                    ->patch(route('dashboard.customers.update-status', $customer), $data);
                $this->assertRedirectsToTheDetailsPage($response, $customer);
                $response->assertSessionHasErrors('status')->assertSessionMissing('success');
            }
            $this->actingAs($this->admin(['customers.view']), 'admin')->withSession(['locale' => $locale])
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked'])->assertForbidden();
        }

        $this->assertSame('active', $customer->fresh()->status);
        $this->assertSame($before, TranslationValue::count(), 'The PATCH writes no translation row at all.');
    }

    public function test_cus_be_004_the_shared_status_keys_are_not_created_after_the_admin_seeders(): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $this->assertShellKeysOnly($this->writtenKeys(fn () => $this->page($admin, $customer, $locale)), $locale);
            $before = TranslationValue::count();
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked']);
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->patch(route('dashboard.customers.update-status', $customer), ['status' => 'x']);
            $this->assertSame($before, TranslationValue::count(), $locale);
        }

        $this->assertSame([], TranslationValue::whereIn('key', self::SHARED_STATUS_KEYS)->pluck('key')->all());
    }

    public function test_cus_be_004_custom_translations_are_kept_and_used(): void
    {
        $this->seedTranslations();
        $old = now()->subDays(3)->startOfSecond();
        $custom = [
            ['dashboard.Customer_status_updated', 'ar', 'حُدّثت حالة الحساب.'],
            ['dashboard.Customer_status_updated', 'en', 'Account status saved.'],
            ['dashboard.Customers_Status_Blocked', 'ar', 'موقوف'],
            ['dashboard.Active', 'ar', 'مُفعّلة'],
            ['dashboard.Pending', 'ar', 'قيد الانتظار'],
            ['dashboard.Blocked', 'ar', 'محجوب'],
        ];
        foreach ($custom as [$key, $locale, $value]) {
            TranslationValue::updateOrCreate(['key' => $key, 'locale' => $locale], ['value' => $value]);
            TranslationValue::where('key', $key)->where('locale', $locale)->update(['created_at' => $old, 'updated_at' => $old]);
        }
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked'])
            ->assertSessionHas('success', 'حُدّثت حالة الحساب.');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'blocked'])
            ->assertSessionHas('success', 'Account status saved.');
        $page = $this->page($admin, $customer, 'ar');
        $this->assertSame('موقوف', $page->viewData('statuses')['blocked']);
        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">موقوف</span>', $page->getContent());
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->patch(route('dashboard.customers.update-status', $customer), ['status' => 'x']);

        foreach ($custom as [$key, $locale, $value]) {
            $row = TranslationValue::where('key', $key)->where('locale', $locale)->first();
            $this->assertSame($value, $row->value, $key);
            $this->assertEquals($old, $row->updated_at, "{$key}: updated_at");
        }
    }

    public function test_cus_be_004_the_labels_and_the_message_in_arabic_and_english(): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'active']);
        $admin = $this->admin(super: true);

        $this->assertSame(['active' => 'نشط', 'pending' => 'بانتظار التفعيل', 'blocked' => 'محظور'], $this->page($admin, $customer, 'ar')->viewData('statuses'));
        $this->assertSame(['active' => 'Active', 'pending' => 'Pending activation', 'blocked' => 'Blocked'], $this->page($admin, $customer, 'en')->viewData('statuses'));
        foreach (['ar' => 'تم تحديث حالة العميل.', 'en' => 'Customer status updated successfully.'] as $locale => $message) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'pending'])
                ->assertSessionHas('success', $message);
            $this->assertSame($message, AdminCustomerTranslationSeeder::TRANSLATIONS['dashboard.Customer_status_updated'][$locale]);
        }
    }

    public function test_cus_be_004_the_default_labels_and_message_without_translations(): void
    {
        $customer = $this->customer(['status' => 'blocked']);
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $page = $this->page($admin, $customer, $locale);
            $this->assertSame(['active' => 'Active', 'pending' => 'Pending activation', 'blocked' => 'Blocked'], $page->viewData('statuses'), $locale);
            $this->assertSame(['active', 'pending', 'blocked'], $this->xpathValues($page->getContent(), '//select[@id="customer-status"]/option/@value'));
        }

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'active'])
            ->assertSessionHas('success', 'Customer status updated successfully.');
        $this->assertNull(TranslationValue::where('key', 'dashboard.Customer_status_updated')->first(), 'No row is created for the message.');
    }

    // ---------------------------------------------------------------- CUS-BE-006: the field name in the error

    // The validation messages name the field by its visible label (dashboard.Customers_Status_Label, read without
    // auto-create), not by "status". The rules, the error key, the JSON 422 and the safe redirect are unchanged.

    private const STATUS_ERRORS = [
        'ar' => ['in' => 'قيمة الحالة الجديدة المختارة غير صالحة.', 'required' => 'حقل الحالة الجديدة مطلوب.'],
        'en' => ['in' => 'The selected new status is invalid.', 'required' => 'The new status field is required.'],
    ];

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function refusedStatuses(): array
    {
        $cases = [];
        foreach (['ar', 'en'] as $locale) {
            $cases["{$locale}, unknown"] = [$locale, ['status' => 'deleted'], 'in'];
            $cases["{$locale}, empty"] = [$locale, ['status' => ''], 'required'];
            $cases["{$locale}, missing"] = [$locale, [], 'required'];
            $cases["{$locale}, array"] = [$locale, ['status' => ['blocked']], 'in'];
        }

        return $cases;
    }

    /** @dataProvider refusedStatuses */
    public function test_cus_be_006_the_html_error_names_the_field_in_the_admin_language(string $locale, array $data, string $rule): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'pending']);
        $admin = $this->admin(super: true);
        $message = self::STATUS_ERRORS[$locale][$rule];

        $response = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->withHeader('Referer', 'https://evil.example/')
            ->patch(route('dashboard.customers.update-status', $customer), $data);

        $this->assertRedirectsToTheDetailsPage($response, $customer);
        $response->assertSessionHasErrors(['status' => $message]);
        $this->assertSame(['status'], session('errors')->getBag('default')->keys());
        $this->assertSame('pending', $customer->fresh()->status);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get($response->headers->get('Location'))->assertOk()->getContent();
        $this->assertStringContainsString('<p class="admin-product-moderation-error" id="customer-status-error" role="alert">'.e($message).'</p>', $html);
        $this->assertSame($message, $this->xpath($html)->query('//*[@id="customer-status-error"]')->item(0)->textContent);
    }

    /** @dataProvider refusedStatuses */
    public function test_cus_be_006_the_json_error_names_the_field_in_the_admin_language(string $locale, array $data, string $rule): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'pending']);
        $message = self::STATUS_ERRORS[$locale][$rule];

        $response = $this->actingAs($this->admin(super: true), 'admin')->withSession(['locale' => $locale])
            ->patchJson(route('dashboard.customers.update-status', $customer), $data)
            ->assertUnprocessable()
            ->assertHeaderMissing('Location')
            ->assertExactJson(['message' => $message, 'errors' => ['status' => [$message]]]);

        $this->assertSame('pending', $customer->fresh()->status);
    }

    public function test_cus_be_006_a_custom_label_is_used_and_kept(): void
    {
        $this->seedTranslations();
        $old = now()->subDays(3)->startOfSecond();
        TranslationValue::where('key', 'dashboard.Customers_Status_Label')->where('locale', 'ar')->update(['value' => 'حالة الحساب', 'updated_at' => $old]);
        $customer = $this->customer(['status' => 'active']);

        $this->actingAs($this->admin(super: true), 'admin')->withSession(['locale' => 'ar'])
            ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'x'])
            ->assertSessionHasErrors(['status' => 'قيمة حالة الحساب المختارة غير صالحة.']);

        $row = TranslationValue::where('key', 'dashboard.Customers_Status_Label')->where('locale', 'ar')->first();
        $this->assertSame('حالة الحساب', $row->value);
        $this->assertEquals($old, $row->updated_at);
    }

    public function test_cus_be_006_without_translations_the_default_label_and_no_row_written(): void
    {
        $customer = $this->customer(['status' => 'blocked']);
        $admin = $this->admin(super: true);
        $before = TranslationValue::count();

        foreach (['ar' => 'حقل new status مطلوب.', 'en' => 'The new status field is required.'] as $locale => $message) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => ''])
                ->assertSessionHasErrors(['status' => $message]);
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->patchJson(route('dashboard.customers.update-status', $customer), ['status' => 'x'])
                ->assertUnprocessable();
        }

        $this->assertSame('blocked', $customer->fresh()->status);
        $this->assertSame($before, TranslationValue::count(), 'The field name writes no translation row.');
        $this->assertNull(TranslationValue::where('key', 'dashboard.Customers_Status_Label')->first());
    }

    public function test_cus_be_006_access_is_unchanged(): void
    {
        $this->seedTranslations();
        $customer = $this->customer(['status' => 'active']);

        $this->withSession(['locale' => 'ar'])->patchJson(route('dashboard.customers.update-status', $customer), ['status' => 'x'])->assertUnauthorized();
        foreach ([[], ['customers.view'], ['customers.view', 'orders.view']] as $abilities) {
            $admin = $this->admin($abilities);
            $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])
                ->patch(route('dashboard.customers.update-status', $customer), ['status' => 'x'])
                ->assertForbidden()->assertSessionHasNoErrors();
            $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])
                ->patchJson(route('dashboard.customers.update-status', $customer), [])
                ->assertForbidden()->assertJsonMissingPath('errors');
        }
        $this->assertSame('active', $customer->fresh()->status);
    }

    // ---------------------------------------------------------------- orders, addresses, reviews

    public function test_orders_addresses_and_reviews_are_shown(): void
    {
        $this->seedTranslations();
        $customer = $this->customerWithHistory();
        $html = $this->page($this->admin(super: true), $customer, 'ar')->getContent();
        $text = $this->contentText($html);

        $this->assertSame(5, substr_count($html, 'data-order-id='));
        foreach (['قيد الانتظار', 'قيد التنفيذ', 'مكتمل', 'ملغى جزئيًا', 'ملغى'] as $status) {
            $this->assertStringContainsString($status, $text, $status);
        }
        $this->assertStringContainsString('<span dir="ltr">112.50</span>', $html);

        $this->assertSame(2, substr_count($html, 'data-address-id='));
        $this->assertStringContainsString('<span class="admin-products-badge is-active">العنوان الافتراضي</span>', $html);
        $this->assertStringContainsString('المدينة: الخليل', $text);

        $this->assertSame(2, substr_count($html, 'data-review-id='));
        $this->assertStringContainsString('التقييم: 4 من 5', $text);
        $this->assertStringContainsString('منتج جميل.', $text);
    }

    public function test_order_links_follow_orders_view(): void
    {
        $customer = $this->customerWithHistory();
        $orderLink = '#href="'.preg_quote(route('dashboard.orders.index'), '#').'/\d+"#';

        $this->assertMatchesRegularExpression($orderLink, $this->page($this->admin(super: true), $customer)->getContent());
        $this->assertMatchesRegularExpression($orderLink, $this->page($this->admin(['customers.view', 'orders.view']), $customer)->getContent());

        $html = $this->page($this->admin(['customers.view']), $customer)->getContent();
        $this->assertDoesNotMatchRegularExpression($orderLink, $html);
        $this->assertStringContainsString('<span dir="ltr">HF-2026-001</span>', $html, 'The number stays, without a link.');
    }

    public function test_the_empty_states(): void
    {
        $html = $this->page($this->admin(super: true), $this->customer())->getContent();

        $this->assertStringContainsString('This customer has no orders.', $html);
        $this->assertStringContainsString('No saved addresses.', $html);
        $this->assertStringContainsString('This customer has no reviews.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    // ---------------------------------------------------------------- languages, escaping, translations

    public function test_arabic_is_right_to_left_and_english_left_to_right(): void
    {
        $this->seedTranslations();
        $customer = $this->customerWithHistory();
        $admin = $this->admin(super: true);

        $ar = $this->page($admin, $customer, 'ar')->getContent();
        $text = $this->contentText($ar);
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['العودة إلى العملاء', 'بيانات التواصل', 'البريد الإلكتروني', 'رقم الهاتف', 'حالة الحساب', 'الحالة الجديدة', 'حفظ الحالة', 'آخر الطلبات', 'رقم الطلب', 'الإجمالي', 'العناوين', 'التقييمات'] as $arabic) {
            $this->assertStringContainsString($arabic, $text, $arabic);
        }
        foreach (['Contact details', 'Email', 'Phone', 'Status', 'Account status', 'New status', 'Save status', 'Recent orders', 'Order number', 'Total', 'Addresses', 'Default address', 'City', 'Reviews', 'Rating', 'Pending', 'Processing', 'Completed', 'Cancelled', 'Back to customers'] as $english) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($english, '/').'\b/u', $text, "Unintended English: {$english}");
        }

        $en = $this->page($admin, $customer, 'en')->getContent();
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        foreach (['Back to customers', 'Contact details', 'Recent orders', 'Rating: 4 of 5', 'Partially cancelled'] as $english) {
            $this->assertStringContainsString($english, $en, $english);
        }
    }

    public function test_customer_data_is_escaped(): void
    {
        $customer = $this->customerWithHistory(['name' => '<script>alert(1)</script>']);
        $html = $this->page($this->admin(super: true), $customer)->getContent();

        foreach (['<script>alert(1)</script>', '<script>alert(3)</script>', '<script>alert(4)</script>'] as $raw) {
            $this->assertStringNotContainsString($raw, $html, $raw);
        }
        $this->assertStringContainsString('<h2 class="admin-product-title"><bdi>&lt;script&gt;alert(1)&lt;/script&gt;</bdi></h2>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(4)&lt;/script&gt;', $html);
    }

    public function test_rendering_writes_no_translation_row_for_the_page_texts(): void
    {
        $customer = $this->customerWithHistory();
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $this->page($admin, $customer, $locale);
            $this->page($this->admin(['customers.view']), $customer, $locale);
        }

        $this->assertSame([], $this->pageKeyRows(), 'The view never auto-creates its keys.');
        // The keys the previous page read with t() (auto-create on), no longer read by the view.
        $old = ['Recent_Orders', 'Addresses', 'Save', 'No_records_found', 'Home', 'Customers'];
        $this->assertSame([], TranslationValue::whereIn('key', array_map(fn ($key) => 'dashboard.'.$key, $old))->pluck('key')->unique()->values()->all());
    }

    public function test_the_translation_row_check_detects_a_written_page_key(): void
    {
        TranslationValue::create(['key' => 'dashboard.Customers_Show_Back', 'locale' => 'ar', 'value' => 'Back to customers']);
        TranslationValue::create(['key' => 'dashboard.Orders_Status_Completed', 'locale' => 'ar', 'value' => 'Completed']);

        $this->assertSame(['dashboard.Customers_Show_Back', 'dashboard.Orders_Status_Completed'], $this->pageKeyRows());
    }

    public function test_the_page_band_is_hidden_for_this_page_and_the_layout_errors_stay_visible(): void
    {
        $css = file_get_contents(resource_path('css/admin-dashboard.css'));

        $this->assertStringContainsString("html.admin-shell .pc-content:has(#admin-customer-show) > .page-header {\n  display: none;\n}", $css);
        // CUS-UI-06B: the layout's error list is not hidden here (it may carry errors this page does not show).
        $this->assertStringNotContainsString('.pc-content:has(#admin-customer-show) > .row > .alert-danger', $css);
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminHomeTranslationSeeder::class);
        $this->seed(AdminAbilityTranslationSeeder::class);
        $this->seed(AdminCustomerTranslationSeeder::class);
        // dashboard.City is reused as approved (AdminHomeTranslationSeeder corrects it only), created as on dev.
        TranslationValue::insertOrIgnore([
            ['key' => 'dashboard.City', 'locale' => 'ar', 'value' => 'المدينة'],
            ['key' => 'dashboard.City', 'locale' => 'en', 'value' => 'City'],
        ]);
    }

    private function customer(array $attributes = []): Customer
    {
        static $sequence = 0;
        $sequence++;

        return Customer::factory()->create($attributes + [
            'name' => "Customer {$sequence}",
            'email' => "show{$sequence}@example.test",
            'phone' => '05993000'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'status' => 'active',
        ]);
    }

    /** A customer with 5 orders (one per status), 2 addresses (one default, one with a city) and 2 reviews. */
    private function customerWithHistory(array $attributes = []): Customer
    {
        $customer = $this->customer($attributes);
        $governorate = Governorate::query()->create(['name' => 'الخليل', 'slug' => 'hebron-gov-'.$customer->id]);
        $city = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'الخليل', 'slug' => 'hebron-'.$customer->id, 'status' => 'active']);

        DB::table('customer_addresses')->insert([
            ['customer_id' => $customer->id, 'city_id' => $city->id, 'label' => 'البيت', 'recipient_name' => 'ليلى', 'phone' => '0599000001', 'address_line' => 'شارع عين سارة <script>alert(3)</script>', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
            ['customer_id' => $customer->id, 'city_id' => null, 'label' => 'العمل', 'recipient_name' => 'ليلى', 'phone' => '0599000009', 'address_line' => 'دوار المنارة', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach (['pending', 'processing', 'completed', 'partially_cancelled', 'cancelled'] as $i => $status) {
            $orderId = DB::table('orders')->insertGetId(['number' => 'HF-2026-00'.($i + 1), 'customer_id' => $customer->id, 'status' => $status, 'payment_method' => 'cod', 'grand_total' => 100 + $i * 12.5, 'created_at' => now()->subDays($i), 'updated_at' => now()]);
        }
        DB::table('reviews')->insert([
            ['customer_id' => $customer->id, 'order_id' => $orderId, 'reviewable_type' => 'product', 'rating' => 4, 'comment' => "منتج جميل.\n<script>alert(4)</script>", 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
            ['customer_id' => $customer->id, 'order_id' => $orderId, 'reviewable_type' => 'vendor', 'rating' => 5, 'comment' => null, 'status' => 'published', 'created_at' => now()->subDay(), 'updated_at' => now()],
        ]);

        return $customer;
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => "Admin {$sequence}", 'email' => "cus-show{$sequence}@example.com", 'phone' => '05990008'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    /**
     * The distinct keys of the translation rows written while $request runs.
     *
     * @return list<string>
     */
    private function writtenKeys(callable $request): array
    {
        $lastId = (int) TranslationValue::max('id');
        $request();

        return TranslationValue::where('id', '>', $lastId)->distinct()->orderBy('key')->pluck('key')->all();
    }

    /** Every written key belongs to the admin shell (literal in its files), none to the customers controller or views. */
    private function assertShellKeysOnly(array $written, string $message = ''): void
    {
        $shell = implode("\n", array_map('file_get_contents', array_merge(
            [resource_path('views/layouts/dashboard-layout.blade.php'), resource_path('views/components/lang/language-switcher-dashboard.blade.php')],
            glob(resource_path('views/layouts/partials/dashboard/*.blade.php'))
        )));

        foreach ($written as $key) {
            $this->assertNotContains($key, [...self::SHARED_STATUS_KEYS, ...self::CUSTOMER_STATUS_KEYS], "{$message}: {$key}");
            $this->assertFalse(str_starts_with($key, 'dashboard.Customers_') || str_starts_with($key, 'dashboard.Orders_Status_'), "{$message}: {$key}");
            $this->assertStringContainsString("'{$key}'", $shell, "{$message}: {$key} is not a key of the admin shell.");
        }
    }

    /** @return list<string> */
    private function xpathValues(string $html, string $query): array
    {
        $values = [];
        foreach ($this->xpath($html)->query($query) as $node) {
            $values[] = $node->nodeValue;
        }

        return $values;
    }

    /** The status PATCH, with the given Referer header (none when null). Redirects are not followed. */
    private function patchStatus(Admin $admin, Customer $customer, array $data, ?string $referer): TestResponse
    {
        $request = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en']);
        if ($referer !== null) {
            $request = $request->withHeader('Referer', $referer);
        }

        return $request->patch(route('dashboard.customers.update-status', $customer), $data);
    }

    private function assertRedirectsToTheDetailsPage(TestResponse $response, Customer $customer): void
    {
        $page = route('dashboard.customers.show', $customer);
        $location = (string) $response->headers->get('Location');

        $response->assertStatus(302);
        $this->assertSame($page, $location, 'The redirect goes to this customer\'s details page.');
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST), 'No external redirect.');
    }

    private function page(Admin $admin, Customer $customer, string $locale = 'en'): TestResponse
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.customers.show', $customer))->assertOk();
    }

    /** @return list<string> the rows of the page's own keys (Customers_*, Orders_Status_*), matched by prefix in PHP */
    private function pageKeyRows(): array
    {
        return TranslationValue::query()->orderBy('key')->pluck('key')
            ->filter(fn (string $key) => str_starts_with($key, 'dashboard.Customers_') || str_starts_with($key, 'dashboard.Orders_Status_'))
            ->values()->all();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function contentText(string $html): string
    {
        return preg_replace('/\s+/u', ' ', $this->xpath($html)->query('//*[@id="admin-customer-show"]')->item(0)->textContent);
    }
}
