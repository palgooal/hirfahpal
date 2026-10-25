// VEN-F1: the auth pages script (submit lock and password toggle). Run by Tests\Feature\AdminAuthScriptTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { initPasswordToggles, initSubmitLocks } from '../../resources/js/admin-auth.js';

// A minimal element: attributes, listeners, classes, children by selector.
function element(children = {}) {
    const attributes = new Map();
    const listeners = {};
    const classes = new Set();

    return {
        attributes,
        listeners,
        dataset: {},
        textContent: '',
        type: '',
        valid: true,
        classList: {
            contains: (name) => classes.has(name),
            toggle: (name, force) => (force ? classes.add(name) : classes.delete(name)),
        },
        setAttribute: (name, value) => attributes.set(name, String(value)),
        removeAttribute: (name) => attributes.delete(name),
        getAttribute: (name) => attributes.get(name) ?? null,
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        querySelector: (selector) => children[selector] ?? null,
        checkValidity() {
            return this.valid;
        },
        dispatch(type, event) {
            (listeners[type] ?? []).forEach((fn) => fn(event));
        },
    };
}

function submitEvent() {
    return { prevented: false, preventDefault() { this.prevented = true; } };
}

function loginPage({ loadingLabel = 'جارٍ تسجيل الدخول...' } = {}) {
    const label = element();
    label.textContent = 'تسجيل الدخول';
    const button = element({ span: label });
    if (loadingLabel !== null) {
        button.dataset.loadingLabel = loadingLabel;
    }
    const status = element();
    const form = element({ 'button[type="submit"]': button, '[data-submit-status]': status });
    const win = element();
    const doc = {
        querySelectorAll: (selector) => (selector === 'form[data-submit-lock]' ? [form] : []),
    };

    return { doc, win, form, button, label, status, locks: initSubmitLocks(doc, win) };
}

test('the first submit posts and shows the loading state; the second is cancelled', () => {
    const { form, button, label, status } = loginPage();

    const first = submitEvent();
    form.dispatch('submit', first);
    assert.equal(first.prevented, false, 'The first submit goes through.');
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(button.getAttribute('aria-disabled'), 'true');
    assert.equal(button.disabled, undefined, 'The button is never disabled, so it keeps focus.');
    assert.equal(label.textContent, 'جارٍ تسجيل الدخول...');
    assert.equal(status.textContent, 'جارٍ تسجيل الدخول...', 'The status region announces it.');

    const second = submitEvent();
    form.dispatch('submit', second);
    assert.equal(second.prevented, true, 'A second submit is cancelled.');
});

test('Enter submits through the same submit event, so it is locked too', () => {
    const { form } = loginPage();

    // Enter in a field fires the form submit event (implicit submission): one lock covers click and Enter.
    form.dispatch('submit', submitEvent());
    const enter = submitEvent();
    form.dispatch('submit', enter);
    assert.equal(enter.prevented, true);
});

test('an invalid form is not locked', () => {
    const { form, button, label } = loginPage();

    form.valid = false;
    const invalid = submitEvent();
    form.dispatch('submit', invalid);
    assert.equal(invalid.prevented, false);
    assert.equal(form.getAttribute('aria-busy'), null);
    assert.equal(button.getAttribute('aria-disabled'), null);
    assert.equal(label.textContent, 'تسجيل الدخول');

    form.valid = true;
    const valid = submitEvent();
    form.dispatch('submit', valid);
    assert.equal(valid.prevented, false, 'Once valid, the first real submit goes through.');
    assert.equal(form.getAttribute('aria-busy'), 'true');
});

test('the loading label comes from the page in its language', () => {
    const { form, label, status } = loginPage({ loadingLabel: 'Signing in...' });

    form.dispatch('submit', submitEvent());
    assert.equal(label.textContent, 'Signing in...');
    assert.equal(status.textContent, 'Signing in...');
});

test('without a loading label the text is kept but the form is still locked', () => {
    const { form, label, status } = loginPage({ loadingLabel: null });

    form.dispatch('submit', submitEvent());
    assert.equal(label.textContent, 'تسجيل الدخول');
    assert.equal(status.textContent, '');
    const second = submitEvent();
    form.dispatch('submit', second);
    assert.equal(second.prevented, true);
});

test('a page restored from the back/forward cache can be submitted again', () => {
    const { form, button, label, status, win } = loginPage();

    form.dispatch('submit', submitEvent());
    win.dispatch('pageshow', { persisted: false });
    assert.equal(form.getAttribute('aria-busy'), 'true', 'A normal load does not reset.');

    win.dispatch('pageshow', { persisted: true });
    assert.equal(form.getAttribute('aria-busy'), null);
    assert.equal(button.getAttribute('aria-disabled'), null);
    assert.equal(label.textContent, 'تسجيل الدخول');
    assert.equal(status.textContent, '');

    const again = submitEvent();
    form.dispatch('submit', again);
    assert.equal(again.prevented, false);
});

test('a page without form[data-submit-lock] (admin login, register, reset) is left alone', () => {
    const asked = [];
    const doc = { querySelectorAll: (selector) => (asked.push(selector), []) };

    assert.deepEqual(initSubmitLocks(doc, null), []);
    assert.deepEqual(asked, ['form[data-submit-lock]'], 'Only opted-in forms are looked up.');
});

test('the password toggle still switches the input and its labels', () => {
    const input = element();
    input.type = 'password';
    const showIcon = element();
    const hideIcon = element();
    hideIcon.classList.toggle('hidden', true);
    const button = element({ '[data-icon-show]': showIcon, '[data-icon-hide]': hideIcon });
    button.setAttribute('aria-controls', 'password');
    button.dataset.labelShow = 'Show password';
    button.dataset.labelHide = 'Hide password';
    const doc = {
        querySelectorAll: (selector) => (selector === '[data-password-toggle]' ? [button] : []),
        getElementById: (id) => (id === 'password' ? input : null),
    };

    initPasswordToggles(doc);

    button.dispatch('click');
    assert.equal(input.type, 'text');
    assert.equal(button.getAttribute('aria-pressed'), 'true');
    assert.equal(button.getAttribute('aria-label'), 'Hide password');
    assert.equal(showIcon.classList.contains('hidden'), true);
    assert.equal(hideIcon.classList.contains('hidden'), false);

    button.dispatch('click');
    assert.equal(input.type, 'password');
    assert.equal(button.getAttribute('aria-pressed'), 'false');
    assert.equal(button.getAttribute('aria-label'), 'Show password');
});
