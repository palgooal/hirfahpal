// ORD-02A (step 2): the shared order presentation helpers (resources/js/vendor-order-shared.js), moved unchanged from
// vendor-resource-list.js, which re-exports them. Run by Tests\Feature\VendorOrderSharedComponentsTest (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import * as shared from '../../resources/js/vendor-order-shared.js';
import * as list from '../../resources/js/vendor-resource-list.js';

test('the shared module exports exactly the four helpers', () => {
    assert.deepEqual(Object.keys(shared).sort(), ['ORDER_ACTION_STYLES', 'formatAmount', 'formatOrderDate', 'numberedLabel']);
});

test('the list module re-exports the very same helpers, so existing imports keep working', () => {
    for (const name of ['ORDER_ACTION_STYLES', 'formatAmount', 'formatOrderDate', 'numberedLabel']) {
        assert.equal(list[name], shared[name], name);
    }
    assert.equal(typeof list.orderActionsFor, 'function', 'The list keeps its own action rules.');
    assert.equal('orderActionsFor' in shared, false);
});

test('the helpers behave as before (ORD-01C)', () => {
    assert.deepEqual(shared.ORDER_ACTION_STYLES, { accept: 'btn-light-primary', reject: 'btn-light-danger', preparing: 'btn-light-secondary', ready: 'btn-light-secondary' });
    assert.equal(shared.numberedLabel('Accept order :number', 'HF-1-V2'), 'Accept order HF-1-V2');
    assert.equal(shared.numberedLabel(null, 'HF-1'), null);
    assert.equal(shared.formatAmount('1234.5'), '1,234.50');
    assert.equal(shared.formatAmount('abc'), null);
    assert.match(shared.formatOrderDate('2026-10-15T09:05:00.000000Z', 'ar', 'Asia/Hebron'), /أكتوبر.*12:05/);
    assert.match(shared.formatOrderDate('2026-10-15T09:05:00.000000Z', 'en', 'UTC'), /Oct 15, 2026.*09:05/);
    assert.equal(shared.formatOrderDate('not a date', 'en', 'UTC'), '');
});

test('importing the modules touches no document', () => {
    assert.equal(typeof document, 'undefined');
});
