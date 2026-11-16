// H4-M: the customer address location rules (Governorate → City). Run by Tests\Feature\CustomerAddressesPageTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { cityAfterGovernorateChange, cityBelongs, cityDisabled, cityOptionValues, locationModel, locationState } from '../../resources/js/storefront-address-locations.js';

// The active cities of each active governorate, as the optgroups give them: Gaza (1) holds 10 and 11, Nablus (2) holds
// 20, Empty (3) has none.
const GROUPS = [
    { governorateId: '1', cityIds: ['10', '11'] },
    { governorateId: '2', cityIds: ['20'] },
    { governorateId: '3', cityIds: [] },
];
const MODEL = locationModel(GROUPS);

test('a new address: no governorate, no city, the city disabled', () => {
    const state = locationState(MODEL, { governorateId: '', cityId: '' });

    assert.deepEqual(state.view(), { governorateId: '', cityId: '', cityOptions: [''], cityDisabled: true, sendEmptyCity: true });
});

test('a governorate shows only its own active cities, none chosen for the customer', () => {
    const state = locationState(MODEL, { governorateId: '', cityId: '' });

    assert.deepEqual(state.governorateChanged('1'), { governorateId: '1', cityId: '', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.governorateChanged('2').cityOptions, ['', '20']);
    assert.equal(state.view().cityId, '', 'Never the first city.');
    assert.deepEqual(state.governorateChanged('3'), { governorateId: '3', cityId: '', cityOptions: [''], cityDisabled: false, sendEmptyCity: false });
});

test('the city is optional: chosen, then cleared, with the governorate kept', () => {
    const state = locationState(MODEL, { governorateId: '', cityId: '' });
    state.governorateChanged('1');

    assert.deepEqual(state.cityChanged('11'), { governorateId: '1', cityId: '11', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.cityChanged(''), { governorateId: '1', cityId: '', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false });
});

test('clearing the governorate clears the city and disables it, sending an empty city', () => {
    const state = locationState(MODEL, { governorateId: '1', cityId: '10' });

    assert.deepEqual(state.governorateChanged(''), { governorateId: '', cityId: '', cityOptions: [''], cityDisabled: true, sendEmptyCity: true });
});

test('switching the governorate clears an incompatible city only', () => {
    const state = locationState(MODEL, { governorateId: '1', cityId: '10' });

    assert.deepEqual(state.governorateChanged('2'), { governorateId: '2', cityId: '', cityOptions: ['', '20'], cityDisabled: false, sendEmptyCity: false });
    state.cityChanged('20');
    assert.equal(state.governorateChanged('2').cityId, '20', 'The same governorate keeps its city.');
    assert.equal(cityAfterGovernorateChange(MODEL, '1', '10'), '10');
    assert.equal(cityAfterGovernorateChange(MODEL, '2', '10'), '');
    assert.equal(cityAfterGovernorateChange(MODEL, '', '10'), '');
    assert.equal(cityAfterGovernorateChange(MODEL, '1', ''), '');
});

test('a stored active pair opens as it is', () => {
    const state = locationState(MODEL, { governorateId: '1', cityId: '11' });

    assert.deepEqual(state.view(), { governorateId: '1', cityId: '11', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false });
});

test('a stored inactive city of an active governorate stays selected and can be chosen again', () => {
    const model = locationModel(GROUPS, { cityId: '12', governorateId: '1' });
    const state = locationState(model, { governorateId: '1', cityId: '12' });

    assert.deepEqual(state.view(), { governorateId: '1', cityId: '12', cityOptions: ['', '12', '10', '11'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.cityChanged('10').cityOptions, ['', '12', '10', '11'], 'Still offered under its own governorate.');
    assert.equal(cityBelongs(model, '12', '1'), true);
    assert.equal(state.governorateChanged('2').cityId, '');
    assert.deepEqual(state.governorateChanged('1').cityOptions, ['', '12', '10', '11']);
});

test('a stored inactive governorate with its city stays as it is until the governorate changes', () => {
    // Governorate 9 is inactive (not among the groups); its city 90 is listed as the unavailable city.
    const model = locationModel(GROUPS, { cityId: '90', governorateId: '9' });
    const state = locationState(model, { governorateId: '9', cityId: '90' });

    assert.deepEqual(state.view(), { governorateId: '9', cityId: '90', cityOptions: ['', '90'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.governorateChanged('1'), { governorateId: '1', cityId: '', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.governorateChanged('9').cityOptions, ['', '90'], 'Its own governorate offers it again.');
});

test('a stored mismatched pair is kept, the city shown, until the governorate changes', () => {
    // City 10 (Gaza) stored under Nablus (2).
    const state = locationState(MODEL, { governorateId: '2', cityId: '10' });

    assert.deepEqual(state.view(), { governorateId: '2', cityId: '10', cityOptions: ['', '10', '20'], cityDisabled: false, sendEmptyCity: false });
    assert.deepEqual(state.governorateChanged('1'), { governorateId: '1', cityId: '10', cityOptions: ['', '10', '11'], cityDisabled: false, sendEmptyCity: false }, 'Its own governorate keeps it.');
    assert.deepEqual(state.governorateChanged('2').cityId, '', 'Back to the mismatch: cleared.');
});

test('a stored city without a governorate stays selected and submitted, never disabled', () => {
    const model = locationModel(GROUPS, { cityId: '50', governorateId: '' });
    const state = locationState(model, { governorateId: '', cityId: '50' });

    assert.deepEqual(state.view(), { governorateId: '', cityId: '50', cityOptions: ['', '50'], cityDisabled: false, sendEmptyCity: false });
    assert.equal(cityDisabled('', '50'), false);
    // Clearing it is the customer's choice: the empty city is then sent explicitly.
    assert.deepEqual(state.cityChanged(''), { governorateId: '', cityId: '', cityOptions: [''], cityDisabled: true, sendEmptyCity: true });
});

test('choosing a governorate for a stored city without one clears the city', () => {
    const model = locationModel(GROUPS, { cityId: '50', governorateId: '' });
    const state = locationState(model, { governorateId: '', cityId: '50' });

    assert.equal(state.governorateChanged('1').cityId, '');
});

test('restore() brings back the opening pair after any change (closing, resetting or reopening)', () => {
    const model = locationModel(GROUPS, { cityId: '50', governorateId: '' });
    for (const opening of [{ governorateId: '', cityId: '' }, { governorateId: '1', cityId: '10' }, { governorateId: '2', cityId: '10' }, { governorateId: '', cityId: '50' }]) {
        const state = locationState(model, opening);
        const before = state.view();

        state.governorateChanged('2');
        state.cityChanged('20');
        state.governorateChanged('');
        assert.deepEqual(state.restore(), before, JSON.stringify(opening));
        assert.deepEqual(state.restore(), before, 'Restoring twice is the same.');
    }
});

test('the selected city is always among the options', () => {
    const model = locationModel(GROUPS, { cityId: '12', governorateId: '1' });
    for (const governorateId of ['', '1', '2', '3', '9']) {
        for (const cityId of ['', '10', '11', '12', '20', '50']) {
            const options = cityOptionValues(model, governorateId, cityId);
            assert.ok(options.includes(cityId), `${governorateId}/${cityId}`);
            assert.equal(options[0], '');
            assert.equal(new Set(options).size, options.length, 'No duplicates.');
        }
    }
});

test('the model ignores malformed input', () => {
    assert.deepEqual([...locationModel(undefined).cities], []);
    assert.equal(locationModel(GROUPS, { cityId: '', governorateId: '1' }).unavailable, null);
    assert.deepEqual(locationModel([{ governorateId: 4, cityIds: [40] }]).cities.get('4'), ['40']);
});
