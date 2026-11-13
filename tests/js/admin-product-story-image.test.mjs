// PRODUCT-STORY-MEDIA (7B-4F-2A): the admin product story image script (resources/js/admin-product-story-image.js)
// and the shared chooser module. Run by Tests\Feature\AdminProductStoryImageScriptTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import * as chooser from '../../resources/js/admin-media/vendor-image-chooser.js';
import * as gallery from '../../resources/js/admin-product-gallery.js';
import {
    chooseImage,
    clearChoice,
    initialState,
    initStoryImage,
    removeImage,
    shownImage,
    storyFields,
} from '../../resources/js/admin-product-story-image.js';

const tick = () => new Promise((resolve) => setTimeout(resolve, 5));

// ---------------------------------------------------------------- a small DOM

function makeDoc() {
    const doc = { activeElement: null, ids: {} };
    doc.body = makeElement(doc, 'body');
    doc.activeElement = doc.body;
    doc.createElement = (tag) => makeElement(doc, tag);
    doc.getElementById = (id) => doc.ids[id] ?? null;

    return doc;
}

function makeElement(doc, tag, { id = '', hidden = false } = {}) {
    const attrs = new Map();
    const listeners = {};
    const node = {
        tagName: tag.toUpperCase(),
        ownerDocument: doc,
        id,
        hidden,
        disabled: false,
        isConnected: true,
        className: '',
        dataset: {},
        children: [],
        listeners,
        _text: '',
        get textContent() {
            return this.children.length ? this.children.map((child) => child.textContent).join('') : this._text;
        },
        set textContent(value) {
            this.children = [];
            this._text = String(value);
        },
        setAttribute: (name, value) => attrs.set(name, String(value)),
        getAttribute: (name) => (attrs.has(name) ? attrs.get(name) : null),
        removeAttribute: (name) => attrs.delete(name),
        addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
        dispatch(type, event = {}) {
            (listeners[type] ?? []).forEach((fn) => fn({ target: node, ...event }));
        },
        click() {
            this.dispatch('click');
        },
        append(...nodes) {
            this.children.push(...nodes);
        },
        replaceChildren(...nodes) {
            this.children = [...nodes];
            this._text = '';
        },
        querySelector(selector) {
            const wanted = selector.toUpperCase();
            const walk = (list) => {
                for (const child of list) {
                    if (child.tagName === wanted) {
                        return child;
                    }
                    const found = walk(child.children ?? []);
                    if (found) {
                        return found;
                    }
                }

                return null;
            };

            return walk(this.children);
        },
        focus() {
            doc.activeElement = node;
        },
    };

    if (id) {
        doc.ids[id] = node;
    }

    return node;
}

function makeDialog(doc) {
    const dialog = makeElement(doc, 'dialog', { id: 'product-story-dialog' });
    dialog.open = false;
    dialog.showModal = () => {
        if (dialog.open) {
            throw new Error('InvalidStateError');
        }
        dialog.open = true;
    };
    dialog.close = () => {
        if (dialog.open) {
            dialog.open = false;
            dialog.dispatch('close');
        }
    };

    return dialog;
}

/** The story field and its chooser as form.blade.php and story-image-dialog.blade.php render them. */
function page({ editing = true, current = null, chosen = null, removed = false, vendor = '' } = {}) {
    const doc = makeDoc();
    const el = (id, tag = 'div', hidden = false) => makeElement(doc, tag, { id, hidden });

    const root = el('product-story-image');
    el('product-story-image-preview');
    el('product-story-image-thumb');
    el('product-story-image-name', 'bdi');
    el('product-story-image-empty', 'p');
    el('product-story-image-actions', 'div', true);
    const chooseButton = el('product-story-image-choose', 'button');
    chooseButton.append(makeElement(doc, 'span'));
    const removeButton = el('product-story-image-remove', 'button');
    removeButton.append(makeElement(doc, 'span'));
    el('product-story-image-vendor-first', 'p', true);
    const mediaInput = el('product-story-image-media-id', 'input');
    const removeInput = el('product-story-image-remove-flag', 'input');
    mediaInput.value = '';
    removeInput.value = '';
    el('product-story-image-status', 'p');

    makeDialog(doc);
    el('product-story-dialog-title', 'h2');
    const search = el('product-story-search', 'input');
    search.value = '';
    el('product-story-choices', 'ul');
    el('product-story-loading', 'div', true);
    el('product-story-choices-empty', 'div', true);
    el('product-story-choices-empty-text', 'span');
    el('product-story-error', 'div', true);
    el('product-story-error-text', 'span');
    el('product-story-retry', 'button');
    el('product-story-more', 'button', true);
    el('product-story-dialog-close', 'button');
    el('product-story-dialog-cancel', 'button');

    if (!editing) {
        const select = el('product-vendor', 'select');
        select.value = vendor;
    }

    const config = {
        editing,
        current,
        chosen,
        removed,
        mediaUrl: editing ? 'https://hirfah.test/dashboard/products/5/vendor-media' : 'https://hirfah.test/dashboard/products/vendor-media',
        perPage: 24,
        labels: {
            choose: 'Choose image',
            replace: 'Replace image',
            added: ':name was added.',
            replaced: ':name was replaced.',
            removed: ':name was removed from the product. The file stays in the media library.',
            cleared: 'The chosen images were cleared because the vendor changed.',
        },
        messages: {
            dialogTitle: 'Choose an image',
            dialogReplaceTitle: 'Choose a replacement image',
            choose: 'Choose :name',
            inGallery: 'Already added',
            libraryEmpty: 'There are no images in this vendor\'s media library yet.',
            libraryEmptySearch: 'No images match your search.',
            loadError: 'The images could not be loaded.',
            forbidden: 'You do not have permission to do this.',
            network: 'The connection failed. Check the network and try again.',
            session: 'Your session has ended. Sign in again to continue.',
            invalid: 'The request could not be processed. Change the search and try again.',
        },
    };
    const win = { location: { href: 'https://hirfah.test/dashboard/products/5/edit' } };
    const $ = (id) => doc.ids[id];
    const story = initStoryImage(root, config, win);

    return { doc, $, story, config };
}

/** Answers the chooser's requests: the URLs asked for are kept. */
function stubFetch(answer) {
    const calls = [];
    globalThis.fetch = async (url) => {
        calls.push(String(url));
        const { status = 200, body = null, fail = false } = typeof answer === 'function' ? answer(url) : answer;

        if (fail) {
            throw new TypeError('Failed to fetch');
        }

        return { status, json: async () => body };
    };

    return calls;
}

const libraryPage = (items, page = 1, last = 1) => ({ status: 200, body: { data: items, current_page: page, last_page: last } });
const media = (id, name = `photo-${id}.png`) => ({ id, url: `https://hirfah.test/storage/vendors/media/1/${id}.png`, file_original_name: name, alt: '' });

// ---------------------------------------------------------------- one shared copy of the chooser

test('the gallery re-exports the shared chooser helpers themselves, not copies', () => {
    for (const name of ['createLoader', 'emptyChoices', 'errorText', 'interpretList', 'itemFromMedia', 'mediaListUrl', 'renderChoice', 'safeUrl']) {
        assert.equal(typeof chooser[name], 'function', name);
        assert.equal(gallery[name], chooser[name], `${name} is the shared function`);
    }
});

// ---------------------------------------------------------------- state

test('the starting state takes the stored image and a choice or removal a refused save brought back', () => {
    const current = { url: 'https://hirfah.test/a.png', name: 'Old jug' };

    assert.deepEqual(initialState({}), { current: null, chosen: null, removed: false });
    assert.deepEqual(initialState({ current }), { current, chosen: null, removed: false });
    assert.deepEqual(initialState({ current, removed: true }), { current, chosen: null, removed: true });
    assert.deepEqual(initialState({ removed: true }).removed, false, 'Nothing stored, nothing to remove.');
    assert.equal(initialState({ current, chosen: { id: 7, url: '', name: 'New' }, removed: true }).removed, false, 'A choice wins over a removal.');
    assert.equal(initialState({ chosen: { id: 0 } }).chosen, null);
    assert.equal(initialState({ chosen: { id: '7' } }).chosen, null);
});

test('the posted values are never a choice and a removal at once', () => {
    const current = { url: '', name: 'Old' };
    const states = [
        { current: null, chosen: null, removed: false },
        { current, chosen: null, removed: false },
        { current, chosen: null, removed: true },
        { current, chosen: { id: 9 }, removed: false },
        { current, chosen: { id: 9 }, removed: true },
        { current: null, chosen: { id: 9 }, removed: false },
        { current: null, chosen: null, removed: true },
    ];

    for (const state of states) {
        const fields = storyFields(state);
        assert.ok(!(fields.mediaId !== '' && fields.remove !== ''), JSON.stringify(state));
    }

    assert.deepEqual(storyFields(states[0]), { mediaId: '', remove: '' });
    assert.deepEqual(storyFields(states[1]), { mediaId: '', remove: '' }, 'Kept: both empty.');
    assert.deepEqual(storyFields(states[2]), { mediaId: '', remove: '1' });
    assert.deepEqual(storyFields(states[4]), { mediaId: '9', remove: '' });
    assert.deepEqual(storyFields(states[6]), { mediaId: '', remove: '' }, 'Nothing stored: nothing to remove.');
});

test('choosing, removing and a vendor change move the state as expected', () => {
    const current = { url: '', name: 'Old' };
    let state = initialState({ current });

    state = chooseImage(state, { ref: 'media:12', url: 'https://hirfah.test/12.png', name: 'Jug' });
    assert.deepEqual(state.chosen, { id: 12, url: 'https://hirfah.test/12.png', name: 'Jug' });
    assert.equal(shownImage(state).name, 'Jug');
    assert.equal(chooseImage(state, { ref: 'existing:3' }), state, 'Only a library image can be chosen.');
    assert.equal(chooseImage(state, { ref: 'media:0' }), state);

    state = removeImage(state);
    assert.deepEqual(state, { current, chosen: null, removed: true });
    assert.equal(shownImage(state), null);

    const fresh = chooseImage(initialState({}), { ref: 'media:4', url: '', name: 'A' });
    assert.equal(clearChoice(fresh).chosen, null);
    const untouched = initialState({});
    assert.equal(clearChoice(untouched), untouched);
});

// ---------------------------------------------------------------- the field

test('the field shows the stored image, offers Replace and Remove, and posts nothing until changed', () => {
    const { $, doc } = page({ current: { url: 'https://hirfah.test/storage/vendors/media/1/old.png', name: 'Old jug' } });

    assert.equal($('product-story-image-actions').hidden, false, 'Revealed by the script.');
    assert.equal($('product-story-image-preview').hidden, false);
    assert.equal($('product-story-image-empty').hidden, true);
    assert.equal($('product-story-image-name').textContent, 'Old jug');
    assert.equal($('product-story-image-thumb').children[0].tagName, 'IMG');
    assert.equal($('product-story-image-thumb').children[0].alt, 'Old jug');
    assert.equal($('product-story-image-choose').querySelector('span').textContent, 'Replace image');
    assert.equal($('product-story-image-remove').hidden, false);
    assert.equal($('product-story-image-media-id').value, '');
    assert.equal($('product-story-image-remove-flag').value, '');
    assert.equal(doc.activeElement, doc.body, 'Nothing takes focus on load.');
});

test('a stored image whose address cannot be shown gets the placeholder, never a broken link', () => {
    const { $ } = page({ current: { url: 'javascript:alert(1)', name: 'Odd' } });

    assert.equal($('product-story-image-thumb').children[0].tagName, 'I');
});

test('Remove clears the image, posts the removal and keeps focus on the field', () => {
    const { $, doc } = page({ current: { url: 'https://hirfah.test/a.png', name: 'Old jug' } });

    $('product-story-image-remove').click();

    assert.equal($('product-story-image-preview').hidden, true);
    assert.equal($('product-story-image-empty').hidden, false);
    assert.equal($('product-story-image-remove').hidden, true);
    assert.equal($('product-story-image-choose').querySelector('span').textContent, 'Choose image');
    assert.deepEqual([$('product-story-image-media-id').value, $('product-story-image-remove-flag').value], ['', '1']);
    assert.equal($('product-story-image-status').textContent, 'Old jug was removed from the product. The file stays in the media library.');
    assert.equal(doc.activeElement, $('product-story-image-choose'));
});

test('choosing in the dialog sets the media id only, closes the dialog and returns focus', async () => {
    const calls = stubFetch(libraryPage([media(21, 'Blue jug'), media(22)]));
    const { $, doc } = page({ current: { url: 'https://hirfah.test/a.png', name: 'Old jug' }, removed: true });

    assert.equal($('product-story-image-remove-flag').value, '1', 'The removal a refused save brought back.');
    $('product-story-image-choose').click();
    await tick();

    const dialog = $('product-story-dialog');
    assert.equal(dialog.open, true);
    assert.equal(doc.activeElement, $('product-story-search'));
    assert.equal($('product-story-dialog-title').textContent, 'Choose an image', 'Nothing shown: a first choice.');
    assert.deepEqual(calls, ['https://hirfah.test/dashboard/products/5/vendor-media?page=1&per_page=24'], 'Editing: the stored vendor, never one named here.');

    const choices = $('product-story-choices').children;
    assert.equal(choices.length, 2);
    const button = choices[0].querySelector('div').querySelector('button');
    assert.equal(button.getAttribute('aria-label'), 'Choose Blue jug');
    button.click();

    assert.equal(dialog.open, false);
    assert.deepEqual([$('product-story-image-media-id').value, $('product-story-image-remove-flag').value], ['21', '']);
    assert.equal($('product-story-image-name').textContent, 'Blue jug');
    assert.equal($('product-story-image-status').textContent, 'Blue jug was added.');
    assert.equal(doc.activeElement, $('product-story-image-choose'));
});

test('replacing names the image that was replaced, and the dialog title says so', async () => {
    stubFetch(libraryPage([media(30, 'Green jug')]));
    const { $ } = page({ current: { url: 'https://hirfah.test/a.png', name: 'Old jug' } });

    $('product-story-image-choose').click();
    await tick();
    assert.equal($('product-story-dialog-title').textContent, 'Choose a replacement image');
    $('product-story-choices').children[0].querySelector('div').querySelector('button').click();

    assert.equal($('product-story-image-media-id').value, '30');
    assert.equal($('product-story-image-status').textContent, 'Old jug was replaced.');
});

test('a choice a refused save brought back is posted again and shown', () => {
    const { $ } = page({ current: { url: '', name: 'Old' }, chosen: { id: 44, url: 'https://hirfah.test/44.png', name: 'Restored jug' } });

    assert.deepEqual([$('product-story-image-media-id').value, $('product-story-image-remove-flag').value], ['44', '']);
    assert.equal($('product-story-image-name').textContent, 'Restored jug');
});

test('a new product needs its vendor first, asks for that vendor, and drops a choice when the vendor changes', async () => {
    const calls = stubFetch(libraryPage([media(5, 'Cup')]));
    const { $ } = page({ editing: false, vendor: '' });

    assert.equal($('product-story-image-choose').disabled, true);
    assert.equal($('product-story-image-vendor-first').hidden, false);
    $('product-story-image-choose').click();
    assert.equal($('product-story-dialog').open, false);

    const vendor = $('product-vendor');
    vendor.value = '7';
    vendor.dispatch('change');
    assert.equal($('product-story-image-choose').disabled, false);
    assert.equal($('product-story-image-vendor-first').hidden, true);
    assert.equal($('product-story-image-status').textContent, '', 'Nothing chosen: nothing to announce.');

    $('product-story-image-choose').click();
    await tick();
    assert.deepEqual(calls, ['https://hirfah.test/dashboard/products/vendor-media?page=1&per_page=24&vendor_id=7']);
    $('product-story-choices').children[0].querySelector('div').querySelector('button').click();
    assert.equal($('product-story-image-media-id').value, '5');

    vendor.value = '8';
    vendor.dispatch('change');
    assert.equal($('product-story-image-media-id').value, '');
    assert.equal($('product-story-image-preview').hidden, true);
    assert.equal($('product-story-image-status').textContent, 'The chosen images were cleared because the vendor changed.');
});

test('Close, Cancel and the backdrop close the chooser without changing the field', async () => {
    stubFetch(libraryPage([media(1)]));
    const { $, doc } = page({});
    const dialog = $('product-story-dialog');

    for (const close of [() => $('product-story-dialog-close').click(), () => $('product-story-dialog-cancel').click(), () => dialog.dispatch('click', { target: dialog })]) {
        $('product-story-image-choose').click();
        await tick();
        doc.activeElement = doc.body;
        close();
        assert.equal(dialog.open, false);
        assert.equal(doc.activeElement, $('product-story-image-choose'), 'Focus returns to the button.');
        assert.deepEqual([$('product-story-image-media-id').value, $('product-story-image-remove-flag').value], ['', '']);
    }
});

test('an empty library, a refused request and a network failure show their own state', async () => {
    stubFetch(libraryPage([]));
    let view = page({});
    view.$('product-story-image-choose').click();
    await tick();
    assert.equal(view.$('product-story-choices-empty').hidden, false);
    assert.equal(view.$('product-story-choices-empty-text').textContent, 'There are no images in this vendor\'s media library yet.');
    assert.equal(view.doc.ids['product-story-choices-empty-hint'], undefined, 'The admin-library explanation stays the gallery\'s.');

    stubFetch({ status: 403, body: { message: 'server text' } });
    view = page({});
    view.$('product-story-image-choose').click();
    await tick();
    assert.equal(view.$('product-story-error').hidden, false);
    assert.equal(view.$('product-story-error-text').textContent, 'You do not have permission to do this.', 'Never the server\'s own message.');
    assert.equal(view.$('product-story-retry').hidden, true);

    stubFetch({ fail: true });
    view = page({});
    view.$('product-story-image-choose').click();
    await tick();
    assert.equal(view.$('product-story-error-text').textContent, 'The connection failed. Check the network and try again.');
    assert.equal(view.$('product-story-retry').hidden, false);
});

test('Load more asks for the next page once', async () => {
    const calls = stubFetch((url) => (new URL(url).searchParams.get('page') === '2' ? libraryPage([media(3)], 2, 2) : libraryPage([media(1), media(2)], 1, 2)));
    const { $ } = page({});

    $('product-story-image-choose').click();
    await tick();
    assert.equal($('product-story-more').hidden, false);
    $('product-story-more').click();
    await tick();

    assert.equal($('product-story-choices').children.length, 3);
    assert.equal($('product-story-more').hidden, true);
    assert.equal(calls.length, 2);
});
