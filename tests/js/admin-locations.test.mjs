// LOC-UI-7B-2: the locations page script. Run by Tests\Feature\AdminLocationsScriptTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { fillCount, filterGroups, firstField, initLocationsPage, matchesSearch, normalizeSearch } from '../../resources/js/admin-locations.js';

const tick = () => new Promise((resolve) => setTimeout(resolve, 5));

// A minimal element: attributes, listeners, focus, and children by selector (q).
function element({ id = '', attributes = {}, dataset = {}, value = '', text = '' } = {}) {
    const attrs = new Map(Object.entries(attributes));
    const listeners = {};

    return {
        id,
        attrs,
        listeners,
        dataset: { ...dataset },
        hidden: Object.hasOwn(attributes, 'hidden'),
        disabled: false,
        removed: false,
        value,
        textContent: text,
        q: {},
        parent: null,
        doc: null,
        setAttribute: (name, val) => attrs.set(name, String(val)),
        getAttribute: (name) => attrs.get(name) ?? null,
        hasAttribute: (name) => attrs.has(name),
        removeAttribute: (name) => attrs.delete(name),
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        dispatch(type, event = {}) {
            (listeners[type] ?? []).forEach((fn) => fn(event));

            return event;
        },
        querySelector(selector) {
            return this.q[selector]?.[0] ?? null;
        },
        querySelectorAll(selector) {
            return this.q[selector] ?? [];
        },
        focus() {
            this.doc.active = this;
        },
        closest(selector) {
            let node = this.parent;
            while (node && !node.matches?.includes(selector)) {
                node = node.parent;
            }

            return node;
        },
        remove() {
            this.removed = true;
        },
    };
}

// A native <dialog>: showModal() fails on an open dialog; the close event is queued, as in the browser.
function dialogElement(options, { open = false } = {}) {
    const dialog = element(options);
    dialog.open = open;
    dialog.modal = false;
    dialog.showModal = function () {
        if (this.open) {
            throw new Error('InvalidStateError: the dialog is already open.');
        }
        this.open = true;
        this.modal = true;
    };
    dialog.close = function () {
        if (!this.open) {
            return;
        }
        this.open = false;
        this.modal = false;
        setTimeout(() => this.dispatch('close'), 0);
    };

    return dialog;
}

// A <select> whose value can only be one of its options.
function selectElement(options, values) {
    const select = element(options);
    let current = options.value ?? '';
    Object.defineProperty(select, 'value', {
        get: () => current,
        set: (next) => {
            current = values.includes(String(next)) ? String(next) : '';
        },
    });

    return select;
}

function click(extra = {}) {
    return { button: 0, ctrlKey: false, metaKey: false, shiftKey: false, altKey: false, prevented: false, preventDefault() { this.prevented = true; }, ...extra };
}

/**
 * The page as locations.blade.php renders it for an editor: two cities (Gaza with Rimal and Shati, Nablus) and an
 * unassigned governorate (Legacy). serverOpen opens a dialog as the server does; invalid marks a field invalid.
 */
function buildPage({ serverOpen = null, invalid = null, dialogs = true, search = '', withAlert = true, url = '' } = {}) {
    const doc = { active: null, ids: {} };
    const all = [];
    const make = (factory, ...args) => {
        const made = factory(...args);
        made.doc = doc;
        all.push(made);

        return made;
    };

    const page = make(element, { id: 'admin-locations' });

    const cityName = make(element, { id: 'location-city-name' });
    const citySubmitLabel = make(element, { text: 'Add city' });
    const citySubmit = make(element, { dataset: { saving: 'Saving…' } });
    citySubmit.q.span = [citySubmitLabel];
    const cityForm = make(element);
    cityForm.q['button[type="submit"]'] = [citySubmit];
    const cityClose = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const cityCancel = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const cityDialog = make(dialogElement, { id: 'location-city-dialog' }, { open: serverOpen === 'city' });

    const govSelect = make(selectElement, { id: 'location-governorate-city', value: serverOpen === 'governorate' && invalid !== 'city_id' ? '1' : '' }, ['', '1', '2']);
    const govName = make(element, { id: 'location-governorate-name', value: serverOpen === 'governorate' ? 'Rimal' : '' });
    const govSubmitLabel = make(element, { text: 'Add governorate' });
    const govSubmit = make(element, { dataset: { saving: 'Saving…' } });
    govSubmit.q.span = [govSubmitLabel];
    const govForm = make(element);
    govForm.q['button[type="submit"]'] = [govSubmit];
    const govClose = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const govCancel = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const govDialog = make(dialogElement, { id: 'location-governorate-dialog' }, { open: serverOpen === 'governorate' });

    if (!dialogs) {
        delete cityDialog.showModal;
        delete govDialog.showModal;
    }

    const invalidFields = { name: govName, city_id: govSelect, 'city-name': cityName };
    cityDialog.q = {
        '[data-location-close]': [cityClose, cityCancel],
        'select, input:not([type="hidden"])': [cityName],
        '[aria-invalid="true"]': invalid === 'city-name' ? [cityName] : [],
        'select[name="city_id"]': [],
    };
    govDialog.q = {
        '[data-location-close]': [govClose, govCancel],
        'select, input:not([type="hidden"])': [govSelect, govName],
        '[aria-invalid="true"]': ['city_id', 'name'].includes(invalid) ? [invalidFields[invalid]] : [],
        'select[name="city_id"]': [govSelect],
    };

    const addCity = make(element, { attributes: { href: '?add=city#location-city-dialog', 'aria-controls': 'location-city-dialog' } });
    const addGov = make(element, { attributes: { href: '?add=governorate#location-governorate-dialog', 'aria-controls': 'location-governorate-dialog' } });
    const shortcutGaza = make(element, { attributes: { href: '?add=governorate&city=1', 'aria-controls': 'location-governorate-dialog' }, dataset: { cityId: '1' } });
    const shortcutNablus = make(element, { attributes: { href: '?add=governorate&city=2', 'aria-controls': 'location-governorate-dialog' }, dataset: { cityId: '2' } });

    const gaza = make(element, { dataset: { locationNames: 'Gaza\nRimal\nShati' } });
    const nablus = make(element, { dataset: { locationNames: 'نابلس\nالبلدة القديمة' } });
    const unassigned = make(element, { dataset: { locationNames: 'Legacy\nخان يونس' } });
    const noResults = make(element, { attributes: { hidden: '' } });
    const input = make(element, { value: search });
    const status = make(element, { dataset: { template: 'Results: :count' } });
    const searchBox = make(element, { attributes: { hidden: '' } });
    searchBox.q = { 'input[type="search"]': [input], '[role="status"]': [status] };

    const alert = make(element);
    alert.matches = ['[data-location-alert]'];
    const alertClose = make(element, { attributes: { hidden: '' } });
    alertClose.parent = alert;

    page.q = {
        'dialog.admin-locations-dialog': [cityDialog, govDialog],
        '[data-location-open]': [addCity, addGov, shortcutGaza, shortcutNablus],
        '.admin-locations-actions [aria-controls="location-city-dialog"]': [addCity],
        '.admin-locations-actions [aria-controls="location-governorate-dialog"]': [addGov],
        'form[data-location-form]': [cityForm, govForm],
        '[data-location-search]': [searchBox],
        '[data-location-group]': [gaza, nablus, unassigned],
        '[data-location-no-results]': [noResults],
        '[data-location-alert-close]': withAlert ? [alertClose] : [],
    };

    doc.ids = { 'admin-locations': page, 'location-city-dialog': cityDialog, 'location-governorate-dialog': govDialog };
    doc.getElementById = (id) => doc.ids[id] ?? null;

    const win = element();
    win.location = { search: url };
    win.history = {
        state: { kept: true },
        calls: [],
        replaceState(state, title, address) {
            this.calls.push([state, address]);
            win.location.search = '';
        },
    };

    const controls = initLocationsPage(doc, win);

    return {
        doc, win, page, controls, cityDialog, govDialog, cityName, govSelect, govName, cityClose, cityCancel, govCancel,
        addCity, addGov, shortcutGaza, shortcutNablus, cityForm, citySubmit, citySubmitLabel, govForm, govSubmit, govSubmitLabel,
        groups: { gaza, nablus, unassigned }, noResults, input, status, searchBox, alert, alertClose,
    };
}

// ---------------------------------------------------------------- search

test('the search ignores letter case, accents, harakat, tatweel and the Arabic letter variants', () => {
    assert.equal(normalizeSearch('  GAZA   City '), 'gaza city');
    assert.equal(normalizeSearch('Café'), 'cafe');
    assert.equal(normalizeSearch('أريحا'), normalizeSearch('اريحا'));
    assert.equal(normalizeSearch('إربد'), normalizeSearch('اربد'));
    assert.equal(normalizeSearch('آمنة'), normalizeSearch('امنه'), 'Alef with madda, and teh marbuta as heh.');
    assert.equal(normalizeSearch('غزّة'), normalizeSearch('غزه'), 'Shadda dropped.');
    assert.equal(normalizeSearch('مَدِينَة'), normalizeSearch('مدينه'), 'Harakat dropped.');
    assert.equal(normalizeSearch('غـــزة'), normalizeSearch('غزه'), 'Tatweel dropped.');
    assert.equal(normalizeSearch('مصطفى'), normalizeSearch('مصطفي'), 'Alef maksura as yeh.');
    assert.equal(normalizeSearch('ٱلخليل'), normalizeSearch('الخليل'), 'Alef wasla as alef.');
    assert.equal(normalizeSearch(undefined), '');
});

test('a group matches when its city or one of its governorates contains the query, never across two names', () => {
    const names = 'Gaza\nRimal\nShati';

    assert.equal(matchesSearch(names, ''), true, 'An empty query shows everything.');
    assert.equal(matchesSearch(names, '   '), true);
    assert.equal(matchesSearch(names, 'GAZ'), true);
    assert.equal(matchesSearch(names, 'rim'), true);
    assert.equal(matchesSearch(names, 'Gaza Rimal'), false, 'Two names are never read as one.');
    assert.equal(matchesSearch(names, 'nablus'), false);
    assert.equal(matchesSearch('غزة\nالرمال', 'غزه'), true);
    assert.equal(matchesSearch('غزة\nالرمال', 'الرِّمال'), true);
    assert.equal(matchesSearch(undefined, 'x'), false);
});

test('filtering hides the groups that do not match and counts the others', () => {
    const groups = [
        { dataset: { locationNames: 'Gaza\nRimal' }, hidden: false },
        { dataset: { locationNames: 'Nablus' }, hidden: false },
        { dataset: { locationNames: 'Legacy' }, hidden: false },
    ];

    assert.equal(filterGroups(groups, 'a'), 3);
    assert.equal(filterGroups(groups, 'legacy'), 1);
    assert.deepEqual(groups.map((group) => group.hidden), [true, true, false]);
    assert.equal(filterGroups(groups, ''), 3);
    assert.deepEqual(groups.map((group) => group.hidden), [false, false, false]);
    assert.equal(fillCount('Results: :count', 2), 'Results: 2');
    assert.equal(fillCount('عدد النتائج: :count', 0), 'عدد النتائج: 0');
    assert.equal(fillCount(undefined, 1), '');
});

test('the page search filters cities and the unassigned group, announces the count and shows the no-match state', () => {
    const { searchBox, input, status, noResults, groups } = buildPage();

    assert.equal(searchBox.hidden, false, 'Revealed by the script.');

    input.value = 'shati';
    input.dispatch('input');
    assert.deepEqual([groups.gaza.hidden, groups.nablus.hidden, groups.unassigned.hidden], [false, true, true]);
    assert.equal(status.textContent, 'Results: 1');
    assert.equal(noResults.hidden, true);

    input.value = 'خان';
    input.dispatch('input');
    assert.deepEqual([groups.gaza.hidden, groups.nablus.hidden, groups.unassigned.hidden], [true, true, false], 'The unassigned group is searchable.');

    input.value = 'البلده';
    input.dispatch('input');
    assert.equal(groups.nablus.hidden, false, 'Teh marbuta typed as heh.');

    input.value = 'zzz';
    input.dispatch('input');
    assert.equal(status.textContent, 'Results: 0');
    assert.equal(noResults.hidden, false);

    input.value = '';
    input.dispatch('input');
    assert.deepEqual([groups.gaza.hidden, groups.nablus.hidden, groups.unassigned.hidden], [false, false, false]);
    assert.equal(status.textContent, '', 'Nothing announced without a query.');
    assert.equal(noResults.hidden, true);
});

test('a search value restored by the browser filters at once', () => {
    const { groups, status } = buildPage({ search: 'nablus' });

    assert.equal(groups.gaza.hidden, true);
    assert.equal(status.textContent, 'Results: 0', 'Nablus is written in Arabic in this page.');
});

// ---------------------------------------------------------------- dialogs

test('an add link opens its dialog as a modal on the first field to fill, and the link is not followed', () => {
    const { addCity, cityDialog, cityName, doc } = buildPage();

    assert.equal(addCity.getAttribute('aria-haspopup'), 'dialog');
    const event = addCity.dispatch('click', click());

    assert.equal(event.prevented, true);
    assert.equal(cityDialog.modal, true);
    assert.equal(doc.active, cityName);
});

test('a link opened in a new tab or window is left to the browser (the server opens the dialog there)', () => {
    const { addCity, cityDialog } = buildPage();

    for (const extra of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
        assert.equal(addCity.dispatch('click', click(extra)).prevented, false, JSON.stringify(extra));
    }
    assert.equal(cityDialog.open, false);
});

test('Cancel, Close and Escape close the dialog and focus returns to the link that opened it', async () => {
    const { addCity, cityDialog, cityCancel, cityClose, doc, win } = buildPage();

    addCity.dispatch('click', click());
    assert.equal(cityCancel.dispatch('click', click()).prevented, true, 'Cancel does not load the page.');
    assert.equal(cityDialog.open, false);
    await tick();
    assert.equal(doc.active, addCity);

    addCity.dispatch('click', click());
    cityClose.dispatch('click', click());
    await tick();
    assert.equal(cityDialog.open, false);
    assert.equal(doc.active, addCity);

    // Escape: the browser closes the dialog itself.
    addCity.dispatch('click', click());
    cityDialog.close();
    await tick();
    assert.equal(doc.active, addCity);
    assert.deepEqual(win.history.calls, [], 'No add link in the address: nothing to replace.');
});

test('a click on the backdrop does not close a form dialog (D4)', () => {
    const { addCity, cityDialog } = buildPage();

    addCity.dispatch('click', click());
    cityDialog.dispatch('click', { target: cityDialog });

    assert.equal(cityDialog.open, true);
    assert.equal(cityDialog.listeners.click, undefined, 'No click handler on the dialog itself.');
});

test('a city shortcut chooses that city and focuses the governorate name; focus comes back to the shortcut', async () => {
    const { shortcutNablus, shortcutGaza, govDialog, govSelect, govName, doc } = buildPage();

    assert.equal(shortcutNablus.getAttribute('aria-haspopup'), 'dialog');
    shortcutNablus.dispatch('click', click());
    assert.equal(govDialog.modal, true);
    assert.equal(govSelect.value, '2');
    assert.equal(doc.active, govName);

    govDialog.close();
    await tick();
    assert.equal(doc.active, shortcutNablus);

    shortcutGaza.dispatch('click', click());
    assert.equal(govSelect.value, '1');
});

test('the main governorate link without a chosen city focuses the city first', () => {
    const { addGov, govSelect, doc } = buildPage();

    addGov.dispatch('click', click());

    assert.equal(govSelect.value, '');
    assert.equal(doc.active, govSelect);
});

test('a dialog the server opened after a failed save becomes a modal on its first invalid field', async () => {
    const { govDialog, govName, govSelect, doc, addGov } = buildPage({ serverOpen: 'governorate', invalid: 'name' });

    assert.equal(govDialog.modal, true, 'Reopened as a modal (showModal on an open dialog would throw).');
    assert.equal(doc.active, govName);
    assert.equal(govSelect.value, '1', 'The entered values are the server\'s.');
    assert.equal(govName.value, 'Rimal');

    // The close of the first (non-modal) opening does not move focus away.
    await tick();
    assert.equal(govDialog.open, true);
    assert.equal(doc.active, govName);

    govDialog.close();
    await tick();
    assert.equal(doc.active, addGov, 'Focus goes to the page\'s add link.');
});

test('an invalid city choice takes focus before the name', () => {
    const { govSelect, doc } = buildPage({ serverOpen: 'governorate', invalid: 'city_id' });

    assert.equal(doc.active, govSelect);
});

test('a failed city is reopened as a modal on its name', () => {
    const { cityDialog, cityName, govDialog, doc } = buildPage({ serverOpen: 'city', invalid: 'city-name' });

    assert.equal(cityDialog.modal, true);
    assert.equal(govDialog.open, false);
    assert.equal(doc.active, cityName);
});

test('closing a dialog opened from an add link drops the ?add=… from the address', async () => {
    const { cityDialog, win } = buildPage({ serverOpen: 'city', url: '?add=city' });

    await tick(); // The close of the first (non-modal) opening, before anyone acts.
    assert.deepEqual(win.history.calls, []);
    cityDialog.close();
    await tick();

    assert.deepEqual(win.history.calls, [[{ kept: true }, '/dashboard/settings/locations']]);
});

test('without <dialog> support the links and Cancel are left to the server, the rest still works', () => {
    const { addCity, cityCancel, cityDialog, searchBox, cityForm, citySubmitLabel, controls } = buildPage({ dialogs: false });

    assert.equal(controls.modal, false);
    assert.equal(addCity.getAttribute('aria-haspopup'), null);
    assert.equal(addCity.dispatch('click', click()).prevented, false);
    assert.equal(cityCancel.dispatch('click', click()).prevented, false);
    assert.equal(cityDialog.open, false);
    assert.equal(searchBox.hidden, false);

    cityForm.dispatch('submit', click());
    assert.equal(citySubmitLabel.textContent, 'Saving…');
});

test('a page without the locations is left alone', () => {
    assert.equal(initLocationsPage({ getElementById: () => null }, null), null);
});

// ---------------------------------------------------------------- forms

test('each form posts once and shows its saving label; the other form is not affected', async () => {
    const { cityForm, citySubmit, citySubmitLabel, govForm, govSubmitLabel } = buildPage();

    const first = cityForm.dispatch('submit', click());
    assert.equal(first.prevented, false, 'The first submit goes through.');
    assert.equal(cityForm.getAttribute('aria-busy'), 'true');
    assert.equal(citySubmit.getAttribute('aria-disabled'), 'true');
    assert.equal(citySubmitLabel.textContent, 'Saving…');
    assert.equal(citySubmit.disabled, false, 'Not disabled synchronously, so the submission is not cancelled.');
    await tick();
    assert.equal(citySubmit.disabled, true);

    assert.equal(cityForm.dispatch('submit', click()).prevented, true, 'A second submit is cancelled.');
    assert.equal(govSubmitLabel.textContent, 'Add governorate');
    assert.equal(govForm.dispatch('submit', click()).prevented, false);
});

test('a page restored from the back/forward cache can be submitted again', () => {
    const { win, cityForm, citySubmit, citySubmitLabel, govForm, govSubmitLabel } = buildPage();

    cityForm.dispatch('submit', click());
    govForm.dispatch('submit', click());
    win.dispatch('pageshow', { persisted: false });
    assert.equal(citySubmitLabel.textContent, 'Saving…', 'A normal load does not reset.');

    win.dispatch('pageshow', { persisted: true });
    assert.equal(cityForm.getAttribute('aria-busy'), null);
    assert.equal(citySubmit.getAttribute('aria-disabled'), null);
    assert.equal(citySubmit.disabled, false);
    assert.equal(citySubmitLabel.textContent, 'Add city');
    assert.equal(govSubmitLabel.textContent, 'Add governorate');
    assert.equal(cityForm.dispatch('submit', click()).prevented, false);
});

// ---------------------------------------------------------------- focus target, notices

test('the first field to fill: an invalid one, else the first empty one, else the first', () => {
    const name = { value: '' };
    const select = { value: '3' };
    const dialog = (invalid, fields) => ({
        querySelector: () => invalid,
        querySelectorAll: () => fields,
    });

    assert.equal(firstField(dialog(select, [select, name])), select);
    assert.equal(firstField(dialog(null, [select, name])), name);
    assert.equal(firstField(dialog(null, [{ value: 'x' }, select])).value, 'x');
    assert.equal(firstField(dialog(null, [])), null);
});

test('the success notice can be dismissed', () => {
    const { alert, alertClose } = buildPage();

    assert.equal(alertClose.hidden, false);
    alertClose.dispatch('click');
    assert.equal(alert.removed, true);
});
