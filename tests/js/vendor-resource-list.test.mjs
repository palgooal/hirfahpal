// VENDOR-UI-CLEANUP-01A (F-3) / 01B: the vendor resource list's texts (resources/js/vendor-resource-list.js).
// Run by Tests\Feature\VendorProductListTextsTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { columnLabel, detailsUrl, filterParams, filtersFromQuery, filtersQuery, formatAmount, listQuery, formatOrderDate, hasFilters, isPaged, listTexts, numberedLabel, ORDER_ACTION_STYLES, orderActionsFor, orderFilters, moderationBadges, moderationTexts, namedLabel, pageNumber, pagerState, pageUrl, productPreviewUrl, recoveryPage, refreshPage, statusLabel } from '../../resources/js/vendor-resource-list.js';

const ARABIC = {
    actions: 'الإجراءات',
    edit: 'تعديل',
    delete: 'حذف',
    editNamed: 'تعديل المنتج: :name',
    deleteNamed: 'حذف المنتج: :name',
    view: 'مشاهدة',
    viewNamed: 'مشاهدة المنتج: :name (يفتح في تبويب جديد)',
    columns: { name: 'المنتج', sku: 'رمز المنتج (SKU)', status: 'الحالة', price: 'السعر', stock_quantity: 'المخزون' },
    statuses: { draft: 'مسودة', active: 'نشط', inactive: 'غير نشط', out_of_stock: 'نفد من المخزون' },
};
const ORDER_ACTIONS_FALLBACK = { accept: { label: 'Accept', named: null }, reject: { label: 'Reject', named: null }, preparing: { label: 'Preparing', named: null }, ready: { label: 'Ready', named: null } };
const ENGLISH_FALLBACK = { actions: 'Actions', edit: 'Edit', delete: 'Delete', editNamed: null, deleteNamed: null, view: 'View', viewNamed: null, columns: {}, statuses: {}, orderActions: ORDER_ACTIONS_FALLBACK, actionError: 'The order could not be updated. Please try again.', detailsLink: { label: 'View details', named: null } };

test('the products list reads its translated texts from the page', () => {
    assert.deepEqual(listTexts(JSON.stringify(ARABIC)), { ...ARABIC, orderActions: ORDER_ACTIONS_FALLBACK, actionError: ENGLISH_FALLBACK.actionError, detailsLink: ENGLISH_FALLBACK.detailsLink });
});

test('a list without texts (orders) or with an unreadable value keeps the former English texts', () => {
    assert.deepEqual(listTexts(undefined), ENGLISH_FALLBACK);
    assert.deepEqual(listTexts(''), ENGLISH_FALLBACK);
    assert.deepEqual(listTexts('{not json'), ENGLISH_FALLBACK);
    assert.deepEqual(listTexts('null'), ENGLISH_FALLBACK);
    assert.deepEqual(listTexts('{"edit":"تعديل","delete":"","actions":42,"columns":"x","statuses":{"draft":"","active":7}}'),
        { ...ENGLISH_FALLBACK, edit: 'تعديل' }, 'Each missing, empty or non-text value falls back on its own.');
});

test('column headers: the page translation by path, else the list own label (other lists keep theirs)', () => {
    const texts = listTexts(JSON.stringify(ARABIC));
    assert.deepEqual([['name', 'Product'], ['sku', 'SKU'], ['status', 'Status'], ['price', 'Price'], ['stock_quantity', 'Stock']].map(([path, label]) => columnLabel(path, label, texts)),
        ['المنتج', 'رمز المنتج (SKU)', 'الحالة', 'السعر', 'المخزون']);
    assert.equal(columnLabel('number', 'Order', listTexts(undefined)), 'Order', 'Orders: unchanged.');
    assert.equal(columnLabel('created_at', 'Date', texts), 'Date', 'A column without a translation keeps its label.');
});

test('every stored status value has its label; the value itself is not changed; an unknown one shows as it is', () => {
    const texts = listTexts(JSON.stringify(ARABIC));
    assert.deepEqual(['draft', 'active', 'inactive', 'out_of_stock'].map((status) => statusLabel(status, texts)), ['مسودة', 'نشط', 'غير نشط', 'نفد من المخزون']);
    assert.equal(statusLabel('archived', texts), 'archived');
    assert.equal(statusLabel('draft', listTexts(undefined)), 'draft', 'Without texts the value shows as before.');
});

test('accessible names name the product, as plain text, special characters included', () => {
    assert.equal(namedLabel('Edit product: :name', 'Clay jug'), 'Edit product: Clay jug');
    assert.equal(namedLabel('حذف المنتج: :name', 'إبريق فخاري'), 'حذف المنتج: إبريق فخاري');
    const hostile = '<img src=x onerror=alert(1)> "Tom & Jerry\'s" $& $1 :name';
    assert.equal(namedLabel('Edit product: :name', hostile), `Edit product: ${hostile}`, 'No markup is parsed and "$" patterns are not expanded.');
    assert.equal(namedLabel('Edit product: :name', '  '), 'Edit product: —', 'A blank name still gives a readable label.');
    assert.equal(namedLabel(null, 'Clay jug'), null, 'No template: no accessible name is set.');
});

test('the module does not touch a document that is not there', () => {
    assert.equal(typeof document, 'undefined');
});

// ---- VENDOR-PRODUCT-PUBLISH-UI-01E: the products list's moderation badges ----

const BADGES = { archived: 'مؤرشف', suspended: 'معلّق إداريًا' };

test('the badge texts come from the page only; a missing or unreadable value gives no text', () => {
    assert.deepEqual(moderationTexts(JSON.stringify(BADGES)), BADGES);
    assert.deepEqual(moderationTexts('{"archived":"Archived","suspended":""}'), { archived: 'Archived' });
    assert.deepEqual(moderationTexts('{"archived":7,"other":"x"}'), {});
    for (const raw of [undefined, '', 'not json', 'null']) assert.deepEqual(moderationTexts(raw), {});
});

test('one badge per true flag, archived first; none for an ordinary product', () => {
    assert.deepEqual(moderationBadges({ archived: false, suspended: false }, BADGES), []);
    assert.deepEqual(moderationBadges({ archived: false, suspended: true }, BADGES), [['suspended', 'معلّق إداريًا']]);
    assert.deepEqual(moderationBadges({ archived: true, suspended: false }, BADGES), [['archived', 'مؤرشف']]);
    assert.deepEqual(moderationBadges({ archived: true, suspended: true }, BADGES), [['archived', 'مؤرشف'], ['suspended', 'معلّق إداريًا']]);
});

test('only real true flags count, and a flag without a text shows nothing', () => {
    for (const moderation of [undefined, null, {}, { archived: 'true', suspended: 1 }]) assert.deepEqual(moderationBadges(moderation, BADGES), []);
    assert.deepEqual(moderationBadges({ archived: true, suspended: true }, { suspended: 'Suspended by administration' }), [['suspended', 'Suspended by administration']]);
    assert.deepEqual(moderationBadges({ archived: true }, undefined), []);
});

// ---- VENDOR-PRODUCT-LIST-PAGINATION-01B: the products list's pager ----

const pageOf = (current, last, count, total = 41) => ({ current_page: current, last_page: last, total, data: Array.from({ length: count }, (_, i) => ({ id: i + 1 })) });

test('a page number is a whole number of at least 1; anything else is page 1', () => {
    for (const [given, expected] of [[1, 1], [3, 3], ['2', 2], [' 4 ', 4], [0, 1], [-2, 1], [2.5, 1], ['2.5', 1], ['abc', 1], ['', 1], [null, 1], [undefined, 1], [NaN, 1], [true, 1], [[2], 1]]) {
        assert.equal(pageNumber(given), expected, JSON.stringify(given));
    }
});

test('the page URL keeps the endpoint and its own parameters and sets only page', () => {
    const base = 'http://127.0.0.1:8765/vendor/dashboard/products/manage';
    assert.equal(pageUrl('http://127.0.0.1:8765/vendor/dashboard/products', 3, base), 'http://127.0.0.1:8765/vendor/dashboard/products?page=3');
    assert.equal(pageUrl('/vendor/dashboard/products?status=draft&per_page=20', 2, base), 'http://127.0.0.1:8765/vendor/dashboard/products?status=draft&per_page=20&page=2');
    assert.equal(pageUrl('/vendor/dashboard/products?page=9&search=a%26b', 4, base), 'http://127.0.0.1:8765/vendor/dashboard/products?page=4&search=a%26b');
    for (const bad of [0, -1, 'x', 1.5]) assert.equal(pageUrl('/vendor/dashboard/products', bad, base), 'http://127.0.0.1:8765/vendor/dashboard/products?page=1');
});

test('the pager state: first, middle and last pages, a single page, and an empty list', () => {
    assert.deepEqual(pagerState(pageOf(1, 3, 15)), { page: 1, last: 3, total: 41, hasPrevious: false, hasNext: true, shown: true });
    assert.deepEqual(pagerState(pageOf(2, 3, 15)), { page: 2, last: 3, total: 41, hasPrevious: true, hasNext: true, shown: true });
    assert.deepEqual(pagerState(pageOf(3, 3, 11)), { page: 3, last: 3, total: 41, hasPrevious: true, hasNext: false, shown: true });
    assert.deepEqual(pagerState(pageOf(1, 1, 7, 7)), { page: 1, last: 1, total: 7, hasPrevious: false, hasNext: false, shown: false });
    assert.deepEqual(pagerState(pageOf(1, 1, 0, 0)), { page: 1, last: 1, total: 0, hasPrevious: false, hasNext: false, shown: false });
    assert.deepEqual(pagerState(undefined), { page: 1, last: 1, total: 0, hasPrevious: false, hasNext: false, shown: false }, 'A broken answer shows no pager.');
    assert.deepEqual(pagerState({ current_page: 'x', last_page: -3, total: -1 }), { page: 1, last: 1, total: 0, hasPrevious: false, hasNext: false, shown: false });
});

test('a page past the end goes back to the last page; any other page stays', () => {
    assert.equal(recoveryPage(pageOf(4, 3, 0)), 3, 'The last product of the last page was deleted.');
    assert.equal(recoveryPage(pageOf(9, 2, 0, 20)), 2, 'A stale page number.');
    assert.equal(recoveryPage(pageOf(2, 1, 0, 0)), 1, 'Everything deleted: page 1, the empty list.');
    assert.equal(recoveryPage(pageOf(1, 1, 0, 0)), null, 'An empty list on page 1 is just empty.');
    assert.equal(recoveryPage(pageOf(3, 3, 11)), null);
    assert.equal(recoveryPage(pageOf(2, 3, 15)), null);
    // Each recovery goes strictly down, so a chain of recoveries always ends.
    let page = 9;
    for (const last of [5, 3, 1]) { const back = recoveryPage(pageOf(page, last, 0)); assert.ok(back < page); page = back; }
    assert.equal(recoveryPage(pageOf(page, 1, 0, 0)), null);
});

test('after the editor: a new product shows page 1, a saved or deleted one the same page', () => {
    assert.equal(refreshPage('created', 3), 1);
    assert.equal(refreshPage('saved', 3), 3);
    assert.equal(refreshPage('deleted', 3), 3);
    assert.equal(refreshPage(undefined, 2), 2, 'Any other refresh keeps the page.');
    assert.equal(refreshPage('saved', 'x'), 1);
});

// ---- ORD-01A: the orders list pages with the same pager (the API's 12 per page) ----

test('only the products and orders lists page', () => {
    assert.deepEqual(['products', 'orders', 'reviews', 'returns', 'disputes', 'commissions', undefined, ''].map(isPaged),
        [true, true, false, false, false, false, false, false]);
});

test('the orders page URL sets only page on the orders endpoint', () => {
    const base = 'http://127.0.0.1:8765/vendor/dashboard/orders/manage';
    assert.equal(pageUrl('http://127.0.0.1:8765/vendor/dashboard/orders', 2, base), 'http://127.0.0.1:8765/vendor/dashboard/orders?page=2');
    assert.equal(pageUrl('/vendor/dashboard/orders', 'x', base), 'http://127.0.0.1:8765/vendor/dashboard/orders?page=1');
});

test('25 orders: Previous is disabled on page 1, Next on page 3, both enabled between', () => {
    assert.deepEqual(pagerState(pageOf(1, 3, 12, 25)), { page: 1, last: 3, total: 25, hasPrevious: false, hasNext: true, shown: true });
    assert.deepEqual(pagerState(pageOf(2, 3, 12, 25)), { page: 2, last: 3, total: 25, hasPrevious: true, hasNext: true, shown: true });
    assert.deepEqual(pagerState(pageOf(3, 3, 1, 25)), { page: 3, last: 3, total: 25, hasPrevious: true, hasNext: false, shown: true });
});

test('no orders or one page of orders: no pager; a page past the end steps back', () => {
    assert.equal(pagerState(pageOf(1, 1, 0, 0)).shown, false);
    assert.equal(pagerState(pageOf(1, 1, 12, 12)).shown, false, 'Exactly 12 orders: one page.');
    assert.equal(pagerState(pageOf(1, 2, 12, 13)).shown, true, 'The 13th order makes a second page.');
    assert.equal(recoveryPage(pageOf(3, 2, 0, 13)), 2);
});

// ---- ORD-01B: the orders list's search and filters ----

const STATUSES = ['pending', 'accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled'];
const NONE = { search: '', status: '', needsResponse: false };

test('filters are made safe: a trimmed search, a known status, needs-response only when true', () => {
    assert.deepEqual(orderFilters({ search: '  HF-1001 ', status: 'pending', needsResponse: true }, STATUSES), { search: 'HF-1001', status: 'pending', needsResponse: true });
    assert.deepEqual(orderFilters({ search: '   ', status: 'archived', needsResponse: 'true' }, STATUSES), NONE, 'Blank search, unknown status, non-boolean flag.');
    assert.deepEqual(orderFilters(undefined, STATUSES), NONE);
    assert.deepEqual(orderFilters({ search: 7, status: ['pending'] }, STATUSES), NONE);
    assert.deepEqual(orderFilters({ status: 'pending' }), NONE, 'No status is known without the list.');
    assert.equal(orderFilters({ search: 'سارة أحمد' }, STATUSES).search, 'سارة أحمد', 'Arabic text is kept as typed.');
});

test('the page address gives the starting filters, needs_response read as the API reads it', () => {
    assert.deepEqual(filtersFromQuery('?search=HF-1&status=accepted&needs_response=1', STATUSES), { search: 'HF-1', status: 'accepted', needsResponse: true });
    for (const flag of ['1', 'true', 'on', 'yes']) assert.equal(filtersFromQuery(`?needs_response=${flag}`, STATUSES).needsResponse, true, flag);
    for (const flag of ['0', 'false', '', 'no']) assert.equal(filtersFromQuery(`?needs_response=${flag}`, STATUSES).needsResponse, false, flag);
    assert.deepEqual(filtersFromQuery('?status=bogus&search=%20%20', STATUSES), NONE);
    assert.deepEqual(filtersFromQuery('', STATUSES), NONE);
    assert.deepEqual(filtersFromQuery(undefined, STATUSES), NONE);
    assert.equal(filtersFromQuery('?search=%D8%B3%D8%A7%D8%B1%D8%A9', STATUSES).search, 'سارة');
});

test('only the filters in use are sent, and they say whether the list is filtered', () => {
    assert.deepEqual(filterParams(NONE), {});
    assert.deepEqual(filterParams({ search: 'HF', status: '', needsResponse: false }), { search: 'HF' });
    assert.deepEqual(filterParams({ search: 'HF', status: 'pending', needsResponse: true }), { search: 'HF', status: 'pending', needs_response: '1' });
    assert.deepEqual(filterParams(null), {});
    assert.equal(hasFilters(NONE), false);
    assert.equal(hasFilters(null), false, 'The other lists have no filters.');
    for (const filters of [{ ...NONE, search: 'x' }, { ...NONE, status: 'pending' }, { ...NONE, needsResponse: true }]) assert.equal(hasFilters(filters), true);
});

test('each page request carries the filters; a change starts again from page 1; the products request is unchanged', () => {
    const base = 'http://127.0.0.1:8765/vendor/dashboard/orders/manage';
    const endpoint = 'http://127.0.0.1:8765/vendor/dashboard/orders';
    const filters = { search: 'سارة & co', status: 'pending', needsResponse: true };
    const url = new URL(pageUrl(endpoint, 3, base, filters));
    assert.deepEqual([url.searchParams.get('page'), url.searchParams.get('search'), url.searchParams.get('status'), url.searchParams.get('needs_response')], ['3', 'سارة & co', 'pending', '1'], 'Filters kept across pages.');
    assert.equal(new URL(pageUrl(endpoint, 1, base, filters)).searchParams.get('page'), '1', 'Apply and Clear load page 1.');
    assert.equal(pageUrl(`${endpoint}?status=old&search=old`, 2, base, NONE), `${endpoint}?page=2`, 'Cleared filters send nothing.');
    assert.equal(pageUrl(`${endpoint}?status=old`, 2, base, { ...NONE, search: 'HF' }), `${endpoint}?page=2&search=HF`);
    assert.equal(pageUrl('/vendor/dashboard/products?status=draft', 2, base), 'http://127.0.0.1:8765/vendor/dashboard/products?status=draft&page=2', 'No filters given: as before.');
    assert.equal(pageUrl('/vendor/dashboard/products?status=draft', 2, base, null), 'http://127.0.0.1:8765/vendor/dashboard/products?status=draft&page=2');
});

test('the page address follows the filters and keeps its other parameters', () => {
    assert.equal(filtersQuery('', NONE), '');
    assert.equal(filtersQuery('?search=old&status=pending', NONE), '', 'Clear filters: a clean address.');
    assert.equal(filtersQuery('', { search: 'HF 1', status: 'accepted', needsResponse: true }), '?search=HF+1&status=accepted&needs_response=1');
    assert.equal(filtersQuery('?tab=x&search=old', { ...NONE, status: 'rejected' }), '?tab=x&status=rejected');
    assert.deepEqual(filtersFromQuery(filtersQuery('', { search: 'سارة', status: 'delivered', needsResponse: true }), STATUSES), { search: 'سارة', status: 'delivered', needsResponse: true }, 'A reload gives the same filters.');
});

// ---- ORD-01C: the orders list's presentation ----

const ORDER_TEXTS = {
    actions: 'الإجراءات',
    columns: { number: 'رقم الطلب', status: 'الحالة', 'order.customer.name': 'العميل', created_at: 'التاريخ', total: 'الإجمالي' },
    statuses: { pending: 'قيد الانتظار', ready_for_delivery: 'جاهز للتسليم' },
    orderActions: {
        accept: { label: 'قبول', named: 'قبول الطلب :number' },
        reject: { label: 'رفض', named: 'رفض الطلب :number' },
        preparing: { label: 'قيد التحضير', named: 'نقل الطلب :number إلى قيد التحضير' },
        ready: { label: 'جاهز', named: 'تحديد الطلب :number جاهزًا للتسليم' },
    },
    actionError: 'تعذّر تحديث الطلب. حاول مرة أخرى.',
};

test('the orders list reads its headers, statuses, action labels and names and its action error from the page', () => {
    const texts = listTexts(JSON.stringify(ORDER_TEXTS));
    assert.deepEqual([['number', 'Order'], ['status', 'Status'], ['order.customer.name', 'Customer'], ['created_at', 'Date'], ['total', 'Total']].map(([path, label]) => columnLabel(path, label, texts)),
        ['رقم الطلب', 'الحالة', 'العميل', 'التاريخ', 'الإجمالي']);
    assert.equal(texts.actions, 'الإجراءات');
    assert.deepEqual(texts.orderActions, ORDER_TEXTS.orderActions);
    assert.equal(texts.actionError, ORDER_TEXTS.actionError);
    assert.equal(statusLabel('ready_for_delivery', texts), 'جاهز للتسليم');
    assert.equal(statusLabel('unknown_status', texts), 'unknown_status', 'An unknown value shows as it is.');
});

test('a missing or broken action text falls back on its own to the former English label and no accessible name', () => {
    const texts = listTexts(JSON.stringify({ orderActions: { accept: { label: '', named: 7 }, reject: 'x', ready: { label: 'جاهز' } } }));
    assert.deepEqual(texts.orderActions, { ...ORDER_ACTIONS_FALLBACK, ready: { label: 'جاهز', named: null } });
});

test('the actions offered per status follow SRS §4.5 one step at a time (VEN-BE-020); Reject stands apart as the destructive one', () => {
    assert.deepEqual(['pending', 'accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled', undefined].map(orderActionsFor),
        [['accept', 'reject'], ['preparing'], ['ready'], [], [], [], [], [], [], [], []]);
    assert.deepEqual(ORDER_ACTION_STYLES, { accept: 'btn-light-primary', reject: 'btn-light-danger', preparing: 'btn-light-secondary', ready: 'btn-light-secondary' });
});

test('action names include the order number, as plain text', () => {
    assert.equal(numberedLabel('Accept order :number', 'HF-1001-V3'), 'Accept order HF-1001-V3');
    assert.equal(numberedLabel('رفض الطلب :number', 'HF-1001-V3'), 'رفض الطلب HF-1001-V3');
    assert.equal(numberedLabel('Reject order :number', '<b>$&</b>'), 'Reject order <b>$&</b>', 'No markup parsed, no "$" pattern expanded.');
    assert.equal(numberedLabel('Accept order :number', ' '), 'Accept order —');
    assert.equal(numberedLabel(null, 'HF-1'), null);
    for (const { label, named } of Object.values(ORDER_TEXTS.orderActions)) assert.ok(numberedLabel(named, 'HF-1').includes(label), `"${label}" is in its accessible name.`);
});

test('dates follow the page language and the application timezone, with Latin digits; a bad value shows nothing', () => {
    const value = '2026-10-15T09:05:00.000000Z';
    const arabic = formatOrderDate(value, 'ar', 'Asia/Hebron');
    const english = formatOrderDate(value, 'en', 'Asia/Hebron');
    assert.match(arabic, /أكتوبر/, 'Arabic month name.');
    assert.match(english, /Oct/);
    for (const shown of [arabic, english]) {
        assert.match(shown, /15/); assert.match(shown, /2026/); assert.match(shown, /12:05/, 'Asia/Hebron is UTC+3 in October.');
        assert.doesNotMatch(shown, /[٠-٩]/, 'Latin digits, as on the dashboard home.');
    }
    assert.match(formatOrderDate(value, 'en', 'UTC'), /09:05/);
    assert.match(formatOrderDate(value, 'en', 'Not/AZone'), /09:05/, 'An unknown timezone falls back to UTC.');
    for (const bad of ['not a date', '', '   ', null, undefined, 42, '—']) assert.equal(formatOrderDate(bad, 'en', 'UTC'), '', String(bad));
});

test('the total is shown as the admin list shows it; anything else shows nothing', () => {
    assert.deepEqual(['30.00', '1234.5', 30, 0, '0.00'].map(formatAmount), ['30.00', '1,234.50', '30.00', '0.00', '0.00']);
    for (const bad of ['abc', '', '  ', null, undefined, NaN, Infinity, '—', {}]) assert.equal(formatAmount(bad), null, String(bad));
});

// ---- ORD-02A (step 4): list-to-details navigation and the list's page in its address ----

const BASE_ORIGIN = 'http://127.0.0.1:8765/vendor/dashboard/orders/manage';
const LIST_BASE = 'http://127.0.0.1:8765/vendor/dashboard/orders/manage';

test('the list address carries its filters and its page; page 1 is left out; other parameters are kept', () => {
    assert.equal(listQuery('', NONE, 1), '');
    assert.equal(listQuery('', NONE, 3), '?page=3');
    assert.equal(listQuery('', { search: 'HF 1', status: 'pending', needsResponse: true }, 2), '?search=HF+1&status=pending&needs_response=1&page=2');
    assert.equal(listQuery('?page=7&status=old&tab=x', NONE, 1), '?tab=x', 'Filters changed: page 1, old filters gone.');
    assert.equal(listQuery('?tab=x', { ...NONE, status: 'rejected' }, 4), '?tab=x&status=rejected&page=4');
    for (const bad of [0, -2, 'abc', 2.5, null, undefined]) assert.equal(listQuery('', NONE, bad), '', `page ${String(bad)} is page 1`);
    assert.deepEqual(filtersFromQuery(listQuery('', { search: 'سارة', status: 'delivered', needsResponse: true }, 5), STATUSES), { search: 'سارة', status: 'delivered', needsResponse: true }, 'A reload reads the same filters back.');
});

test('a details link is {list}/{id} with only the list context: filters and page', () => {
    assert.equal(detailsUrl(LIST_BASE, 12, NONE, 1, BASE_ORIGIN), `${LIST_BASE}/12`);
    assert.equal(detailsUrl(`${LIST_BASE}/`, 12, NONE, 1, BASE_ORIGIN), `${LIST_BASE}/12`, 'A trailing slash on the base is tolerated.');
    assert.equal(detailsUrl(LIST_BASE, 12, { search: 'A&B ك', status: 'pending', needsResponse: true }, 2, BASE_ORIGIN), `${LIST_BASE}/12?search=A%26B+%D9%83&status=pending&needs_response=1&page=2`);
    const url = new URL(detailsUrl(LIST_BASE, 9, { search: 'x', status: '', needsResponse: false }, 3, BASE_ORIGIN));
    assert.deepEqual([...url.searchParams.keys()], ['search', 'page'], 'Nothing but the context.');
});

test('no details link without a valid order id or base; the id cannot change the path', () => {
    for (const id of [0, -1, 1.5, '12', '12/../../admin', null, undefined, NaN]) assert.equal(detailsUrl(LIST_BASE, id, NONE, 1, BASE_ORIGIN), null, String(id));
    for (const base of ['', null, undefined, 7]) assert.equal(detailsUrl(base, 12, NONE, 1, BASE_ORIGIN), null, String(base));
});

test('the details link reads the page translation; without it, the former English label and no accessible name', () => {
    assert.deepEqual(listTexts(JSON.stringify({ detailsLink: { label: 'عرض التفاصيل', named: 'عرض التفاصيل للطلب :number' } })).detailsLink, { label: 'عرض التفاصيل', named: 'عرض التفاصيل للطلب :number' });
    assert.deepEqual(listTexts(undefined).detailsLink, { label: 'View details', named: null });
    assert.deepEqual(listTexts('{"detailsLink":{"label":"","named":7}}').detailsLink, { label: 'View details', named: null });
    assert.equal(numberedLabel('عرض التفاصيل للطلب :number', 'HF-1-V2'), 'عرض التفاصيل للطلب HF-1-V2');
});

// PREVIEW-04: the products list's "View" link to a product's private preview.
test('a product preview address comes from the page template and a whole-number id only', () => {
    const template = 'https://hirfah.test/vendor/dashboard/preview/products/__ID__';
    assert.equal(productPreviewUrl(template, 19), 'https://hirfah.test/vendor/dashboard/preview/products/19');
    assert.equal(productPreviewUrl(template, '7'), 'https://hirfah.test/vendor/dashboard/preview/products/7');
    for (const id of [0, -1, '1.5', 'abc', '19/../x', '', null, undefined]) {
        assert.equal(productPreviewUrl(template, id), null, String(id));
    }
    assert.equal(productPreviewUrl(undefined, 19), null, 'The other lists have no template: no link.');
    assert.equal(productPreviewUrl('https://hirfah.test/no-placeholder', 19), null);
    assert.equal(namedLabel('مشاهدة المنتج: :name (يفتح في تبويب جديد)', 'جرة'), 'مشاهدة المنتج: جرة (يفتح في تبويب جديد)');
});
