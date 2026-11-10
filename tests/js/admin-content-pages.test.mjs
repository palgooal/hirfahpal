// PAGES-04C: the content pages list script (resources/js/admin-content-pages.js) over small stand-ins.
// Run by Tests\Feature\Dashboard\ContentPageEditorScriptTest.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { copyText, fillTemplate, initCopyButtons, initDeleteDialog } from '../../resources/js/admin-content-pages.js';

function element(dataset = {}) {
    const listeners = {};
    const attributes = {};

    return {
        dataset,
        hidden: true,
        disabled: false,
        textContent: '',
        focused: false,
        addEventListener: (type, handler) => (listeners[type] ??= []).push(handler),
        fire: async (type) => {
            for (const handler of listeners[type] ?? []) {
                await handler({ type });
            }
        },
        setAttribute: (name, value) => (attributes[name] = String(value)),
        getAttribute: (name) => attributes[name] ?? null,
        focus() {
            this.focused = true;
        },
    };
}

test('templates fill only the known placeholders', () => {
    assert.equal(fillTemplate('“:title” at :address (:other)', { title: 'قصتنا', address: '/pages/our-story' }), '“قصتنا” at /pages/our-story (:other)');
    assert.equal(fillTemplate(undefined, {}), '');
});

test('copying uses the Clipboard API, then the copy command, and reports failure', async () => {
    const written = [];
    assert.equal(await copyText('https://hirfah.test/pages/a', { clipboard: { writeText: async (text) => written.push(text) }, doc: null }), true);
    assert.deepEqual(written, ['https://hirfah.test/pages/a']);

    const appended = [];
    const doc = {
        body: { appendChild: (node) => appended.push(node) },
        createElement: () => ({ style: {}, setAttribute() {}, select() {}, remove() { this.removed = true; } }),
        execCommand: (command) => command === 'copy',
    };
    assert.equal(await copyText('https://hirfah.test/pages/b', { clipboard: undefined, doc }), true);
    assert.equal(appended[0].value, 'https://hirfah.test/pages/b');
    assert.equal(appended[0].removed, true, 'The helper field is removed.');

    const refused = { writeText: async () => { throw new Error('denied'); } };
    assert.equal(await copyText('x', { clipboard: refused, doc: { ...doc, execCommand: () => false } }), false);
    assert.equal(await copyText('x', { clipboard: undefined, doc: null }), false);
});

test('copy buttons are revealed and announce the result', async () => {
    const button = element({ copyUrl: 'https://hirfah.test/pages/our-story' });
    const list = { dataset: { copied: 'Copied.', copyFailed: 'Not copied: :url' }, querySelectorAll: () => [button] };
    const status = element();
    let result = true;
    const copied = [];
    initCopyButtons(list, status, { copy: async (url) => (copied.push(url), result) });

    assert.equal(button.hidden, false);
    await button.fire('click');
    assert.deepEqual(copied, ['https://hirfah.test/pages/our-story']);
    assert.equal(status.textContent, 'Copied.');

    result = false;
    await button.fire('click');
    assert.equal(status.textContent, 'Not copied: https://hirfah.test/pages/our-story');
});

test('the delete dialog is filled from the row, focuses cancel and submits once', async () => {
    const form = element();
    const text = element({ template: '“:title”: links to :address will stop working.' });
    const cancel = element();
    const confirm = element();
    const parts = { '[data-delete-form]': form, '[data-delete-text]': text, '[data-dialog-cancel]': cancel, '[data-delete-confirm]': confirm };
    const dialog = { ...element(), open: false, querySelector: (selector) => parts[selector] };
    dialog.showModal = () => (dialog.open = true);
    dialog.close = () => {
        dialog.open = false;
        dialog.fire('close');
    };
    const button = element({ deleteUrl: '/dashboard/content-pages/9', deleteTitle: 'قصتنا', deleteAddress: '/pages/our-story' });

    initDeleteDialog(dialog, [button]);
    assert.equal(button.hidden, false);

    await button.fire('click');
    assert.equal(dialog.open, true);
    assert.equal(form.getAttribute('action'), '/dashboard/content-pages/9');
    assert.equal(text.textContent, '“قصتنا”: links to /pages/our-story will stop working.');
    assert.equal(cancel.focused, true, 'Cancel is focused first.');

    await cancel.fire('click');
    assert.equal(dialog.open, false);
    assert.equal(button.focused, true, 'Focus returns to the row.');

    await form.fire('submit');
    assert.equal(confirm.disabled, true, 'No second submission.');
    await button.fire('click');
    assert.equal(confirm.disabled, false, 'Re-enabled when opened again.');
});
