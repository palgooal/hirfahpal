<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use DOMDocument;
use DOMElement;
use DOMXPath;
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

    // ---------------------------------------------------------------- the location selectors (H1-G, Governorate → City)

    public function test_the_governorate_comes_first_then_an_optional_city_both_with_an_empty_option(): void
    {
        $this->locations();
        $html = $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.addresses'))->assertOk()->getContent();

        $governorate = strpos($html, 'name="governorate_id"');
        $city = strpos($html, 'name="city_id"');
        $this->assertNotFalse($governorate);
        $this->assertNotFalse($city);
        $this->assertLessThan($city, $governorate, 'The governorate is chosen first.');
        $this->assertLessThan(strpos($html, 'name="address_line"'), $city);
        $this->assertStringContainsString('المدينة <span class="font-normal text-muted">(اختياري)</span>', $html);

        foreach (['governorate_id' => '— اختر المحافظة —', 'city_id' => '— بدون مدينة —'] as $name => $label) {
            $options = $this->selectOptions($html, $name);
            $this->assertSame(['', $label], [$options[0]['value'], $options[0]['label']], "{$name}: an explicit empty first option.");
            $this->assertSelected($html, $name, '', $label, null, "{$name}: only the empty option is preselected for a new address.");
            $this->assertSame('', $this->submitted($html, $name), "{$name}: a new address submits no location unless chosen.");
            $this->assertSame([], array_filter(array_column($options, 'disabled')));
        }
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="(governorate_id|city_id)"[^>]*\srequired/', $html, 'Both are optional.');
    }

    public function test_cities_are_grouped_under_their_active_governorates(): void
    {
        $this->locations();
        $html = $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.addresses'))->assertOk()->getContent();

        $this->assertSame(['— اختر المحافظة —', 'Empty Governorate', 'Gaza Governorate', 'Nablus Governorate'], array_column($this->selectOptions($html, 'governorate_id'), 'label'), 'Active governorates only, by name.');
        $groups = [];
        foreach (array_slice($this->selectOptions($html, 'city_id'), 1) as $option) {
            $groups[$option['group']][] = $option['label'];
        }
        // Active cities of active governorates; a governorate with no active city has no group.
        $this->assertSame(['Gaza Governorate' => ['Gaza', 'Rafah'], 'Nablus Governorate' => ['Nablus']], $groups);
        $this->assertStringNotContainsString('غير متاحة', $html, 'No unavailable option without a stored one.');
    }

    public function test_a_stored_active_pair_is_selected_and_kept_by_an_unrelated_edit(): void
    {
        $l = $this->locations();
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['rafah']->id);

        $html = $this->editPage($customer, $address);
        $this->assertSelected($html, 'governorate_id', (string) $l['gaza']->id, 'Gaza Governorate');
        $this->assertSelected($html, 'city_id', (string) $l['rafah']->id, 'Rafah', 'Gaza Governorate');

        $this->submitEdit($customer, $address, $html, ['label' => 'Work'])->assertRedirect(route('customer.addresses'))->assertSessionHasNoErrors();
        $this->assertStored($address, $l['gaza']->id, $l['rafah']->id);
        $this->assertSame('Work', $address->fresh()->label);
    }

    public function test_inactive_stored_values_stay_selected_marked_and_submitted(): void
    {
        $l = $this->locations();
        foreach ([
            'inactive governorate and its city' => [$l['closed']->id, $l['closedTown']->id, 'Closed Governorate', 'Closed Town'],
            'inactive city of an active governorate' => [$l['gaza']->id, $l['closedCity']->id, null, 'Closed City'],
        ] as $label => [$governorateId, $cityId, $unavailableGovernorate, $unavailableCity]) {
            [$customer, $address] = $this->addressWith($governorateId, $cityId);
            $html = $this->editPage($customer, $address);

            if ($unavailableGovernorate) {
                $this->assertSelected($html, 'governorate_id', (string) $governorateId, "{$unavailableGovernorate} (غير متاحة)");
            } else {
                $this->assertSelected($html, 'governorate_id', (string) $governorateId, 'Gaza Governorate');
            }
            $this->assertSelected($html, 'city_id', (string) $cityId, "{$unavailableCity} (غير متاحة)", null);
            $this->assertSame(1, substr_count($html, "{$unavailableCity} (غير متاحة)"), "{$label}: listed once.");

            $this->submitEdit($customer, $address, $html, ['address_line' => 'New line'])->assertSessionHasNoErrors();
            $this->assertStored($address, $governorateId, $cityId, $label);
            $this->assertSame('New line', $address->fresh()->address_line);
        }
    }

    public function test_a_legacy_mismatched_pair_or_a_city_without_a_governorate_is_preserved(): void
    {
        $l = $this->locations();

        // Gaza city stored under Nablus Governorate: each selector shows its own stored value.
        [$customer, $address] = $this->addressWith($l['nablus']->id, $l['gazaCity']->id);
        $html = $this->editPage($customer, $address);
        $this->assertSelected($html, 'governorate_id', (string) $l['nablus']->id, 'Nablus Governorate');
        $this->assertSelected($html, 'city_id', (string) $l['gazaCity']->id, 'Gaza', 'Gaza Governorate');
        $this->submitEdit($customer, $address, $html, ['phone' => '0599999999'])->assertSessionHasNoErrors();
        $this->assertStored($address, $l['nablus']->id, $l['gazaCity']->id);

        // A city with no governorate: the governorate stays empty, never the first in the list.
        [$customer, $address] = $this->addressWith(null, $l['orphan']->id);
        $html = $this->editPage($customer, $address);
        $this->assertSelected($html, 'governorate_id', '', '— اختر المحافظة —');
        $this->assertSelected($html, 'city_id', (string) $l['orphan']->id, 'Orphan (غير متاحة)', null);
        $this->submitEdit($customer, $address, $html, ['phone' => '0599999999'])->assertSessionHasNoErrors();
        $this->assertStored($address, null, $l['orphan']->id);
    }

    public function test_a_governorate_only_or_empty_location_works(): void
    {
        $l = $this->locations();

        foreach ([[$l['empty']->id, null], [null, null]] as [$governorateId, $cityId]) {
            [$customer, $address] = $this->addressWith($governorateId, $cityId);
            $html = $this->editPage($customer, $address);
            $this->assertSame((string) $governorateId, $this->submitted($html, 'governorate_id'), 'Never the first governorate in the list.');
            $this->assertSame('', $this->submitted($html, 'city_id'));

            $this->submitEdit($customer, $address, $html, ['label' => 'Kept'])->assertSessionHasNoErrors();
            $this->assertStored($address, $governorateId, $cityId);
        }

        // A new address through the browser form: governorate only, then nothing.
        $customer = Customer::factory()->create();
        $base = ['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street'];
        $this->actingAs($customer, 'customer')->post(route('customer.addresses.store'), $base + ['governorate_id' => (string) $l['gaza']->id, 'city_id' => ''])->assertSessionHasNoErrors();
        $this->actingAs($customer, 'customer')->post(route('customer.addresses.store'), $base + ['governorate_id' => '', 'city_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame([[$l['gaza']->id, null], [null, null]], $customer->addresses()->orderBy('id')->get()->map(fn ($a) => [$a->governorate_id, $a->city_id])->all());
    }

    public function test_a_changed_governorate_with_an_incompatible_city_is_refused_and_can_be_resolved(): void
    {
        $l = $this->locations();
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['gazaCity']->id);
        $editUrl = route('customer.addresses', ['edit' => $address->id]);
        $html = $this->editPage($customer, $address);

        // Without JavaScript the city is not cleared for the customer: the server refuses the pair and the page says so
        // on the city.
        $page = $this->actingAs($customer, 'customer')->followingRedirects()->from($editUrl)
            ->patch(route('customer.addresses.update', $address), $this->formValues($html, ['governorate_id' => (string) $l['nablus']->id]))
            ->assertOk()->getContent();
        $this->assertStored($address, $l['gaza']->id, $l['gazaCity']->id);
        $this->assertStringContainsString('<p id="address-city-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">The selected city does not belong to the selected governorate.</p>', $page);
        $citySelect = $this->xpath($page)->query('//select[@name="city_id"]')->item(0);
        $this->assertSame(['true', 'address-city-error'], [$citySelect->getAttribute('aria-invalid'), $citySelect->getAttribute('aria-describedby')]);
        $this->assertStringNotContainsString('address-governorate-error', $page);
        $this->assertSelected($page, 'governorate_id', (string) $l['nablus']->id, 'Nablus Governorate');
        $this->assertSelected($page, 'city_id', (string) $l['gazaCity']->id, 'Gaza', 'Gaza Governorate');

        // Resolved by choosing a city of the new governorate, or by clearing the city.
        $this->actingAs($customer, 'customer')->patch(route('customer.addresses.update', $address), $this->formValues($page, ['city_id' => (string) $l['nablusCity']->id]))->assertSessionHasNoErrors();
        $this->assertStored($address, $l['nablus']->id, $l['nablusCity']->id);
        $this->actingAs($customer, 'customer')->patch(route('customer.addresses.update', $address), $this->formValues($this->editPage($customer, $address), ['governorate_id' => (string) $l['gaza']->id, 'city_id' => '']))->assertSessionHasNoErrors();
        $this->assertStored($address, $l['gaza']->id, null);
    }

    public function test_the_form_submits_natively_without_javascript(): void
    {
        $l = $this->locations();
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['gazaCity']->id);
        $html = $this->editPage($customer, $address);

        $xpath = $this->xpath($html);
        $form = $xpath->query('//form[@id="addressForm"]')->item(0);
        $this->assertSame(['POST', route('customer.addresses.update', $address)], [$form->getAttribute('method'), $form->getAttribute('action')]);
        foreach (['_token', '_method', 'recipient_name', 'label', 'phone', 'governorate_id', 'city_id', 'address_line'] as $name) {
            $this->assertSame(1, $xpath->query(".//*[@name='{$name}']", $form)->length, "{$name} is inside the form.");
        }
        $this->assertSame('PATCH', $xpath->query(".//input[@name='_method']", $form)->item(0)->getAttribute('value'));
        $this->assertNotSame('', $xpath->query(".//input[@name='_token']", $form)->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('.//button[@type="submit"]', $form)->length);

        // A new address form posts to the store route without method spoofing.
        $create = $this->xpath($this->actingAs($customer, 'customer')->get(route('customer.addresses'))->getContent());
        $form = $create->query('//form[@id="addressForm"]')->item(0);
        $this->assertSame(route('customer.addresses.store'), $form->getAttribute('action'));
        $this->assertSame(0, $create->query(".//input[@name='_method']", $form)->length);
    }

    // ---------------------------------------------------------------- the city follows the governorate (H4-M)

    public function test_each_city_option_carries_its_governorate_for_the_script_and_the_select_is_never_disabled_in_html(): void
    {
        $l = $this->locations();

        foreach ([
            'new address' => [null, null, null],
            'inactive city of an active governorate' => [$l['gaza']->id, $l['closedCity']->id, (string) $l['gaza']->id],
            'city of an inactive governorate' => [$l['closed']->id, $l['closedTown']->id, (string) $l['closed']->id],
            'city without a governorate' => [null, $l['orphan']->id, ''],
        ] as $label => [$governorateId, $cityId, $unavailableGovernorate]) {
            if ($cityId === null) {
                $html = $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.addresses'))->assertOk()->getContent();
            } else {
                [$customer, $address] = $this->addressWith($governorateId, $cityId);
                $html = $this->editPage($customer, $address);
            }
            $xpath = $this->xpath($html);

            $groups = [];
            foreach ($xpath->query('//select[@name="city_id"]/optgroup') as $group) {
                /** @var DOMElement $group */
                $groups[$group->getAttribute('label')] = $group->getAttribute('data-governorate-id');
            }
            $this->assertSame(['Gaza Governorate' => (string) $l['gaza']->id, 'Nablus Governorate' => (string) $l['nablus']->id], $groups, $label);

            // Every active city is still rendered, so the form works without JavaScript.
            $this->assertSame(['', ...($cityId === null ? [] : [(string) $cityId]), (string) $l['gazaCity']->id, (string) $l['rafah']->id, (string) $l['nablusCity']->id],
                array_column($this->selectOptions($html, 'city_id'), 'value'), $label);

            $unavailable = $xpath->query('//select[@name="city_id"]/option[@value!=""]');
            $this->assertSame($cityId === null ? 0 : 1, $unavailable->length, $label);
            if ($cityId !== null) {
                $this->assertSame($unavailableGovernorate, $unavailable->item(0)->getAttribute('data-governorate-id'), $label);
            }

            $city = $xpath->query('//select[@name="city_id"]')->item(0);
            $this->assertFalse($city->hasAttribute('disabled'), "{$label}: the script disables it, never the HTML.");
            $this->assertSame(0, $xpath->query('//form[@id="addressForm"]//input[@name="city_id"]')->length, "{$label}: no hidden city in the HTML.");
        }
    }

    public function test_a_city_without_a_governorate_is_refused_from_the_browser_form_and_nothing_changes(): void
    {
        $l = $this->locations();
        $customer = Customer::factory()->create();
        $base = ['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street'];

        $page = $this->actingAs($customer, 'customer')->followingRedirects()->from(route('customer.addresses'))
            ->post(route('customer.addresses.store'), $base + ['governorate_id' => '', 'city_id' => (string) $l['gazaCity']->id])
            ->assertOk()->getContent();
        $this->assertSame(0, $customer->addresses()->count());
        $this->assertStringContainsString('<p id="address-governorate-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">A governorate is required when a city is selected.</p>', $page);
        $this->assertStringNotContainsString('address-city-error', $page);

        // On an edit: the stored pair is kept.
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['gazaCity']->id);
        $this->submitEdit($customer, $address, $this->editPage($customer, $address), ['governorate_id' => ''])
            ->assertSessionHasErrors('governorate_id')->assertSessionDoesntHaveErrors('city_id');
        $this->assertStored($address, $l['gaza']->id, $l['gazaCity']->id);
    }

    public function test_clearing_the_governorate_sends_an_empty_city_that_clears_the_stored_pair(): void
    {
        $l = $this->locations();

        foreach ([[$l['gaza']->id, $l['gazaCity']->id], [null, $l['orphan']->id], [$l['nablus']->id, $l['gazaCity']->id]] as [$governorateId, $cityId]) {
            [$customer, $address] = $this->addressWith($governorateId, $cityId);
            $html = $this->editPage($customer, $address);

            // What the script submits once the city select is disabled: the hidden empty city in its place.
            $this->submitEdit($customer, $address, $html, ['governorate_id' => '', 'city_id' => ''])->assertSessionHasNoErrors();
            $this->assertStored($address, null, null);
        }

        // Why the empty city is needed: a disabled select is not submitted, and an omitted city keeps the stored one.
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['gazaCity']->id);
        $values = $this->formValues($this->editPage($customer, $address), ['governorate_id' => '']);
        unset($values['city_id']);
        $this->actingAs($customer, 'customer')->patch(route('customer.addresses.update', $address), $values)->assertSessionHasErrors('governorate_id');
        $this->assertStored($address, $l['gaza']->id, $l['gazaCity']->id);
    }

    public function test_switching_to_another_governorate_with_a_cleared_or_compatible_city_is_saved(): void
    {
        $l = $this->locations();
        [$customer, $address] = $this->addressWith($l['gaza']->id, $l['gazaCity']->id);

        // The script clears the incompatible city: the governorate alone is saved.
        $this->submitEdit($customer, $address, $this->editPage($customer, $address), ['governorate_id' => (string) $l['nablus']->id, 'city_id' => ''])->assertSessionHasNoErrors();
        $this->assertStored($address, $l['nablus']->id, null);

        // A legacy mismatched pair: back to the city's own governorate, the city kept.
        [$customer, $address] = $this->addressWith($l['nablus']->id, $l['gazaCity']->id);
        $this->submitEdit($customer, $address, $this->editPage($customer, $address), ['governorate_id' => (string) $l['gaza']->id])->assertSessionHasNoErrors();
        $this->assertStored($address, $l['gaza']->id, $l['gazaCity']->id);
    }

    public function test_the_address_script_uses_the_location_module_and_restores_the_opening_pair(): void
    {
        $script = file_get_contents(resource_path('js/storefront-addresses.js'));

        $this->assertStringContainsString("import { locationModel, locationState } from './storefront-address-locations.js';", $script);
        $this->assertMatchesRegularExpression('/function openAddressModal\(title\) \{[^}]*locations\?\.restore\(\);/s', $script);
        $this->assertMatchesRegularExpression('/function closeAddressModal\(\) \{[^}]*\.reset\(\);\s*locations\?\.restore\(\);/s', $script);
        $this->assertStringContainsString("emptyCity.name = 'city_id';", $script);
        $this->assertStringContainsString('emptyCity.disabled = !view.sendEmptyCity;', $script);
        $this->assertStringContainsString('city.disabled = view.cityDisabled;', $script);
        $this->assertStringContainsString("@vite('resources/js/storefront-addresses.js')", file_get_contents(resource_path('views/pages/customer-addresses.blade.php')));
    }

    public function test_the_location_script_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
        $node = strtok($node, "\r\n");
        if ($node === false || $node === '') {
            $this->markTestSkipped('Node is not available.');
        }

        $process = proc_open([$node, '--test', 'tests/js/storefront-address-locations.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, Governorate|City> */
    private function locations(): array
    {
        $gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate']);
        $nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus-governorate']);
        $empty = Governorate::query()->create(['name' => 'Empty Governorate', 'slug' => 'empty-governorate']);
        $closed = Governorate::query()->create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);

        return [
            'gaza' => $gaza,
            'nablus' => $nablus,
            'empty' => $empty,
            'closed' => $closed,
            'rafah' => City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Rafah', 'slug' => 'rafah']),
            'gazaCity' => City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza']),
            'closedCity' => City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']),
            'nablusCity' => City::query()->create(['governorate_id' => $nablus->id, 'name' => 'Nablus', 'slug' => 'nablus']),
            'closedTown' => City::query()->create(['governorate_id' => $closed->id, 'name' => 'Closed Town', 'slug' => 'closed-town']),
            'orphan' => City::query()->create(['governorate_id' => null, 'name' => 'Orphan', 'slug' => 'orphan']),
        ];
    }

    /** @return array{0: Customer, 1: CustomerAddress} */
    private function addressWith(?int $governorateId, ?int $cityId): array
    {
        $customer = Customer::factory()->create();

        return [$customer, $this->storefrontAddress($customer, ['governorate_id' => $governorateId, 'city_id' => $cityId])];
    }

    private function editPage(Customer $customer, CustomerAddress $address): string
    {
        return $this->actingAs($customer, 'customer')->get(route('customer.addresses', ['edit' => $address->id]))->assertOk()->getContent();
    }

    /** PATCH what the browser would send from the rendered edit form, with $changes typed in. */
    private function submitEdit(Customer $customer, CustomerAddress $address, string $html, array $changes)
    {
        return $this->actingAs($customer, 'customer')->patch(route('customer.addresses.update', $address), $this->formValues($html, $changes));
    }

    /** @return array<string, string> the address form's fields as a browser submits them */
    private function formValues(string $html, array $changes = []): array
    {
        $xpath = $this->xpath($html);
        $form = $xpath->query('//form[@id="addressForm"]')->item(0);
        $values = [];
        foreach ($xpath->query('.//input[@name and not(@name="_token") and not(@name="_method")]', $form) as $input) {
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $values['address_line'] = $xpath->query('.//textarea[@name="address_line"]', $form)->item(0)->textContent;
        $values['governorate_id'] = $this->submitted($html, 'governorate_id');
        $values['city_id'] = $this->submitted($html, 'city_id');

        return $changes + $values;
    }

    /** The value a browser submits for a select: its selected option, or the first one. */
    private function submitted(string $html, string $name): string
    {
        $options = $this->selectOptions($html, $name);
        foreach ($options as $option) {
            if ($option['selected']) {
                return $option['value'];
            }
        }

        return $options[0]['value'];
    }

    /** @return list<array{value: string, label: string, selected: bool, disabled: bool, group: ?string}> */
    private function selectOptions(string $html, string $name): array
    {
        $options = [];
        foreach ($this->xpath($html)->query("//select[@name='{$name}']//option") as $option) {
            /** @var DOMElement $option */
            $parent = $option->parentNode;
            $options[] = [
                'value' => $option->getAttribute('value'),
                'label' => trim($option->textContent),
                'selected' => $option->hasAttribute('selected'),
                'disabled' => $option->hasAttribute('disabled'),
                'group' => $parent instanceof DOMElement && $parent->nodeName === 'optgroup' ? $parent->getAttribute('label') : null,
            ];
        }

        return $options;
    }

    /** Exactly one option is selected (never left to the browser's first-option fallback), and it is enabled. */
    private function assertSelected(string $html, string $name, string $value, string $label, ?string $group = null, string $message = ''): void
    {
        $selected = array_values(array_filter($this->selectOptions($html, $name), fn (array $option) => $option['selected']));

        $this->assertCount(1, $selected, $message ?: "{$name}: exactly one selected option.");
        $this->assertSame(['value' => $value, 'label' => $label, 'selected' => true, 'disabled' => false, 'group' => $group], $selected[0], $message);
    }

    private function assertStored(CustomerAddress $address, ?int $governorateId, ?int $cityId, string $message = ''): void
    {
        $fresh = $address->fresh();

        $this->assertSame([$governorateId, $cityId], [$fresh->governorate_id, $fresh->city_id], $message);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
