<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\RoleUser;
use Database\Seeders\AdminHomeTranslationSeeder;
use Database\Seeders\AdminSettingsTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LOC-UI-7B-1: the cities and governorates page's structure. One page heading, the counts (unassigned governorates
 * included once), the cities with their governorates and the unassigned group, the empty states, the search markup
 * left to the script, the add dialogs for settings.edit only, the add links that work without JavaScript, and the
 * failed form's dialog reopened with its own errors and input. Rules, routes and authorization unchanged.
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

    public function test_the_counts_include_unassigned_governorates_once(): void
    {
        $gaza = $this->city('Gaza');
        $nablus = $this->city('Nablus');
        $this->governorate('Rimal', $gaza);
        $this->governorate('Shati', $gaza);
        $this->governorate('Old City', $nablus);
        $this->governorate('Legacy');
        $admin = $this->admin(['settings.view']);

        $xpath = $this->xpath($this->page($admin, 'en'));

        $this->assertSame('2', trim($xpath->query('//dd[@data-location-count="cities"]')->item(0)->textContent));
        $this->assertSame((string) Governorate::query()->count(), trim($xpath->query('//dd[@data-location-count="governorates"]')->item(0)->textContent));
        $this->assertSame('4', trim($xpath->query('//dd[@data-location-count="governorates"]')->item(0)->textContent));
        $this->assertSame('Unassigned: 1', trim($xpath->query('//dd[contains(@class, "admin-locations-stat-note")]')->item(0)->textContent));

        $arabic = $this->xpath($this->page($admin, 'ar'));
        $this->assertSame('غير المرتبطة: 1', trim($arabic->query('//dd[contains(@class, "admin-locations-stat-note")]')->item(0)->textContent));
    }

    public function test_cities_list_their_own_governorates_and_unassigned_ones_are_grouped_apart(): void
    {
        $gaza = $this->city('Gaza');
        $this->city('Nablus');
        $this->governorate('Shati', $gaza);
        $this->governorate('Rimal', $gaza);
        $this->governorate('Legacy');
        $admin = $this->admin(['settings.view']);

        $xpath = $this->xpath($this->page($admin, 'en'));
        $groups = $xpath->query('//ul[contains(@class, "admin-locations-list")]/li[@data-location-group]');

        $this->assertSame(2, $groups->length);
        $this->assertSame('Gaza', trim($xpath->query('.//h3', $groups->item(0))->item(0)->textContent));
        $this->assertSame(['Rimal', 'Shati'], $this->chips($xpath, $groups->item(0)), 'Sorted by name.');
        $this->assertSame('Governorates: 2', trim($xpath->query('.//*[contains(@class, "admin-locations-count")]', $groups->item(0))->item(0)->textContent));
        $this->assertSame("Gaza\nRimal\nShati", $groups->item(0)->getAttribute('data-location-names'));
        $this->assertSame('Nablus', trim($xpath->query('.//h3', $groups->item(1))->item(0)->textContent));
        $this->assertSame([], $this->chips($xpath, $groups->item(1)));
        $this->assertStringContainsString('No governorates added yet.', $groups->item(1)->textContent);

        $unassigned = $xpath->query('//section[contains(@class, "admin-locations-unassigned")]');
        $this->assertSame(1, $unassigned->length);
        $this->assertSame(['Legacy'], $this->chips($xpath, $unassigned->item(0)));
        $this->assertStringContainsString('Unassigned governorates', $unassigned->item(0)->textContent);
        $this->assertStringContainsString('These governorates are not linked to a city, so they cannot be chosen for products.', $unassigned->item(0)->textContent);
        foreach ($groups as $group) {
            $this->assertNotContains('Legacy', $this->chips($xpath, $group));
        }
    }

    public function test_the_unassigned_group_is_absent_when_every_governorate_has_a_city(): void
    {
        $this->governorate('Rimal', $this->city('Gaza'));

        $xpath = $this->xpath($this->page($this->admin(['settings.view']), 'en'));

        $this->assertSame(0, $xpath->query('//*[contains(@class, "admin-locations-unassigned")]')->length);
        $this->assertSame(0, $xpath->query('//dd[contains(@class, "admin-locations-stat-note")]')->length);
    }

    public function test_the_empty_states(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);
        $viewer = $this->admin(['settings.view']);

        // Nothing at all: the empty state, with the first-city prompt for an editor only.
        $html = $this->page($editor, 'ar');
        $this->assertStringContainsString('لم تُضف مدن بعد.', $html);
        $this->assertStringContainsString('أضف أول مدينة للبدء.', $html);
        $this->assertStringNotContainsString('Add the first city to start.', $this->page($viewer, 'en'));
        $this->assertStringContainsString('No cities added yet.', $this->page($viewer, 'en'));

        // Unassigned governorates without any city: both the note and the group.
        $this->governorate('Legacy');
        $xpath = $this->xpath($this->page($viewer, 'en'));
        $this->assertSame('No cities added yet.', trim($xpath->query('//p[contains(@class, "admin-locations-empty-note")]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//section[contains(@class, "admin-locations-unassigned")]')->length);

        // The no-match state waits for the script.
        $this->assertSame('', $xpath->query('//*[@data-location-no-results]')->item(0)->getAttribute('hidden'));
        $this->assertStringContainsString('No locations match your search.', $xpath->query('//*[@data-location-no-results]')->item(0)->textContent);
    }

    public function test_the_search_markup_is_ready_and_hidden_until_the_script_runs(): void
    {
        $this->city('Gaza');

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
        $gaza = $this->city('Gaza');
        $nablus = $this->city('Nablus');
        $xpath = $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'en'));

        foreach (['city' => 'cities.store', 'governorate' => 'governorates.store'] as $form => $route) {
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

        $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'city']).'#location-city-dialog', $xpath->query('//a[@data-location-open="city"]')->item(0)->getAttribute('href'));
        $links = $xpath->query('//div[contains(@class, "admin-locations-actions")]/a[@data-location-open="governorate"]');
        $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'governorate']).'#location-governorate-dialog', $links->item(0)->getAttribute('href'));

        // One shortcut per city, named after it, preselecting it.
        $shortcuts = $xpath->query('//a[contains(@class, "admin-locations-shortcut")]');
        $this->assertSame(2, $shortcuts->length);
        foreach ([$gaza, $nablus] as $index => $city) {
            $shortcut = $shortcuts->item($index);
            $this->assertSame((string) $city->id, $shortcut->getAttribute('data-city-id'));
            $this->assertSame('Add governorate to '.$city->name, $shortcut->getAttribute('aria-label'));
            $this->assertSame(route('dashboard.setting.locations.index', ['add' => 'governorate', 'city' => $city->id]).'#location-governorate-dialog', $shortcut->getAttribute('href'));
        }
        $this->assertSame('إضافة محافظة إلى Gaza', $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'ar'))->query('//a[contains(@class, "admin-locations-shortcut")]')->item(0)->getAttribute('aria-label'));
    }

    public function test_the_add_links_open_their_dialog_from_the_server(): void
    {
        $this->city('Gaza');
        $nablus = $this->city('Nablus');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'city']));
        $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertFalse($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertTrue($xpath->query('//input[@id="location-city-name"]')->item(0)->hasAttribute('autofocus'));

        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'governorate']));
        $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'));
        $this->assertSame([], $this->selectedCities($xpath));
        $this->assertTrue($xpath->query('//select[@id="location-governorate-city"]')->item(0)->hasAttribute('autofocus'));

        // The shortcut: that city chosen, the name next.
        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'governorate', 'city' => $nablus->id]));
        $this->assertSame([(string) $nablus->id], $this->selectedCities($xpath));
        $this->assertTrue($xpath->query('//input[@id="location-governorate-name"]')->item(0)->hasAttribute('autofocus'));

        // Unknown or malformed values open nothing (or choose nothing), and never fail.
        foreach ([['add' => 'other'], ['add' => ['city']], ['add' => 'governorate', 'city' => ['1']], ['add' => 'governorate', 'city' => '999']] as $query) {
            $xpath = $this->xpath($this->page($editor, 'en', $query));
            $this->assertSame([], $this->selectedCities($xpath), json_encode($query));
        }
        $xpath = $this->xpath($this->page($editor, 'en', ['add' => ['city']]));
        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
    }

    public function test_adding_a_governorate_needs_a_city_first(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $xpath = $this->xpath($this->page($editor, 'en', ['add' => 'governorate']));

        $button = $xpath->query('//div[contains(@class, "admin-locations-actions")]/button')->item(0);
        $this->assertSame('true', $button->getAttribute('aria-disabled'));
        $this->assertSame('location-needs-city', $button->getAttribute('aria-describedby'));
        $this->assertSame('Add a city before adding its governorates.', trim($xpath->query('//*[@id="location-needs-city"]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//a[@data-location-open="governorate"]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@id="location-governorate-dialog"]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
    }

    public function test_a_viewer_sees_the_locations_but_no_add_action(): void
    {
        $this->governorate('Rimal', $this->city('Gaza'));
        $this->governorate('Legacy');
        $viewer = $this->admin(['settings.view']);

        foreach ([[], ['add' => 'city'], ['add' => 'governorate']] as $query) {
            $html = $this->page($viewer, 'en', $query);
            $xpath = $this->xpath($html);

            $this->assertSame(0, $xpath->query('//dialog')->length);
            $this->assertSame(0, $xpath->query('//*[@id="admin-locations"]//form')->length);
            $this->assertSame(0, $xpath->query('//*[@data-location-open]')->length);
            $this->assertSame(0, $xpath->query('//*[contains(@class, "admin-locations-actions")]')->length);
            $this->assertSame('2', trim($xpath->query('//dd[@data-location-count="governorates"]')->item(0)->textContent));
            $this->assertStringContainsString('Rimal', $html);
            $this->assertStringContainsString('Legacy', $html);
        }
    }

    public function test_a_failed_city_reopens_its_own_dialog_with_its_error_and_name(): void
    {
        $this->city('Gaza');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        foreach (['ar' => 'الاسم', 'en' => 'name'] as $locale => $attribute) {
            foreach (['Gaza', ''] as $name) {
                $xpath = $this->xpath($this->submit($editor, $locale, 'cities', ['location_form' => 'city', 'name' => $name]));

                $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), "{$locale} {$name}");
                $this->assertFalse($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'));
                $input = $xpath->query('//input[@id="location-city-name"]')->item(0);
                $this->assertSame($name, $input->getAttribute('value'));
                $this->assertSame('true', $input->getAttribute('aria-invalid'));
                $this->assertSame('location-city-name-error', $input->getAttribute('aria-describedby'));
                $this->assertTrue($input->hasAttribute('autofocus'));
                $this->assertStringContainsString($attribute, $xpath->query('//*[@id="location-city-name-error"]')->item(0)->textContent);
                // The other form is untouched.
                $this->assertSame('', $xpath->query('//input[@id="location-governorate-name"]')->item(0)->getAttribute('value'));
                $this->assertSame(0, $xpath->query('//*[@id="location-governorate-name-error" or @id="location-governorate-city-error"]')->length);
                $this->assertSame([], $this->selectedCities($xpath));
            }
        }
        $this->assertSame(1, City::query()->count());
    }

    public function test_a_failed_governorate_reopens_its_own_dialog_with_its_errors_city_and_name(): void
    {
        $gaza = $this->city('Gaza');
        $this->governorate('Legacy');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $cases = [
            'no name' => [['city_id' => $gaza->id, 'name' => ''], ['name'], [(string) $gaza->id]],
            'duplicate of an unassigned one' => [['city_id' => $gaza->id, 'name' => 'Legacy'], ['name'], [(string) $gaza->id]],
            'no city' => [['city_id' => '', 'name' => 'Rimal'], ['city'], []],
            'unknown city' => [['city_id' => 999, 'name' => 'Rimal'], ['city'], []],
        ];

        foreach (['ar' => ['name' => 'الاسم', 'city' => 'المدينة'], 'en' => ['name' => 'name', 'city' => 'city']] as $locale => $attributes) {
            foreach ($cases as $case => [$input, $errors, $selected]) {
                $html = $this->submit($editor, $locale, 'governorates', ['location_form' => 'governorate'] + $input);
                $xpath = $this->xpath($html);
                $label = "{$locale}: {$case}";

                $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'), $label);
                $this->assertFalse($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), $label);
                $this->assertSame($input['name'], $xpath->query('//input[@id="location-governorate-name"]')->item(0)->getAttribute('value'), $label);
                $this->assertSame($selected, $this->selectedCities($xpath), $label);
                foreach (['name' => 'location-governorate-name', 'city' => 'location-governorate-city'] as $field => $id) {
                    $error = $xpath->query('//*[@id="'.$id.'-error"]')->item(0);
                    if (in_array($field, $errors, true)) {
                        $this->assertStringContainsString($attributes[$field], $error->textContent, $label);
                        $this->assertSame('true', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('aria-invalid'), $label);
                    } else {
                        $this->assertNull($error, "{$label}: no {$field} error");
                    }
                }
                // The city form stays empty: its name field never shows the governorate's name.
                $this->assertSame('', $xpath->query('//input[@id="location-city-name"]')->item(0)->getAttribute('value'), $label);
                $this->assertNull($xpath->query('//*[@id="location-city-name-error"]')->item(0), $label);
                $this->assertStringNotContainsString('city id', $html, $label);
            }
        }
        $this->assertSame(1, Governorate::query()->count());
    }

    public function test_without_a_known_form_marker_the_errors_choose_the_dialog(): void
    {
        $gaza = $this->city('Gaza');
        $editor = $this->admin(['settings.view', 'settings.edit']);

        foreach ([[], ['location_form' => 'other'], ['location_form' => ['city']]] as $marker) {
            $xpath = $this->xpath($this->submit($editor, 'en', 'governorates', $marker + ['city_id' => $gaza->id, 'name' => '']));
            $this->assertTrue($xpath->query('//dialog[@id="location-governorate-dialog"]')->item(0)->hasAttribute('open'), json_encode($marker));
            $this->assertNotNull($xpath->query('//*[@id="location-governorate-name-error"]')->item(0));

            $xpath = $this->xpath($this->submit($editor, 'en', 'cities', $marker + ['name' => 'Gaza']));
            $this->assertTrue($xpath->query('//dialog[@id="location-city-dialog"]')->item(0)->hasAttribute('open'), json_encode($marker));
            $this->assertNotNull($xpath->query('//*[@id="location-city-name-error"]')->item(0));
        }
    }

    public function test_a_success_closes_every_dialog_and_shows_the_notice(): void
    {
        $editor = $this->admin(['settings.view', 'settings.edit']);

        $xpath = $this->xpath($this->submit($editor, 'ar', 'cities', ['location_form' => 'city', 'name' => 'Gaza']));

        $this->assertSame(0, $xpath->query('//dialog[@open]')->length);
        $alert = $xpath->query('//*[@data-location-alert]')->item(0);
        $this->assertSame('status', $alert->getAttribute('role'));
        $this->assertStringContainsString('تمت إضافة المدينة بنجاح.', $alert->textContent);
        $this->assertTrue($xpath->query('.//button[@data-location-alert-close]', $alert)->item(0)->hasAttribute('hidden'));
        $this->assertSame(['Gaza'], City::query()->pluck('name')->all());
    }

    public function test_names_are_escaped(): void
    {
        $city = $this->city('<b>Gaza</b>');
        $this->governorate('<i>Rimal</i>', $city);
        $this->governorate('<u>Legacy</u>');

        $html = $this->page($this->admin(['settings.view', 'settings.edit']), 'en');

        $this->assertStringNotContainsString('<b>Gaza</b>', $html);
        $this->assertStringNotContainsString('<i>Rimal</i>', $html);
        $this->assertStringNotContainsString('<u>Legacy</u>', $html);
        $this->assertStringContainsString('<bdi>&lt;b&gt;Gaza&lt;/b&gt;</bdi>', $html);
        $this->assertStringContainsString('<bdi>&lt;i&gt;Rimal&lt;/i&gt;</bdi>', $html);
        $this->assertStringContainsString('aria-label="Add governorate to &lt;b&gt;Gaza&lt;/b&gt;"', $html);
    }

    public function test_the_locations_take_three_queries_however_many_cities(): void
    {
        $admin = $this->admin(['settings.view', 'settings.edit']);
        $this->governorate('Legacy');
        $counts = [];

        foreach ([1, 5] as $total) {
            while (City::query()->count() < $total) {
                $city = $this->city('City '.City::query()->count());
                $this->governorate('Governorate of '.$city->name, $city);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->page($admin, 'en');
            $counts[$total] = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/from "(cities|governorates)"/', $query['query']))->count();
            DB::disableQueryLog();
        }

        $this->assertSame([1 => 3, 5 => 3], $counts);
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
        $this->governorate('Rimal', $this->city('Gaza'));
        $xpath = $this->xpath($this->page($this->admin(['settings.view', 'settings.edit']), 'en'));
        foreach (['location-city-dialog', 'location-governorate-dialog'] as $id) {
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

    /** @return list<string> */
    private function selectedCities(DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//select[@id="location-governorate-city"]/option[@selected]') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }

    private function city(string $name): City
    {
        return City::query()->create(['name' => $name, 'slug' => 'city-'.(City::query()->count() + 1)]);
    }

    private function governorate(string $name, ?City $city = null): Governorate
    {
        return Governorate::query()->create(['city_id' => $city?->id, 'name' => $name, 'slug' => 'governorate-'.(Governorate::query()->count() + 1)]);
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
