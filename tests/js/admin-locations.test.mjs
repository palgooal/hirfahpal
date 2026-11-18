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
 * The page as locations.blade.php renders it for an editor (Governorate → City): two governorates (Gaza with Rimal and
 * Shati, Nablus) and an unassigned city (Legacy). The governorate dialog has one name; the cities dialog a governorate
 * select and city rows. serverOpen opens a dialog as the server does; invalid marks a field invalid.
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

    const govName = make(element, { id: 'location-governorate-name' });
    const govSubmitLabel = make(element, { text: 'Add governorate' });
    const govSubmit = make(element, { dataset: { saving: 'Saving…' } });
    govSubmit.q.span = [govSubmitLabel];
    const govForm = make(element);
    govForm.q['button[type="submit"]'] = [govSubmit];
    const govClose = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const govCancel = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const govDialog = make(dialogElement, { id: 'location-governorate-dialog' }, { open: serverOpen === 'governorate' });

    const citySelect = make(selectElement, { id: 'location-city-governorate', value: serverOpen === 'city' && invalid !== 'governorate_id' ? '1' : '' }, ['', '1', '2']);
    const cityName = make(element, { id: 'location-city-name', value: serverOpen === 'city' ? 'Rimal' : '' });
    const citySubmitLabel = make(element, { text: 'Add city' });
    const citySubmit = make(element, { dataset: { saving: 'Saving…' } });
    citySubmit.q.span = [citySubmitLabel];
    const cityForm = make(element);
    cityForm.q['button[type="submit"]'] = [citySubmit];
    const cityClose = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const cityCancel = make(element, { attributes: { href: '/dashboard/settings/locations' } });
    const cityDialog = make(dialogElement, { id: 'location-city-dialog' }, { open: serverOpen === 'city' });

    // The extra city rows: the add-row button inside its field, the rows container and the row template. A cloned
    // row is an Element (the browser's class; a stand-in here) holding one input.
    globalThis.Element ??= class {};
    const rowInput = make(element);
    const row = Object.assign(Object.create(globalThis.Element.prototype), make(element));
    row.q = { input: [rowInput] };
    row.querySelector = (selector) => row.q[selector]?.[0] ?? null;
    const cityInputs = make(element);
    cityInputs.inserted = [];
    cityInputs.insertBefore = (added, before) => cityInputs.inserted.push([added, before]);
    const rowField = make(element);
    rowField.matches = ['.admin-cat-form-field'];
    rowField.q = { '[data-city-inputs]': [cityInputs], 'template[data-city-row-template]': [{ content: { firstElementChild: { cloneNode: () => row } } }] };
    const addRow = make(element);
    addRow.parent = rowField;

    if (!dialogs) {
        delete govDialog.showModal;
        delete cityDialog.showModal;
    }

    const invalidFields = { name: cityName, governorate_id: citySelect, 'governorate-name': govName };
    govDialog.q = {
        '[data-location-close]': [govClose, govCancel],
        'select, input:not([type="hidden"])': [govName],
        '[aria-invalid="true"]': invalid === 'governorate-name' ? [govName] : [],
        'select[name="governorate_id"]': [],
    };
    cityDialog.q = {
        '[data-location-close]': [cityClose, cityCancel],
        'select, input:not([type="hidden"])': [citySelect, cityName],
        '[aria-invalid="true"]': ['governorate_id', 'name'].includes(invalid) ? [invalidFields[invalid]] : [],
        'select[name="governorate_id"]': [citySelect],
    };

    const addGov = make(element, { attributes: { href: '?add=governorate#location-governorate-dialog', 'aria-controls': 'location-governorate-dialog' } });
    const addCity = make(element, { attributes: { href: '?add=city#location-city-dialog', 'aria-controls': 'location-city-dialog' } });
    const shortcutGaza = make(element, { attributes: { href: '?add=city&governorate=1', 'aria-controls': 'location-city-dialog' }, dataset: { governorateId: '1' } });
    const shortcutNablus = make(element, { attributes: { href: '?add=city&governorate=2', 'aria-controls': 'location-city-dialog' }, dataset: { governorateId: '2' } });

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
        'dialog.admin-locations-dialog': [govDialog, cityDialog],
        '[data-location-open]': [addGov, addCity, shortcutGaza, shortcutNablus],
        '.admin-locations-actions [aria-controls="location-governorate-dialog"]': [addGov],
        '.admin-locations-actions [aria-controls="location-city-dialog"]': [addCity],
        '[data-add-city-row]': [addRow],
        'form[data-location-form]': [govForm, cityForm],
        '[data-location-search]': [searchBox],
        '[data-location-group]': [gaza, nablus, unassigned],
        '[data-location-no-results]': [noResults],
        '[data-location-alert-close]': withAlert ? [alertClose] : [],
    };

    doc.ids = { 'admin-locations': page, 'location-governorate-dialog': govDialog, 'location-city-dialog': cityDialog };
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
        doc, win, page, controls, govDialog, cityDialog, govName, citySelect, cityName, govClose, govCancel, cityCancel,
        addGov, addCity, shortcutGaza, shortcutNablus, govForm, govSubmit, govSubmitLabel, cityForm, citySubmit, citySubmitLabel,
        groups: { gaza, nablus, unassigned }, noResults, input, status, searchBox, alert, alertClose, addRow, cityInputs, row, rowInput,
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

test('a group matches when its governorate or one of its cities contains the query, never across two names', () => {
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

test('the page search filters governorates and the unassigned cities, announces the count and shows the no-match state', () => {
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
    const { addGov, govDialog, govName, doc } = buildPage();

    assert.equal(addGov.getAttribute('aria-haspopup'), 'dialog');
    const event = addGov.dispatch('click', click());

    assert.equal(event.prevented, true);
    assert.equal(govDialog.modal, true);
    assert.equal(doc.active, govName);
});

test('a link opened in a new tab or window is left to the browser (the server opens the dialog there)', () => {
    const { addGov, govDialog } = buildPage();

    for (const extra of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
        assert.equal(addGov.dispatch('click', click(extra)).prevented, false, JSON.stringify(extra));
    }
    assert.equal(govDialog.open, false);
});

test('Cancel, Close and Escape close the dialog and focus returns to the link that opened it', async () => {
    const { addGov, govDialog, govCancel, govClose, doc, win } = buildPage();

    addGov.dispatch('click', click());
    assert.equal(govCancel.dispatch('click', click()).prevented, true, 'Cancel does not load the page.');
    assert.equal(govDialog.open, false);
    await tick();
    assert.equal(doc.active, addGov);

    addGov.dispatch('click', click());
    govClose.dispatch('click', click());
    await tick();
    assert.equal(govDialog.open, false);
    assert.equal(doc.active, addGov);

    // Escape: the browser closes the dialog itself.
    addGov.dispatch('click', click());
    govDialog.close();
    await tick();
    assert.equal(doc.active, addGov);
    assert.deepEqual(win.history.calls, [], 'No add link in the address: nothing to replace.');
});

test('a click on the backdrop does not close a form dialog (D4)', () => {
    const { addGov, govDialog } = buildPage();

    addGov.dispatch('click', click());
    govDialog.dispatch('click', { target: govDialog });

    assert.equal(govDialog.open, true);
    assert.equal(govDialog.listeners.click, undefined, 'No click handler on the dialog itself.');
});

test('a governorate shortcut chooses that governorate and focuses the city name; focus comes back to the shortcut', async () => {
    const { shortcutNablus, shortcutGaza, cityDialog, citySelect, cityName, doc } = buildPage();

    assert.equal(shortcutNablus.getAttribute('aria-haspopup'), 'dialog');
    shortcutNablus.dispatch('click', click());
    assert.equal(cityDialog.modal, true);
    assert.equal(citySelect.value, '2');
    assert.equal(doc.active, cityName);

    cityDialog.close();
    await tick();
    assert.equal(doc.active, shortcutNablus);

    shortcutGaza.dispatch('click', click());
    assert.equal(citySelect.value, '1');
});

test('the main city link without a chosen governorate focuses the governorate first', () => {
    const { addCity, citySelect, doc } = buildPage();

    addCity.dispatch('click', click());

    assert.equal(citySelect.value, '');
    assert.equal(doc.active, citySelect);
});

test('opening the governorate dialog leaves the cities form untouched', () => {
    const { addGov, citySelect, shortcutNablus, cityDialog } = buildPage();

    shortcutNablus.dispatch('click', click());
    cityDialog.close();
    addGov.dispatch('click', click());

    assert.equal(citySelect.value, '2', 'The choice made through the shortcut is not cleared.');
});

test('a dialog the server opened after a failed save becomes a modal on its first invalid field', async () => {
    const { cityDialog, cityName, citySelect, doc, addCity } = buildPage({ serverOpen: 'city', invalid: 'name' });

    assert.equal(cityDialog.modal, true, 'Reopened as a modal (showModal on an open dialog would throw).');
    assert.equal(doc.active, cityName);
    assert.equal(citySelect.value, '1', 'The entered values are the server\'s.');
    assert.equal(cityName.value, 'Rimal');

    // The close of the first (non-modal) opening does not move focus away.
    await tick();
    assert.equal(cityDialog.open, true);
    assert.equal(doc.active, cityName);

    cityDialog.close();
    await tick();
    assert.equal(doc.active, addCity, 'Focus goes to the page\'s add link.');
});

test('an invalid governorate choice takes focus before the city name', () => {
    const { citySelect, doc } = buildPage({ serverOpen: 'city', invalid: 'governorate_id' });

    assert.equal(doc.active, citySelect);
});

test('a failed governorate is reopened as a modal on its name', () => {
    const { govDialog, govName, cityDialog, doc } = buildPage({ serverOpen: 'governorate', invalid: 'governorate-name' });

    assert.equal(govDialog.modal, true);
    assert.equal(cityDialog.open, false);
    assert.equal(doc.active, govName);
});

test('closing a dialog opened from an add link drops the ?add=… from the address', async () => {
    const { govDialog, win } = buildPage({ serverOpen: 'governorate', url: '?add=governorate' });

    await tick(); // The close of the first (non-modal) opening, before anyone acts.
    assert.deepEqual(win.history.calls, []);
    govDialog.close();
    await tick();

    assert.deepEqual(win.history.calls, [[{ kept: true }, '/dashboard/settings/locations']]);
});

test('the add-row button adds an empty city row before itself and focuses it, keeping the typed rows', () => {
    const { addRow, cityInputs, row, rowInput, cityName, doc } = buildPage({ serverOpen: 'city' });

    addRow.dispatch('click');
    addRow.dispatch('click');

    assert.deepEqual(cityInputs.inserted, [[row, addRow], [row, addRow]]);
    assert.equal(doc.active, rowInput);
    assert.equal(cityName.value, 'Rimal', 'What was typed stays.');
});

test('without <dialog> support the links and Cancel are left to the server, the rest still works', () => {
    const { addGov, govCancel, govDialog, searchBox, govForm, govSubmitLabel, controls } = buildPage({ dialogs: false });

    assert.equal(controls.modal, false);
    assert.equal(addGov.getAttribute('aria-haspopup'), null);
    assert.equal(addGov.dispatch('click', click()).prevented, false);
    assert.equal(govCancel.dispatch('click', click()).prevented, false);
    assert.equal(govDialog.open, false);
    assert.equal(searchBox.hidden, false);

    govForm.dispatch('submit', click());
    assert.equal(govSubmitLabel.textContent, 'Saving…');
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
