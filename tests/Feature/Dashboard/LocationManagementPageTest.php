<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\City;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Vendor;
use Database\Seeders\AdminHomeTranslationSeeder;
use Database\Seeders\AdminSettingsTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * LOC-UI-7B-1, H2-C: the governorates and cities page (Governorate → City). One page heading, the counts (unassigned
 * cities included once), the governorates with their cities and the read-only group of cities linked to no governorate,
 * the empty states, the search markup left to the script, the add dialogs for settings.edit only (the cities form only
 * once an active governorate exists), the add links that work without JavaScript, and the failed form's dialog reopened
 * with its own errors and input. H2-B: the add endpoints write only cities.governorate_id.
 */
class LocationManagementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->seed(AdminHomeTranslationSeeder::class);
    }

    public function test_the_page_has_one_heading_with_its_own_title_in_each_language(): void
    {
        $admin = $this->admin(['settings.view', 'settings.edit']);

        foreach (['ar' => ['rtl', 'إدارة المدن والمحافظات', 'الإعدادات'], 'en' => ['ltr', 'Manage cities and governorates', 'Settings']] as $locale => [$direction, $title, $settings]) {
            $html = $this->page($admin, $locale);
            $xpath = $this->xpath($html);

            $this->assertMatchesRegularExpression('/<html[^>]* dir="'.$direction.'"/', $html, $locale);
            $this->assertSame(1, $xpath->query('//h1')->length, $locale);
            $this->assertSame($title, trim($xpath->query('//h1')->item(0)->textContent), $locale);
            $this->assertStringContainsString($title.' | ', $xpath->query('//title')->item(0)->textContent, $locale);
            $this->assertSame(0, $xpath->query('//*[@id="admin-locations"]//h1')->length, "{$locale}: {$settings} is no longer the page heading");
        }
    }

    public function test_the_counts_include_unassigned_cities_once(): void
    {
        $gaza = $this->governorate('Gaza');
        $nablus = $this->governorate('Nablus');
        $this->city('Rimal', $gaza);
        $this->city('Shati', $gaza);
        $this->city('Old City', $nablus);
        $this->city('Legacy');
        $admin = $this->admin(['settings.view']);

        $xpath = $this->xpath($this->page($admin, 'en'));

        $this->assertSame('2', trim($xpath->query('//dd[@data-location-count="governorates"]')->item(0)->textContent));
        $this->assertSame((string) City::query()->count(), trim($xpath->query('//dd[@data-location-count="cities"]')->item(0)->textContent));
        $this->assertSame('4', trim($xpath->query('//dd[@data-location-count="cities"]')->item(0)->textContent));
        $this->assertSame('Unassigned: 1', trim($xpath->query('//dd[contains(@class, "admin-locations-stat-note")]')->item(0)->textContent));
        // Governorates first, then cities.
        $this->assertSame(['Governorates', 'Cities'], $this->texts($xpath, '//dl[contains(@class, "admin-locations-summary")]//dt'));

        $arabic = $this->xpath($this->page($admin, 'ar'));
        $this->assertSame('غير المرتبطة: 1', trim($arabic->query('//dd[contains(@class, "admin-locations-stat-note")]')->item(0)->textContent));
    }

    public function test_governorates_list_their_own_cities_and_unassigned_ones_are_grouped_apart(): void
    {
        $gaza = $this->governorate('Gaza');
        $this->governorate('Nablus');
        $this->city('Shati', $gaza);
        $this->city('Rimal', $gaza);
        // Legacy data: a city with no governorate, and a governorate pointing to it the old way (governorates.city_id).
        $legacy = $this->city('Legacy');
        $this->legacyGovernorate('Old Link', $legacy);
        $admin = $this->admin(['settings.view']);

        $xpath = $this->xpath($this->page($admin, 'en'));
        $groups = $xpath->query('//ul[contains(@class, "admin-locations-list")]/li[@data-location-group]');

        $this->assertSame(3, $groups->length);
        $this->assertSame('Gaza', trim($xpath->query('.//h3', $groups->item(0))->item(0)->textContent));
        $this->assertSame(['Rimal', 'Shati'], $this->chips($xpath, $groups->item(0)), 'Sorted by name.');
        $this->assertSame('Cities: 2', trim($xpath->query('.//*[contains(@class, "admin-locations-count")]', $groups->item(0))->item(0)->textContent));
        $this->assertSame("Gaza\nRimal\nShati", $groups->item(0)->getAttribute('data-location-names'));
        $this->assertSame('Nablus', trim($xpath->query('.//h3', $groups->item(1))->item(0)->textContent));
        $this->assertSame([], $this->chips($xpath, $groups->item(1)));
        $this->assertStringContainsString('No cities added yet.', $groups->item(1)->textContent);
        // The old reverse link is not read: the governorate has no city, and the city stays unassigned.
        $this->assertSame('Old Link', trim($xpath->query('.//h3', $groups->item(2))->item(0)->textContent));
        $this->assertSame([], $this->chips($xpath, $groups->item(2)));

        $unassigned = $xpath->query('//section[contains(@class, "admin-locations-unassigned")]');
        $this->assertSame(1, $unassigned->length);
        $this->assertSame(['Legacy'], $this->chips($xpath, $unassigned->item(0)));
        $this->assertStringContainsString('Cities without a governorate', $unassigned->item(0)->textContent);
        $this->assertStringContainsString('These cities are not linked to a governorate yet, so they are not offered for store and delivery addresses.', $unassigned->item(0)->textContent);
        // Read only: no add, edit or move action in the group.
        $this->assertSame(0, $xpath->query('.//a | .//button | .//form', $unassigned->item(0))->length);
        foreach ($groups as $group) {
            $this->assertNotContains('Legacy', $this->chips($xpath, $group));
        }
    }

    public function test_the_unassigned_group_is_absent_when_every_city_has_a_governorate(): void
    {
        $this->city('Rimal', $this->governorate('Gaza'));

        $xpath = $this->xpath($this->page($this->admin(['settings.view']), 'en'));

        $this->assertSame(0, $xpath->query('//*[contains(@class, "admin-locations-unassigned")]')->length);
        $this->assertSame(0, $xpath->query('//dd[contains(@class, "admin-locations-stat-note")]')->length);
    }

    public function test_the_empty_states(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $viewer = $this->admin(['settings.view']);

        // Nothing at all: the empty state, with the first-governorate prompt for an editor only.
        $html = $this->page($editor, 'ar');
        $this->assertStringContainsString('لم تُضف محافظات بعد.', $html);
        $this->assertStringContainsString('أضف أول محافظة للبدء.', $html);
        $this->assertStringNotContainsString('Add the first governorate to start.', $this->page($viewer, 'en'));
        $this->assertStringContainsString('No governorates added yet.', $this->page($viewer, 'en'));

        // Unassigned cities without any governorate: both the note and the group.
        $this->city('Legacy');
        $xpath = $this->xpath($this->page($viewer, 'en'));
        $this->assertSame('No governorates added yet.', trim($xpath->query('//p[contains(@class, "admin-locations-empty-note")]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//section[contains(@class, "admin-locations-unassigned")]')->length);

        // The no-match state waits for the script.
        $this->assertSame('', $xpath->query('//*[@data-location-no-results]')->item(0)->getAttribute('hidden'));
        $this->assertStringContainsString('No locations match your search.', $xpath->query('//*[@data-location-no-results]')->item(0)->textContent);
    }

    public function test_the_search_markup_is_ready_and_hidden_until_the_script_runs(): void
    {
        $this->governorate('Gaza');

        foreach (['ar' => ['البحث في المواقع', 'ابحث باسم المدينة أو المحافظة', 'عدد النتائج: :count'], 'en' => ['Search locations', 'Search by city or governorate', 'Results: :count']] as $locale => [$label, $placeholder, $template]) {
            $xpath = $this->xpath($this->page($this->admin(['settings.view']), $locale));
            $search = $xpath->query('//*[@data-location-search]')->item(0);

            $this->assertSame('search', $search->getAttribute('role'));
            $this->assertTrue($search->hasAttribute('hidden'), $locale);
            $searchLabel = $xpath->query('.//label[@for="locations-search"]', $search)->item(0);
            $this->assertSame($label, trim($searchLabel->textContent));
            $this->assertStringContainsString('visually-hidden', $searchLabel->getAttribute('class'), 'Named for assistive technology, not shown.');
            $this->assertSame('locations-search-results', $xpath->query('//input[@id="locations-search"]')->item(0)->getAttribute('aria-describedby'));
            $this->assertSame($placeholder, $xpath->query('//input[@id="locations-search"]')->item(0)->getAttribute('placeholder'));
            $status = $xpath->query('//*[@id="locations-search-results"]')->item(0);
            $this->assertSame('status', $status->getAttribute('role'));
            $this->assertSame($template, $status->getAttribute('data-template'));
        }
    }

    public function test_an_editor_gets_both_dialogs_and_add_links_that_work_without_javascript(): void
    {
        $gaza = $this->governorate('Gaza');
        $nablus = $this->governorate('Nablus');
        $closed = $this->governorate('Closed', 'inactive');
        $xpath = $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'en'));

        foreach (['governorate' => 'governorates.store', 'city' => 'cities.store'] as $form => $route) {
            $dialog = $xpath->query('//dialog[@id="location-'.$form.'-dialog"]')->item(0);
            $this->assertNotNull($dialog, $form);
            $this->assertFalse($dialog->hasAttribute('open'), "{$form}: closed without an error or an add link");
            $this->assertSame('location-'.$form.'-title', $dialog->getAttribute('aria-labelledby'));
            $this->assertSame(route('dashboard.setting.locations.'.$route), $xpath->query('.//form', $dialog)->item(0)->getAttribute('action'));
            $this->assertSame($form, $xpath->query('.//input[@name="location_form"]', $dialog)->item(0)->getAttribute('value'));
            // Cancel and Close are links back to the page: usable without the script.
            foreach ($xpath->query('.//a[@data-location-close]', $dialog) as $close) {
                $this->assertSame(route('dashboard.setting.locations.index'), $close->getAttribute('href'));
            }
            $this->assertSame(2, $xpath->query('.//a[@data-location-close]', $dialog)->length);
        }

        // Governorate first: one name, no city. Cities: an active governorate and several name rows.
        $governorateForm = $xpath->query('//dialog[@id="location-governorate-dialog"]//form')->item(0);
        $this->assertSame(['_token', 'location_form', 'name'], $this->fieldNames($xpath, $governorateForm));
        $cityForm = $xpath->query('//dialog[@id="location-city-dialog"]//form')->item(0);
        $this->assertSame(['_token', 'location_form', 'governorate_id', 'cities[]', 'cities[]', 'cities[]'], $this->fieldNames($xpath, $cityForm));
        $this->assertSame(['', (string) $gaza->id, (string) $nablus->id], $this->optionValues($xpath), 'Active governorates only.');
        $this->assertSame(1, $xpath->query('.//button[@data-add-city-row]', $cityForm)->length);
        $this->assertSame(1, $xpath->query('.//template[@data-city-row-template]//input[@name="cities[]"]', $cityForm)->length);
        $this->assertSame(0, $xpath->query('//*[@name="city_id" or @name="governorates[]"]')->length, 'No reverse field is left.');

        // The primary action adds a governorate; the cities action comes second.
        $actions = $xpath->query('//div[contains(@class, "admin-locations-actions")]/a');
        $this->assertSame(['governorate', 'city'], [$actions->item(0)->getAttribute('data-location-open'), $actions->item(1)->getAttribute('data-location-open')]);
        $this->assertStringContainsString('btn-primary', $actions->item(0)->getAttribute('class'));
        $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'governorate']).'#location-governorate-dialog', $actions->item(0)->getAttribute('href'));
        $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'city']).'#location-city-dialog', $actions->item(1)->getAttribute('href'));

        // One shortcut per active governorate, named after it, preselecting it; an inactive one is marked instead.
        $shortcuts = $xpath->query('//a[contains(@class, "admin-locations-shortcut")]');
        $this->assertSame(2, $shortcuts->length);
        foreach ([$gaza, $nablus] as $index => $governorate) {
            $shortcut = $shortcuts->item($index);
            $this->assertSame((string) $governorate->id, $shortcut->getAttribute('data-governorate-id'));
            $this->assertSame('city', $shortcut->getAttribute('data-location-open'));
            $this->assertSame('Add city to '.$governorate->name, $shortcut->getAttribute('aria-label'));
            $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'city', 'governorate' => $governorate->id]).'#location-city-dialog', $shortcut->getAttribute('href'));
        }
        $closedGroup = $xpath->query('//li[@data-location-group][.//h3[normalize-space()="Closed"]]')->item(0);
        $this->assertSame('Inactive', trim($xpath->query('.//*[@data-location-inactive]', $closedGroup)->item(0)->textContent));
        $this->assertSame(0, $xpath->query('.//a', $closedGroup)->length);
        $this->assertNotNull($closed);
        $this->assertSame('إضافة مدينة إلى Gaza', $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'ar'))->query('//a[contains(@class, "admin-locations-shortcut")]')->item(0)->getAttribute('aria-label'));
    }

    public function test_the_add_links_open_their_dialog_from_the_server(): void
    {
        $this->governorate('Gaza');
        $nablus = $this->governorate('Nablus');
        $closed = $this->governorate('Closed', 'inactive');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'governorate']));
        $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertFalse($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertTrue($xpath->query('//input[@id="location-governorate-name"]')->item(0)->hasAttribute('autofocus'));

        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'city']));
        $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertSame([], $this->selectedGovernorates($xpath));
        $this->assertTrue($xpath->query('//select[@id="location-city-governorate"]')->item(0)->hasAttribute('autofocus'));

        // The shortcut: that governorate chosen, the city name next.
        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'city', 'governorate' => $nablus->id]));
        $this->assertSame([(string) $nablus->id], $this->selectedGovernorates($xpath));
        $this->assertTrue($xpath->query('//input[@id="location-city-name"]')->item(0)->hasAttribute('autofocus'));

        // Unknown, malformed or inactive values choose nothing, and never fail.
        foreach ([['add' => 'other'], ['add' => ['city']], ['add' => 'city', 'governorate' => ['1']], ['add' => 'city', 'governorate' => '999'], ['add' => 'city', 'governorate' => $closed->id]] as $query) {
            $xpath = $this->xpath($this->page($editor, 'en', $query));
            $this->assertSame([], $this->selectedGovernorates($xpath), json_encode($query));
        }
        $xpath = $this->xpath($this->page($editor, 'en', ['add' => ['city']]));
        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
    }

    public function test_adding_cities_needs_an_active_governorate_first(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);

        foreach (['no governorate' => fn () => null, 'only an inactive one' => fn () => $this->governorate('Closed', 'inactive')] as $label => $arrange) {
            $arrange();
            $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'city']));

            $button = $xpath->query('//div[contains(@class, "admin-locations-actions")]/button')->item(0);
            $this->assertSame('true', $button->getAttribute('aria-disabled'), $label);
            $this->assertSame('location-needs-governorate', $button->getAttribute('aria-describedby'));
            $this->assertSame('Add a governorate before adding its cities.', trim($xpath->query('//*[@id="location-needs-governorate"]')->item(0)->textContent));
            $this->assertSame(0, $xpath->query('//a[@data-location-open="city"]')->length, $label);
            $this->assertSame(0, $xpath->query('//dialog[@id="location-city-dialog"]')->length, $label);
            $this->assertSame(0, $xpath->query('//dialog[@open]')->length, $label);
            // A governorate can always be added.
            $this->assertSame(1, $xpath->query('//a[@data-location-open="governorate"]')->length, $label);
            $this->assertSame(1, $xpath->query('//dialog[@id="location-governorate-dialog"]')->length, $label);
        }
    }

    public function test_a_viewer_sees_the_locations_but_no_add_action(): void
    {
        $this->city('Rimal', $this->governorate('Gaza'));
        $this->city('Legacy');
        $viewer = $this->admin(['settings.view']);

        foreach ([[], ['add' => 'city'], ['add' => 'governorate']] as $query) {
            $html = $this->page($viewer, 'en', $query);
            $xpath = $this->xpath($html);

            $this->assertSame(0, $xpath->query('//dialog')->length);
            $this->assertSame(0, $xpath->query('//*[@id="admin-locations"]//form')->length);
            $this->assertSame(0, $xpath->query('//*[@data-location-open]')->length);
            $this->assertSame(0, $xpath->query('//*[contains(@class, "admin-locations-actions")]')->length);
            $this->assertSame('2', trim($xpath->query('//dd[@data-location-count="cities"]')->item(0)->textContent));
            $this->assertStringContainsString('Rimal', $html);
            $this->assertStringContainsString('Legacy', $html);
        }
    }

    public function test_a_failed_governorate_reopens_its_own_dialog_with_its_error_and_name(): void
    {
        $this->governorate('Gaza');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        foreach (['ar' => 'المحافظة', 'en' => 'Governorate'] as $locale => $attribute) {
            foreach (['Gaza', '', str_repeat('a', 256)] as $name) {
                $xpath = $this->xpath($this->submit($editor, $locale, 'governorates', ['location_form' => 'governorate', 'name' => $name]));
                $label = "{$locale}: ".mb_substr($name, 0, 10);

                $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'), $label);
                $this->assertFalse($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), $label);
                $input = $xpath->query('//input[@id="location-governorate-name"]')->item(0);
                $this->assertSame($name, $input->getAttribute('value'), $label);
                $this->assertSame('true', $input->getAttribute('aria-invalid'));
                $this->assertSame('location-governorate-name-error', $input->getAttribute('aria-describedby'));
                $this->assertTrue($input->hasAttribute('autofocus'));
                $this->assertStringContainsString($attribute, $xpath->query('//*[@id="location-governorate-name-error"]')->item(0)->textContent, $label);
                // The other form is untouched.
                $this->assertSame(['', '', ''], $this->cityRows($xpath), $label);
                $this->assertSame(0, $xpath->query('//*[starts-with(@id, "location-city-name") and contains(@id, "-error")] | //*[@id="location-city-governorate-error"]')->length, $label);
                $this->assertSame([], $this->selectedGovernorates($xpath));
            }
        }
        $this->assertSame(1, Governorate::query()->count());
    }

    public function test_a_failed_city_reopens_its_own_dialog_with_its_errors_governorate_and_names(): void
    {
        $gaza = $this->governorate('Gaza');
        $closed = $this->governorate('Closed', 'inactive');
        $this->city('Legacy');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $cases = [
            'no name' => [['governorate_id' => $gaza->id, 'cities' => ['', '  ']], ['list'], [(string) $gaza->id], ['', '', '']],
            'a duplicate of an unassigned city' => [['governorate_id' => $gaza->id, 'cities' => ['Rafah', 'Legacy']], ['row1'], [(string) $gaza->id], ['Rafah', 'Legacy', '']],
            'the same name twice' => [['governorate_id' => $gaza->id, 'cities' => ['Rafah', '', 'Rafah']], ['row0', 'row1'], [(string) $gaza->id], ['Rafah', 'Rafah', '']],
            'the older single-name payload' => [['governorate_id' => $gaza->id, 'name' => 'Legacy'], ['row0'], [(string) $gaza->id], ['Legacy', '', '']],
            'no governorate' => [['governorate_id' => '', 'cities' => ['Rafah']], ['governorate'], [], ['Rafah', '', '']],
            'unknown governorate' => [['governorate_id' => 999, 'cities' => ['Rafah']], ['governorate'], [], ['Rafah', '', '']],
            'inactive governorate' => [['governorate_id' => $closed->id, 'cities' => ['Rafah']], ['governorate'], [], ['Rafah', '', '']],
        ];

        foreach (['ar' => ['governorate' => 'المحافظة', 'cities' => 'المدن'], 'en' => ['governorate' => 'Governorate', 'cities' => 'Cities']] as $locale => $attributes) {
            foreach ($cases as $case => [$input, $errors, $selected, $rows]) {
                $html = $this->submit($editor, $locale, 'cities', ['location_form' => 'city'] + $input);
                $xpath = $this->xpath($html);
                $label = "{$locale}: {$case}";

                $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), $label);
                $this->assertFalse($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'), $label);
                $this->assertSame($rows, $this->cityRows($xpath), "{$label}: the typed names are kept, in order.");
                $this->assertSame($selected, $this->selectedGovernorates($xpath), $label);
                $fields = ['governorate' => 'location-city-governorate', 'list' => 'location-city-name', 'row0' => 'location-city-name', 'row1' => 'location-city-name-1'];
                foreach (array_unique(array_values($fields)) as $id) {
                    $wanted = array_keys(array_filter($fields, fn ($field) => $field === $id));
                    $error = $xpath->query('//*[@id="'.$id.'-error"]')->item(0);
                    if (array_intersect($wanted, $errors) !== []) {
                        $this->assertNotNull($error, "{$label}: an error on {$id}");
                        $this->assertStringContainsString($id === 'location-city-governorate' ? $attributes['governorate'] : $attributes['cities'], $error->textContent, $label);
                        $this->assertSame('true', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('aria-invalid'), $label);
                        $this->assertSame($id.'-error', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('aria-describedby'), $label);
                    } else {
                        $this->assertNull($error, "{$label}: no error on {$id}");
                    }
                }
                // Focus: the governorate when it failed (or none is chosen), else the first name.
                $focus = in_array('governorate', $errors, true) ? 'location-city-governorate' : 'location-city-name';
                $this->assertTrue($xpath->query('//*[@id="'.$focus.'"]')->item(0)->hasAttribute('autofocus'), $label);
                // The governorate form stays empty: its name field never shows a city's name.
                $this->assertSame('', $xpath->query('//input[@id="location-governorate-name"]')->item(0)->getAttribute('value'), $label);
                $this->assertNull($xpath->query('//*[@id="location-governorate-name-error"]')->item(0), $label);
                $this->assertStringNotContainsString('governorate id', $html, $label);
            }
        }
        $this->assertSame(['Legacy'], City::query()->pluck('name')->all(), 'Nothing refused is saved.');
    }

    public function test_without_a_known_form_marker_the_errors_choose_the_dialog(): void
    {
        $gaza = $this->governorate('Gaza');
        $this->city('Rafah', $gaza);
        $editor = $this->admin(['settings.view', 'settings.edit']);

        foreach ([[], ['location_form' => 'other'], ['location_form' => ['city']]] as $marker) {
            $xpath = $this->xpath($this->submit($editor, 'en', 'governorates', $marker + ['name' => '']));
            $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'), json_encode($marker));
            $this->assertNotNull($xpath->query('//*[@id="location-governorate-name-error"]')->item(0));

            $xpath = $this->xpath($this->submit($editor, 'en', 'cities', $marker + ['governorate_id' => $gaza->id, 'cities' => ['Rafah']]));
            $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), json_encode($marker));
            $this->assertNotNull($xpath->query('//*[@id="location-city-name-error"]')->item(0));

            $xpath = $this->xpath($this->submit($editor, 'en', 'cities', $marker + ['cities' => ['Shati']]));
            $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), json_encode($marker));
            $this->assertNotNull($xpath->query('//*[@id="location-city-governorate-error"]')->item(0));
        }
    }

    public function test_a_success_closes_every_dialog_and_shows_the_notice(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $xpath = $this->xpath($this->submit($editor, 'ar', 'governorates', ['location_form' => 'governorate', 'name' => 'Gaza']));
        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
        $alert = $xpath->query('//*[@data-location-alert]')->item(0);
        $this->assertSame('status', $alert->getAttribute('role'));
        $this->assertStringContainsString('تمت إضافة المحافظة بنجاح.', $alert->textContent);
        $this->assertTrue($xpath->query('.//button[@data-location-alert-close]', $alert)->item(0)->hasAttribute('hidden'));

        $gaza = Governorate::query()->sole();
        $xpath = $this->xpath($this->submit($editor, 'ar', 'cities', ['location_form' => 'city', 'governorate_id' => $gaza->id, 'cities' => ['Rimal', 'Shati', '']]));
        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
        $this->assertStringContainsString('تمت إضافة المدن بنجاح.', $xpath->query('//*[@data-location-alert]')->item(0)->textContent, 'The plural notice for several cities.');
        $xpath = $this->xpath($this->submit($editor, 'en', 'cities', ['location_form' => 'city', 'governorate_id' => $gaza->id, 'cities' => ['Tal al-Hawa']]));
        $this->assertStringContainsString('City added successfully.', $xpath->query('//*[@data-location-alert]')->item(0)->textContent);

        $this->assertSame(['Rimal', 'Shati', 'Tal al-Hawa'], $gaza->cities()->orderBy('name')->pluck('name')->all());
        $listed = $this->xpath($this->page($editor, 'en'));
        $this->assertSame(['Rimal', 'Shati', 'Tal al-Hawa'], $this->chips($listed, $listed->query('//li[@data-location-group]')->item(0)), 'Listed under their governorate.');
    }

    public function test_names_are_escaped(): void
    {
        $governorate = $this->governorate('<b>Gaza</b>');
        $this->city('<i>Rimal</i>', $governorate);
        $this->city('<u>Legacy</u>');

        $html = $this->page($this->admin(['settings.view', 'settings.edit']), 'en');

        $this->assertStringNotContainsString('<b>Gaza</b>', $html);
        $this->assertStringNotContainsString('<i>Rimal</i>', $html);
        $this->assertStringNotContainsString('<u>Legacy</u>', $html);
        $this->assertStringContainsString('<bdi>&lt;b&gt;Gaza&lt;/b&gt;</bdi>', $html);
        $this->assertStringContainsString('<bdi>&lt;i&gt;Rimal&lt;/i&gt;</bdi>', $html);
        $this->assertStringContainsString('<bdi>&lt;u&gt;Legacy&lt;/u&gt;</bdi>', $html);
        $this->assertStringContainsString('aria-label="Add city to &lt;b&gt;Gaza&lt;/b&gt;"', $html);
    }

    public function test_the_locations_take_three_queries_however_many_governorates(): void
    {
        $admin = $this->admin(['settings.view', 'settings.edit']);
        $this->city('Legacy');
        $counts = [];

        foreach ([1, 5] as $total) {
            while (Governorate::query()->count() < $total) {
                $governorate = $this->governorate('Governorate '.Governorate::query()->count());
                $this->city('City of '.$governorate->name, $governorate);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->page($admin, 'en');
            $counts[$total] = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/from "(cities|governorates)"/', $query['query']))->count();
            DB::disableQueryLog();
        }

        // The governorates, their cities, and the unassigned cities.
        $this->assertSame([1 => 3, 5 => 3], $counts);
    }

    public function test_new_cities_are_offered_in_my_store_and_in_the_address_form(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $this->store('governorates', ['name' => 'Gaza Governorate'], $editor)->assertSessionHasNoErrors();
        $gaza = Governorate::query()->sole();
        $this->store('cities', ['governorate_id' => $gaza->id, 'cities' => ['Rimal', 'Shati']], $editor)->assertSessionHasNoErrors();
        [$rimal, $shati] = $gaza->cities()->orderBy('name')->get()->all();

        // My Store: the governorate with its cities, through cities.governorate_id.
        $vendor = Vendor::factory()->approved()->create();
        $locations = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))->assertOk()->json('locations.governorates');
        $this->assertSame([['id' => $gaza->id, 'name' => 'Gaza Governorate', 'cities' => [
            ['id' => $rimal->id, 'governorate_id' => $gaza->id, 'name' => 'Rimal'],
            ['id' => $shati->id, 'governorate_id' => $gaza->id, 'name' => 'Shati'],
        ]]], $locations);

        // The customer address form: the cities grouped under their governorate.
        $html = $this->actingAs(Customer::factory()->create(), 'customer')->get(route('customer.addresses'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $group = $xpath->query('//select[@name="city_id"]/optgroup[@label="Gaza Governorate"]')->item(0);
        $this->assertNotNull($group);
        $this->assertSame([(string) $rimal->id, (string) $shati->id], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($xpath->query('./option', $group))));
    }

    // ---- H2-B: Governorate → City (governorates are parents, cities belong to one active governorate) ----

    public function test_a_governorate_is_created_alone_and_never_linked_to_a_city(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $legacyCity = $this->city('Gaza');

        $this->store('governorates', ['name' => 'Gaza Governorate'], $editor)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'تمت إضافة المحافظة بنجاح.');
        // A city sent by the old form is ignored, and its single governorates[] row is the name.
        $this->store('governorates', ['city_id' => $legacyCity->id, 'governorates' => ['Nablus Governorate', '']], $editor)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([
            ['name' => 'Gaza Governorate', 'city_id' => null, 'status' => 'active'],
            ['name' => 'Nablus Governorate', 'city_id' => null, 'status' => 'active'],
        ], Governorate::query()->orderBy('name')->get(['name', 'city_id', 'status'])->toArray());
    }

    public function test_a_governorate_name_is_required_short_and_unique(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $this->governorate('Gaza Governorate');

        foreach ([
            'missing' => [],
            'empty' => ['name' => ''],
            'too long' => ['name' => str_repeat('a', 256)],
            'duplicate' => ['name' => 'Gaza Governorate'],
            'not a string' => ['name' => ['Gaza']],
            'several old rows' => ['governorates' => ['Rimal', 'Shati']],
        ] as $label => $payload) {
            $this->store('governorates', $payload, $editor)
                ->assertRedirect()->assertSessionHasErrors('name');
            $this->assertSame(1, Governorate::query()->count(), $label);
        }
    }

    public function test_one_city_is_created_under_an_active_governorate(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $governorate = $this->governorate('Gaza Governorate');

        // The single-name payload of the older form, and a one-row cities[].
        $this->store('cities', ['governorate_id' => $governorate->id, 'name' => 'Gaza'], $editor)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'تمت إضافة المدينة بنجاح.');
        $this->store('cities', ['governorate_id' => (string) $governorate->id, 'cities' => ['Rafah']], $editor)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([
            ['name' => 'Gaza', 'governorate_id' => $governorate->id, 'status' => 'active'],
            ['name' => 'Rafah', 'governorate_id' => $governorate->id, 'status' => 'active'],
        ], City::query()->orderBy('name')->get(['name', 'governorate_id', 'status'])->toArray());
        $this->assertNull($governorate->fresh()->city_id, 'The governorate is never linked back to a city.');
    }

    public function test_several_cities_are_created_in_one_submission_and_blank_rows_are_ignored(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $governorate = $this->governorate('Gaza Governorate');

        $this->store('cities', ['governorate_id' => $governorate->id, 'cities' => ['Rafah', '', 'Gaza', '   ', null]], $editor)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['Gaza', 'Rafah'], City::query()->where('governorate_id', $governorate->id)->orderBy('name')->pluck('name')->all());
        $this->assertSame(2, City::query()->count());
        $this->assertSame(2, City::query()->distinct()->count('slug'));
    }

    public function test_cities_need_an_existing_active_governorate(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $closed = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);

        foreach ([
            'missing' => [],
            'empty' => ['governorate_id' => ''],
            'unknown' => ['governorate_id' => 999],
            'malformed' => ['governorate_id' => 'abc'],
            'inactive' => ['governorate_id' => $closed->id],
        ] as $label => $governorate) {
            $this->store('cities', $governorate + ['cities' => ['Gaza', 'Rafah']], $editor)
                ->assertRedirect()->assertSessionHasErrors('governorate_id')->assertSessionDoesntHaveErrors(['cities', 'cities.0', 'cities.1']);
            $this->assertSame(0, City::query()->count(), $label);
        }
    }

    public function test_city_names_are_unique_globally_and_within_the_submission_with_no_partial_writes(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $gaza = $this->governorate('Gaza Governorate');
        $nablus = $this->governorate('Nablus Governorate');
        City::query()->create(['governorate_id' => $nablus->id, 'name' => 'Nablus', 'slug' => 'nablus']);

        // A name used under another governorate, and the same name twice in one submission: nothing is saved.
        $this->store('cities', ['governorate_id' => $gaza->id, 'cities' => ['Rafah', 'Nablus']], $editor)
            ->assertSessionHasErrors('cities.1')->assertSessionDoesntHaveErrors('cities.0');
        $this->store('cities', ['governorate_id' => $gaza->id, 'cities' => ['Rafah', 'Rafah']], $editor)
            ->assertSessionHasErrors(['cities.0', 'cities.1']);
        $this->store('cities', ['governorate_id' => $gaza->id, 'cities' => ['Rafah', str_repeat('a', 256)]], $editor)
            ->assertSessionHasErrors('cities.1');

        $this->assertSame(['Nablus'], City::query()->pluck('name')->all());
    }

    public function test_an_empty_city_submission_is_refused(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $governorate = $this->governorate('Gaza Governorate');

        foreach ([[], ['cities' => []], ['cities' => ['', '  ', null]], ['name' => ''], ['cities' => 'Gaza']] as $payload) {
            $this->store('cities', ['governorate_id' => $governorate->id] + $payload, $editor)
                ->assertRedirect()->assertSessionHasErrors('cities');
        }
        $this->store('cities', ['governorate_id' => $governorate->id, 'cities' => [['Gaza']]], $editor)
            ->assertSessionHasErrors('cities.0');

        $this->assertSame(0, City::query()->count());
    }

    public function test_reverse_linked_legacy_data_is_left_unchanged_and_listed_apart(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        // The local data shape: a city with no governorate, and a governorate pointing to it (governorates.city_id).
        $legacyCity = $this->city('غزة');
        $legacyGovernorate = $this->legacyGovernorate('محافظة غزة', $legacyCity);
        $closed = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $before = [Governorate::query()->orderBy('id')->get()->toArray(), City::query()->orderBy('id')->get()->toArray()];

        $this->store('governorates', ['name' => 'Gaza Governorate'], $editor)->assertSessionHasNoErrors();
        $gaza = Governorate::query()->where('name', 'Gaza Governorate')->sole();
        $this->store('cities', ['governorate_id' => $gaza->id, 'cities' => ['Rafah']], $editor)->assertSessionHasNoErrors();

        // The existing rows (ids, links, statuses) are exactly as before.
        $this->assertSame($before[0], Governorate::query()->whereKey([$legacyGovernorate->id, $closed->id])->orderBy('id')->get()->toArray());
        $this->assertSame($before[1], City::query()->whereKey($legacyCity->id)->get()->toArray());

        $response = $this->actingAs($editor, 'admin')->get(route('dashboard.setting.locations.index'))->assertOk();
        $this->assertSame([$legacyCity->id], $response->viewData('unassignedCities')->pluck('id')->all(), 'Listed apart, read only.');
    }

    public function test_the_page_data_lists_governorates_with_their_cities_by_name(): void
    {
        $nablus = $this->governorate('Nablus Governorate');
        $gaza = $this->governorate('Gaza Governorate');
        City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Rafah', 'slug' => 'rafah']);
        City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza']);
        $this->city('Orphan');

        $response = $this->actingAs($this->admin(['settings.view']), 'admin')->get(route('dashboard.setting.locations.index'))->assertOk();

        $this->assertSame(
            ['Gaza Governorate' => ['Gaza', 'Rafah'], 'Nablus Governorate' => []],
            $response->viewData('governorates')->mapWithKeys(fn (Governorate $governorate) => [$governorate->name => $governorate->cities->pluck('name')->all()])->all(),
        );
        $this->assertSame(['Orphan'], $response->viewData('unassignedCities')->pluck('name')->all());
        $this->assertNotNull($nablus);
    }

    public function test_adding_still_needs_settings_edit(): void
    {
        $viewer = $this->admin(['settings.view']);
        $governorate = $this->governorate('Gaza Governorate');

        $this->store('governorates', ['name' => 'Nablus Governorate'], $viewer)->assertForbidden();
        $this->store('cities', ['governorate_id' => $governorate->id, 'cities' => ['Gaza']], $viewer)->assertForbidden();

        $this->assertSame(1, Governorate::query()->count());
        $this->assertSame(0, City::query()->count());
    }

    public function test_the_layouts_raw_error_list_is_hidden_on_this_page(): void
    {
        $this->assertStringContainsString(
            'html.admin-shell .pc-content:has(#admin-locations) > .row > .alert-danger',
            file_get_contents(resource_path('css/admin-dashboard.css'))
        );
    }

    public function test_the_dialogs_close_button_is_a_44px_target_on_this_page_only(): void
    {
        $css = file_get_contents(resource_path('css/admin-dashboard.css'));

        $this->assertMatchesRegularExpression('/html\.admin-shell \.admin-locations-dialog \.admin-media-icon-button \{\s*width: 2\.75rem;\s*height: 2\.75rem;\s*font-size: 1\.375rem;\s*\}/', $css);
        // The shared component keeps its size; the shell's focus ring is untouched.
        $this->assertMatchesRegularExpression('/html\.admin-shell \.admin-media-icon-button \{[^}]*width: 2rem;\s*height: 2rem;/', $css);
        $this->assertMatchesRegularExpression('/html\.admin-shell :focus-visible \{\s*outline: 2px solid var\(--color-hf-olive\);\s*outline-offset: 2px;\s*\}/', $css);

        // Both dialogs carry the class the rule is scoped to, with their Close link.
        $this->city('Rimal', $this->governorate('Gaza'));
        $xpath = $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'en'));
        foreach (['location-governorate-dialog', 'location-city-dialog'] as $id) {
            $this->assertSame(1, $xpath->query('//dialog[@id="'.$id.'" and contains(@class, "admin-locations-dialog")]//a[contains(@class, "admin-media-icon-button") and @data-location-close and @aria-label="Close"]')->length, $id);
        }
    }

    /** The page's HTML; the session language and an optional query. */
    private function page(Admin $admin, string $locale, array $query = []): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
            ->get(route('dashboard.setting.locations.index', $query))->assertOk()->getContent();
    }

    /** Posts one of the two forms from the page and returns the page it comes back to. */
    private function submit(Admin $admin, string $locale, string $form, array $input): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
            ->from(route('dashboard.setting.locations.index'))->followingRedirects()
            ->post(route('dashboard.setting.locations.'.$form.'.store'), $input)->assertOk()->getContent();
    }

    /** Posts to one of the add endpoints in Arabic, from the page, as $admin when given; returns the redirect. */
    private function store(string $form, array $payload, ?Admin $admin = null): TestResponse
    {
        $request = $admin ? $this->actingAs($admin, 'admin') : $this;

        return $request->withSession(['locale' => 'ar'])->from(route('dashboard.setting.locations.index'))
            ->post(route('dashboard.setting.locations.'.$form.'.store'), $payload);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /** @return list<string> */
    private function chips(DOMXPath $xpath, \DOMNode $group): array
    {
        $names = [];
        foreach ($xpath->query('.//ul[contains(@class, "admin-locations-chips")]/li', $group) as $chip) {
            $names[] = trim($chip->textContent);
        }

        return $names;
    }

    /** @return list<string> the governorate options chosen in the cities form */
    private function selectedGovernorates(DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//select[@id="location-city-governorate"]/option[@selected]') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }

    /** @return list<string> every governorate option of the cities form, the empty one first */
    private function optionValues(DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//select[@id="location-city-governorate"]/option') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }

    /** @return list<string> the city rows' values, in order */
    private function cityRows(DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//div[@data-city-inputs]//input[@name="cities[]"]') as $input) {
            $values[] = $input->getAttribute('value');
        }

        return $values;
    }

    /** @return list<string> the names of a form's fields, in order (the row template excluded) */
    private function fieldNames(DOMXPath $xpath, \DOMNode $form): array
    {
        $names = [];
        foreach ($xpath->query('.//*[@name][not(ancestor::template)]', $form) as $field) {
            $names[] = $field->getAttribute('name');
        }

        return $names;
    }

    /** @return list<string> */
    private function texts(DOMXPath $xpath, string $query): array
    {
        $texts = [];
        foreach ($xpath->query($query) as $node) {
            $texts[] = trim($node->textContent);
        }

        return $texts;
    }

    private function governorate(string $name, string $status = 'active'): Governorate
    {
        return Governorate::query()->create(['name' => $name, 'slug' => 'governorate-'.(Governorate::query()->count() + 1), 'status' => $status]);
    }

    /** A city of the governorate (cities.governorate_id), or a legacy city linked to none. */
    private function city(string $name, ?Governorate $governorate = null): City
    {
        return City::query()->create(['governorate_id' => $governorate?->id, 'name' => $name, 'slug' => 'city-'.(City::query()->count() + 1)]);
    }

    /** Legacy data: a governorate pointing to a city the old way (governorates.city_id), as before H2. */
    private function legacyGovernorate(string $name, City $city): Governorate
    {
        return Governorate::query()->create(['city_id' => $city->id, 'name' => $name, 'slug' => 'governorate-'.(Governorate::query()->count() + 1)]);
    }

    private function admin(array $abilities): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password',
            'status' => 'active', 'super_admin' => false,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
