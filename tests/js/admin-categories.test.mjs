// AD-UI-CAT-01E-3A / 01E-3B: the categories list and form script. Run by Tests\Feature\AdminCategoriesScriptTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { createSubmitGuard, deleteTarget, initCategoryForm, splitTemplate } from '../../resources/js/admin-categories.js';

test('the dialog deletes a row only with its destroy URL and its server-issued confirmation (N-CAT-1)', () => {
    assert.deepEqual(deleteTarget({ destroy: '/dashboard/categories/5', confirmation: 'abc' }), { action: '/dashboard/categories/5', confirmation: 'abc' });
    // Missing either: the row's own form (the confirmation page) is used instead.
    assert.equal(deleteTarget({ destroy: '/dashboard/categories/5', confirmation: '' }), null);
    assert.equal(deleteTarget({ destroy: '', confirmation: 'abc' }), null);
    assert.equal(deleteTarget({}), null);
    assert.equal(deleteTarget(undefined), null);
});

// A minimal element: attributes, listeners, children by selector.
function element(children = {}) {
    const attributes = new Map();
    const listeners = {};

    return {
        attributes,
        listeners,
        dataset: {},
        disabled: false,
        textContent: '',
        focused: false,
        setAttribute: (name, value) => attributes.set(name, String(value)),
        removeAttribute: (name) => attributes.delete(name),
        getAttribute: (name) => attributes.get(name) ?? null,
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        querySelector: (selector) => children[selector] ?? null,
        focus() {
            this.focused = true;
        },
        dispatch(type, event) {
            (listeners[type] ?? []).forEach((fn) => fn(event));
        },
    };
}

function submitEvent() {
    return { prevented: false, preventDefault() { this.prevented = true; } };
}

function formPage({ withErrors = false } = {}) {
    const label = element();
    label.textContent = 'Save category';
    const button = element({ span: label });
    button.dataset.saving = 'Saving…';
    const form = element({ '[data-category-submit]': button });
    const summary = withErrors ? element() : null;
    const win = element();
    const doc = {
        querySelector: (selector) => (selector === 'form[data-category-form]' ? form : null),
        getElementById: (id) => (id === 'category-form-errors' ? summary : null),
    };

    return { doc, win, form, button, label, summary, controls: initCategoryForm(doc, win) };
}

test('the submit guard lets one submission through until it is reset', () => {
    const guard = createSubmitGuard();

    assert.equal(guard.start(), true);
    assert.equal(guard.busy, true);
    assert.equal(guard.start(), false);
    guard.reset();
    assert.equal(guard.start(), true);
});

test('the category form posts once: the second submit is cancelled and the saving state is shown', async () => {
    const { form, button, label } = formPage();

    const first = submitEvent();
    form.dispatch('submit', first);
    assert.equal(first.prevented, false, 'The first submit goes through.');
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(button.getAttribute('aria-disabled'), 'true');
    assert.equal(label.textContent, 'Saving…');
    assert.equal(button.disabled, false, 'Not disabled synchronously, so the submission is not cancelled.');
    await new Promise((resolve) => setTimeout(resolve, 5));
    assert.equal(button.disabled, true);

    const second = submitEvent();
    form.dispatch('submit', second);
    assert.equal(second.prevented, true, 'A second submit (double click, Enter) is cancelled.');
});

test('a page restored from the back/forward cache can be submitted again', () => {
    const { form, button, label, win } = formPage();

    form.dispatch('submit', submitEvent());
    win.dispatch('pageshow', { persisted: false });
    assert.equal(label.textContent, 'Saving…', 'A normal load does not reset.');

    win.dispatch('pageshow', { persisted: true });
    assert.equal(form.getAttribute('aria-busy'), null);
    assert.equal(button.getAttribute('aria-disabled'), null);
    assert.equal(button.disabled, false);
    assert.equal(label.textContent, 'Save category');

    const again = submitEvent();
    form.dispatch('submit', again);
    assert.equal(again.prevented, false);
});

test('the error summary takes focus; a page without the form is left alone', () => {
    assert.equal(formPage({ withErrors: true }).summary.focused, true);
    assert.equal(initCategoryForm({ querySelector: () => null, getElementById: () => null }, null), null);
});

test('the confirmation sentence is split around the name, in either word order', () => {
    assert.deepEqual(splitTemplate(':name will be deleted. This cannot be undone.'), ['', ' will be deleted. This cannot be undone.']);
    assert.deepEqual(splitTemplate('سيُحذف التصنيف :name. لا يمكن التراجع عن ذلك.'), ['سيُحذف التصنيف ', '. لا يمكن التراجع عن ذلك.']);
});

test('a sentence without the placeholder is kept whole, and nothing is lost when it appears twice', () => {
    assert.deepEqual(splitTemplate('Delete?'), ['Delete?', '']);
    assert.deepEqual(splitTemplate(':name and :name'), ['', ' and :name']);
    assert.deepEqual(splitTemplate(undefined), ['', '']);
});
