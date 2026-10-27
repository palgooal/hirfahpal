// ADMIN-MEDIA-ORGANIZATION-01 P4: the read-only vendor media page script (resources/js/admin-vendor-media.js).
// Run by Tests\Feature\AdminVendorMediaPageTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    canRetry,
    createListLoader,
    createRouter,
    detailFacts,
    errorText,
    folderView,
    foldersUrl,
    hrefForVendor,
    interpretList,
    mediaUrl,
    renderFacts,
    renderFolderCard,
    renderImageCard,
    safeUrl,
    statusBadge,
    vendorFromHref,
} from '../../resources/js/admin-vendor-media.js';

const PAGE = 'https://hirfah.test/dashboard/vendor-media';
const FOLDERS = 'https://hirfah.test/dashboard/vendor-media/folders';
const MEDIA = 'https://hirfah.test/dashboard/vendor-media/folders/__ID__/media';

const messages = {
    accountStatus: 'Account status',
    approvalStatus: 'Approval status',
    count: 'Images: :count',
    open: 'Details of :name',
    notFound: 'This vendor folder could not be found.',
    sessionExpired: 'Your session has ended. Sign in again to continue.',
    forbidden: 'You do not have permission to do this.',
    invalidRequest: 'The request could not be processed. Change the search and try again.',
    network: 'The connection failed. Check the network and try again.',
    notSet: 'Not set',
    fieldOriginalName: 'File name',
    fieldAlt: 'Alternative text',
    fieldTitle: 'Title',
    fieldType: 'Type',
    fieldSize: 'Size',
    fieldDimensions: 'Dimensions',
    fieldUploadedAt: 'Uploaded on',
};

const statuses = {
    account: {
        active: { label: 'Active', tone: 'is-active' },
        pending: { label: 'Pending activation', tone: 'is-low' },
        blocked: { label: 'Blocked', tone: 'is-suspended' },
    },
    approval: {
        pending: { label: 'Pending review', tone: 'is-low' },
        approved: { label: 'Approved', tone: 'is-active' },
        rejected: { label: 'Rejected', tone: 'is-suspended' },
    },
    noProfile: 'No store profile',
};

// ---- A minimal DOM: enough to see what the render functions build, and that text stays text ----

class FakeElement {
    constructor(tag) {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.attributes = {};
        this.dataset = {};
        this.listeners = {};
        this.text = '';
        this.className = '';
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

    dispatch(type, event) {
        (this.listeners[type] ?? []).forEach((listener) => listener(event));
    }

    all() {
        return this.children.filter((child) => typeof child !== 'string').flatMap((child) => [child, ...child.all()]);
    }
}

const doc = { createElement: (tag) => new FakeElement(tag) };

const click = (overrides = {}) => {
    const event = { button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false, defaultPrevented: false, ...overrides };
    event.preventDefault = () => {
        event.defaultPrevented = true;
    };

    return event;
};

// ---- A history with Back and Forward ----

function fakeWindow(href) {
    const entries = [{ href, state: null }];
    let index = 0;
    const popListeners = [];
    const pop = () => popListeners.forEach((listener) => listener());

    return {
        entries,
        location: {
            get href() {
                return entries[index].href;
            },
        },
        history: {
            get state() {
                return entries[index].state;
            },
            pushState(state, title, url) {
                entries.splice(index + 1);
                entries.push({ href: url, state });
                index += 1;
            },
            replaceState(state, title, url) {
                entries[index] = { href: url, state };
            },
            back() {
                if (index > 0) {
                    index -= 1;
                    pop();
                }
            },
            forward() {
                if (index < entries.length - 1) {
                    index += 1;
                    pop();
                }
            },
            get length() {
                return entries.length;
            },
        },
        addEventListener(type, listener) {
            if (type === 'popstate') {
                popListeners.push(listener);
            }
        },
    };
}

// ---- A request the test answers when it wants ----

function deferredFetch() {
    const calls = [];
    const fetchList = (query, page, signal) => new Promise((resolve) => {
        calls.push({ query, page, signal, resolve });
    });

    return { calls, fetchList };
}

const page = (data, current, last, total = null, extra = {}) => ({ status: 200, body: { data, current_page: current, last_page: last, per_page: 2, total: total ?? data.length, ...extra } });
const tick = () => new Promise((resolve) => setImmediate(resolve));

// ---------------------------------------------------------------------------------------------
// URLs and ?vendor=
// ---------------------------------------------------------------------------------------------

test('the folders URL carries the page, size, search and filters only when set', () => {
    assert.equal(foldersUrl(FOLDERS, { page: 1, perPage: 24 }), `${FOLDERS}?page=1&per_page=24`);

    const url = new URL(foldersUrl(FOLDERS, { page: 3, perPage: 24, search: 'فخار & co', status: 'blocked', hasMedia: true }));
    assert.equal(url.searchParams.get('page'), '3');
    assert.equal(url.searchParams.get('search'), 'فخار & co');
    assert.equal(url.searchParams.get('status'), 'blocked');
    assert.equal(url.searchParams.get('has_media'), '1');

    assert.equal(new URL(foldersUrl(FOLDERS, { page: 1, perPage: 24, hasMedia: false })).searchParams.has('has_media'), false);
});

test('the media URL fills the vendor id and refuses an id the route would not accept', () => {
    assert.equal(mediaUrl(MEDIA, '12', { page: 2, perPage: 40, search: 'jug' }), 'https://hirfah.test/dashboard/vendor-media/folders/12/media?page=2&per_page=40&search=jug');

    for (const id of ['0', '01', '-1', '1x', '../1', '', '12345678901234567890', null, undefined]) {
        assert.equal(mediaUrl(MEDIA, id, { page: 1, perPage: 40 }), null, String(id));
    }
});

test('?vendor= is read strictly and written without touching other parameters', () => {
    assert.deepEqual(vendorFromHref(PAGE), { id: null, invalid: false });
    assert.deepEqual(vendorFromHref(`${PAGE}?vendor=7`), { id: '7', invalid: false });

    for (const value of ['abc', '0', '07', '7 ', '-7', '', '7/../8']) {
        assert.deepEqual(vendorFromHref(`${PAGE}?vendor=${encodeURIComponent(value)}`), { id: null, invalid: true }, value);
    }

    assert.equal(hrefForVendor(`${PAGE}?lang=ar#top`, '7'), `${PAGE}?lang=ar&vendor=7`);
    assert.equal(hrefForVendor(`${PAGE}?vendor=7&lang=ar`, null), `${PAGE}?lang=ar`);
});

test('only http and https image addresses are used', () => {
    assert.equal(safeUrl('https://cdn.test/a.png', PAGE), 'https://cdn.test/a.png');
    assert.equal(safeUrl('/storage/vendors/media/7/a.png', PAGE), 'https://hirfah.test/storage/vendors/media/7/a.png');

    for (const value of ['javascript:alert(1)', 'data:image/png;base64,AAAA', 'vbscript:x', '', '   ', null, 42]) {
        assert.equal(safeUrl(value, PAGE), '', String(value));
    }
});

// ---------------------------------------------------------------------------------------------
// Server answers and errors
// ---------------------------------------------------------------------------------------------

test('a list answer is read defensively', () => {
    const outcome = interpretList(page([{ id: 1 }, null, 'x', { id: 2 }], 1, 3, 5, { vendor: { vendor_id: 7 } }));

    assert.equal(outcome.ok, true);
    assert.deepEqual(outcome.data, [{ id: 1 }, { id: 2 }]);
    assert.equal(outcome.page, 1);
    assert.equal(outcome.lastPage, 3);
    assert.equal(outcome.total, 5);
    assert.deepEqual(outcome.vendor, { vendor_id: 7 });

    const odd = interpretList({ status: 200, body: { data: [], current_page: 'x', last_page: -1, total: 'many' } });
    assert.deepEqual([odd.page, odd.lastPage, odd.total], [1, 1, null]);

    assert.deepEqual(interpretList({ status: 200, body: { message: 'no data' } }), { ok: false, error: 'failed' });
});

test('every failure is named and shown in the page language, never as the server message', () => {
    const cases = [
        [0, 'network', messages.network],
        [401, 'session', messages.sessionExpired],
        [419, 'session', messages.sessionExpired],
        [403, 'forbidden', messages.forbidden],
        [404, 'notFound', messages.notFound],
        [422, 'invalid', messages.invalidRequest],
        [500, 'failed', 'fallback'],
    ];

    for (const [status, error, text] of cases) {
        const outcome = interpretList({ status, body: { message: 'No query results for model [App\\Models\\Vendor] 999' } });

        assert.deepEqual(outcome, { ok: false, error }, String(status));
        assert.equal(errorText(outcome.error, messages, 'fallback'), text, String(status));
    }

    assert.equal(canRetry('network'), true);
    assert.equal(canRetry('failed'), true);
    assert.equal(['session', 'forbidden', 'notFound', 'invalid'].some(canRetry), false, 'Trying again cannot fix these.');
});

// ---------------------------------------------------------------------------------------------
// Folders, badges, details
// ---------------------------------------------------------------------------------------------

test('a folder shows the store name, else the vendor name, and the vendor name only when it differs', () => {
    const store = folderView({ vendor_id: 7, label: 'Clay House', store_name: 'Clay House', vendor_name: 'Ahmad', status: 'active', approval_status: 'approved', media_count: 3 }, statuses);
    assert.deepEqual(store, {
        id: '7',
        label: 'Clay House',
        secondary: 'Ahmad',
        account: { text: 'Active', tone: 'is-active' },
        approval: { text: 'Approved', tone: 'is-active' },
        count: 3,
    });

    const noProfile = folderView({ vendor_id: 8, label: 'Sara', store_name: null, vendor_name: 'Sara', status: 'pending', approval_status: null, media_count: 0 }, statuses);
    assert.equal(noProfile.label, 'Sara');
    assert.equal(noProfile.secondary, '');
    assert.deepEqual(noProfile.approval, { text: 'No store profile', tone: 'is-muted' });
    assert.equal(noProfile.count, 0);

    const blank = folderView({ vendor_id: 9, label: '  ', vendor_name: '', status: 'archived', media_count: -2 }, statuses);
    assert.equal(blank.label, '#9');
    assert.deepEqual(blank.account, { text: 'archived', tone: 'is-muted' }, 'An unknown status stays readable.');
    assert.equal(blank.count, 0);
});

test('badges use the translated labels of the page', () => {
    const arabic = { account: { blocked: { label: 'محظور', tone: 'is-suspended' } }, approval: {}, noProfile: 'بلا ملف متجر' };

    assert.deepEqual(statusBadge('account', 'blocked', arabic), { text: 'محظور', tone: 'is-suspended' });
    assert.deepEqual(statusBadge('approval', undefined, arabic), { text: 'بلا ملف متجر', tone: 'is-muted' });
});

test('the details are read-only facts from P3 fields, with "Not set" for empty values', () => {
    const facts = detailFacts({
        id: 5, url: 'https://x/a.png', file_original_name: 'jug.png', alt: '', title: null, mime_type: 'image/png', size: 2048, width: 800, height: 600, created_at: '2026-10-27T10:00:00.000000Z',
        // Fields P3 never sends are ignored even if present.
        file_path: 'vendors/media/7/a.png', uploader_id: 7, caption: 'Cap',
    }, { locale: 'en', messages });

    assert.deepEqual(facts.map((fact) => fact.key), ['name', 'alt', 'title', 'type', 'size', 'dimensions', 'date']);
    assert.deepEqual(facts.map((fact) => fact.label), ['File name', 'Alternative text', 'Title', 'Type', 'Size', 'Dimensions', 'Uploaded on']);

    const value = Object.fromEntries(facts.map((fact) => [fact.key, fact.value]));
    assert.equal(value.name, 'jug.png');
    assert.equal(value.alt, 'Not set');
    assert.equal(value.title, 'Not set');
    assert.equal(value.type, 'image/png');
    assert.match(value.size, /^2\s?kB$/);
    assert.equal(value.dimensions, '800 × 600');
    assert.match(value.date, /2026/);
    assert.ok(!JSON.stringify(facts).includes('vendors/media'), 'No storage path in the details.');
    assert.deepEqual(facts.filter((fact) => fact.ltr).map((fact) => fact.key), ['type', 'dimensions']);

    const empty = Object.fromEntries(detailFacts({ id: 6 }, { locale: 'ar', messages }).map((fact) => [fact.key, fact.value]));
    assert.deepEqual(Object.values(empty), Array(7).fill('Not set'));
});

// ---------------------------------------------------------------------------------------------
// Load more, stale answers
// ---------------------------------------------------------------------------------------------

test('Load more asks for each next page once, drops repeated items and stops at the last page', async () => {
    const { calls, fetchList } = deferredFetch();
    const events = [];
    const loader = createListLoader({ fetchList, onChange: (event) => events.push(event.type) });

    const first = loader.reset({ search: '' });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].page, 1);
    calls[0].resolve(page([{ id: 1 }, { id: 2 }], 1, 2, 3));
    await first;

    assert.equal(loader.hasMore, true);

    const second = loader.more();
    assert.equal(await loader.more(), false, 'No second request while one runs.');
    assert.equal(calls.length, 2);
    assert.equal(calls[1].page, 2);
    calls[1].resolve(page([{ id: 2 }, { id: 3 }], 2, 2, 3));
    await second;

    assert.deepEqual(loader.state.items.map((item) => item.id), [1, 2, 3], 'Item 2 is not repeated.');
    assert.equal(loader.hasMore, false);
    assert.equal(await loader.more(), false);
    assert.equal(calls.length, 2, 'Nothing is asked after the last page.');
    assert.deepEqual(events, ['reset', 'loading', 'loaded', 'loading', 'loaded']);
});

test('a new search starts again from page 1 and a slower old answer never shows', async () => {
    const { calls, fetchList } = deferredFetch();
    const loaded = [];
    const loader = createListLoader({ fetchList, onChange: (event) => event.type === 'loaded' && loaded.push(event.added.map((item) => item.id)) });

    const old = loader.reset({ vendorId: '1', search: '' });
    const current = loader.reset({ vendorId: '10', search: '' });

    assert.equal(calls[0].signal?.aborted, true, 'The old request is aborted.');
    assert.equal(calls[1].page, 1);

    calls[1].resolve(page([{ id: 100 }], 1, 1));
    await current;
    calls[0].resolve(page([{ id: 1 }], 1, 1));
    assert.equal(await old, false);

    assert.deepEqual(loader.state.items.map((item) => item.id), [100]);
    assert.deepEqual(loaded, [[100]], 'Vendor 1 never reaches the page.');
    assert.equal(loader.state.query.vendorId, '10');
});

test('stop() drops an answer still on its way', async () => {
    const { calls, fetchList } = deferredFetch();
    const loader = createListLoader({ fetchList });

    const pending = loader.reset({ vendorId: '7' });
    loader.stop();
    calls[0].resolve(page([{ id: 1 }], 1, 1));

    assert.equal(await pending, false);
    assert.deepEqual(loader.state.items, []);
    assert.equal(loader.state.loading, false);
});

test('a failed next page keeps the loaded items and retry asks for that page again', async () => {
    const { calls, fetchList } = deferredFetch();
    const errors = [];
    const loader = createListLoader({ fetchList, onChange: (event) => event.type === 'error' && errors.push(event.error) });

    const first = loader.reset({});
    calls[0].resolve(page([{ id: 1 }, { id: 2 }], 1, 3, 6));
    await first;

    const second = loader.more();
    calls[1].resolve({ status: 0, body: null });
    await second;

    assert.deepEqual(errors, ['network']);
    assert.deepEqual(loader.state.items.map((item) => item.id), [1, 2], 'Nothing loaded is lost.');
    assert.equal(loader.hasMore, false, 'Load more waits for the retry.');

    const retry = loader.retry();
    assert.equal(calls[2].page, 2);
    calls[2].resolve(page([{ id: 3 }, { id: 4 }], 2, 3, 6));
    await retry;

    assert.deepEqual(loader.state.items.map((item) => item.id), [1, 2, 3, 4]);
    assert.equal(loader.hasMore, true);
});

test('a failed first page can be retried, and retry does nothing without an error', async () => {
    const { calls, fetchList } = deferredFetch();
    const loader = createListLoader({ fetchList });

    assert.equal(await loader.retry(), false, 'Nothing to retry before a query.');

    const first = loader.reset({ search: 'x' });
    calls[0].resolve({ status: 500, body: null });
    await first;
    assert.equal(loader.state.error, 'failed');

    const retry = loader.retry();
    assert.equal(calls[1].page, 1);
    calls[1].resolve(page([], 1, 1, 0));
    await retry;

    assert.equal(loader.state.error, null);
    assert.equal(await loader.retry(), false);
    assert.equal(calls.length, 2);
});

test('folders are keyed by vendor id', async () => {
    const { calls, fetchList } = deferredFetch();
    const loader = createListLoader({ fetchList, keyOf: (folder) => String(folder.vendor_id) });

    const first = loader.reset({});
    calls[0].resolve(page([{ vendor_id: 1 }, { vendor_id: 10 }], 1, 2));
    await first;
    const second = loader.more();
    calls[1].resolve(page([{ vendor_id: 10 }, { vendor_id: 11 }], 2, 2));
    await second;

    assert.deepEqual(loader.state.items.map((folder) => folder.vendor_id), [1, 10, 11]);
});

// ---------------------------------------------------------------------------------------------
// History
// ---------------------------------------------------------------------------------------------

test('opening a folder pushes ?vendor=ID and Back and Forward follow it', async () => {
    const win = fakeWindow(`${PAGE}?lang=ar`);
    const routes = [];
    const router = createRouter(win, (route, how) => routes.push([route.id, how]));

    assert.deepEqual(router.current(), { id: null, invalid: false });

    router.open('7');
    assert.equal(win.location.href, `${PAGE}?lang=ar&vendor=7`);

    win.history.back();
    assert.equal(win.location.href, `${PAGE}?lang=ar`);

    win.history.forward();
    assert.equal(win.location.href, `${PAGE}?lang=ar&vendor=7`);

    assert.deepEqual(routes, [['7', 'push'], [null, 'pop'], ['7', 'pop']]);
});

test('Back to vendors goes back when the folder came from the list, else opens the list', () => {
    const win = fakeWindow(PAGE);
    const routes = [];
    const router = createRouter(win, (route, how) => routes.push([route.id, how]));

    router.open('7');
    router.toList();
    assert.equal(win.location.href, PAGE);
    assert.equal(win.entries.length, 2, 'No extra entry: Forward still returns to the folder.');

    // Opened directly (a shared link or a refresh): there is no list entry to go back to.
    const direct = fakeWindow(`${PAGE}?vendor=9`);
    const directRoutes = [];
    createRouter(direct, (route, how) => directRoutes.push([route.id, how])).toList();
    assert.equal(direct.location.href, PAGE);
    assert.equal(direct.entries.length, 2);
    assert.deepEqual(directRoutes, [[null, 'push']]);

    direct.history.back();
    assert.deepEqual(directRoutes.at(-1), ['9', 'pop'], 'Browser Back returns to the folder.');
    assert.deepEqual(routes, [['7', 'push'], [null, 'pop']]);
});

test('an invalid ?vendor= is replaced by the list without adding history', () => {
    const win = fakeWindow(`${PAGE}?vendor=abc`);
    const router = createRouter(win, () => {});

    assert.deepEqual(router.current(), { id: null, invalid: true });
    router.clearInvalid();

    assert.equal(win.location.href, PAGE);
    assert.equal(win.entries.length, 1);
});

// ---------------------------------------------------------------------------------------------
// Rendering: text stays text, links stay links, nothing to edit
// ---------------------------------------------------------------------------------------------

const hostile = '<img src=x onerror="alert(1)">';

test('a folder card is a real link whose texts are plain text', () => {
    const opened = [];
    const view = folderView({ vendor_id: 7, label: hostile, vendor_name: 'Ahmad <b>', status: 'blocked', approval_status: 'rejected', media_count: 2 }, statuses);
    const card = renderFolderCard(doc, view, { href: `${PAGE}?vendor=7`, messages, onOpen: (id) => opened.push(id) });
    const link = card.children[0];

    assert.equal(card.dataset.vendorId, '7');
    assert.equal(link.tagName, 'A');
    assert.equal(link.href, `${PAGE}?vendor=7`);
    assert.ok(card.textContent.includes(hostile), 'The name is shown as typed.');
    assert.ok(card.textContent.includes('Ahmad <b>'));
    assert.equal(card.all().filter((node) => node.tagName === 'IMG' || node.tagName === 'B').length, 0, 'No element comes from server text.');
    assert.ok(card.textContent.includes('Account status: Blocked'));
    assert.ok(card.textContent.includes('Approval status: Rejected'));
    assert.ok(card.textContent.includes('Images: 2'));

    const plain = click();
    link.dispatch('click', plain);
    assert.equal(plain.defaultPrevented, true);

    for (const modifier of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey']) {
        const event = click({ [modifier]: true });
        link.dispatch('click', event);
        assert.equal(event.defaultPrevented, false, `${modifier}: the browser opens the link itself.`);
    }

    link.dispatch('click', click({ button: 1 }));
    assert.deepEqual(opened, ['7']);
});

test('an image card opens the details and offers no selection or action', () => {
    const opened = [];
    const item = { id: 5, url: 'javascript:alert(1)', file_original_name: hostile, size: 4 };
    const card = renderImageCard(doc, item, { messages, locale: 'en', base: PAGE, onOpen: (picked) => opened.push(picked.id) });
    const nodes = card.all();
    const button = nodes.find((node) => node.tagName === 'BUTTON');
    const image = nodes.find((node) => node.tagName === 'IMG');

    assert.equal(card.dataset.id, '5');
    assert.equal(image.src, '', 'An unsafe address is never loaded.');
    assert.equal(image.alt, '');
    assert.equal(button.getAttribute('aria-label'), `Details of ${hostile}`);
    assert.ok(card.textContent.includes(hostile));
    assert.deepEqual(nodes.map((node) => node.tagName).filter((tag) => ['INPUT', 'LABEL', 'FORM', 'SELECT', 'TEXTAREA'].includes(tag)), [], 'No checkbox or field.');
    assert.equal(nodes.filter((node) => node.tagName === 'BUTTON').length, 1, 'Only the details button.');

    button.dispatch('click', click());
    assert.deepEqual(opened, [5]);

    const safe = renderImageCard(doc, { id: 6, url: '/storage/vendors/media/7/a.png' }, { messages, locale: 'en', base: PAGE, onOpen: () => {} });
    assert.equal(safe.all().find((node) => node.tagName === 'IMG').src, 'https://hirfah.test/storage/vendors/media/7/a.png');
    assert.ok(safe.textContent.includes('#6'), 'An image without a name is still named.');
});

test('the details list is plain text, with left-to-right values marked', () => {
    const list = doc.createElement('dl');
    renderFacts(doc, list, detailFacts({ id: 5, file_original_name: hostile, mime_type: 'image/png', width: 10, height: 20 }, { locale: 'ar', messages }));

    assert.equal(list.children.length, 7);
    assert.ok(list.textContent.includes(hostile));
    assert.equal(list.all().filter((node) => node.tagName === 'IMG').length, 0);

    const dirs = Object.fromEntries(list.children.map((row) => [row.dataset.fact, row.children[1].getAttribute('dir')]));
    assert.equal(dirs.type, 'ltr');
    assert.equal(dirs.dimensions, 'ltr');
    assert.equal(dirs.name, null);
});
