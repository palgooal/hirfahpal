// AD-UI-PROD-M4: the product details tabs (resources/js/admin-product-show.js). Run with node --test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { indexFromHash, initProductTabs, nextIndex } from '../../resources/js/admin-product-show.js';

test('arrow keys follow the reading direction and wrap; Home and End jump; other keys do nothing', () => {
    assert.equal(nextIndex('ArrowRight', 0, 5, 'ltr'), 1);
    assert.equal(nextIndex('ArrowLeft', 0, 5, 'ltr'), 4);
    assert.equal(nextIndex('ArrowLeft', 0, 5, 'rtl'), 1, 'Left is "next" in right-to-left pages.');
    assert.equal(nextIndex('ArrowRight', 0, 5, 'rtl'), 4);
    assert.equal(nextIndex('ArrowRight', 4, 5, 'ltr'), 0);
    assert.equal(nextIndex('Home', 3, 5), 0);
    assert.equal(nextIndex('End', 1, 5), 4);
    assert.equal(nextIndex('Enter', 1, 5), null, 'Enter and Space are the native button click.');
    assert.equal(nextIndex('ArrowDown', 1, 5), null);
    assert.equal(nextIndex('ArrowRight', 0, 0), null);
});

test('a fragment selects its tab; anything else selects the first', () => {
    const keys = ['info', 'images', 'stock', 'moderation', 'log'];
    assert.equal(indexFromHash('#product-panel-log', keys), 4);
    assert.equal(indexFromHash('#product-tab-stock', keys), 2);
    assert.equal(indexFromHash('#product-panel-unknown', keys), 0);
    assert.equal(indexFromHash('#other', keys), 0);
    assert.equal(indexFromHash('', keys), 0);
    assert.equal(indexFromHash(undefined, keys), 0);
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

function page(keys = ['info', 'images', 'stock', 'moderation', 'log']) {
    const tabs = keys.map((key) => Object.assign(element({ 'aria-controls': `product-panel-${key}` }), { id: `product-tab-${key}`, dataset: { productTab: key } }));
    const panels = Object.fromEntries(keys.map((key) => [`#product-panel-${key}`, element()]));
    const tablist = Object.assign(element(), { hidden: true, querySelectorAll: () => tabs });
    const classes = new Set();
    const root = {
        classList: { add: (name) => classes.add(name) },
        querySelector: (selector) => (selector === '[data-product-tablist]' ? tablist : panels[selector] ?? null),
    };

    return { root, tabs, panels: keys.map((key) => panels[`#product-panel-${key}`]), tablist, classes };
}

test('init reveals the tab list, links each panel to its tab and shows only the first panel', () => {
    const { root, tabs, panels, tablist, classes } = page();

    assert.ok(initProductTabs(root));
    assert.equal(tablist.hidden, false);
    assert.ok(classes.has('is-tabbed'));
    panels.forEach((panel, i) => {
        assert.equal(panel.getAttribute('role'), 'tabpanel');
        assert.equal(panel.getAttribute('aria-labelledby'), tabs[i].id);
        assert.equal(panel.getAttribute('tabindex'), '0');
        assert.equal(panel.hidden, i !== 0);
    });
    assert.deepEqual(tabs.map((t) => t.getAttribute('aria-selected')), ['true', 'false', 'false', 'false', 'false']);
    assert.deepEqual(tabs.map((t) => t.getAttribute('tabindex')), ['0', '-1', '-1', '-1', '-1']);
});

test('keys move the selection and the focus (roving tabindex); a click selects without moving focus elsewhere', () => {
    const { root, tabs, panels } = page();
    initProductTabs(root, { direction: 'rtl' });

    const event = tabs[0].fire('keydown', { key: 'ArrowLeft' });
    assert.ok(event.prevented);
    assert.equal(tabs[1].getAttribute('aria-selected'), 'true');
    assert.equal(tabs[1].getAttribute('tabindex'), '0');
    assert.equal(tabs[0].getAttribute('tabindex'), '-1');
    assert.ok(tabs[1].focused);
    assert.equal(panels[1].hidden, false);
    assert.equal(panels[0].hidden, true);

    tabs[1].fire('keydown', { key: 'End' });
    assert.ok(tabs[4].focused);
    assert.equal(panels[4].hidden, false);

    const ignored = tabs[4].fire('keydown', { key: 'Tab' });
    assert.equal(ignored.prevented, undefined, 'Tab leaves the tab list normally.');

    tabs[2].fire('click');
    assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
    assert.equal(panels[2].hidden, false);
});

test('the log page link opens the log tab', () => {
    const { root, panels } = page();
    initProductTabs(root, { hash: '#product-panel-log' });

    assert.equal(panels[4].hidden, false);
    assert.equal(panels[0].hidden, true);
});

test('a page without the tab list or with a missing panel is left as it is', () => {
    assert.equal(initProductTabs(null), null);
    assert.equal(initProductTabs({ querySelector: () => null }), null);

    const { root, panels, tablist } = page();
    const broken = { ...root, querySelector: (selector) => (selector === '#product-panel-log' ? null : root.querySelector(selector)) };
    assert.equal(initProductTabs(broken), null);
    assert.equal(tablist.hidden, true);
    assert.ok(panels.every((panel) => panel.hidden === false), 'Every group stays shown.');
});
