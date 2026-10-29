// VENDOR-MEDIA-04B: the vendor Media Library gallery script (resources/js/vendor-media-library.js).
// Run by Tests\Feature\VendorMediaLibraryPageTest (node --test), so it is part of php artisan test.
import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';

import {
    PER_PAGE,
    canRetry,
    cleanSearch,
    createGallery,
    errorText,
    imageView,
    initMediaLibrary,
    interpretPage,
    listUrl,
    renderCard,
    safeUrl,
    viewState,
} from '../../resources/js/vendor-media-library.js';

const PAGE = 'https://hirfah.test/vendor/dashboard/media';
const LIST = 'https://hirfah.test/vendor/dashboard/products/media/library';

const messages = {
    count: 'Images: :count',
    pageStatus: 'Page :page of :last',
    open: 'Preview :name',
    untitled: 'Untitled image',
    loadError: 'Your media library could not be loaded.',
    invalid: 'The search could not be run. Change it and try again.',
    session: 'Your session has ended. Sign in again to continue.',
    forbidden: 'You do not have permission to do this.',
    network: 'The connection failed. Check the network and try again.',
};

const image = (id, extra = {}) => ({ id, path: `vendors/media/1/${id}.png`, alt_text: `Image ${id}`, url: `https://hirfah.test/storage/vendors/media/1/${id}.png`, ...extra });
const paged = (images, { page = 1, last = 1, total = images.length } = {}) => ({
    status: 200,
    body: { images, pagination: { current_page: page, last_page: last, per_page: PER_PAGE, total } },
});

// ---- A minimal DOM: enough to see what the script builds, and that text stays text ----

let active = null;

class FakeElement {
    constructor(tag) {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.attributes = {};
        this.dataset = {};
        this.listeners = {};
        this.text = '';
        this.className = '';
        this.hidden = false;
        this.disabled = false;
        this.value = '';
        this.open = false;
        this.isConnected = true;
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

    replaceChildren(...nodes) {
        this.text = '';
        this.children = nodes;
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

    dispatch(type, event = {}) {
        (this.listeners[type] ?? []).forEach((listener) => listener({ target: this, preventDefault() {}, ...event }));
    }

    focus() {
        active = this;
    }

    showModal() {
        this.open = true;
    }

    close() {
        this.open = false;
        this.dispatch('close');
    }

    all() {
        return this.children.filter((child) => typeof child !== 'string').flatMap((child) => [child, ...child.all()]);
    }
}

const doc = { createElement: (tag) => new FakeElement(tag) };

const NAMES = ['search-form', 'search', 'clear', 'summary', 'loading', 'error', 'error-text', 'retry', 'empty', 'no-results', 'grid', 'pager',
    'previous', 'next', 'page-status', 'dialog', 'dialog-image', 'dialog-caption', 'dialog-original', 'dialog-close'];

function fakeRoot() {
    const elements = Object.fromEntries(NAMES.map((name) => [name, new FakeElement(name === 'dialog' ? 'dialog' : 'div')]));
    const root = new FakeElement('div');
    root.ownerDocument = doc;
    root.querySelector = (selector) => elements[/^\[data-media-(.+)\]$/.exec(selector)?.[1]] ?? null;
    const ui = Object.fromEntries(Object.entries(elements).map(([name, node]) => [name.replace(/-(\w)/g, (_, c) => c.toUpperCase()), node]));

    return { root, ui };
}

// ---- fetch, answered from a queue; every requested URL is kept ----

let requests = [];
let answers = [];

const respond = (answer) => answers.push(answer);
const flush = async () => {
    for (let i = 0; i < 10; i += 1) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};

beforeEach(() => {
    requests = [];
    answers = [];
    active = null;
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        const answer = answers.shift() ?? paged([]);

        if (answer === 'network') {
            throw new TypeError('Failed to fetch');
        }

        return { status: answer.status, json: async () => answer.body };
    };
});

afterEach(() => {
    delete globalThis.fetch;
});

async function start(first) {
    respond(first);
    const page = fakeRoot();
    const api = initMediaLibrary(page.root, { url: LIST, messages }, { location: { href: PAGE } });
    await flush();

    return { ...page, api };
}

const cards = (ui) => ui.grid.children;
const openButton = (card) => card.children[0].children[0];
const query = (url) => Object.fromEntries(new URL(url).searchParams);

// ---------------------------------------------------------------------------------------------
// Requests and answers
// ---------------------------------------------------------------------------------------------

test('every request is one page of 24, never the whole library', () => {
    assert.equal(PER_PAGE, 24);
    assert.equal(listUrl(LIST), `${LIST}?page=1&per_page=24`);
    assert.equal(listUrl(LIST, { page: 3, search: '  jug ' }), `${LIST}?page=3&per_page=24&search=jug`);
    assert.equal(listUrl(LIST, { page: 0 }), `${LIST}?page=1&per_page=24`, 'A bad page is page 1.');
    assert.equal(listUrl(`${LIST}?lang=ar`, { page: 2 }), `${LIST}?lang=ar&page=2&per_page=24`);
    assert.deepEqual(query(listUrl(LIST, { search: 'سجادة & 100%' })), { page: '1', per_page: '24', search: 'سجادة & 100%' }, 'The search is encoded, not interpreted.');
});

test('a search is trimmed and kept to the 100 characters the listing accepts', () => {
    assert.equal(cleanSearch('  vase  '), 'vase');
    assert.equal(cleanSearch(null), '');
    assert.equal(Array.from(cleanSearch('ب'.repeat(150))).length, 100);
    assert.equal(Array.from(cleanSearch('😀'.repeat(101))).length, 100, 'Code points, as mb_strlen counts them.');
});

test('only http(s) image addresses are used', () => {
    assert.equal(safeUrl('https://hirfah.test/storage/a.png', PAGE), 'https://hirfah.test/storage/a.png');
    assert.equal(safeUrl('/storage/a.png', PAGE), 'https://hirfah.test/storage/a.png');
    for (const bad of ['javascript:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>', 'vbscript:x', '', '   ', null, 42]) {
        assert.equal(safeUrl(bad, PAGE), '', String(bad));
    }
});

test('a paged answer is read; anything else is a named error', () => {
    assert.deepEqual(interpretPage(paged([image(1)], { page: 2, last: 3, total: 50 })), { ok: true, images: [image(1)], page: 2, lastPage: 3, total: 50 });
    assert.deepEqual(interpretPage({ status: 200, body: { images: [image(1), null, 'x'], pagination: {} } }), { ok: true, images: [image(1)], page: 1, lastPage: 1, total: 0 });
    assert.deepEqual(interpretPage({ status: 200, body: { images: [image(1)] } }), { ok: false, error: 'failed' }, 'The unpaged shape is never taken as a page.');

    const errors = { 0: 'network', 401: 'session', 419: 'session', 403: 'forbidden', 422: 'invalid', 404: 'failed', 500: 'failed' };
    for (const [status, error] of Object.entries(errors)) {
        assert.deepEqual(interpretPage({ status: Number(status), body: { message: '<b>server text</b>' } }), { ok: false, error }, status);
    }
});

test('errors read the page texts, never the server message, and only some are retried', () => {
    assert.equal(errorText('network', messages), messages.network);
    assert.equal(errorText('session', messages), messages.session);
    assert.equal(errorText('forbidden', messages), messages.forbidden);
    assert.equal(errorText('invalid', messages), messages.invalid);
    assert.equal(errorText('failed', messages), messages.loadError);
    assert.deepEqual(['network', 'failed', 'session', 'forbidden', 'invalid'].map(canRetry), [true, true, false, false, false]);
});

test('a card names the image by its alt text, else "Untitled image"', () => {
    assert.deepEqual(imageView(image(7, { alt_text: '  إبريق  ' }), { messages, base: PAGE }), { id: '7', label: 'إبريق', url: 'https://hirfah.test/storage/vendors/media/1/7.png' });
    assert.equal(imageView(image(7, { alt_text: '' }), { messages, base: PAGE }).label, 'Untitled image');
    assert.equal(imageView(image(7, { alt_text: null, url: 'javascript:alert(1)' }), { messages, base: PAGE }).url, '');
});

// ---------------------------------------------------------------------------------------------
// Rendering untrusted metadata
// ---------------------------------------------------------------------------------------------

test('names stay text: markup in metadata is never parsed', () => {
    const hostile = '<img src=x onerror=alert(1)>"\'><script>alert(1)</script>';
    let opened = null;
    const card = renderCard(doc, imageView(image(5, { alt_text: hostile }), { messages, base: PAGE }), { messages, onOpen: (view) => { opened = view; } });

    const [thumb, meta] = card.children;
    const [button] = thumb.children;
    const [name] = meta.children;
    const [picture] = button.children;
    assert.equal(card.tagName, 'LI');
    assert.equal(card.className, 'vendor-media-card');
    assert.equal(card.dataset.id, '5');
    assert.deepEqual([thumb.className, meta.className, button.className, name.className], ['vendor-media-thumb', 'vendor-media-meta', 'vendor-media-open', 'vendor-media-name'], 'The admin card layout.');
    assert.equal(button.tagName, 'BUTTON');
    assert.equal(button.type, 'button');
    assert.equal(button.getAttribute('aria-label'), `Preview ${hostile}`);
    assert.equal(name.tagName, 'BDI');
    assert.equal(name.textContent, hostile);
    assert.equal(name.title, hostile);
    assert.deepEqual(card.all().map((node) => node.tagName), ['DIV', 'BUTTON', 'IMG', 'DIV', 'BDI'], 'Only the elements the script made.');
    assert.equal(picture.alt, '', 'The thumbnail is decorative; the button names it.');
    assert.equal(picture.loading, 'lazy');

    button.dispatch('click');
    assert.equal(opened.label, hostile);
});

test('an unsafe image address shows a placeholder, not an image', () => {
    const card = renderCard(doc, imageView(image(5, { url: 'javascript:alert(1)' }), { messages, base: PAGE }), { messages, onOpen() {} });

    assert.deepEqual(card.all().map((node) => node.tagName), ['DIV', 'BUTTON', 'I', 'DIV', 'BDI']);
    assert.equal(card.all()[2].getAttribute('aria-hidden'), 'true');
});

// ---------------------------------------------------------------------------------------------
// The paged gallery
// ---------------------------------------------------------------------------------------------

test('an answer the gallery has moved past is dropped', async () => {
    const pending = [];
    const gallery = createGallery({ fetchPage: (requested) => new Promise((resolve) => pending.push({ requested, resolve })) });

    const first = gallery.load({ page: 1, search: 'old' });
    const second = gallery.load({ page: 1, search: 'new' });
    pending[1].resolve(paged([image(2)]));
    pending[0].resolve(paged([image(1)]));

    assert.equal(await second, true);
    assert.equal(await first, false);
    assert.equal(gallery.state.search, 'new');
    assert.deepEqual(gallery.state.images.map((item) => item.id), [2]);
});

test('a page past the end shows the last page instead', async () => {
    const asked = [];
    const answersFor = { 5: paged([], { page: 5, last: 2, total: 30 }), 2: paged([image(25)], { page: 2, last: 2, total: 25 }) };
    const gallery = createGallery({ fetchPage: async (requested) => { asked.push(requested); return answersFor[requested.page]; } });

    assert.equal(await gallery.load({ page: 5 }), true);
    assert.deepEqual(asked, [{ page: 5, search: '' }, { page: 2, search: '' }]);
    assert.equal(gallery.state.page, 2);
    assert.equal(gallery.hasNext, false);
    assert.equal(gallery.hasPrevious, true);
});

test('retry repeats the request that failed', async () => {
    const asked = [];
    const queue = [{ status: 500, body: null }, paged([image(1)], { page: 2, last: 2, total: 25 })];
    const gallery = createGallery({ fetchPage: async (requested) => { asked.push(requested); return queue.shift(); } });

    assert.equal(await gallery.load({ page: 2, search: 'jug' }), false);
    assert.equal(gallery.state.error, 'failed');
    assert.equal(await gallery.retry(), true);
    assert.deepEqual(asked, [{ page: 2, search: 'jug' }, { page: 2, search: 'jug' }]);
});

test('the shown state: loading, error, empty, no results, images', () => {
    const base = { loading: false, error: null, images: [], search: '' };
    assert.equal(viewState({ ...base, loading: true }), 'loading');
    assert.equal(viewState({ ...base, error: 'failed' }), 'error');
    assert.equal(viewState(base), 'empty');
    assert.equal(viewState({ ...base, search: 'jug' }), 'noResults');
    assert.equal(viewState({ ...base, images: [image(1)] }), 'images');
});

// ---------------------------------------------------------------------------------------------
// The page, against the listing endpoint
// ---------------------------------------------------------------------------------------------

test('the page loads page 1 of 24 and shows the grid, count and pager', async () => {
    const images = Array.from({ length: 24 }, (_, i) => image(100 - i));
    const { root, ui } = await start(paged(images, { page: 1, last: 2, total: 30 }));

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, `${LIST}?page=1&per_page=24`);
    assert.equal(requests[0].options.credentials, 'same-origin');
    assert.equal(requests[0].options.headers.Accept, 'application/json');
    assert.notEqual(requests[0].options.method, 'POST');

    assert.equal(root.dataset.state, 'images');
    assert.equal(cards(ui).length, 24);
    assert.equal(cards(ui)[0].dataset.id, '100');
    assert.equal(ui.summary.textContent, 'Images: 30');
    assert.equal(ui.grid.hidden, false);
    assert.equal(ui.grid.getAttribute('aria-busy'), 'false');
    assert.deepEqual([ui.loading.hidden, ui.error.hidden, ui.empty.hidden, ui.noResults.hidden], [true, true, true, true]);
    assert.equal(ui.pager.hidden, false);
    assert.equal(ui.previous.disabled, true);
    assert.equal(ui.next.disabled, false);
    assert.equal(ui.pageStatus.textContent, 'Page 1 of 2');
});

test('the pager moves one page and moves focus to the summary', async () => {
    const { ui } = await start(paged([image(1)], { page: 1, last: 2, total: 25 }));

    respond(paged([image(2)], { page: 2, last: 2, total: 25 }));
    ui.next.dispatch('click');
    assert.equal(ui.next.disabled, true, 'The pager waits while a page loads.');
    await flush();

    assert.equal(requests[1].url, `${LIST}?page=2&per_page=24`);
    assert.equal(cards(ui)[0].dataset.id, '2');
    assert.equal(ui.pageStatus.textContent, 'Page 2 of 2');
    assert.equal(ui.next.disabled, true);
    assert.equal(ui.previous.disabled, false);
    assert.equal(active, ui.summary);

    ui.next.dispatch('click');
    await flush();
    assert.equal(requests.length, 2, 'No page past the last.');

    respond(paged([image(1)], { page: 1, last: 2, total: 25 }));
    ui.previous.dispatch('click');
    await flush();
    assert.equal(requests[2].url, `${LIST}?page=1&per_page=24`);
});

test('one page of images has no pager', async () => {
    const { ui } = await start(paged([image(1), image(2)]));

    assert.equal(ui.pager.hidden, true);
});

test('a search starts again from page 1, and a search without matches says so', async () => {
    const { root, ui } = await start(paged([image(1)], { page: 1, last: 3, total: 60 }));

    respond(paged([], { total: 0 }));
    ui.search.value = '  jug  ';
    ui.search.dispatch('input');
    assert.equal(ui.clear.hidden, false);
    ui.searchForm.dispatch('submit');
    await flush();

    assert.equal(requests[1].url, `${LIST}?page=1&per_page=24&search=jug`);
    assert.equal(root.dataset.state, 'noResults');
    assert.equal(ui.noResults.hidden, false);
    assert.equal(ui.empty.hidden, true);
    assert.equal(ui.grid.hidden, true);
    assert.equal(ui.pager.hidden, true);
    assert.equal(ui.summary.textContent, 'Images: 0');

    ui.searchForm.dispatch('submit');
    await flush();
    assert.equal(requests.length, 2, 'The same search is not asked again.');

    respond(paged([image(1)], { page: 1, last: 3, total: 60 }));
    ui.clear.dispatch('click');
    await flush();
    assert.equal(requests[2].url, `${LIST}?page=1&per_page=24`);
    assert.equal(ui.search.value, '');
    assert.equal(ui.clear.hidden, true);
    assert.equal(active, ui.search);
    assert.equal(root.dataset.state, 'images');
});

test('typing searches once, after a pause', async (t) => {
    const { ui } = await start(paged([image(1)]));
    t.mock.timers.enable({ apis: ['setTimeout'] });

    for (const value of ['v', 'va', 'vas', 'vase']) {
        ui.search.value = value;
        ui.search.dispatch('input');
        t.mock.timers.tick(100);
    }
    assert.equal(requests.length, 1);

    respond(paged([image(3)]));
    t.mock.timers.tick(400);
    t.mock.timers.reset();
    await flush();

    assert.equal(requests.length, 2);
    assert.equal(query(requests[1].url).search, 'vase');
});

test('an empty library shows the empty state, not "no results"', async () => {
    const { root, ui } = await start(paged([], { total: 0 }));

    assert.equal(root.dataset.state, 'empty');
    assert.equal(ui.empty.hidden, false);
    assert.equal(ui.noResults.hidden, true);
    assert.equal(ui.grid.hidden, true);
    assert.equal(ui.pager.hidden, true);
    assert.equal(cards(ui).length, 0);
});

test('while loading only the loading state shows', async () => {
    const page = fakeRoot();
    globalThis.fetch = () => new Promise(() => {});
    initMediaLibrary(page.root, { url: LIST, messages }, { location: { href: PAGE } });

    assert.equal(page.root.dataset.state, 'loading');
    assert.equal(page.ui.loading.hidden, false);
    assert.equal(page.ui.grid.getAttribute('aria-busy'), 'true');
    assert.deepEqual([page.ui.error.hidden, page.ui.empty.hidden, page.ui.noResults.hidden, page.ui.grid.hidden], [true, true, true, true]);
});

test('a failed load shows the page text with a retry; a lost session has no retry', async () => {
    const { root, ui } = await start({ status: 500, body: { message: '<b>SQLSTATE secret</b>' } });

    assert.equal(root.dataset.state, 'error');
    assert.equal(ui.error.hidden, false);
    assert.equal(ui.errorText.textContent, messages.loadError);
    assert.equal(ui.retry.hidden, false);
    assert.equal(ui.grid.hidden, true);

    respond(paged([image(1)]));
    ui.retry.dispatch('click');
    await flush();
    assert.equal(requests[1].url, `${LIST}?page=1&per_page=24`);
    assert.equal(root.dataset.state, 'images');

    const session = await start({ status: 401, body: null });
    assert.equal(session.ui.errorText.textContent, messages.session);
    assert.equal(session.ui.retry.hidden, true);

    const offline = await start('network');
    assert.equal(offline.ui.errorText.textContent, messages.network);
    assert.equal(offline.ui.retry.hidden, false);
});

test('the preview dialog shows the image and gives focus back to its card', async () => {
    const hostile = '<svg onload=alert(1)>';
    const { ui } = await start(paged([image(9, { alt_text: hostile })]));
    const button = openButton(cards(ui)[0]);

    button.dispatch('click');
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.dialogImage.src, 'https://hirfah.test/storage/vendors/media/1/9.png');
    assert.equal(ui.dialogImage.alt, hostile);
    assert.equal(ui.dialogImage.hidden, false);
    assert.equal(ui.dialogCaption.textContent, hostile);
    assert.equal(ui.dialogCaption.children.length, 0);
    assert.equal(ui.dialogOriginal.href, 'https://hirfah.test/storage/vendors/media/1/9.png');

    ui.dialogClose.dispatch('click');
    assert.equal(ui.dialog.open, false);
    assert.equal(active, button);

    button.dispatch('click');
    ui.dialog.dispatch('click', { target: ui.dialog });
    assert.equal(ui.dialog.open, false, 'A click on the backdrop closes it.');
});

test('an image without a safe address previews without an image or link', async () => {
    const { ui } = await start(paged([image(9, { url: 'javascript:alert(1)' })]));

    openButton(cards(ui)[0]).dispatch('click');
    assert.equal(ui.dialogImage.hidden, true);
    assert.equal(ui.dialogOriginal.hidden, true);
    assert.equal(ui.dialogOriginal.href, '#');
});

test('only the vendor listing endpoint is ever requested', async () => {
    const { ui } = await start(paged([image(1)], { page: 1, last: 2, total: 30 }));
    respond(paged([image(2)], { page: 2, last: 2, total: 30 }));
    ui.next.dispatch('click');
    await flush();
    respond(paged([]));
    ui.search.value = 'x';
    ui.searchForm.dispatch('submit');
    await flush();

    assert.equal(requests.length, 3);
    for (const { url } of requests) {
        assert.ok(url.startsWith(`${LIST}?`), url);
        assert.equal(query(url).per_page, '24');
        assert.ok(!/vendor_id|vendor=|dashboard\/vendor-media/.test(url), url);
    }
});

test('the module never touches a document when there is none', () => {
    assert.equal(typeof document, 'undefined');
});
