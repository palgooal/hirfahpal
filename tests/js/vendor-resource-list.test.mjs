// VENDOR-UI-CLEANUP-01A (F-3): the vendor resource list's action texts (resources/js/vendor-resource-list.js).
// Run by Tests\Feature\VendorProductListTextsTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { listTexts } from '../../resources/js/vendor-resource-list.js';

test('the products list reads its translated action texts from the page', () => {
    assert.deepEqual(listTexts(JSON.stringify({ actions: 'الإجراءات', edit: 'تعديل', delete: 'حذف' })), { actions: 'الإجراءات', edit: 'تعديل', delete: 'حذف' });
    assert.deepEqual(listTexts('{"actions":"Actions","edit":"Edit","delete":"Delete"}'), { actions: 'Actions', edit: 'Edit', delete: 'Delete' });
});

test('a list without texts (orders) or with an unreadable value keeps the former English texts', () => {
    const english = { actions: 'Actions', edit: 'Edit', delete: 'Delete' };

    assert.deepEqual(listTexts(undefined), english);
    assert.deepEqual(listTexts(''), english);
    assert.deepEqual(listTexts('{not json'), english);
    assert.deepEqual(listTexts('null'), english);
    assert.deepEqual(listTexts('{"edit":"تعديل","delete":"","actions":42}'), { actions: 'Actions', edit: 'تعديل', delete: 'Delete' }, 'Each missing or empty text falls back on its own.');
});

test('the module does not touch a document that is not there', () => {
    assert.equal(typeof document, 'undefined');
});
