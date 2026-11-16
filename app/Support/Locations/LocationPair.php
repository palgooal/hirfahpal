<?php

namespace App\Support\Locations;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Validation\Validator;

/**
 * A record's optional governorate and city in the approved hierarchy (a governorate holds its cities through
 * cities.governorate_id), validated as the pair the record will hold after the request: an omitted field keeps its
 * stored value, an explicit null or empty value clears it, and a supplied value replaces it.
 *
 * A pair that stays as stored is accepted as it is, so unrelated updates never fail on legacy data (a mismatched pair,
 * an inactive value, a city without a governorate). A changed pair must exist, use only active values among those newly
 * selected, never hold a city without its governorate, and the city must belong to the governorate. Missing, unknown or
 * inactive governorates are reported on governorate_id; unknown, inactive or mismatched cities on city_id. Nothing is
 * written, and nothing is added when the basic rules already failed on either field.
 */
final class LocationPair
{
    public const GOVERNORATE = 'governorate_id';

    public const CITY = 'city_id';

    /** @var array<string, string> */
    private const MESSAGES = [
        'governorate_missing' => 'A governorate is required when a city is selected.',
        'governorate_invalid' => 'The selected governorate is invalid.',
        'governorate_inactive' => 'The selected governorate is not available.',
        'city_invalid' => 'The selected city is invalid.',
        'city_inactive' => 'The selected city is not available.',
        'mismatch' => 'The selected city does not belong to the selected governorate.',
    ];

    /**
     * @param  array<string, mixed>  $input  the request input (omitted keys keep the stored values)
     * @param  array<string, string>  $messages  overrides for self::MESSAGES, by key
     */
    public static function validate(Validator $validator, array $input, ?int $storedGovernorateId, ?int $storedCityId, array $messages = []): void
    {
        if ($validator->errors()->has(self::GOVERNORATE) || $validator->errors()->has(self::CITY)) {
            return;
        }

        $messages = array_merge(self::MESSAGES, $messages);
        $governorateValue = self::effective($input, self::GOVERNORATE, $storedGovernorateId);
        $cityValue = self::effective($input, self::CITY, $storedCityId);
        $governorateId = self::normalize($governorateValue);
        $cityId = self::normalize($cityValue);
        $errors = [];

        if ($governorateId === false) {
            $errors[self::GOVERNORATE] = $messages['governorate_invalid'];
        }
        if ($cityId === false) {
            $errors[self::CITY] = $messages['city_invalid'];
        }
        if ($errors === [] && $governorateId === $storedGovernorateId && $cityId === $storedCityId) {
            return;
        }

        $governorate = is_int($governorateId) ? Governorate::query()->find($governorateId, ['id', 'status']) : null;
        $city = is_int($cityId) ? City::query()->find($cityId, ['id', 'governorate_id', 'status']) : null;

        if (is_int($governorateId)) {
            if ($governorate === null) {
                $errors[self::GOVERNORATE] = $messages['governorate_invalid'];
            } elseif ($governorateId !== $storedGovernorateId && $governorate->status !== 'active') {
                $errors[self::GOVERNORATE] = $messages['governorate_inactive'];
            }
        }
        if (is_int($cityId)) {
            if ($city === null) {
                $errors[self::CITY] = $messages['city_invalid'];
            } elseif ($cityId !== $storedCityId && $city->status !== 'active') {
                $errors[self::CITY] = $messages['city_inactive'];
            }
        }

        if ($errors === [] && $city !== null) {
            if ($governorate === null) {
                $errors[self::GOVERNORATE] = $messages['governorate_missing'];
            } elseif ((int) $city->governorate_id !== $governorate->id) {
                $errors[self::CITY] = $messages['mismatch'];
            }
        }

        foreach ($errors as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }

    /**
     * The value the record will hold: the stored one when the field is omitted, otherwise the submitted one.
     *
     * @param  array<string, mixed>  $input
     */
    public static function effective(array $input, string $field, ?int $stored): mixed
    {
        return array_key_exists($field, $input) ? $input[$field] : $stored;
    }

    /** null for an empty value, the id for a positive integer (or digit string), false for anything else. */
    private static function normalize(mixed $value): int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value > 0 ? $value : false;
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= 18 && (int) $value > 0) {
            return (int) $value;
        }

        return false;
    }
}
