// VENDOR-UI-CLEANUP-01A (F-3) / 01B: the vendor resource list's texts (resources/js/vendor-resource-list.js).
// Run by Tests\Feature\VendorProductListTextsTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { columnLabel, listTexts, moderationBadges, moderationTexts, namedLabel, pageNumber, pagerState, pageUrl, recoveryPage, refreshPage, statusLabel } from '../../resources/js/vendor-resource-list.js';

const ARABIC = {
    actions: 'الإجراءات',
    edit: 'تعديل',
    delete: 'حذف',
    editNamed: 'تعديل المنتج: :name',
    deleteNamed: 'حذف المنتج: :name',
    columns: { name: 'المنتج', sku: 'رمز المنتج (SKU)', status: 'الحالة', price: 'السعر', stock_quantity: 'المخزون' },
    statuses: { draft: 'مسودة', active: 'نشط', inactive: 'غير نشط', out_of_stock: 'نفد من المخزون' },
};
const ENGLISH_FALLBACK = { actions: 'Actions', edit: 'Edit', delete: 'Delete', editNamed: null, deleteNamed: null, columns: {}, statuses: {} };

test('the products list reads its translated texts from the page', () => {
    assert.deepEqual(listTexts(JSON.stringify(ARABIC)), ARABIC);
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
