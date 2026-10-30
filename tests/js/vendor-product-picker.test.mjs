// VPM-01B: the vendor product editor's image picker dialog (resources/js/vendor-product-editor.js).
// Run by Tests\Feature\VendorProductPickerTest (node --test), so it is part of php artisan test.
import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';

import {
    MAX_IMAGES,
    MAX_UPLOAD_FILES,
    initPicker,
    isAttached,
    pickerErrorText,
    pickerTitle,
    planChoice,
    renderChoice,
    uploadError,
    uploadPlan,
} from '../../resources/js/vendor-product-editor.js';

const PAGE = 'https://hirfah.test/vendor/dashboard/products/manage';
const LIST = 'https://hirfah.test/vendor/dashboard/products/media/library';

const messages = {
    titleMain: 'Choose the main product image',
    titleReplace: 'Choose a replacement image',
    titleGallery: 'Choose a gallery image',
    libraryCount: 'Images: :count',
    pageStatus: 'Page :page of :last',
    choose: 'Choose :name',
    attached: 'Already in this product',
    mediaAlt: 'Media library image',
    libraryError: 'Your media library could not be loaded.',
    session: 'Your session has ended. Sign in again to continue.',
    forbidden: 'You do not have permission to do this.',
    invalid: 'The request could not be processed. Reload the page and try again.',
    network: 'The connection failed. Check the network and try again.',
};

const medium = (id, extra = {}) => ({ id, path: `vendors/media/1/${id}.png`, alt_text: `Image ${id}`, url: `https://hirfah.test/storage/vendors/media/1/${id}.png`, ...extra });
const paged = (images, { page = 1, last = 1, total = images.length } = {}) => ({ status: 200, body: { images, pagination: { current_page: page, last_page: last, per_page: 24, total } } });

// ---- A minimal DOM ----

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
        this.clicks = 0;
    }

    set textContent(value) {
        this.children = [];
        this.text = String(value);
    }

    get textContent() {
        return this.text + this.children.map((child) => child.textContent).join('');
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
        if (type === 'click' && typeof this.onclick === 'function') this.onclick({ currentTarget: this });
    }

    click() {
        this.clicks += 1;
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
        return this.children.flatMap((child) => [child, ...child.all()]);
    }

    querySelectorAll(selector) {
        return selector === 'button' ? this.all().filter((node) => node.tagName === 'BUTTON') : [];
    }
}

const doc = { createElement: (tag) => new FakeElement(tag) };

const NAMES = ['title', 'close', 'search-form', 'search', 'summary', 'status', 'alert', 'loading', 'error', 'error-text', 'retry', 'empty', 'no-results',
    'items', 'pager', 'previous', 'next', 'page-status', 'upload', 'file'];

function fakeDialog() {
    const elements = Object.fromEntries(NAMES.map((name) => [name, new FakeElement(name === 'items' ? 'ul' : 'div')]));
    const root = new FakeElement('dialog');
    root.ownerDocument = doc;
    root.querySelector = (selector) => elements[/^\[data-product-media-(.+)\]$/.exec(selector)?.[1]] ?? null;
    const ui = Object.fromEntries(Object.entries(elements).map(([name, node]) => [name.replace(/-(\w)/g, (_, c) => c.toUpperCase()), node]));

    return { root, ui };
}

// ---- fetch, answered from a queue ----

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

function picker({ attached = [], chosen = [], uploads = [] } = {}) {
    const page = fakeDialog();
    const api = initPicker(page.root, {
        url: LIST,
        messages,
        isTaken: (item) => isAttached(attached, item),
        onChoose: (item) => chosen.push(item),
        onUpload: (files) => uploads.push(files),
    }, { location: { href: PAGE } });

    return { ...page, api, chosen, uploads };
}

async function opened(first, options = {}) {
    const setup = picker(options);
    const from = new FakeElement('button');
    respond(first);
    setup.api.open({ title: messages.titleGallery, from, fallback: () => setup.fallback });
    setup.from = from;
    setup.fallback = new FakeElement('button');
    await flush();

    return setup;
}

const query = (url) => Object.fromEntries(new URL(url).searchParams);
const openButton = (card) => card.children[0].children[0];

// ---------------------------------------------------------------------------------------------
// Pure helpers
// ---------------------------------------------------------------------------------------------

test('the title follows the mode: main image, gallery image, or a replacement', () => {
    assert.equal(pickerTitle(messages, { mode: 'primary', target: null }), messages.titleMain);
    assert.equal(pickerTitle(messages, { mode: 'gallery', target: null }), messages.titleGallery);
    assert.equal(pickerTitle(messages, { mode: 'replace', target: { id: 3 } }), messages.titleReplace);
    assert.equal(pickerTitle(messages, { mode: 'primary', target: { id: 1 } }), messages.titleReplace, 'Changing the main image replaces it.');
});

test('an image is attached when the product has its path', () => {
    const images = [{ id: 1, path: 'vendors/media/1/1.png' }, { id: 2, path: 'products/legacy.png' }];

    assert.equal(isAttached(images, medium(1)), true);
    assert.equal(isAttached(images, medium(5)), false);
    assert.equal(isAttached(images, { id: 9 }), false);
    assert.equal(isAttached([], medium(1)), false);
});

test('an upload sends 1 to 10 files; more than 10 is refused before anything is sent', () => {
    const files = (count) => Array.from({ length: count }, (_, i) => ({ name: `${i}.png` }));

    assert.equal(MAX_UPLOAD_FILES, 10);
    assert.deepEqual(uploadPlan([]), { ok: false, error: null });
    assert.deepEqual(uploadPlan(null), { ok: false, error: null });
    assert.equal(uploadPlan(files(1)).ok, true);
    assert.equal(uploadPlan(files(10)).files.length, 10);
    assert.deepEqual(uploadPlan(files(11)), { ok: false, error: 'uploadMax' });
});

test('a refused upload is named: too many files, an invalid file, or as the image requests', () => {
    assert.equal(uploadError({ status: 422, body: { errors: { files: ['You can upload up to 10 images at a time.'] } } }), 'uploadMax');
    assert.equal(uploadError({ status: 422, body: { errors: { 'files.0': ['The file must be an image.'] } } }), 'uploadInvalid');
    assert.equal(uploadError({ status: 422, body: null }), 'uploadInvalid');
    assert.equal(uploadError({ status: 401, body: null }), 'session');
    assert.equal(uploadError({ status: 419, body: null }), 'session');
    assert.equal(uploadError({ status: 403, body: null }), 'forbidden');
    assert.equal(uploadError({ status: 0, body: null }), 'network');
    assert.equal(uploadError({ status: 500, body: { message: 'SQLSTATE' } }), 'failed');
});

test('listing errors read the page texts', () => {
    assert.equal(pickerErrorText('network', messages), messages.network);
    assert.equal(pickerErrorText('session', messages), messages.session);
    assert.equal(pickerErrorText('forbidden', messages), messages.forbidden);
    assert.equal(pickerErrorText('invalid', messages), messages.invalid);
    assert.equal(pickerErrorText('failed', messages), messages.libraryError);
});

test('the six-image limit: a seventh is refused, replacing at six is allowed', () => {
    const six = Array.from({ length: MAX_IMAGES }, (_, i) => ({ id: i + 1, path: `p${i}.png`, sort_order: i, is_primary: i === 0 }));

    assert.deepEqual(planChoice(six, { mode: 'gallery', target: null }), { action: 'refuse', error: 'limit' });
    assert.deepEqual(planChoice(six, { mode: 'replace', target: six[3] }), { action: 'replace', target: six[3] });
});

// ---------------------------------------------------------------------------------------------
// A choice card
// ---------------------------------------------------------------------------------------------

test('a choice card: the admin chooser layout, safe text, and the attached note', () => {
    const hostile = '<img src=x onerror=alert(1)>';
    const picked = [];
    const card = renderChoice(doc, medium(4, { alt_text: hostile }), { messages, base: PAGE, taken: true, onChoose: (item) => picked.push(item) });
    const [thumb, meta] = card.children;
    const button = thumb.children[0];

    assert.equal(card.className, 'vendor-media-card is-taken');
    assert.deepEqual([thumb.className, button.className, meta.className], ['vendor-media-thumb', 'vendor-media-open', 'vendor-media-meta']);
    assert.equal(button.type, 'button');
    assert.equal(button.getAttribute('aria-label'), `Choose ${hostile}`);
    assert.equal(button.getAttribute('aria-describedby'), 'vendor-product-picker-taken-4');
    assert.equal(button.disabled, false, 'An attached image stays selectable (duplicates are a later phase).');
    assert.equal(button.children[0].alt, '');
    assert.equal(meta.children[0].textContent, hostile);
    assert.equal(meta.children[0].children.length, 0);
    assert.equal(meta.children[1].textContent, 'Already in this product');
    assert.deepEqual(card.all().map((node) => node.tagName), ['DIV', 'BUTTON', 'IMG', 'DIV', 'BDI', 'SPAN']);

    button.onclick();
    assert.equal(picked[0].path, 'vendors/media/1/4.png', 'The chosen library image, with its path.');
});

test('a choice card without a safe address shows a placeholder; one not attached has no note', () => {
    const card = renderChoice(doc, medium(4, { url: 'javascript:alert(1)', alt_text: '' }), { messages, base: PAGE, taken: false, onChoose() {} });

    assert.equal(card.className, 'vendor-media-card');
    assert.deepEqual(card.all().map((node) => node.tagName), ['DIV', 'BUTTON', 'I', 'DIV', 'BDI']);
    assert.equal(card.all()[3].children[0].textContent, 'Media library image');
    assert.equal(card.all()[1].getAttribute('aria-describedby'), null);
});

// ---------------------------------------------------------------------------------------------
// The dialog
// ---------------------------------------------------------------------------------------------

test('opening shows the dialog with its title, focuses the search and asks for page 1 of 24 only', async () => {
    const { root, ui } = await opened(paged([medium(9), medium(8)], { last: 2, total: 30 }), { attached: [{ path: 'vendors/media/1/8.png' }] });

    assert.equal(root.open, true);
    assert.equal(ui.title.textContent, messages.titleGallery);
    assert.equal(active, ui.search);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, `${LIST}?page=1&per_page=24`, 'Never the unpaged library.');
    assert.equal(requests[0].options.credentials, 'same-origin');
    assert.equal(root.dataset.state, 'images');
    assert.equal(ui.items.children.length, 2);
    assert.equal(ui.items.children[0].className, 'vendor-media-card');
    assert.equal(ui.items.children[1].className, 'vendor-media-card is-taken', 'Images already in the product are marked.');
    assert.equal(ui.summary.textContent, 'Images: 30');
    assert.equal(ui.pager.hidden, false);
    assert.equal(ui.pageStatus.textContent, 'Page 1 of 2');
});

test('closing gives focus back to the button that opened it, or to the fallback when it is gone', async () => {
    const first = await opened(paged([medium(1)]));
    first.ui.close.dispatch('click');
    assert.equal(first.root.open, false);
    assert.equal(active, first.from);

    const second = await opened(paged([medium(1)]));
    second.from.isConnected = false;
    second.root.dispatch('click', { target: second.root });
    assert.equal(second.root.open, false, 'A click on the backdrop closes it.');
    assert.equal(active, second.fallback);

    const third = await opened(paged([medium(1)]));
    third.from.disabled = true;
    third.root.close();
    assert.equal(active, third.fallback, 'A disabled opener (the limit reached) is not focused.');
});

test('choosing an image hands the library image to the editor', async () => {
    const { ui, chosen } = await opened(paged([medium(7)]));

    openButton(ui.items.children[0]).dispatch('click');
    assert.deepEqual(chosen.map((item) => item.path), ['vendors/media/1/7.png']);
});

test('search starts from page 1; no matches and an empty library are told apart', async () => {
    const { root, ui } = await opened(paged([medium(1)], { last: 3, total: 60 }));

    respond(paged([], { total: 0 }));
    ui.search.value = '  jug ';
    ui.searchForm.dispatch('submit');
    await flush();
    assert.equal(requests[1].url, `${LIST}?page=1&per_page=24&search=jug`);
    assert.equal(root.dataset.state, 'noResults');
    assert.equal(ui.noResults.hidden, false);
    assert.equal(ui.empty.hidden, true);
    assert.equal(ui.pager.hidden, true);

    const empty = await opened(paged([], { total: 0 }));
    assert.equal(empty.root.dataset.state, 'empty');
    assert.equal(empty.ui.empty.hidden, false);
    assert.equal(empty.ui.noResults.hidden, true);
});

test('typing searches once, after a pause', async (t) => {
    const { ui } = await opened(paged([medium(1)]));
    t.mock.timers.enable({ apis: ['setTimeout'] });

    for (const value of ['v', 'va', 'vase']) {
        ui.search.value = value;
        ui.search.dispatch('input');
        t.mock.timers.tick(100);
    }
    assert.equal(requests.length, 1);
    respond(paged([medium(2)]));
    t.mock.timers.tick(400);
    t.mock.timers.reset();
    await flush();

    assert.equal(query(requests[1].url).search, 'vase');
});

test('the pager moves one page of 24 and focuses the count', async () => {
    const { ui } = await opened(paged([medium(1)], { last: 2, total: 30 }));

    respond(paged([medium(2)], { page: 2, last: 2, total: 30 }));
    ui.next.dispatch('click');
    await flush();
    assert.equal(requests[1].url, `${LIST}?page=2&per_page=24`);
    assert.equal(ui.pageStatus.textContent, 'Page 2 of 2');
    assert.equal(ui.next.disabled, true);
    assert.equal(ui.previous.disabled, false);
    assert.equal(active, ui.summary);
});

test('loading and error states; retry for a lost connection, none for a lost session', async () => {
    const setup = picker();
    globalThis.fetch = () => new Promise(() => {});
    setup.api.open({ title: messages.titleMain });
    assert.equal(setup.root.dataset.state, 'loading');
    assert.equal(setup.ui.loading.hidden, false);
    assert.equal(setup.ui.items.getAttribute('aria-busy'), 'true');

    globalThis.fetch = async (url) => { requests.push({ url }); const answer = answers.shift(); if (answer === 'network') throw new TypeError('x'); return { status: answer.status, json: async () => answer.body }; };
    const offline = await opened('network');
    assert.equal(offline.root.dataset.state, 'error');
    assert.equal(offline.ui.errorText.textContent, messages.network);
    assert.equal(offline.ui.retry.hidden, false);
    respond(paged([medium(1)]));
    offline.ui.retry.dispatch('click');
    await flush();
    assert.equal(offline.root.dataset.state, 'images');

    const session = await opened({ status: 401, body: { message: '<b>server</b>' } });
    assert.equal(session.ui.errorText.textContent, messages.session);
    assert.equal(session.ui.retry.hidden, true);
});

test('upload: the button opens the file chooser; the chosen files go to the editor and the input is cleared', async () => {
    const { ui, uploads } = await opened(paged([medium(1)]));

    ui.upload.dispatch('click');
    assert.equal(ui.file.clicks, 1);
    ui.file.files = [{ name: 'a.png' }, { name: 'b.png' }];
    ui.file.value = 'C:\\fakepath\\a.png';
    ui.file.dispatch('change');
    assert.deepEqual(uploads, [[{ name: 'a.png' }, { name: 'b.png' }]]);
    assert.equal(ui.file.value, '');
});

test('while busy the upload and every choice are disabled; refresh asks page 1 again with the search', async () => {
    const { api, ui } = await opened(paged([medium(1), medium(2)]));

    api.setBusy(true);
    assert.equal(ui.upload.disabled, true);
    assert.ok(ui.items.querySelectorAll('button').every((button) => button.disabled));
    ui.upload.dispatch('click');
    assert.equal(ui.file.clicks, 0, 'No file chooser while busy.');
    api.setBusy(false);
    assert.equal(ui.upload.disabled, false);

    respond(paged([medium(3), medium(1), medium(2)]));
    api.refresh();
    await flush();
    assert.equal(requests.at(-1).url, `${LIST}?page=1&per_page=24`);
    assert.equal(ui.items.children[0].dataset.id, '3', 'The new upload first.');
});

test('the dialog shows its own status and alert texts', async () => {
    const { api, ui } = await opened(paged([medium(1)]));

    api.say('Uploading…');
    assert.equal(ui.status.textContent, 'Uploading…');
    api.alert('You can upload up to 10 images at a time.');
    assert.equal(ui.alert.hidden, false);
    assert.equal(ui.alert.textContent, 'You can upload up to 10 images at a time.');
    api.alert('');
    assert.equal(ui.alert.hidden, true);
});

test('only the vendor listing endpoint is ever requested, always paged', async () => {
    const { ui } = await opened(paged([medium(1)], { last: 2, total: 30 }));
    respond(paged([medium(2)], { page: 2, last: 2, total: 30 }));
    ui.next.dispatch('click');
    await flush();
    respond(paged([]));
    ui.search.value = 'x';
    ui.searchForm.dispatch('submit');
    await flush();

    for (const { url } of requests) {
        assert.ok(url.startsWith(`${LIST}?`), url);
        assert.equal(query(url).per_page, '24');
        assert.ok(!/vendor_id|vendor=|\/dashboard\/products\/vendor-media/.test(url), url);
    }
});
