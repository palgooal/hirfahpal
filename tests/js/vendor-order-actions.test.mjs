// ORD-01D: the vendor orders list's action dialogs and feedback (resources/js/vendor-order-actions.js).
// Run by Tests\Feature\VendorOrderActionDialogsTest (node --test), so it is part of php artisan test.
// The controller tests use a small fake document (elements, a dialog with showModal/close, focus): they check the
// logic (what is sent, when, what is shown, where focus is asked to go), not a browser's dialog, focus trap or
// screen reader behavior.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { actionOutcome, actionTexts, CONFIRMED_ACTIONS, createActionGate, createOrderActions, EXPECTED_STATUS, fill, numberedParts, outcomeMessage, REASON_MAX, reasonCount, sendOrderAction, validateReason } from '../../resources/js/vendor-order-actions.js';

// ---------------------------------------------------------------- pure pieces

test('the reason limit is the server rule, and Accept and Reject are the actions that ask first', () => {
    assert.equal(REASON_MAX, 1000);
    assert.deepEqual(CONFIRMED_ACTIONS, ['accept', 'reject']);
    assert.deepEqual(EXPECTED_STATUS, { accept: 'accepted', reject: 'rejected', preparing: 'preparing', ready: 'ready_for_delivery' });
});

test('a reason is required, trimmed, and at most 1000 characters', () => {
    for (const blank of ['', '   ', '\n\t  \n', undefined, null, 42]) assert.deepEqual(validateReason(blank), { reason: '', error: 'required' }, JSON.stringify(blank));
    assert.deepEqual(validateReason('  Out of stock  '), { reason: 'Out of stock', error: null });
    assert.deepEqual(validateReason('نفدت الكمية'), { reason: 'نفدت الكمية', error: null });
    assert.deepEqual(validateReason('a'.repeat(1000)), { reason: 'a'.repeat(1000), error: null }, 'Exactly 1000: allowed.');
    assert.equal(validateReason('a'.repeat(1001)).error, 'too_long', '1001: refused.');
    assert.equal(validateReason(`  ${'a'.repeat(1000)}  `).error, null, 'Spaces around 1000 characters are trimmed first, as the server does.');
});

test('outcomes come from the status and body only, and a failure is never a success', () => {
    const json = (status, payload) => ({ status, ok: status >= 200 && status < 300, json: true, redirected: false, payload });
    assert.equal(actionOutcome('accept', json(200, { order: { status: 'accepted' } })), 'success');
    assert.equal(actionOutcome('reject', json(200, { order: { status: 'rejected' } })), 'success');
    assert.equal(actionOutcome('ready', json(200, { order: { status: 'ready_for_delivery' } })), 'success');
    assert.equal(actionOutcome('accept', json(200, { order: { status: 'pending' } })), 'conflict', 'A 200 without the new status is not a success.');
    assert.equal(actionOutcome('accept', json(200, {})), 'conflict');
    assert.equal(actionOutcome('reject', json(422, { message: 'The rejection reason field is required.', errors: { rejection_reason: ['x'] } })), 'invalid');
    assert.equal(actionOutcome('accept', json(422, { message: 'Not enough physical stock to commit product [Clay jug].' })), 'conflict', 'A stock or state refusal.');
    assert.equal(actionOutcome('accept', json(422, { message: 'x', errors: {} })), 'conflict');
    assert.equal(actionOutcome('accept', json(404, { message: 'Not Found' })), 'conflict');
    assert.equal(actionOutcome('accept', json(409, {})), 'conflict');
    assert.equal(actionOutcome('accept', json(401, { message: 'Unauthenticated.' })), 'session');
    assert.equal(actionOutcome('accept', json(419, { message: 'CSRF token mismatch.' })), 'session');
    assert.equal(actionOutcome('accept', { status: 419, ok: false, json: false }), 'session');
    assert.equal(actionOutcome('accept', json(403, { message: 'approval' })), 'forbidden');
    assert.equal(actionOutcome('accept', json(500, { message: 'SQLSTATE[...]' })), 'failed');
    assert.equal(actionOutcome('accept', json(429, {})), 'failed');
    assert.equal(actionOutcome('accept', { status: 200, ok: true, json: false, redirected: true }), 'failed', 'A redirect to an HTML page is not a success.');
    assert.equal(actionOutcome('accept', { status: 200, ok: true, json: false, redirected: false }), 'failed');
    assert.equal(actionOutcome('accept', undefined), 'failed');
});

/** A fetch that answers as given; it records what it was asked. */
const fakeFetch = (answer) => {
    const calls = [];
    const impl = async (url, init) => {
        calls.push({ url, init });
        if (answer === 'network') throw new TypeError('Failed to fetch');
        const { status = 200, body = null, type = 'application/json', redirected = false, badJson = false } = answer;
        return {
            status, ok: status >= 200 && status < 300, redirected,
            headers: { get: (name) => (name.toLowerCase() === 'content-type' ? type : null) },
            json: async () => { if (badJson) throw new SyntaxError('bad'); return body; },
        };
    };
    impl.calls = calls;

    return impl;
};

test('each action is sent as before: same URL, method, headers and body', async () => {
    const accept = fakeFetch({ body: { order: { status: 'accepted' } } });
    assert.equal(await sendOrderAction(accept, { endpoint: '/vendor/dashboard/orders', id: 7, action: 'accept', csrfToken: 'tok' }), 'success');
    assert.deepEqual(accept.calls, [{ url: '/vendor/dashboard/orders/7/accept', init: { method: 'PATCH', credentials: 'same-origin', body: null, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': 'tok' } } }]);

    const reject = fakeFetch({ body: { order: { status: 'rejected' } } });
    const body = JSON.stringify({ rejection_reason: 'Out of stock' });
    assert.equal(await sendOrderAction(reject, { endpoint: '/o', id: 3, action: 'reject', body, csrfToken: 't' }), 'success');
    assert.equal(reject.calls[0].url, '/o/3/reject');
    assert.equal(reject.calls[0].init.body, '{"rejection_reason":"Out of stock"}');
    assert.equal(reject.calls[0].init.headers['Content-Type'], 'application/json');
});

test('a 422, a network failure, a redirect and a broken answer each have their outcome', async () => {
    const send = (answer, action = 'reject') => sendOrderAction(fakeFetch(answer), { endpoint: '/o', id: 1, action });
    assert.equal(await send({ status: 422, body: { message: 'm', errors: { rejection_reason: ['The rejection reason field must not be greater than 1000 characters.'] } } }), 'invalid');
    assert.equal(await send({ status: 422, body: { message: 'Only pending orders can be rejected.' } }), 'conflict');
    assert.equal(await send('network'), 'network');
    assert.equal(await send({ status: 200, type: 'text/html; charset=UTF-8', redirected: true }), 'failed');
    assert.equal(await send({ status: 200, badJson: true }), 'failed');
    assert.equal(await send({ status: 500, type: 'text/html' }), 'failed');
    assert.equal(await send({ status: 419, type: 'text/html' }), 'session');
});

test('one action at a time', () => {
    const gate = createActionGate();
    assert.equal(gate.start(), true);
    assert.equal(gate.running, true);
    assert.equal(gate.start(), false, 'A second click or Enter while waiting is refused.');
    gate.finish();
    assert.equal(gate.start(), true);
});

test('templates: the number is split out for isolation, placeholders are filled as plain text', () => {
    assert.deepEqual(numberedParts('Accept order :number?'), ['Accept order ', '?']);
    assert.deepEqual(numberedParts('قبول الطلب :number؟'), ['قبول الطلب ', '؟']);
    assert.deepEqual(numberedParts('No number'), ['No number', null]);
    assert.deepEqual(numberedParts(undefined), ['', null]);
    assert.equal(fill('Order :number was accepted.', { number: 'HF-1-V2' }), 'Order HF-1-V2 was accepted.');
    assert.equal(fill('The reason must be :max characters or fewer.', { max: 1000 }), 'The reason must be 1000 characters or fewer.');
    assert.equal(fill('Order :number', { number: '$& <b>' }), 'Order $& <b>');
    assert.equal(reasonCount(''), '0 / 1000');
    assert.equal(reasonCount('abc'), '3 / 1000');
});

test('texts come from the page; each missing one keeps its English text; Accept has its own conflict text', () => {
    const texts = actionTexts(JSON.stringify({ success: { accept: 'تم قبول الطلب :number.', reject: '' }, errors: { network: 'تعذّر الاتصال.' }, reason: { required: 'اكتب السبب.' } }));
    assert.equal(texts.success.accept, 'تم قبول الطلب :number.');
    assert.equal(texts.success.reject, 'Order :number was rejected.');
    assert.equal(texts.errors.network, 'تعذّر الاتصال.');
    assert.equal(texts.reason.required, 'اكتب السبب.');
    assert.equal(texts.reason.too_long, 'The reason must be :max characters or fewer.');
    for (const raw of [undefined, '', '{bad', 'null']) assert.equal(actionTexts(raw).errors.failed, 'The order could not be updated. Please try again.');
    assert.equal(outcomeMessage(texts, 'accept', 'conflict'), texts.errors.conflictAccept);
    assert.equal(outcomeMessage(texts, 'reject', 'conflict'), texts.errors.conflict);
    assert.equal(outcomeMessage(texts, 'accept', 'mystery'), texts.errors.failed);
});

// ---------------------------------------------------------------- the controller, on a fake document

const doc = { activeElement: null };

class El {
    constructor(props = {}) {
        Object.assign(this, { dataset: {}, attributes: {}, listeners: {}, textContent: '', hidden: false, disabled: false, value: '', readOnly: false, isConnected: true, open: false, parts: [], map: {}, ownerDocument: { createElement: () => new El() } }, props);
    }

    addEventListener(type, handler) { (this.listeners[type] ??= []).push(handler); }

    dispatch(type) {
        const event = { type, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        (this.listeners[type] ?? []).forEach((handler) => handler(event));

        return event;
    }

    setAttribute(name, value) { this.attributes[name] = String(value); }

    getAttribute(name) { return this.attributes[name] ?? null; }

    focus() { doc.activeElement = this; }

    replaceChildren(...nodes) { this.parts = nodes; this.textContent = nodes.map((node) => (typeof node === 'string' ? node : node.textContent)).join(''); }

    querySelector(selector) { return this.map[selector] ?? null; }

    showModal() { this.open = true; }

    close() { if (!this.open) return; this.open = false; this.dispatch('close'); }
}

const dialogFor = (template, withReason) => {
    const ui = {
        form: new El(), title: new El({ dataset: { template } }), error: new El({ hidden: true }), cancel: new El(), confirm: new El(),
        reason: withReason ? new El({ attributes: { 'aria-invalid': 'false' } }) : null, reasonError: withReason ? new El({ hidden: true }) : null, count: withReason ? new El() : null,
    };
    ui.dialog = new El({ map: { form: ui.form, '[data-order-dialog-title]': ui.title, '[data-order-dialog-error]': ui.error, '[data-order-dialog-cancel]': ui.cancel, '[data-order-dialog-confirm]': ui.confirm, '[data-order-reject-reason]': ui.reason, '[data-order-reject-error]': ui.reasonError, '[data-order-reject-count]': ui.count } });

    return ui;
};

/** A controllable fetch: each call waits until answer() is called. */
const pendingFetch = () => {
    const calls = [];
    const impl = (url, init) => new Promise((resolve, reject) => calls.push({ url, init, resolve, reject }));
    impl.calls = calls;
    impl.answer = (status, body, index = calls.length - 1) => calls[index].resolve({ status, ok: status >= 200 && status < 300, redirected: false, headers: { get: () => 'application/json' }, json: async () => body });
    impl.fail = (index = calls.length - 1) => calls[index].reject(new TypeError('Failed to fetch'));

    return impl;
};

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const setup = (texts = {}) => {
    const accept = dialogFor('Accept order :number?', false);
    const reject = dialogFor('Reject order :number?', true);
    const success = new El();
    const failure = new El();
    const root = new El({ dataset: { orderActionTexts: JSON.stringify(texts) }, map: { '[data-order-success]': success, '[data-order-failure]': failure, '[data-order-dialog="accept"]': accept.dialog, '[data-order-dialog="reject"]': reject.dialog } });
    const fetchImpl = pendingFetch();
    const reloads = [];
    const actions = createOrderActions({ root, endpoint: '/o', csrfToken: 'tok', fetchImpl, reload: (args) => reloads.push(args) });

    return { actions, accept, reject, success, failure, fetchImpl, reloads };
};

const ORDER = { id: 9, number: 'HF-100-V2' };

test('Accept asks first: nothing is sent until Confirm; the title names the order, isolated left to right; focus starts on Cancel', () => {
    const { actions, accept, fetchImpl } = setup();
    const button = new El();
    actions.request('accept', ORDER, button);

    assert.equal(accept.dialog.open, true);
    assert.equal(fetchImpl.calls.length, 0, 'Opening sends nothing.');
    assert.equal(accept.title.textContent, 'Accept order HF-100-V2?');
    assert.equal(accept.title.parts[1].dir, 'ltr', 'The number is its own left-to-right element.');
    assert.equal(doc.activeElement, accept.cancel, 'Enter alone never accepts.');
});

test('Cancel and Escape close Accept without sending and give focus back to the button', () => {
    for (const how of ['cancel', 'escape']) {
        const { actions, accept, fetchImpl } = setup();
        const button = new El();
        actions.request('accept', ORDER, button);
        if (how === 'cancel') accept.cancel.dispatch('click');
        else assert.equal(accept.dialog.dispatch('cancel').defaultPrevented, true);
        assert.equal(accept.dialog.open, false, how);
        assert.equal(fetchImpl.calls.length, 0, `${how}: nothing sent.`);
        assert.equal(doc.activeElement, button, `${how}: focus back on the action button.`);
    }
});

test('Confirm sends once; a second Confirm, Cancel or Escape while waiting does nothing; success closes, announces and reloads', async () => {
    const { actions, accept, fetchImpl, success, failure, reloads } = setup({ success: { accept: 'تم قبول الطلب :number.' } });
    actions.request('accept', ORDER, new El());
    accept.form.dispatch('submit');
    accept.form.dispatch('submit');
    await tick();

    assert.equal(fetchImpl.calls.length, 1, 'One request only.');
    assert.equal(fetchImpl.calls[0].url, '/o/9/accept');
    assert.equal(accept.confirm.disabled, true);
    assert.equal(accept.cancel.disabled, true);
    assert.equal(accept.form.getAttribute('aria-busy'), 'true');
    accept.cancel.dispatch('click');
    assert.equal(accept.dialog.dispatch('cancel').defaultPrevented, true);
    assert.equal(accept.dialog.open, true, 'Not closed while waiting.');
    actions.request('reject', ORDER, new El());
    assert.equal(fetchImpl.calls.length, 1, 'No other action meanwhile.');

    fetchImpl.answer(200, { order: { status: 'accepted' } });
    await tick(); await tick();
    assert.equal(accept.dialog.open, false);
    assert.equal(success.textContent, 'تم قبول الطلب HF-100-V2.');
    assert.equal(failure.textContent, '');
    assert.deepEqual(reloads, [{ orderId: 9 }], 'The list reloads and puts focus on the same order.');
});

test('Reject: an empty or blank reason is refused in the dialog, nothing is sent; a valid one is sent trimmed', async () => {
    const { actions, reject, fetchImpl, success } = setup({ reason: { required: 'اكتب سبب رفض هذا الطلب.' } });
    actions.request('reject', ORDER, new El());
    assert.equal(doc.activeElement, reject.reason, 'Focus starts in the reason.');
    assert.equal(reject.count.textContent, '0 / 1000');

    for (const blank of ['', '   \n ']) {
        reject.reason.value = blank;
        reject.form.dispatch('submit');
        await tick();
        assert.equal(fetchImpl.calls.length, 0, JSON.stringify(blank));
        assert.equal(reject.reasonError.textContent, 'اكتب سبب رفض هذا الطلب.');
        assert.equal(reject.reasonError.hidden, false);
        assert.equal(reject.reason.getAttribute('aria-invalid'), 'true');
        assert.equal(doc.activeElement, reject.reason);
    }

    reject.reason.value = '  Out of stock ';
    reject.reason.dispatch('input');
    assert.equal(reject.reasonError.hidden, true, 'The error goes as soon as the reason is valid.');
    assert.equal(reject.count.textContent, '15 / 1000');
    reject.form.dispatch('submit');
    await tick();
    assert.equal(fetchImpl.calls[0].init.body, '{"rejection_reason":"Out of stock"}');
    fetchImpl.answer(200, { order: { status: 'rejected' } });
    await tick(); await tick();
    assert.equal(success.textContent, 'Order HF-100-V2 was rejected.');
});

test('Reject: 1000 characters are sent, 1001 are refused before sending', async () => {
    const { actions, reject, fetchImpl } = setup();
    actions.request('reject', ORDER, new El());
    reject.reason.value = 'a'.repeat(1001);
    reject.form.dispatch('submit');
    await tick();
    assert.equal(fetchImpl.calls.length, 0);
    assert.equal(reject.reasonError.textContent, 'The reason must be 1000 characters or fewer.');

    reject.reason.value = 'a'.repeat(1000);
    reject.form.dispatch('submit');
    await tick();
    assert.equal(fetchImpl.calls.length, 1);
});

test('Reject: a 422 with field errors keeps the dialog open with a translated field error, never the server text', async () => {
    const { actions, reject, fetchImpl, success, failure, reloads } = setup({ reason: { invalid: 'لم يُقبل السبب.' } });
    actions.request('reject', ORDER, new El());
    reject.reason.value = 'Reason';
    reject.form.dispatch('submit');
    await tick();
    fetchImpl.answer(422, { message: 'The rejection reason field is required.', errors: { rejection_reason: ['The rejection reason field is required.'] } });
    await tick(); await tick();

    assert.equal(reject.dialog.open, true);
    assert.equal(reject.reasonError.textContent, 'لم يُقبل السبب.');
    assert.equal(reject.reason.getAttribute('aria-invalid'), 'true');
    assert.equal(doc.activeElement, reject.reason);
    assert.equal(reject.confirm.disabled, false, 'The vendor can try again.');
    assert.equal(reject.reason.readOnly, false);
    assert.deepEqual([success.textContent, failure.textContent, reloads], ['', '', []], 'No success, no reload.');
});

test('a network failure keeps the dialog open with its message; a retry is possible', async () => {
    const { actions, accept, fetchImpl, success, reloads } = setup({ errors: { network: 'تعذّر الاتصال.' } });
    actions.request('accept', ORDER, new El());
    accept.form.dispatch('submit');
    await tick();
    fetchImpl.fail();
    await tick(); await tick();

    assert.equal(accept.dialog.open, true);
    assert.deepEqual([accept.error.textContent, accept.error.hidden], ['تعذّر الاتصال.', false]);
    assert.equal(doc.activeElement, accept.confirm);
    assert.deepEqual([success.textContent, reloads], ['', []], 'Never reported as a success.');

    accept.form.dispatch('submit');
    await tick();
    assert.equal(fetchImpl.calls.length, 2, 'Confirm again sends again.');
    assert.equal(accept.error.hidden, true, 'The old message goes when trying again.');
});

test('a stock or state refusal (422 without field errors) closes the dialog, says so in the page and refreshes the list', async () => {
    const { actions, accept, fetchImpl, success, failure, reloads } = setup();
    actions.request('accept', ORDER, new El());
    accept.form.dispatch('submit');
    await tick();
    fetchImpl.answer(422, { message: 'Not enough physical stock to commit product [Clay jug].' });
    await tick(); await tick();

    assert.equal(accept.dialog.open, false);
    assert.equal(failure.textContent, 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The list has been refreshed.');
    assert.doesNotMatch(failure.textContent, /Clay jug|physical stock/, 'The server message is not shown.');
    assert.equal(success.textContent, '');
    assert.deepEqual(reloads, [{ orderId: 9 }]);
});

test('session and permission failures stay in the dialog with their own text', async () => {
    for (const [status, text] of [[419, 'Your session has ended. Sign in again, then try again.'], [401, 'Your session has ended. Sign in again, then try again.'], [403, 'You cannot update this order.'], [500, 'The order could not be updated. Please try again.']]) {
        const { actions, reject, fetchImpl, success } = setup();
        actions.request('reject', ORDER, new El());
        reject.reason.value = 'Reason';
        reject.form.dispatch('submit');
        await tick();
        fetchImpl.answer(status, { message: 'internal detail' });
        await tick(); await tick();
        assert.equal(reject.dialog.open, true, String(status));
        assert.equal(reject.error.textContent, text, String(status));
        assert.equal(success.textContent, '');
    }
});

test('a dialog the browser closed while waiting: the outcome is said in the page and focus goes back to the button', async () => {
    const { actions, accept, fetchImpl, failure } = setup();
    const button = new El();
    actions.request('accept', ORDER, button);
    accept.form.dispatch('submit');
    await tick();
    accept.dialog.close();
    assert.equal(doc.activeElement, button);
    fetchImpl.fail();
    await tick(); await tick();
    assert.equal(failure.textContent, 'The connection failed. Check your connection and try again.');
});

test('Preparing and Ready are sent at once, as before; a failure re-enables the button and says why; a success announces and reloads', async () => {
    const { actions, accept, reject, fetchImpl, success, failure, reloads } = setup();
    const preparing = new El();
    actions.request('preparing', ORDER, preparing);
    actions.request('preparing', ORDER, preparing);
    await tick();
    assert.equal(accept.dialog.open || reject.dialog.open, false, 'No dialog.');
    assert.equal(fetchImpl.calls.length, 1, 'One request.');
    assert.deepEqual([fetchImpl.calls[0].url, fetchImpl.calls[0].init.body], ['/o/9/preparing', null]);
    assert.equal(preparing.disabled, true);
    fetchImpl.fail();
    await tick(); await tick();
    assert.equal(preparing.disabled, false);
    assert.equal(doc.activeElement, preparing);
    assert.equal(failure.textContent, 'The connection failed. Check your connection and try again.');

    actions.request('ready', ORDER, new El());
    await tick();
    assert.equal(failure.textContent, '', 'A new action clears the old message.');
    fetchImpl.answer(200, { order: { status: 'ready_for_delivery' } });
    await tick(); await tick();
    assert.equal(success.textContent, 'Order HF-100-V2 is ready for delivery.');
    assert.deepEqual(reloads, [{ orderId: 9 }]);
});
