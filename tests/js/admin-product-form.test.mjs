// 7B-8F-3: the admin product form script (resources/js/admin-product-form.js). Run by
// Tests\Feature\AdminProductFormScriptTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { focusErrorSummary } from '../../resources/js/admin-product-form.js';

/** A document whose focus behaves as the browser's: focus() on a focusable element makes it the active element. */
function makeDoc({ summary = true, focusable = true } = {}) {
    const doc = { body: { tagName: 'BODY' }, documentElement: { tagName: 'HTML' }, focusCalls: 0 };
    doc.activeElement = doc.body;
    const node = summary
        ? {
            id: 'product-form-errors',
            focus() {
                doc.focusCalls += 1;
                if (focusable) {
                    doc.activeElement = node;
                }
            },
        }
        : null;
    doc.summary = node;
    doc.getElementById = (id) => (id === 'product-form-errors' ? node : null);

    return doc;
}

test('a page that comes back with errors moves focus to the summary', () => {
    const doc = makeDoc();

    assert.equal(focusErrorSummary(doc), true);
    assert.equal(doc.activeElement, doc.summary);
    assert.equal(doc.focusCalls, 1);
});

test('a page without errors leaves focus where it is', () => {
    const doc = makeDoc({ summary: false });

    assert.equal(focusErrorSummary(doc), false);
    assert.equal(doc.activeElement, doc.body);
});

test('focus already on an element (the user, or the browser) is never taken away', () => {
    const doc = makeDoc();
    const field = { tagName: 'INPUT' };
    doc.activeElement = field;

    assert.equal(focusErrorSummary(doc), false);
    assert.equal(doc.activeElement, field);
    assert.equal(doc.focusCalls, 0);
});

test('a summary the browser already focused (autofocus) stays focused', () => {
    const doc = makeDoc();
    doc.activeElement = doc.summary;

    assert.equal(focusErrorSummary(doc), true);
    assert.equal(doc.activeElement, doc.summary);
});

test('focus that sits on the document element counts as nothing focused', () => {
    const doc = makeDoc();
    doc.activeElement = doc.documentElement;

    assert.equal(focusErrorSummary(doc), true);
    assert.equal(doc.activeElement, doc.summary);
});

test('a summary that cannot take focus reports it', () => {
    const doc = makeDoc({ focusable: false });

    assert.equal(focusErrorSummary(doc), false);
    assert.equal(doc.focusCalls, 1);
});
