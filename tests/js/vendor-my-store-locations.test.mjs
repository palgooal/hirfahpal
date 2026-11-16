// H1-J: the My Store location rules (Governorate → City). Run by Tests\Feature\VendorMyStorePageTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { cityBelongs, fillName, locationOptions, locationProblem, normalizeLocations, storedLocation, toId } from '../../resources/js/vendor-my-store-locations.js';

const TEXTS = { selectGovernorate: 'Choose a governorate', selectCity: 'Choose a city', unavailable: ':name (unavailable)' };

// Active governorates with their active cities, as the profile read gives them.
const LOCATIONS = normalizeLocations({
    governorates: [
        { id: 1, name: 'Gaza Governorate', cities: [{ id: 10, governorate_id: 1, name: 'Gaza' }, { id: 11, governorate_id: 1, name: 'Rafah' }] },
        { id: 2, name: 'Nablus Governorate', cities: [{ id: 20, governorate_id: 2, name: 'Nablus' }] },
        { id: 3, name: 'Empty Governorate', cities: [] },
    ],
});

const stored = (profile = {}) => storedLocation(profile);
const NONE = stored();
const pair = (governorateId, cityId) => ({ governorateId, cityId });
const values = (options) => options.map((option) => option.value);
const labels = (options) => options.map((option) => option.label);

test('ids are integers or null; anything else is invalid', () => {
    assert.equal(toId(null), null);
    assert.equal(toId(5), 5);
    assert.equal(toId('7'), 7);
    for (const value of ['', 'abc', '1.5', 1.5, true, {}, undefined]) {
        assert.equal(toId(value), undefined, String(value));
    }
});

test('the locations keep well-formed governorates and cities only', () => {
    const locations = normalizeLocations({
        governorates: [
            { id: '4', name: 'Hebron', cities: [{ id: '40', name: 'Dura' }, { id: 'x', name: 'Bad' }, { id: 41, name: '' }, null] },
            { id: null, name: 'No id' },
            { id: 5, name: 7 },
            { id: 6, name: 'No cities' },
        ],
    });

    assert.deepEqual(locations, [
        { id: 4, name: 'Hebron', cities: [{ id: 40, name: 'Dura' }] },
        { id: 6, name: 'No cities', cities: [] },
    ]);
    assert.deepEqual(normalizeLocations(undefined), []);
    assert.deepEqual(normalizeLocations({ governorates: 'nope' }), []);
});

test('the stored pair keeps its names and the stored city\'s own governorate', () => {
    assert.deepEqual(stored({ governorate_id: 2, city_id: 10, governorate: { name: 'Nablus Governorate' }, city: { name: 'Gaza', governorate_id: 1 } }), {
        governorateId: 2, governorateName: 'Nablus Governorate', cityId: 10, cityName: 'Gaza', cityGovernorateId: 1,
    });
    assert.deepEqual(NONE, { governorateId: null, governorateName: null, cityId: null, cityName: null, cityGovernorateId: null });
    assert.deepEqual(storedLocation(null), NONE);
    assert.equal(stored({ city_id: 9, city: { name: 'Orphan', governorate_id: null } }).cityGovernorateId, null);
});

test('governorate first: an empty option, then the active governorates; nothing preselected without a stored pair', () => {
    const options = locationOptions(LOCATIONS, NONE, pair(null, null), TEXTS);

    assert.deepEqual(options.governorates, [
        { value: '', label: 'Choose a governorate' },
        { value: '1', label: 'Gaza Governorate' },
        { value: '2', label: 'Nablus Governorate' },
        { value: '3', label: 'Empty Governorate' },
    ]);
    // No governorate chosen: no city to choose.
    assert.deepEqual(options.cities, [{ value: '', label: 'Choose a city' }]);
});

test('only the cities of the chosen governorate are offered', () => {
    assert.deepEqual(values(locationOptions(LOCATIONS, NONE, pair(1, null), TEXTS).cities), ['', '10', '11']);
    assert.deepEqual(values(locationOptions(LOCATIONS, NONE, pair(2, null), TEXTS).cities), ['', '20']);
    assert.deepEqual(values(locationOptions(LOCATIONS, NONE, pair(3, null), TEXTS).cities), ['']);
});

test('a stored active pair is offered and selectable as it is', () => {
    const saved = stored({ governorate_id: 1, city_id: 11, governorate: { name: 'Gaza Governorate' }, city: { name: 'Rafah', governorate_id: 1 } });
    const options = locationOptions(LOCATIONS, saved, pair(1, 11), TEXTS);

    assert.deepEqual(values(options.governorates), ['', '1', '2', '3']);
    assert.deepEqual(values(options.cities), ['', '10', '11'], 'No extra option.');
    assert.equal(locationProblem(LOCATIONS, saved, pair(1, 11)), null);
});

test('inactive stored values stay listed, marked unavailable, and are accepted unchanged', () => {
    const saved = stored({ governorate_id: 9, city_id: 90, governorate: { name: 'Closed Governorate' }, city: { name: 'Closed Town', governorate_id: 9 } });
    const options = locationOptions(LOCATIONS, saved, pair(9, 90), TEXTS);

    assert.deepEqual(options.governorates.slice(0, 2), [{ value: '', label: 'Choose a governorate' }, { value: '9', label: 'Closed Governorate (unavailable)' }]);
    assert.deepEqual(options.cities, [{ value: '', label: 'Choose a city' }, { value: '90', label: 'Closed Town (unavailable)' }]);
    assert.equal(locationProblem(LOCATIONS, saved, pair(9, 90)), null);

    // An inactive city of an active governorate.
    const inactiveCity = stored({ governorate_id: 1, city_id: 12, governorate: { name: 'Gaza Governorate' }, city: { name: 'Closed City', governorate_id: 1 } });
    assert.deepEqual(labels(locationOptions(LOCATIONS, inactiveCity, pair(1, 12), TEXTS).cities), ['Choose a city', 'Closed City (unavailable)', 'Gaza', 'Rafah']);
    assert.equal(locationProblem(LOCATIONS, inactiveCity, pair(1, 12)), null);
});

test('a legacy mismatched pair or a city without a governorate is kept as it is', () => {
    // Gaza (of Gaza Governorate) stored under Nablus Governorate.
    const mismatched = stored({ governorate_id: 2, city_id: 10, governorate: { name: 'Nablus Governorate' }, city: { name: 'Gaza', governorate_id: 1 } });
    assert.deepEqual(labels(locationOptions(LOCATIONS, mismatched, pair(2, 10), TEXTS).cities), ['Choose a city', 'Gaza (unavailable)', 'Nablus']);
    assert.equal(locationProblem(LOCATIONS, mismatched, pair(2, 10)), null);

    // The local data shape: a city with no governorate, and no stored governorate.
    const orphan = stored({ governorate_id: null, city_id: 1, city: { name: 'غزة', governorate_id: null } });
    const options = locationOptions(LOCATIONS, orphan, pair(null, 1), TEXTS);
    assert.deepEqual(values(options.governorates), ['', '1', '2', '3']);
    assert.deepEqual(options.cities, [{ value: '', label: 'Choose a city' }, { value: '1', label: 'غزة (unavailable)' }]);
    assert.equal(locationProblem(LOCATIONS, orphan, pair(null, 1)), null);
});

test('changing the governorate keeps the city, lists it for correction and flags the mismatch', () => {
    const saved = stored({ governorate_id: 1, city_id: 10, governorate: { name: 'Gaza Governorate' }, city: { name: 'Gaza', governorate_id: 1 } });
    const changed = pair(2, 10);
    const options = locationOptions(LOCATIONS, saved, changed, TEXTS);

    assert.deepEqual(options.cities, [{ value: '', label: 'Choose a city' }, { value: '10', label: 'Gaza (unavailable)' }, { value: '20', label: 'Nablus' }]);
    assert.equal(locationProblem(LOCATIONS, saved, changed), 'mismatch');

    // Resolved by a city of the new governorate, or explicitly no city; back to the stored pair is fine too.
    assert.equal(locationProblem(LOCATIONS, saved, pair(2, 20)), null);
    assert.equal(locationProblem(LOCATIONS, saved, pair(2, null)), null);
    assert.equal(locationProblem(LOCATIONS, saved, pair(1, 10)), null);
    assert.deepEqual(values(locationOptions(LOCATIONS, saved, pair(2, 20), TEXTS).cities), ['', '20'], 'The old city leaves the list once another is chosen.');
});

test('a city chosen under another governorate, then kept, is named and flagged', () => {
    const changed = pair(2, 11);

    assert.deepEqual(labels(locationOptions(LOCATIONS, NONE, changed, TEXTS).cities), ['Choose a city', 'Rafah (unavailable)', 'Nablus']);
    assert.equal(locationProblem(LOCATIONS, NONE, changed), 'mismatch');
});

test('the stored city stays available while its stored governorate is chosen', () => {
    const saved = stored({ governorate_id: 1, city_id: 12, governorate: { name: 'Gaza Governorate' }, city: { name: 'Closed City', governorate_id: 1 } });

    // After choosing Rafah, the inactive stored city can still be chosen back.
    assert.deepEqual(values(locationOptions(LOCATIONS, saved, pair(1, 11), TEXTS).cities), ['', '12', '10', '11']);
    // Under another governorate it is not offered.
    assert.deepEqual(values(locationOptions(LOCATIONS, saved, pair(2, null), TEXTS).cities), ['', '20']);
});

test('a city without a governorate is refused once the pair changes', () => {
    const saved = stored({ governorate_id: 1, city_id: 10, governorate: { name: 'Gaza Governorate' }, city: { name: 'Gaza', governorate_id: 1 } });

    assert.equal(locationProblem(LOCATIONS, saved, pair(null, 10)), 'governorate', 'The governorate cleared under the city.');
    assert.equal(locationProblem(LOCATIONS, NONE, pair(null, 10)), 'governorate');
    assert.equal(locationProblem(LOCATIONS, saved, pair(null, null)), null, 'Both cleared.');
    assert.equal(locationProblem(LOCATIONS, saved, pair(1, null)), null, 'The city cleared, the governorate kept.');
    assert.equal(locationProblem(LOCATIONS, NONE, pair(3, null)), null, 'A governorate without a city.');
});

test('belonging follows cities.governorate_id, the stored city included even when inactive', () => {
    const saved = stored({ governorate_id: 2, city_id: 12, city: { name: 'Closed City', governorate_id: 1 } });

    assert.equal(cityBelongs(LOCATIONS, saved, 10, 1), true);
    assert.equal(cityBelongs(LOCATIONS, saved, 10, 2), false);
    assert.equal(cityBelongs(LOCATIONS, saved, 12, 1), true, 'The stored inactive city belongs to its own governorate.');
    assert.equal(cityBelongs(LOCATIONS, saved, 12, 2), false);
    assert.equal(cityBelongs(LOCATIONS, saved, 99, 1), false, 'An unknown city belongs to none.');
    assert.equal(cityBelongs(LOCATIONS, saved, null, 1), false);
    assert.equal(cityBelongs(LOCATIONS, saved, 10, null), false);
    // Moving a legacy pair onto the stored city's own governorate repairs it.
    assert.equal(locationProblem(LOCATIONS, saved, pair(1, 12)), null);
});

test('the selection is always among the options, so a select never drops it', () => {
    const saved = stored({ governorate_id: 9, city_id: 1, governorate: { name: 'Closed Governorate' }, city: { name: 'Orphan', governorate_id: null } });

    for (const selection of [pair(9, 1), pair(1, 1), pair(2, 10), pair(null, 1), pair(3, null), pair(null, null)]) {
        const options = locationOptions(LOCATIONS, saved, selection, TEXTS);
        assert.ok(values(options.governorates).includes(selection.governorateId === null ? '' : String(selection.governorateId)), JSON.stringify(selection));
        assert.ok(values(options.cities).includes(selection.cityId === null ? '' : String(selection.cityId)), JSON.stringify(selection));
        assert.equal(new Set(values(options.cities)).size, options.cities.length, 'No duplicate option.');
    }
});

test('the unavailable label fills :name, falling back to the id', () => {
    assert.equal(fillName(':name (غير متاح)', 'غزة'), 'غزة (غير متاح)');
    assert.equal(fillName(undefined, 'x'), '');
    const nameless = stored({ governorate_id: 9, city_id: null });
    assert.equal(locationOptions(LOCATIONS, nameless, pair(9, null), TEXTS).governorates[1].label, '9 (unavailable)');
});
