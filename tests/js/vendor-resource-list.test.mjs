// VENDOR-UI-CLEANUP-01A (F-3) / 01B: the vendor resource list's texts (resources/js/vendor-resource-list.js).
// Run by Tests\Feature\VendorProductListTextsTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { columnLabel, listTexts, moderationBadges, moderationTexts, namedLabel, statusLabel } from '../../resources/js/vendor-resource-list.js';

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
