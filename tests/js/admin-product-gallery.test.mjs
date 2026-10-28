// PRODUCT-MEDIA-01 P5-C: the admin product gallery script (resources/js/admin-product-gallery.js).
// Run by Tests\Feature\AdminProductGalleryPageTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    addItem,
    altText,
    createLoader,
    errorText,
    galleryRefs,
    interpretList,
    itemFromMedia,
    mediaListUrl,
    moveItem,
    removeItem,
    renderChoice,
    renderItem,
    replaceItem,
    safeUrl,
    sameImage,
} from '../../resources/js/admin-product-gallery.js';

const PAGE = 'https://hirfah.test/dashboard/products/7/edit';
const CREATE = 'https://hirfah.test/dashboard/products/vendor-media';
const EDIT = 'https://hirfah.test/dashboard/products/7/vendor-media';

const messages = {
    count: ':count of :max images', main: 'Main image', legacy: 'Not in the vendor library', position: 'Image :position',
    moveUp: 'Move up', moveDown: 'Move down', replace: 'Replace', remove: 'Remove', choose: 'Choose :name', inGallery: 'Already added',
    loadError: 'The images could not be loaded.', forbidden: 'You do not have permission to do this.',
    network: 'The connection failed. Check the network and try again.', session: 'Your session has ended. Sign in again to continue.',
    invalid: 'The request could not be processed. Change the search and try again.',
};

const item = (ref, url = `https://hirfah.test/storage/${ref}.png`, extra = {}) => ({ ref, url, name: `${ref}.png`, alt: '', legacy: false, ...extra });
const A = item('existing:1');
const B = item('existing:2');
const C = item('media:9');

// ---- A minimal DOM, enough to see what the render functions build ----

class FakeElement {
    constructor(tag) {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.attributes = {};
        this.dataset = {};
        this.listeners = {};
        this.text = '';
        this.className = '';
        this.disabled = false;
    }

    set textContent(value) {
        this.children = [];
        this.text = String(value);
    }

    get textContent() {
        return this.text + this.children.map((child) => (typeof child === 'string' ? child : child.textContent)).join('');
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    addEventListener(type, listener) {
        (this.listeners[type] ??= []).push(listener);
    }

    dispatch(type) {
        (this.listeners[type] ?? []).forEach((listener) => listener({}));
    }

    all() {
        return this.children.filter((child) => typeof child !== 'string').flatMap((child) => [child, ...child.all()]);
    }
}

const doc = { createElement: (tag) => new FakeElement(tag) };

// ---------------------------------------------------------------------------------------------
// The list
// ---------------------------------------------------------------------------------------------

test('adding keeps the order, refuses a seventh image and an image already in the list', () => {
    const list = addItem(addItem([], A, 6), B, 6);

    assert.deepEqual(galleryRefs(list), ['existing:1', 'existing:2']);
    assert.equal(addItem(list, A, 6), list, 'The same reference is not added twice.');
    assert.equal(addItem(list, item('media:5', A.url), 6), list, 'The same file under another reference is not added twice.');

    const six = ['a', 'b', 'c', 'd', 'e', 'f'].map((ref) => item(`media:${ref.charCodeAt(0)}`));
    const full = six.reduce((acc, next) => addItem(acc, next, 6), []);
    assert.equal(full.length, 6);
    assert.equal(addItem(full, C, 6), full);
});

test('moving swaps neighbours, the first image is the main one, and the ends do nothing', () => {
    const list = [A, B, C];

    assert.deepEqual(galleryRefs(moveItem(list, 2, -1)), ['existing:1', 'media:9', 'existing:2']);
    assert.deepEqual(galleryRefs(moveItem(list, 1, -1)), ['existing:2', 'existing:1', 'media:9'], 'Moving to the top makes it the main image.');
    assert.deepEqual(galleryRefs(moveItem(list, 0, 1)), ['existing:2', 'existing:1', 'media:9']);
    assert.equal(moveItem(list, 0, -1), list);
    assert.equal(moveItem(list, 2, 1), list);
    assert.equal(moveItem(list, 7, 1), list);
    assert.deepEqual(galleryRefs(list), ['existing:1', 'existing:2', 'media:9'], 'The input is never changed.');
});

test('removing and replacing touch one position only', () => {
    const list = [A, B, C];

    assert.deepEqual(galleryRefs(removeItem(list, 1)), ['existing:1', 'media:9']);
    assert.equal(removeItem(list, 5), list);

    const fresh = item('media:42');
    assert.deepEqual(galleryRefs(replaceItem(list, 1, fresh)), ['existing:1', 'media:42', 'media:9']);
    assert.equal(replaceItem(list, 1, item('media:77', C.url)), list, 'A replacement already at another position is refused.');
    assert.deepEqual(galleryRefs(replaceItem(list, 2, item('media:77', C.url))), ['existing:1', 'existing:2', 'media:77'], 'Replacing an image by itself (another reference) is fine.');
    assert.equal(replaceItem(list, -1, fresh), list);
});

test('two items are the same image by reference or by file', () => {
    assert.equal(sameImage(A, { ...A, url: 'x' }), true);
    assert.equal(sameImage(A, item('media:3', A.url)), true);
    assert.equal(sameImage(A, item('media:3', '')), false);
    assert.equal(sameImage(item('media:3', ''), item('media:4', '')), false, 'Two images without an address are not the same.');
});

// ---------------------------------------------------------------------------------------------
// URLs and answers
// ---------------------------------------------------------------------------------------------

test('a new product asks for its chosen vendor, an existing one never names a vendor', () => {
    assert.equal(mediaListUrl(CREATE, { editing: false, vendorId: '11', page: 2, perPage: 24, search: 'jug' }), `${CREATE}?page=2&per_page=24&vendor_id=11&search=jug`);
    assert.equal(mediaListUrl(EDIT, { editing: true, vendorId: '11', page: 1, perPage: 24 }), `${EDIT}?page=1&per_page=24`);

    for (const vendorId of ['', '0', 'abc', '01', null, undefined]) {
        assert.equal(mediaListUrl(CREATE, { editing: false, vendorId }), null, String(vendorId));
    }
});

test('answers are read defensively and errors are never the server text', () => {
    const ok = interpretList({ status: 200, body: { data: [{ id: 1 }, { id: 'x' }, null], current_page: 1, last_page: 2 } });
    assert.deepEqual(ok, { ok: true, data: [{ id: 1 }], page: 1, lastPage: 2 });

    for (const [status, error, text] of [[0, 'network', messages.network], [401, 'session', messages.session], [403, 'forbidden', messages.forbidden], [422, 'invalid', messages.invalid], [500, 'failed', messages.loadError]]) {
        const outcome = interpretList({ status, body: { message: 'SQLSTATE secret' } });
        assert.deepEqual(outcome, { ok: false, error });
        assert.equal(errorText(outcome.error, messages), text);
    }
});

test('a chooser image becomes a gallery item with a safe address', () => {
    assert.deepEqual(itemFromMedia({ id: 5, url: '/storage/vendors/media/1/a.png', file_original_name: ' jug.png ', alt: 'Blue jug' }, PAGE), {
        ref: 'media:5', url: 'https://hirfah.test/storage/vendors/media/1/a.png', name: 'jug.png', alt: 'Blue jug', legacy: false,
    });
    assert.equal(itemFromMedia({ id: 6, url: 'javascript:alert(1)' }, PAGE).url, '');
    assert.equal(itemFromMedia({ id: 6 }, PAGE).name, '#6');
    assert.equal(safeUrl('data:image/png;base64,AA', PAGE), '');
    assert.equal(altText({ alt: ' ', name: 'jug.png' }), 'jug.png');
});

// ---------------------------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------------------------

const hostile = '<img src=x onerror=alert(1)>';

test('a gallery image shows its position, main badge and four described actions', () => {
    const card = renderItem(doc, item('existing:1', 'https://hirfah.test/a.png', { name: hostile, alt: 'Blue jug', legacy: true }), 0, 3, { messages, base: PAGE });
    const nodes = card.all();
    const buttons = nodes.filter((node) => node.tagName === 'BUTTON');
    const image = nodes.find((node) => node.tagName === 'IMG');

    assert.equal(card.dataset.ref, 'existing:1');
    assert.equal(image.alt, 'Blue jug');
    assert.ok(card.textContent.includes('Image 1'));
    assert.ok(card.textContent.includes('Main image'));
    assert.ok(card.textContent.includes('Not in the vendor library'));
    assert.ok(card.textContent.includes(hostile), 'The name is text.');
    assert.equal(nodes.filter((node) => node.tagName === 'IMG').length, 1, 'No element comes from the name.');
    assert.deepEqual(buttons.map((b) => b.dataset.action), ['up', 'down', 'replace', 'remove']);
    assert.deepEqual(buttons.map((b) => b.textContent), ['Move up', 'Move down', 'Replace', 'Remove']);
    assert.deepEqual(buttons.map((b) => b.disabled), [true, false, false, false], 'The first image cannot move up.');
    const nameId = nodes.find((node) => node.tagName === 'BDI').id;
    assert.ok(buttons.every((b) => b.getAttribute('aria-describedby') === nameId), 'Each action names its image.');
    assert.ok(buttons.every((b) => b.type === 'button'), 'No action submits the form.');

    const last = renderItem(doc, B, 2, 3, { messages, base: PAGE });
    assert.deepEqual(last.all().filter((node) => node.tagName === 'BUTTON').map((b) => b.disabled), [false, true, false, false]);
    assert.ok(!last.textContent.includes('Main image'));

    const broken = renderItem(doc, item('existing:3', 'javascript:alert(1)'), 1, 2, { messages, base: PAGE });
    assert.equal(broken.all().filter((node) => node.tagName === 'IMG').length, 0, 'An unsafe address is never loaded.');
});

test('a chooser image is a named button; one already in the gallery cannot be chosen', () => {
    const chosen = [];
    const card = renderChoice(doc, { id: 5, url: '/storage/vendors/media/1/a.png', file_original_name: hostile }, { messages, base: PAGE, taken: false, onChoose: (picked) => chosen.push(picked.ref) });
    const button = card.all().find((node) => node.tagName === 'BUTTON');

    assert.equal(button.getAttribute('aria-label'), `Choose ${hostile}`);
    assert.equal(button.disabled, false);
    assert.ok(card.textContent.includes(hostile));
    assert.deepEqual(card.all().map((node) => node.tagName).filter((tag) => ['INPUT', 'FORM', 'LABEL'].includes(tag)), [], 'No upload or form control.');
    button.dispatch('click');
    assert.deepEqual(chosen, ['media:5']);

    const taken = renderChoice(doc, { id: 6, url: '/x.png', file_original_name: 'b.png' }, { messages, base: PAGE, taken: true, onChoose: () => {} });
    assert.equal(taken.all().find((node) => node.tagName === 'BUTTON').disabled, true);
    assert.ok(taken.textContent.includes('Already added'));
});

// ---------------------------------------------------------------------------------------------
// The chooser's pages
// ---------------------------------------------------------------------------------------------

function deferredFetch() {
    const calls = [];

    return { calls, fetchList: (query, page, signal) => new Promise((resolve) => calls.push({ query, page, signal, resolve })) };
}

const page = (ids, current, last) => ({ status: 200, body: { data: ids.map((id) => ({ id })), current_page: current, last_page: last } });

test('Load more asks once per page, drops repeats and stops at the last page', async () => {
    const { calls, fetchList } = deferredFetch();
    const loader = createLoader({ fetchList });

    const first = loader.reset({ vendorId: '1', search: '' });
    calls[0].resolve(page([1, 2], 1, 2));
    await first;
    assert.equal(loader.hasMore, true);

    const second = loader.more();
    assert.equal(await loader.more(), false, 'No second request while one runs.');
    calls[1].resolve(page([2, 3], 2, 2));
    await second;

    assert.deepEqual(loader.state.items.map((media) => media.id), [1, 2, 3]);
    assert.equal(loader.hasMore, false);
    assert.equal(calls.length, 2);
});

test('a new search or a closed chooser drops the answer still on its way', async () => {
    const { calls, fetchList } = deferredFetch();
    const loaded = [];
    const loader = createLoader({ fetchList, onChange: (event) => event.type === 'loaded' && loaded.push(event.added.map((media) => media.id)) });

    const old = loader.reset({ vendorId: '1', search: 'old' });
    const current = loader.reset({ vendorId: '1', search: 'new' });
    assert.equal(calls[0].signal?.aborted, true);
    calls[1].resolve(page([5], 1, 1));
    await current;
    calls[0].resolve(page([9], 1, 1));
    assert.equal(await old, false);
    assert.deepEqual(loaded, [[5]]);

    const pending = loader.reset({ vendorId: '1', search: '' });
    loader.stop();
    calls[2].resolve(page([7], 1, 1));
    assert.equal(await pending, false);
    assert.deepEqual(loader.state.items, []);
});

test('a failed page keeps what was loaded and Retry asks for it again', async () => {
    const { calls, fetchList } = deferredFetch();
    const loader = createLoader({ fetchList });

    const first = loader.reset({ vendorId: '1', search: '' });
    calls[0].resolve(page([1], 1, 2));
    await first;
    const second = loader.more();
    calls[1].resolve({ status: 0, body: null });
    await second;

    assert.equal(loader.state.error, 'network');
    assert.deepEqual(loader.state.items.map((media) => media.id), [1]);

    const retry = loader.retry();
    assert.equal(calls[2].page, 2);
    calls[2].resolve(page([2], 2, 2));
    await retry;
    assert.deepEqual(loader.state.items.map((media) => media.id), [1, 2]);
});
