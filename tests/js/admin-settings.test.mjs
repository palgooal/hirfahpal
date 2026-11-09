// SETTINGS-UX-01: the settings tabs (resources/js/admin-settings.js). Run with node --test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { fillText, idFromHash, initialIndex, initSettingsGroups, initSettingsTabs, initSocialLinks, keyFromHash, moveKey, openGroupOf } from '../../resources/js/admin-settings.js';

const KEYS = ['availability', 'general', 'footer'];

test('a fragment names its tab; #site-availability opens site availability only where that tab exists', () => {
    assert.equal(keyFromHash('#site-availability', KEYS), 'availability');
    assert.equal(keyFromHash('#site-availability', ['general', 'footer']), null);
    assert.equal(keyFromHash('#settings-panel-footer', KEYS), 'footer');
    assert.equal(keyFromHash('#settings-tab-general', KEYS), 'general');
    assert.equal(keyFromHash('#settings-panel-unknown', KEYS), null);
    assert.equal(keyFromHash('', KEYS), null);
    assert.equal(keyFromHash(undefined, KEYS), null);
});

test('FOOTER-SOCIAL-02D: a suggested tab comes after the server choice and the fragment', () => {
    assert.equal(initialIndex(KEYS, '', '', 'footer'), 2);
    assert.equal(initialIndex(KEYS, 'general', '', 'footer'), 1, 'Validation errors lead.');
    assert.equal(initialIndex(KEYS, '', '#site-availability', 'footer'), 0, 'A link to a tab leads.');
    assert.equal(initialIndex(['general', 'footer'], '', '', 'unknown'), 0);
});

test('the server choice wins over the fragment, which wins over the first tab', () => {
    assert.equal(initialIndex(KEYS, 'footer', '#site-availability'), 2);
    assert.equal(initialIndex(KEYS, '', '#settings-panel-footer'), 2);
    assert.equal(initialIndex(KEYS, 'availability', ''), 0);
    assert.equal(initialIndex(['general', 'footer'], 'availability', ''), 0, 'An unknown server choice falls back.');
    assert.equal(initialIndex(KEYS, '', ''), 0);
});

function element(attributes = {}) {
    const listeners = {};
    const attrs = new Map(Object.entries(attributes));

    return {
        hidden: false,
        focused: false,
        dataset: {},
        setAttribute: (name, value) => attrs.set(name, String(value)),
        getAttribute: (name) => attrs.get(name) ?? null,
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        focus() {
            this.focused = true;
        },
        fire(type, event = {}) {
            (listeners[type] ?? []).forEach((fn) => fn({ preventDefault() { event.prevented = true; }, ...event }));
            return event;
        },
    };
}

function page({ keys = KEYS, initialTab = '' } = {}) {
    const tabs = keys.map((key) => Object.assign(element({ 'aria-controls': `settings-panel-${key}` }), { id: `settings-tab-${key}`, dataset: { settingsTab: key } }));
    const panels = Object.fromEntries(keys.map((key) => [`#settings-panel-${key}`, Object.assign(element(), { key, contains(node) { return node === this || node?.panel === this; } })]));
    const tablist = Object.assign(element(), { hidden: true, querySelectorAll: () => tabs });
    const form = Object.assign(element({ action: 'https://hirfah.test/dashboard/settings' }), {
        contains: (node) => node === panels['#settings-panel-general'] || node === panels['#settings-panel-footer'] || ['general', 'footer'].includes(node?.panel?.key),
    });
    const classes = new Set();
    const root = {
        dataset: { initialTab },
        classList: { add: (name) => classes.add(name) },
        querySelector: (selector) => ({ '[data-settings-tablist]': tablist, '[data-settings-form]': form })[selector] ?? panels[selector] ?? null,
    };
    const history = { calls: [], replaceState(...args) { this.calls.push(args[2]); } };

    return { root, tabs, panels: Object.values(panels), tablist, form, classes, history };
}

const shown = (panels) => panels.filter((panel) => !panel.hidden).map((panel) => panel.key);

test('it reveals the tab list and shows only the first panel, with the settings form hidden on site availability', () => {
    const { root, tabs, panels, tablist, form, classes } = page();
    initSettingsTabs(root);

    assert.equal(tablist.hidden, false);
    assert.ok(classes.has('is-tabbed'));
    assert.deepEqual(shown(panels), ['availability']);
    assert.equal(form.hidden, true, 'The settings messages and save button do not show under site availability.');
    assert.equal(tabs[0].getAttribute('aria-selected'), 'true');
    assert.equal(tabs[1].getAttribute('tabindex'), '-1');
    panels.forEach((panel, index) => {
        assert.equal(panel.getAttribute('role'), 'tabpanel');
        assert.equal(panel.getAttribute('aria-labelledby'), tabs[index].id);
    });
});

test('the server-named tab opens first and the form action carries it', () => {
    const { root, panels, form } = page({ initialTab: 'footer' });
    initSettingsTabs(root, { hash: '#site-availability' });

    assert.deepEqual(shown(panels), ['footer']);
    assert.equal(form.hidden, false);
    assert.equal(form.getAttribute('action'), 'https://hirfah.test/dashboard/settings#settings-panel-footer');
});

test('clicking and arrow keys select tabs in the reading direction and remember the choice', () => {
    const { root, tabs, panels, form, history } = page();
    initSettingsTabs(root, { direction: 'rtl', history });

    tabs[0].fire('click');
    assert.deepEqual(shown(panels), ['availability']);
    const event = tabs[0].fire('keydown', { key: 'ArrowLeft' });
    assert.ok(event.prevented);
    assert.deepEqual(shown(panels), ['general'], 'Left is "next" in right-to-left pages.');
    assert.ok(tabs[1].focused);
    assert.equal(form.getAttribute('action'), 'https://hirfah.test/dashboard/settings#settings-panel-general');

    tabs[1].fire('keydown', { key: 'End' });
    assert.deepEqual(shown(panels), ['footer']);
    tabs[2].fire('keydown', { key: 'Enter' });
    assert.deepEqual(shown(panels), ['footer'], 'Enter is the native button click.');
    assert.deepEqual(history.calls, ['#settings-panel-availability', '#settings-panel-general', '#settings-panel-footer']);
});

test('an invalid required field in a hidden panel opens that panel', () => {
    const { root, panels } = page({ initialTab: 'footer' });
    initSettingsTabs(root);

    const general = panels.find((panel) => panel.key === 'general');
    root.querySelector('[data-settings-form]').fire('invalid', { target: { panel: general } });

    assert.deepEqual(shown(panels), ['general']);
});

test('without the tab list or a panel the page is left as it is', () => {
    assert.equal(initSettingsTabs(null), null);

    const { root, tablist } = page();
    const broken = { ...root, querySelector: (selector) => (selector === '[data-settings-tablist]' ? tablist : null) };
    assert.equal(initSettingsTabs(broken), null);
    assert.equal(tablist.hidden, true);
});

// SETTINGS-UX-02B: fragments reached while already on the page.

test('a fragment names an element id, encoded or not', () => {
    assert.equal(idFromHash('#footer[x_url]'), 'footer[x_url]');
    assert.equal(idFromHash('#footer%5Bx_url%5D'), 'footer[x_url]');
    assert.equal(idFromHash('#site_name'), 'site_name');
    assert.equal(idFromHash('#%E0%A4%A'), '%E0%A4%A', 'A malformed escape is taken as written.');
    assert.equal(idFromHash('#'), null);
    assert.equal(idFromHash(''), null);
    assert.equal(idFromHash(undefined), null);
});

function windowFor({ panels, fields = {} }) {
    const listeners = {};
    const docListeners = {};
    const win = {
        location: { hash: '', href: 'https://hirfah.test/dashboard/settings', origin: 'https://hirfah.test', pathname: '/dashboard/settings' },
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        document: {
            getElementById: (id) => fields[id] ?? null,
            addEventListener: (type, fn) => (docListeners[type] ??= []).push(fn),
        },
        navigate(hash) {
            this.location.hash = hash;
            (listeners.hashchange ?? []).forEach((fn) => fn({}));
        },
        clickLink(href) {
            const link = { href };
            (docListeners.click ?? []).forEach((fn) => fn({ target: { closest: () => link } }));
        },
    };

    return win;
}

test('a fragment reached on the page (the header badge) opens its tab', () => {
    const { root, panels, history } = page();
    const win = windowFor({ panels });
    initSettingsTabs(root, { history, win, hash: '#settings-panel-footer' });

    assert.deepEqual(shown(panels), ['footer']);
    win.navigate('#site-availability');
    assert.deepEqual(shown(panels), ['availability']);
    win.navigate('#settings-panel-general');
    assert.deepEqual(shown(panels), ['general']);
    win.navigate('#nothing-here');
    assert.deepEqual(shown(panels), ['general'], 'An unknown fragment changes nothing.');
});

test('an error summary link opens the tab holding its field and focuses it, even for the current fragment', () => {
    const { root, panels, history } = page({ initialTab: 'general' });
    const footer = panels.find((panel) => panel.key === 'footer');
    const field = { panel: footer, focused: false, focus() { this.focused = true; } };
    const win = windowFor({ panels, fields: { 'footer[x_url]': field } });
    win.location.hash = '#footer[x_url]';
    initSettingsTabs(root, { history, win });

    assert.deepEqual(shown(panels), ['general'], 'The server choice wins on load.');
    win.clickLink('https://hirfah.test/dashboard/settings#footer[x_url]');
    assert.deepEqual(shown(panels), ['footer']);
    assert.ok(field.focused);

    win.clickLink('https://elsewhere.test/dashboard/settings#site-availability');
    assert.deepEqual(shown(panels), ['footer'], 'A link to another page is left to the browser.');
});

test('the settings messages are hidden with the form while site availability is selected', () => {
    const { root, tabs, panels, form } = page();
    const area = Object.assign(element(), { hidden: false });
    const querySelector = root.querySelector;
    root.querySelector = (selector) => (selector === '[data-settings-form-area]' ? area : querySelector(selector));
    initSettingsTabs(root);

    assert.deepEqual(shown(panels), ['availability']);
    assert.equal(area.hidden, true);
    assert.equal(form.hidden, false, 'The wrapper is hidden, not the form itself.');
    tabs[1].fire('click');
    assert.equal(area.hidden, false);
});

// SETTINGS-UX-03: the footer groups (<details>).

function group(open, errors = 0) {
    return { open: true, dataset: { settingsGroupOpen: open ? 'true' : 'false', settingsGroupErrors: String(errors) } };
}

test('every group is closed except the first and those holding errors; several may stay open', () => {
    const groups = [group(true), group(false), group(false, 2), group(false), group(false, 1), group(false)];
    const root = { querySelectorAll: (selector) => (selector === '[data-settings-group]' ? groups : []) };

    assert.equal(initSettingsGroups(root).length, 6);
    assert.deepEqual(groups.map((g) => g.open), [true, false, true, false, true, false]);
    assert.deepEqual(initSettingsGroups(null), [], 'Without the page nothing happens.');
});

test('reaching a field opens its group; an open group and a field outside any group are left alone', () => {
    const details = { open: false };
    openGroupOf({ closest: (selector) => (selector === 'details' ? details : null) });
    assert.equal(details.open, true);

    openGroupOf({ closest: () => details });
    assert.equal(details.open, true);
    openGroupOf({ closest: () => null });
    openGroupOf(null);
});

test('an error summary link opens the tab and the closed group of its field, then focuses it', () => {
    const { root, panels, history } = page({ initialTab: 'general' });
    const footer = panels.find((panel) => panel.key === 'footer');
    const details = { open: false };
    const field = { panel: footer, focused: false, closest: () => details, focus() { this.focused = details.open; } };
    const win = windowFor({ panels, fields: { 'footer[x_url]': field } });
    initSettingsTabs(root, { history, win });

    win.clickLink('https://hirfah.test/dashboard/settings#footer[x_url]');
    assert.deepEqual(shown(panels), ['footer']);
    assert.equal(details.open, true);
    assert.ok(field.focused, 'Focused once its group is open.');
});

test('a field the browser rejects opens its group as well as its tab', () => {
    const { root, panels } = page({ initialTab: 'footer' });
    initSettingsTabs(root);
    const general = panels.find((panel) => panel.key === 'general');
    const details = { open: false };

    root.querySelector('[data-settings-form]').fire('invalid', { target: { panel: general, closest: () => details } });

    assert.deepEqual(shown(panels), ['general']);
    assert.equal(details.open, true);
});

// FOOTER-SOCIAL-02B: the social links manager.

test('moving a platform swaps it with its neighbour and stops at either end', () => {
    assert.deepEqual(moveKey(['a', 'b', 'c'], 'b', 'up'), ['b', 'a', 'c']);
    assert.deepEqual(moveKey(['a', 'b', 'c'], 'b', 'down'), ['a', 'c', 'b']);
    assert.deepEqual(moveKey(['a', 'b', 'c'], 'a', 'up'), ['a', 'b', 'c']);
    assert.deepEqual(moveKey(['a', 'b', 'c'], 'c', 'down'), ['a', 'b', 'c']);
    assert.deepEqual(moveKey(['a'], 'z', 'up'), ['a']);
    assert.equal(fillText(':platform is now in position :position of :count.', { platform: 'X', position: 2, count: 3 }), 'X is now in position 2 of 3.');
    assert.equal(fillText(undefined, {}), '');
});

function control(extra = {}) {
    const listeners = {};

    return {
        hidden: false, disabled: false, focused: false, value: '', checked: false,
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        focus() { this.focused = true; },
        click() { (listeners.click ?? []).forEach((fn) => fn({})); },
        ...extra,
    };
}

function socialPage(configured = ['instagram', 'x'], all = ['instagram', 'x', 'facebook', 'whatsapp']) {
    const rows = all.map((key) => {
        const parts = {
            '[data-social-move="up"]': control(), '[data-social-move="down"]': control(), '[data-social-remove]': control(),
            '[data-social-position]': control(), '[data-social-url]': control({ value: configured.includes(key) ? `https://${key}.com/a` : '' }),
            '[data-social-enabled]': control({ checked: false }), '[data-social-order-field]': control({ querySelector: () => null }),
            '[data-social-actions]': control({ hidden: true }),
        };

        return { hidden: false, dataset: { socialRow: key, socialConfigured: configured.includes(key) ? '1' : '0', socialName: key.toUpperCase() }, parts, querySelector: (selector) => parts[selector] ?? null };
    });
    const order = [...rows];
    const list = {
        querySelectorAll: () => rows,
        appendChild(row) { order.splice(order.indexOf(row), 1); order.push(row); },
    };
    const select = control({ options: [{ value: '' }, ...all.map((value) => ({ value, hidden: false, disabled: false }))] });
    const addButton = control();
    const addBox = control({ hidden: true });
    const empty = control({ hidden: true });
    const status = { textContent: '' };
    const root = {
        dataset: { textMoved: ':platform :position/:count', textAdded: ':platform added', textRemoved: ':platform removed' },
        querySelector: (selector) => ({ '[data-social-list]': list, '[data-social-add-select]': select, '[data-social-add-button]': addButton,
            '[data-social-add]': addBox, '[data-social-empty]': empty, '[data-social-status]': status })[selector] ?? null,
    };

    return { root, rows, order, select, addButton, addBox, empty, status, row: (key) => rows.find((r) => r.dataset.socialRow === key) };
}

const visible = (page) => page.order.filter((row) => !row.hidden).map((row) => row.dataset.socialRow);
const positions = (page) => page.order.map((row) => `${row.dataset.socialRow}:${row.parts['[data-social-position]'].value}`);

test('only the added platforms are shown, with buttons; the order fields are hidden and numbered', () => {
    const page = socialPage();
    initSocialLinks(page.root);

    assert.deepEqual(visible(page), ['instagram', 'x']);
    assert.deepEqual(positions(page), ['instagram:1', 'x:2', 'facebook:3', 'whatsapp:4']);
    assert.equal(page.row('instagram').parts['[data-social-actions]'].hidden, false);
    assert.equal(page.row('instagram').parts['[data-social-order-field]'].hidden, true);
    assert.equal(page.row('instagram').parts['[data-social-move="up"]'].disabled, true, 'First: cannot move up.');
    assert.equal(page.row('x').parts['[data-social-move="down"]'].disabled, true, 'Last: cannot move down.');
    assert.equal(page.addBox.hidden, false);
    assert.equal(page.empty.hidden, true);
    assert.deepEqual(page.select.options.filter((o) => o.value && !o.disabled).map((o) => o.value), ['facebook', 'whatsapp'], 'Only platforms not added yet can be added.');
});

test('move up and down reorder, renumber, keep focus on a usable button and announce the new position', () => {
    const page = socialPage(['instagram', 'x', 'facebook']);
    initSocialLinks(page.root);

    page.row('x').parts['[data-social-move="up"]'].click();
    assert.deepEqual(visible(page), ['x', 'instagram', 'facebook']);
    assert.deepEqual(positions(page).slice(0, 3), ['x:1', 'instagram:2', 'facebook:3']);
    assert.equal(page.row('x').parts['[data-social-move="up"]'].disabled, true);
    assert.ok(page.row('x').parts['[data-social-move="down"]'].focused, 'At the top: focus goes to Move down.');
    assert.equal(page.status.textContent, 'X 1/3');

    page.row('instagram').parts['[data-social-move="down"]'].click();
    assert.deepEqual(visible(page), ['x', 'facebook', 'instagram']);
    assert.equal(page.status.textContent, 'INSTAGRAM 3/3');
});

test('remove clears the address, hides the row, offers the platform again and moves focus to the add control', () => {
    const page = socialPage();
    initSocialLinks(page.root);

    page.row('instagram').parts['[data-social-remove]'].click();
    assert.deepEqual(visible(page), ['x']);
    assert.equal(page.row('instagram').parts['[data-social-url]'].value, '', 'A platform without an address is not saved.');
    assert.equal(page.row('instagram').dataset.socialConfigured, '0');
    assert.equal(page.select.options.find((o) => o.value === 'instagram').disabled, false);
    assert.ok(page.select.focused);
    assert.equal(page.status.textContent, 'INSTAGRAM removed');
    assert.deepEqual(positions(page)[0], 'x:1');

    page.row('x').parts['[data-social-remove]'].click();
    assert.deepEqual(visible(page), []);
    assert.equal(page.empty.hidden, false, 'Nothing added: the empty message shows.');
});

test('add shows the platform last, enabled, focuses its address and announces it; nothing when none is chosen', () => {
    const page = socialPage();
    initSocialLinks(page.root);

    page.addButton.click();
    assert.deepEqual(visible(page), ['instagram', 'x'], 'No platform chosen: nothing happens.');

    page.select.value = 'whatsapp';
    page.addButton.click();
    assert.deepEqual(visible(page), ['instagram', 'x', 'whatsapp']);
    assert.equal(page.row('whatsapp').parts['[data-social-enabled]'].checked, true);
    assert.ok(page.row('whatsapp').parts['[data-social-url]'].focused);
    assert.equal(page.status.textContent, 'WHATSAPP added');
    assert.equal(page.select.value, '', 'The added platform is no longer offered.');
    assert.equal(page.row('x').parts['[data-social-move="down"]'].disabled, false);

    page.select.value = 'facebook';
    page.addButton.click();
    assert.equal(page.addBox.hidden, true, 'Every platform added: the add control is hidden.');
});

test('an order field holding an error stays in view; without the list the manager does nothing', () => {
    const page = socialPage();
    page.row('x').parts['[data-social-order-field]'].querySelector = (selector) => (selector === '.is-invalid' ? {} : null);
    initSocialLinks(page.root);

    assert.equal(page.row('x').parts['[data-social-order-field]'].hidden, false);
    assert.equal(page.row('instagram').parts['[data-social-order-field]'].hidden, true);
    assert.equal(initSocialLinks(null), null);
    assert.equal(initSocialLinks({ querySelector: () => null }), null);
});
