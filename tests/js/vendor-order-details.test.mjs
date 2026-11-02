// ORD-02A (step 3): the vendor order details page's data handling (resources/js/vendor-order-details.js).
// Run by Tests\Feature\VendorOrderDetailsPageTest (node --test). The page's rendering, actions and focus are checked in
// a real browser; these tests cover what the page reads, shows and reloads.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { DETAIL_ACTIONS, fetchDetails, orderView, parseJson, refreshOutcome, TIMELINE_KEYS, timelineEntries } from '../../resources/js/vendor-order-details.js';

const CONTRACT = {
    id: 12, number: 'HF-500012-V1', status: 'accepted', created_at: '2026-10-21T09:00:00.000000Z',
    timeline: { accepted_at: '2026-10-21T10:00:00.000000Z', rejected_at: null, ready_at: null, delivered_at: null, customer_receipt_confirmed_at: null, completed_at: null },
    rejection_reason: null, subtotal: '25.00', delivery_fee: '5.00', total: '30.00',
    items: [{ product_name: 'Clay jug', product_sku: 'JUG-1', quantity: 2, unit_price: '7.50', line_total: '15.00' }],
    actions: ['preparing'],
};

test('the page reads the contract, in its own shape', () => {
    assert.deepEqual(orderView(CONTRACT), {
        id: 12, number: 'HF-500012-V1', status: 'accepted', createdAt: '2026-10-21T09:00:00.000000Z',
        timeline: { accepted_at: '2026-10-21T10:00:00.000000Z', rejected_at: null, ready_at: null, delivered_at: null, customer_receipt_confirmed_at: null, completed_at: null },
        rejectionReason: null, subtotal: '25.00', deliveryFee: '5.00', total: '30.00',
        items: [{ productName: 'Clay jug', productSku: 'JUG-1', quantity: 2, unitPrice: '7.50', lineTotal: '15.00' }],
        actions: ['preparing'],
    });
});

test('only the contract keys are read: anything else is never shown', () => {
    const view = orderView({ ...CONTRACT, customer: { email: 'x@example.test' }, grand_total: '999.99', commission_amount: '12.34', vendor_response_due_at: '2026-10-22T09:00:00Z',
        items: [{ ...CONTRACT.items[0], product: { stock_quantity: 9 }, product_id: 3 }], timeline: { ...CONTRACT.timeline, preparing_at: '2026-10-21T11:00:00Z' } });
    const shown = JSON.stringify(view);
    for (const value of ['x@example.test', '999.99', '12.34', 'stock_quantity', 'product_id', 'preparing_at', '2026-10-22T09:00:00Z']) assert.equal(shown.includes(value), false, value);
});

test('only known actions are kept, in a fixed order; the server decides which', () => {
    assert.deepEqual(DETAIL_ACTIONS, ['accept', 'reject', 'preparing', 'ready']);
    assert.deepEqual(orderView({ ...CONTRACT, actions: ['ready', 'cancel', 'accept', 'delete'] }).actions, ['accept', 'ready']);
    assert.deepEqual(orderView({ ...CONTRACT, actions: [] }).actions, []);
    assert.deepEqual(orderView({ ...CONTRACT, actions: 'accept' }).actions, []);
});

test('not an order: no view', () => {
    for (const raw of [null, undefined, {}, [], 'x', { id: '12', number: 'HF-1' }, { id: 12 }, { id: 12, number: '' }]) assert.equal(orderView(raw), null, JSON.stringify(raw));
});

test('amounts stay as stored strings; broken item fields become null', () => {
    const view = orderView({ ...CONTRACT, subtotal: 25, items: [{ product_name: 7, product_sku: null, quantity: '2', unit_price: {}, line_total: '15.00' }, null, 'x'] });
    assert.equal(view.subtotal, '25');
    assert.deepEqual(view.items, [{ productName: null, productSku: null, quantity: null, unitPrice: null, lineTotal: '15.00' }]);
});

test('the timeline: lifecycle order, real times only, a missing column stays missing, nothing is invented', () => {
    assert.deepEqual(TIMELINE_KEYS, ['created_at', 'accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at']);
    assert.deepEqual(timelineEntries(orderView(CONTRACT)), [{ key: 'created_at', value: '2026-10-21T09:00:00.000000Z' }, { key: 'accepted_at', value: '2026-10-21T10:00:00.000000Z' }]);
    const completed = orderView({ ...CONTRACT, timeline: { completed_at: '2026-10-25T09:00:00Z', accepted_at: '2026-10-21T10:00:00Z', delivered_at: 'not a time' } });
    assert.deepEqual(timelineEntries(completed).map((entry) => entry.key), ['created_at', 'accepted_at', 'completed_at'], 'No receipt column: no receipt entry; a bad time: no entry.');
    assert.deepEqual(timelineEntries(orderView({ ...CONTRACT, created_at: null, timeline: {} })), []);
    assert.deepEqual(timelineEntries(null), []);
});

test('a reload answer: an order, gone, session, forbidden or failed', () => {
    const json = (status, payload) => ({ status, ok: status >= 200 && status < 300, json: true, payload });
    assert.equal(refreshOutcome(json(200, { order: CONTRACT })), 'ok');
    assert.equal(refreshOutcome(json(200, { order: {} })), 'failed', 'A 200 without an order is not a success.');
    assert.equal(refreshOutcome({ status: 200, ok: true, json: false }), 'failed', 'An HTML page is not the order.');
    assert.equal(refreshOutcome(json(404, {})), 'gone');
    assert.equal(refreshOutcome(json(401, {})), 'session');
    assert.equal(refreshOutcome(json(419, {})), 'session');
    assert.equal(refreshOutcome(json(403, {})), 'forbidden');
    assert.equal(refreshOutcome(json(500, { message: 'SQLSTATE' })), 'failed');
    assert.equal(refreshOutcome(undefined), 'failed');
});

const fakeFetch = (answer) => {
    const calls = [];
    const impl = async (url, init) => {
        calls.push({ url, init });
        if (answer === 'network') throw new TypeError('Failed to fetch');
        return { status: answer.status, ok: answer.status >= 200 && answer.status < 300, headers: { get: () => answer.type ?? 'application/json' }, json: async () => answer.body };
    };
    impl.calls = calls;
    return impl;
};

test('the reload asks the page URL for JSON and keeps only the order', async () => {
    const fetch = fakeFetch({ status: 200, body: { order: CONTRACT } });
    const result = await fetchDetails(fetch, '/vendor/dashboard/orders/manage/12');
    assert.equal(result.outcome, 'ok');
    assert.deepEqual(result.order, orderView(CONTRACT));
    assert.deepEqual(fetch.calls, [{ url: '/vendor/dashboard/orders/manage/12', init: { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' } }]);
    assert.deepEqual(await fetchDetails(fakeFetch('network'), '/x'), { outcome: 'network', order: null });
    assert.deepEqual(await fetchDetails(fakeFetch({ status: 404, body: { message: 'Not Found' } }), '/x'), { outcome: 'gone', order: null });
    assert.deepEqual(await fetchDetails(fakeFetch({ status: 200, type: 'text/html', body: null }), '/x'), { outcome: 'failed', order: null });
});

test('page data attributes that cannot be read are empty', () => {
    for (const raw of [undefined, '', 'not json', '[]', 'null', '7']) assert.deepEqual(parseJson(raw), {});
    assert.deepEqual(parseJson('{"a":1}'), { a: 1 });
});

test('importing the module touches no document', () => {
    assert.equal(typeof document, 'undefined');
});
