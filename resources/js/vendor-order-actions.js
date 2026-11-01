/*
 * ORD-01D: the vendor orders list's actions (used by resources/js/vendor-resource-list.js). Accept and Reject open a
 * confirmation dialog (native <dialog>, as the media library); Preparing and Ready are sent at once, as before. Every
 * action calls the same endpoint with the same body as before (PATCH orders/{id}/{action}); only its feedback changed.
 * A server's own message is never shown: each outcome has the page's translated text (data-order-action-texts).
 * Nothing here touches the document until createOrderActions() is called.
 */

/** RejectVendorOrderRequest: rejection_reason is required, at most 1000 characters. */
export const REASON_MAX = 1000;

/** The status the endpoint returns for each action when it succeeds (VendorDashboard\OrderController). */
export const EXPECTED_STATUS = { accept: 'accepted', reject: 'rejected', preparing: 'preparing', ready: 'ready_for_delivery' };

/** The actions that ask first. */
export const CONFIRMED_ACTIONS = ['accept', 'reject'];

/**
 * A rejection reason as it is sent (trimmed, as the server trims it) and its problem: 'required' when empty or only
 * spaces, 'too_long' above the limit, otherwise null.
 */
export function validateReason(value) {
    const reason = typeof value === 'string' ? value.trim() : '';
    if (reason === '') return { reason, error: 'required' };
    if (reason.length > REASON_MAX) return { reason, error: 'too_long' };

    return { reason, error: null };
}

/**
 * What a response means, from its status and body only: 'success' (a JSON answer with the order in its new status),
 * 'invalid' (422 with field errors), 'conflict' (the order changed or is gone: 422 without field errors, 404, 409, or a
 * success answer with another status), 'session' (401, 419), 'forbidden' (403) or 'failed' (anything else, including
 * a redirect or a page that is not JSON). A 2xx answer that does not show the expected status is never a success.
 */
export function actionOutcome(action, { status, ok, json, redirected, payload } = {}) {
    if (status === 401 || status === 419) return 'session';
    if (status === 403) return 'forbidden';
    if (redirected || !json) return 'failed';
    if (ok) return payload?.order?.status === EXPECTED_STATUS[action] ? 'success' : 'conflict';
    if (status === 422) return payload?.errors && typeof payload.errors === 'object' && Object.keys(payload.errors).length > 0 ? 'invalid' : 'conflict';
    if (status === 404 || status === 409) return 'conflict';

    return 'failed';
}

/**
 * Sends one action as before (same URL, method, headers and body) and gives its outcome; a network failure is
 * 'network'. fetchImpl is the browser's fetch (a test passes its own).
 */
export async function sendOrderAction(fetchImpl, { endpoint, id, action, body = null, csrfToken = '' }) {
    let response;
    try {
        response = await fetchImpl(`${endpoint}/${id}/${action}`, {
            method: 'PATCH', credentials: 'same-origin', body,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, ...(body ? { 'Content-Type': 'application/json' } : {}) },
        });
    } catch {
        return 'network';
    }

    const json = (response.headers?.get?.('content-type') ?? '').includes('application/json');
    let payload = null;
    if (json) {
        try { payload = await response.json(); } catch { payload = null; }
    }

    return actionOutcome(action, { status: response.status, ok: response.ok, json: json && payload !== null, redirected: response.redirected === true, payload });
}

/** One action at a time: a second start while one is running is refused (a double click, Enter twice). */
export function createActionGate() {
    let running = false;

    return {
        start: () => (running ? false : (running = true)),
        finish: () => { running = false; },
        get running() { return running; },
    };
}

/** A ":number" template split around the order number, so the number can be isolated: [before, after] (no number: [text, null]). */
export function numberedParts(template) {
    const text = typeof template === 'string' ? template : '';
    const at = text.indexOf(':number');

    return at === -1 ? [text, null] : [text.slice(0, at), text.slice(at + ':number'.length)];
}

/** A template's ":placeholders" filled in as plain text (split/join, so "$" in a value stays as it is). */
export const fill = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.split(`:${key}`).join(String(value)), String(template ?? ''));

/** The reason counter, in Latin digits as everywhere in the dashboard: "12 / 1000". */
export const reasonCount = (value) => `${typeof value === 'string' ? value.length : 0} / ${REASON_MAX}`;

const FALLBACK = {
    success: { accept: 'Order :number was accepted.', reject: 'Order :number was rejected.', preparing: 'Order :number is now being prepared.', ready: 'Order :number is ready for delivery.' },
    errors: {
        conflict: 'This order could not be updated because it has changed or is no longer available. The list has been refreshed.',
        conflictAccept: 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The list has been refreshed.',
        session: 'Your session has ended. Sign in again, then try again.',
        forbidden: 'You cannot update this order.',
        network: 'The connection failed. Check your connection and try again.',
        failed: 'The order could not be updated. Please try again.',
    },
    reason: { required: 'Enter the reason for rejecting this order.', too_long: 'The reason must be :max characters or fewer.', invalid: 'The reason was not accepted. Check it and try again.' },
};

/** The page's texts (data-order-action-texts, JSON); each missing, empty or non-text value keeps its English text. */
export function actionTexts(raw) {
    let given = {};
    try { given = JSON.parse(raw || '{}') ?? {}; } catch { given = {}; }
    const filled = (value) => typeof value === 'string' && value.trim() !== '';
    const group = (name) => Object.fromEntries(Object.entries(FALLBACK[name]).map(([key, text]) => [key, filled(given?.[name]?.[key]) ? given[name][key] : text]));

    return { success: group('success'), errors: group('errors'), reason: group('reason') };
}

/** The message for an outcome that is not a success (Accept has its own conflict text: its stock may be gone). */
export const outcomeMessage = (texts, action, outcome) => (outcome === 'conflict' && action === 'accept' ? texts.errors.conflictAccept : texts.errors[outcome] ?? texts.errors.failed);

/**
 * The actions of one list. reload({ orderId }) reloads the list and then puts focus on that order's first action, or
 * on the list heading when the order is no longer shown (the list does that; see vendor-resource-list.js).
 */
export function createOrderActions({ root, endpoint, csrfToken, fetchImpl, reload }) {
    const texts = actionTexts(root.dataset.orderActionTexts);
    const gate = createActionGate();
    const success = root.querySelector('[data-order-success]');
    const failure = root.querySelector('[data-order-failure]');
    let current = null;

    // Both regions stay in the page (role status / alert) so that a new text is announced; only one has text at a time.
    const announce = (tone, message) => {
        success.textContent = tone === 'success' ? message : '';
        failure.textContent = tone === 'error' ? message : '';
    };

    const dialogs = Object.fromEntries(CONFIRMED_ACTIONS.map((action) => {
        const dialog = root.querySelector(`[data-order-dialog="${action}"]`);
        const ui = {
            dialog,
            form: dialog.querySelector('form'),
            title: dialog.querySelector('[data-order-dialog-title]'),
            error: dialog.querySelector('[data-order-dialog-error]'),
            cancel: dialog.querySelector('[data-order-dialog-cancel]'),
            confirm: dialog.querySelector('[data-order-dialog-confirm]'),
            reason: dialog.querySelector('[data-order-reject-reason]'),
            reasonError: dialog.querySelector('[data-order-reject-error]'),
            count: dialog.querySelector('[data-order-reject-count]'),
        };

        return [action, ui];
    }));

    const setTitle = (ui, number) => {
        const [before, after] = numberedParts(ui.title.dataset.template);
        const isolated = root.ownerDocument.createElement('bdi');
        isolated.dir = 'ltr';
        isolated.textContent = String(number ?? '');
        ui.title.replaceChildren(before, ...(after === null ? [] : [isolated, after]));
    };

    const showReasonError = (ui, message) => {
        ui.reasonError.textContent = message;
        ui.reasonError.hidden = message === '';
        ui.reason.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
    };

    const showDialogError = (ui, message) => {
        ui.error.textContent = message;
        ui.error.hidden = message === '';
    };

    const setPending = (ui, pending) => {
        ui.confirm.disabled = pending;
        ui.cancel.disabled = pending;
        if (ui.reason) ui.reason.readOnly = pending;
        ui.form.setAttribute('aria-busy', pending ? 'true' : 'false');
    };

    // Closing by Cancel or Escape gives focus back to the button that opened the dialog; after an answer the list
    // reload decides where focus goes.
    const close = (ui, { restore }) => {
        const opener = current?.opener;
        current = null;
        if (ui.dialog.open) ui.dialog.close();
        if (restore && opener?.isConnected) opener.focus();
    };

    const open = (action, item, opener) => {
        const ui = dialogs[action];
        if (gate.running || current) return;
        current = { action, item, opener };
        announce('none', '');
        setTitle(ui, item.number);
        showDialogError(ui, '');
        setPending(ui, false);
        if (ui.reason) { ui.reason.value = ''; ui.count.textContent = reasonCount(''); showReasonError(ui, ''); }
        ui.dialog.showModal();
        // Reject starts in the reason; Accept on Cancel, so Enter alone never accepts.
        (ui.reason ?? ui.cancel).focus();
    };

    const finish = (action, item, outcome, { opener, dialog: shown }) => {
        const number = item.number;
        // A dialog the browser closed meanwhile (a repeated Escape) is not used: the page regions say the outcome.
        const dialog = shown?.dialog.open ? shown : null;
        if (outcome === 'success' || outcome === 'conflict') {
            if (dialog) close(dialog, { restore: false });
            announce(outcome === 'success' ? 'success' : 'error', outcome === 'success' ? fill(texts.success[action], { number }) : outcomeMessage(texts, action, outcome));
            reload({ orderId: item.id });

            return;
        }
        if (dialog) {
            // The dialog stays open, so the vendor can try again or cancel.
            if (outcome === 'invalid' && dialog.reason) {
                const problem = validateReason(dialog.reason.value).error ?? 'invalid';
                showReasonError(dialog, fill(texts.reason[problem], { max: REASON_MAX }));
                dialog.reason.focus();
            } else {
                showDialogError(dialog, outcomeMessage(texts, action, outcome));
                dialog.confirm.focus();
            }

            return;
        }
        announce('error', outcomeMessage(texts, action, outcome));
        if (opener?.isConnected) { opener.disabled = false; opener.focus(); }
    };

    Object.entries(dialogs).forEach(([action, ui]) => {
        ui.form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!current || current.action !== action) return;
            let body = null;
            if (ui.reason) {
                const { reason, error } = validateReason(ui.reason.value);
                if (error) { showReasonError(ui, fill(texts.reason[error], { max: REASON_MAX })); ui.reason.focus(); return; }
                showReasonError(ui, '');
                body = JSON.stringify({ rejection_reason: reason });
            }
            if (!gate.start()) return;
            const { item, opener } = current;
            showDialogError(ui, '');
            setPending(ui, true);
            const outcome = await sendOrderAction(fetchImpl, { endpoint, id: item.id, action, body, csrfToken });
            setPending(ui, false);
            gate.finish();
            finish(action, item, outcome, { opener, dialog: ui });
        });
        ui.cancel.addEventListener('click', () => { if (!gate.running) close(ui, { restore: true }); });
        // Escape: refused while the answer is awaited; otherwise the same as Cancel.
        ui.dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            if (!gate.running) close(ui, { restore: true });
        });
        // Closed by the browser itself (some close a dialog on a repeated Escape anyway): as Cancel.
        ui.dialog.addEventListener('close', () => {
            if (!current || current.action !== action) return;
            const { opener } = current;
            current = null;
            if (opener?.isConnected) opener.focus();
        });
        ui.reason?.addEventListener('input', () => {
            ui.count.textContent = reasonCount(ui.reason.value);
            if (ui.reason.getAttribute('aria-invalid') === 'true' && validateReason(ui.reason.value).error === null) showReasonError(ui, '');
        });
    });

    /** The click of an order action button. */
    const request = async (action, item, button) => {
        if (CONFIRMED_ACTIONS.includes(action)) { open(action, item, button); return; }
        if (!gate.start()) return;
        announce('none', '');
        button.disabled = true;
        const outcome = await sendOrderAction(fetchImpl, { endpoint, id: item.id, action, csrfToken });
        gate.finish();
        finish(action, item, outcome, { opener: button, dialog: null });
    };

    return { request };
}
