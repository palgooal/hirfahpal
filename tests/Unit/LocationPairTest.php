<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Governorate;
use App\Support\Locations\LocationPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * H1-D: the effective governorate/city pair (Governorate → City through cities.governorate_id). Omitted fields keep the
 * stored values, explicit null or "" clears them, an unchanged stored pair is kept even when legacy or inactive, and a
 * changed pair is checked for existence, newly selected inactive values, a city without its governorate and a city of
 * another governorate. Scenarios A–J follow the H1-B review.
 */
class LocationPairTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGES = [
        'governorate_missing' => 'A governorate is required when a city is selected.',
        'governorate_invalid' => 'The selected governorate is invalid.',
        'governorate_inactive' => 'The selected governorate is not available.',
        'city_invalid' => 'The selected city is invalid.',
        'city_inactive' => 'The selected city is not available.',
        'mismatch' => 'The selected city does not belong to the selected governorate.',
    ];

    private Governorate $gaza;

    private City $gazaCity;

    private City $rafah;

    private Governorate $nablus;

    private City $nablusCity;

    private Governorate $closedGovernorate;

    private City $closedCity;

    private City $orphanCity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate']);
        $this->gazaCity = City::query()->create(['governorate_id' => $this->gaza->id, 'name' => 'Gaza', 'slug' => 'gaza']);
        $this->rafah = City::query()->create(['governorate_id' => $this->gaza->id, 'name' => 'Rafah', 'slug' => 'rafah']);
        $this->nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus-governorate']);
        $this->nablusCity = City::query()->create(['governorate_id' => $this->nablus->id, 'name' => 'Nablus', 'slug' => 'nablus']);
        // An inactive governorate, an inactive city of an active governorate, and a legacy city with no governorate.
        $this->closedGovernorate = Governorate::query()->create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);
        $this->closedCity = City::query()->create(['governorate_id' => $this->gaza->id, 'name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $this->orphanCity = City::query()->create(['governorate_id' => null, 'name' => 'Orphan', 'slug' => 'orphan']);
    }

    // ---------------------------------------------------------------- A, B: both omitted, both cleared

    public function test_a_both_fields_omitted_keep_any_stored_pair(): void
    {
        $this->assertPasses(['label' => 'Home'], null, null, 'No location.');
        $this->assertPasses(['label' => 'Home'], $this->gaza->id, $this->gazaCity->id, 'A valid pair.');
        $this->assertPasses(['label' => 'Home'], $this->nablus->id, $this->gazaCity->id, 'A legacy mismatched pair.');
        $this->assertPasses(['label' => 'Home'], $this->closedGovernorate->id, null, 'An inactive governorate.');
        $this->assertPasses(['label' => 'Home'], $this->gaza->id, $this->closedCity->id, 'An inactive city.');
        $this->assertPasses(['label' => 'Home'], null, $this->orphanCity->id, 'A city without a governorate.');
        $this->assertPasses([], $this->nablus->id, $this->orphanCity->id, 'An empty request.');
    }

    public function test_b_both_fields_cleared_with_null_or_empty_values(): void
    {
        foreach ([null, ''] as $empty) {
            $input = ['governorate_id' => $empty, 'city_id' => $empty];
            $this->assertPasses($input, null, null);
            $this->assertPasses($input, $this->gaza->id, $this->gazaCity->id);
            $this->assertPasses($input, $this->nablus->id, $this->gazaCity->id, 'A legacy pair can be cleared.');
            $this->assertPasses($input, $this->closedGovernorate->id, $this->closedCity->id, 'Inactive values can be cleared.');
        }
    }

    // ---------------------------------------------------------------- C, D: changing one field

    public function test_c_only_the_governorate_changes(): void
    {
        // With no stored city, any active governorate can be chosen.
        $this->assertPasses(['governorate_id' => $this->nablus->id], $this->gaza->id, null);
        $this->assertPasses(['governorate_id' => (string) $this->nablus->id], null, null, 'A digit string, as forms send it.');

        // The retained city must belong to the new governorate: never a silently inconsistent pair.
        $this->assertFailsOn(['governorate_id' => $this->nablus->id], $this->gaza->id, $this->gazaCity->id, ['city_id' => 'mismatch']);
        $this->assertFailsOn(['governorate_id' => $this->gaza->id], null, $this->orphanCity->id, ['city_id' => 'mismatch'], 'A legacy orphan city does not belong to the new governorate.');

        // A legacy mismatched pair can be repaired by choosing the city's governorate.
        $this->assertPasses(['governorate_id' => $this->gaza->id], $this->nablus->id, $this->gazaCity->id);
        $this->assertPasses(['governorate_id' => $this->gaza->id], $this->nablus->id, $this->closedCity->id, 'The retained city is not newly selected, so it may stay inactive.');
    }

    public function test_d_only_the_city_changes(): void
    {
        $this->assertPasses(['city_id' => $this->rafah->id], $this->gaza->id, $this->gazaCity->id);
        $this->assertPasses(['city_id' => (string) $this->rafah->id], $this->gaza->id, null, 'A city added under the stored governorate.');

        $this->assertFailsOn(['city_id' => $this->nablusCity->id], $this->gaza->id, $this->gazaCity->id, ['city_id' => 'mismatch']);
        $this->assertFailsOn(['city_id' => $this->gazaCity->id], null, null, ['governorate_id' => 'governorate_missing'], 'A city without a governorate.');
        $this->assertFailsOn(['city_id' => $this->gazaCity->id], null, $this->orphanCity->id, ['governorate_id' => 'governorate_missing']);

        // The retained governorate is not newly selected, so it may stay inactive.
        $closedTown = City::query()->create(['governorate_id' => $this->closedGovernorate->id, 'name' => 'Closed town', 'slug' => 'closed-town']);
        $this->assertPasses(['city_id' => $closedTown->id], $this->closedGovernorate->id, null);
    }

    // ---------------------------------------------------------------- E, F: clearing one field

    public function test_e_the_city_is_cleared_while_the_governorate_is_kept(): void
    {
        foreach ([null, ''] as $empty) {
            $this->assertPasses(['city_id' => $empty], $this->gaza->id, $this->gazaCity->id);
            $this->assertPasses(['city_id' => $empty], $this->nablus->id, $this->gazaCity->id, 'Clearing the city repairs a legacy pair.');
            $this->assertPasses(['city_id' => $empty], $this->closedGovernorate->id, $this->closedCity->id, 'The retained governorate may stay inactive.');
            $this->assertPasses(['governorate_id' => $this->gaza->id, 'city_id' => $empty], $this->gaza->id, $this->gazaCity->id, 'Both sent, as a form does.');
        }
    }

    public function test_f_the_governorate_is_cleared_while_the_city_is_kept(): void
    {
        foreach ([null, ''] as $empty) {
            $this->assertFailsOn(['governorate_id' => $empty], $this->gaza->id, $this->gazaCity->id, ['governorate_id' => 'governorate_missing']);
            $this->assertFailsOn(['governorate_id' => $empty, 'city_id' => $this->gazaCity->id], $this->gaza->id, $this->gazaCity->id, ['governorate_id' => 'governorate_missing']);
            $this->assertFailsOn(['governorate_id' => $empty], $this->nablus->id, $this->gazaCity->id, ['governorate_id' => 'governorate_missing'], 'A legacy pair either.');
        }
    }

    // ---------------------------------------------------------------- G, H: unchanged legacy and inactive values

    public function test_g_a_legacy_pair_survives_unrelated_updates_and_resubmission(): void
    {
        foreach ([[$this->nablus->id, $this->gazaCity->id], [null, $this->orphanCity->id], [$this->gaza->id, $this->orphanCity->id]] as [$governorate, $city]) {
            $this->assertPasses(['label' => 'Work', 'phone' => '0599000000'], $governorate, $city, 'Omitted.');
            $this->assertPasses(['label' => 'Work', 'governorate_id' => $governorate, 'city_id' => $city], $governorate, $city, 'Sent back as stored.');
            $this->assertPasses(['governorate_id' => $governorate === null ? '' : (string) $governorate, 'city_id' => (string) $city], $governorate, $city, 'Sent back as form strings.');
        }
    }

    public function test_h_unchanged_inactive_values_are_kept(): void
    {
        $this->assertPasses(['governorate_id' => $this->closedGovernorate->id], $this->closedGovernorate->id, null);
        $this->assertPasses(['governorate_id' => $this->gaza->id, 'city_id' => $this->closedCity->id], $this->gaza->id, $this->closedCity->id);
        $this->assertPasses(['city_id' => $this->closedCity->id], $this->closedGovernorate->id, $this->closedCity->id, 'Inactive and mismatched, but unchanged.');
    }

    // ---------------------------------------------------------------- I, J: newly selected values

    public function test_i_newly_selected_inactive_values_are_refused(): void
    {
        $this->assertFailsOn(['governorate_id' => $this->closedGovernorate->id], null, null, ['governorate_id' => 'governorate_inactive']);
        $this->assertFailsOn(['governorate_id' => $this->closedGovernorate->id], $this->gaza->id, null, ['governorate_id' => 'governorate_inactive']);
        $this->assertFailsOn(['governorate_id' => $this->gaza->id, 'city_id' => $this->closedCity->id], null, null, ['city_id' => 'city_inactive']);
        $this->assertFailsOn(['city_id' => $this->closedCity->id], $this->gaza->id, $this->gazaCity->id, ['city_id' => 'city_inactive']);

        $closedTown = City::query()->create(['governorate_id' => $this->closedGovernorate->id, 'name' => 'Closed town', 'slug' => 'closed-town', 'status' => 'inactive']);
        $this->assertFailsOn(['governorate_id' => $this->closedGovernorate->id, 'city_id' => $closedTown->id], null, null, ['governorate_id' => 'governorate_inactive', 'city_id' => 'city_inactive'], 'Each field reports its own.');
    }

    public function test_j_a_newly_selected_pair_must_match(): void
    {
        $this->assertPasses(['governorate_id' => $this->gaza->id, 'city_id' => $this->gazaCity->id], null, null);
        $this->assertPasses(['governorate_id' => $this->nablus->id, 'city_id' => $this->nablusCity->id], $this->gaza->id, $this->gazaCity->id);
        $this->assertPasses(['governorate_id' => $this->nablus->id], null, null, 'A governorate without a city.');

        $this->assertFailsOn(['governorate_id' => $this->nablus->id, 'city_id' => $this->gazaCity->id], null, null, ['city_id' => 'mismatch']);
        $this->assertFailsOn(['governorate_id' => $this->nablus->id, 'city_id' => $this->rafah->id], $this->gaza->id, $this->gazaCity->id, ['city_id' => 'mismatch']);
        $this->assertFailsOn(['governorate_id' => $this->gaza->id, 'city_id' => $this->orphanCity->id], null, null, ['city_id' => 'mismatch'], 'A city with no governorate belongs to none.');
        $this->assertFailsOn(['governorate_id' => '', 'city_id' => $this->gazaCity->id], null, null, ['governorate_id' => 'governorate_missing']);
        $this->assertFailsOn(['governorate_id' => null, 'city_id' => $this->orphanCity->id], null, null, ['governorate_id' => 'governorate_missing']);
    }

    // ---------------------------------------------------------------- invalid and unknown ids

    public function test_unknown_ids_are_refused_on_their_own_field(): void
    {
        $this->assertFailsOn(['governorate_id' => 999], null, null, ['governorate_id' => 'governorate_invalid']);
        $this->assertFailsOn(['city_id' => 999], $this->gaza->id, null, ['city_id' => 'city_invalid']);
        $this->assertFailsOn(['governorate_id' => 999, 'city_id' => 998], null, null, ['governorate_id' => 'governorate_invalid', 'city_id' => 'city_invalid'], 'No mismatch on top.');
        $this->assertFailsOn(['governorate_id' => 999], $this->gaza->id, $this->gazaCity->id, ['governorate_id' => 'governorate_invalid'], 'No mismatch on top.');
        $this->assertFailsOn(['city_id' => 999], null, null, ['city_id' => 'city_invalid'], 'Not also a missing governorate.');
    }

    public function test_malformed_values_are_refused_on_their_own_field(): void
    {
        foreach (['abc', '1.5', 1.5, '-1', -1, 0, '0', ' 1', true, [1]] as $value) {
            $label = var_export($value, true);
            $this->assertFailsOn(['governorate_id' => $value], null, null, ['governorate_id' => 'governorate_invalid'], $label);
            $this->assertFailsOn(['city_id' => $value], $this->gaza->id, null, ['city_id' => 'city_invalid'], $label);
            $this->assertFailsOn(['governorate_id' => $value, 'city_id' => $this->nablusCity->id], $this->gaza->id, $this->gazaCity->id, ['governorate_id' => 'governorate_invalid'], "{$label}: the valid city is not also reported.");
        }
    }

    public function test_no_error_is_added_when_the_basic_rules_already_failed(): void
    {
        $rules = ['governorate_id' => ['nullable', 'integer', 'exists:governorates,id'], 'city_id' => ['nullable', 'integer', 'exists:cities,id']];

        foreach ([
            'malformed governorate' => [['governorate_id' => 'abc', 'city_id' => $this->nablusCity->id], ['governorate_id']],
            'unknown governorate' => [['governorate_id' => 999, 'city_id' => $this->nablusCity->id], ['governorate_id']],
            'malformed city' => [['governorate_id' => $this->closedGovernorate->id, 'city_id' => 'abc'], ['city_id']],
            'unknown city' => [['city_id' => 999], ['city_id']],
            'both unknown' => [['governorate_id' => 999, 'city_id' => 998], ['governorate_id', 'city_id']],
        ] as $label => [$input, $fields]) {
            $errors = $this->errors($input, $this->gaza->id, $this->gazaCity->id, $rules);

            $this->assertSame($fields, array_keys($errors), $label);
            foreach ($errors as $field => $messages) {
                $this->assertCount(1, $messages, "{$label}: one message on {$field}.");
                $this->assertNotContains($messages[0], self::MESSAGES, "{$label}: the basic rule's message only.");
            }
        }

        // With the basic rules passing, the pair is still checked.
        $this->assertSame(['city_id' => [self::MESSAGES['mismatch']]], $this->errors(['governorate_id' => $this->nablus->id], $this->gaza->id, $this->gazaCity->id, $rules));
    }

    public function test_other_fields_errors_do_not_stop_the_pair_check(): void
    {
        $errors = $this->errors(['label' => '', 'governorate_id' => $this->nablus->id, 'city_id' => $this->gazaCity->id], null, null, ['label' => ['required']]);

        $this->assertSame(['label', 'city_id'], array_keys($errors));
        $this->assertSame([self::MESSAGES['mismatch']], $errors['city_id']);
    }

    // ---------------------------------------------------------------- messages, resolution, no writes

    public function test_messages_can_be_replaced_by_key(): void
    {
        $validator = Validator::make([], []);
        $validator->after(fn ($validator) => LocationPair::validate($validator, ['governorate_id' => $this->nablus->id, 'city_id' => $this->gazaCity->id], null, null, ['mismatch' => 'المدينة لا تتبع المحافظة.']));

        $this->assertFalse($validator->passes());
        $this->assertSame(['city_id' => ['المدينة لا تتبع المحافظة.']], $validator->errors()->toArray());
        $this->assertFailsOn(['city_id' => $this->gazaCity->id], null, null, ['governorate_id' => 'governorate_missing'], 'Other messages keep their defaults.');
    }

    public function test_the_effective_value_distinguishes_omitted_from_cleared(): void
    {
        $this->assertSame(5, LocationPair::effective([], 'city_id', 5), 'Omitted: the stored value.');
        $this->assertNull(LocationPair::effective([], 'city_id', null));
        $this->assertNull(LocationPair::effective(['city_id' => null], 'city_id', 5), 'Explicit null clears.');
        $this->assertSame('', LocationPair::effective(['city_id' => ''], 'city_id', 5), 'Explicit empty clears.');
        $this->assertSame('7', LocationPair::effective(['city_id' => '7'], 'city_id', 5), 'Supplied: the new value.');
        $this->assertSame(5, LocationPair::effective(['governorate_id' => 9], 'city_id', 5), 'Only the named field counts.');
    }

    public function test_validation_writes_nothing(): void
    {
        $before = [Governorate::query()->orderBy('id')->get()->toArray(), City::query()->orderBy('id')->get()->toArray()];
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $this->errors(['label' => 'Home'], $this->nablus->id, $this->gazaCity->id);
        $this->errors(['governorate_id' => $this->nablus->id], $this->gaza->id, $this->gazaCity->id);
        $this->errors(['governorate_id' => '', 'city_id' => ''], $this->closedGovernorate->id, $this->closedCity->id);
        $this->errors(['governorate_id' => $this->closedGovernorate->id, 'city_id' => $this->orphanCity->id], null, null);
        $this->errors(['governorate_id' => $this->gaza->id], null, $this->orphanCity->id);

        $this->assertNotEmpty($statements);
        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $sql);
        }
        $this->assertSame($before, [Governorate::query()->orderBy('id')->get()->toArray(), City::query()->orderBy('id')->get()->toArray()]);
    }

    // ---------------------------------------------------------------- helpers

    private function assertPasses(array $input, ?int $storedGovernorateId, ?int $storedCityId, string $message = ''): void
    {
        $this->assertSame([], $this->errors($input, $storedGovernorateId, $storedCityId), $message);
    }

    /** @param  array<string, string>  $expected  field => message key */
    private function assertFailsOn(array $input, ?int $storedGovernorateId, ?int $storedCityId, array $expected, string $message = ''): void
    {
        $this->assertSame(
            array_map(fn (string $key) => [self::MESSAGES[$key]], $expected),
            $this->errors($input, $storedGovernorateId, $storedCityId),
            $message,
        );
    }

    private function errors(array $input, ?int $storedGovernorateId, ?int $storedCityId, array $rules = []): array
    {
        $validator = Validator::make($input, $rules);
        $validator->after(fn ($validator) => LocationPair::validate($validator, $input, $storedGovernorateId, $storedCityId));
        $validator->passes();

        return $validator->errors()->toArray();
    }
}
