/*
 * ORD-02A: the vendor order details page (resources/views/vendor-dashboard/order-details.blade.php). It renders the
 * page's embedded order (the server's minimal whitelist, OrderController::details()) as plain text, and reloads that
 * same data as JSON from the page URL after an action. Only the contract's own keys are read; amounts are shown as
 * stored, timestamps only when the server gave them, and the actions are the server's. Order actions use the orders
 * list's request logic and dialogs (vendor-order-actions.js); nothing here sends an action itself.
 */
import { actionTexts, createOrderActions } from './vendor-order-actions.js';
import { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES } from './vendor-order-shared.js';

const root = typeof document !== 'undefined' ? document.querySelector('[data-vendor-order-details]') : null;

/** The actions the page can show, in this order (the server's `actions`; each is also its endpoint). */
export const DETAIL_ACTIONS = ['accept', 'reject', 'preparing', 'ready'];

/** The timeline, in lifecycle order: created_at, then the contract's timestamps (there is no preparing timestamp). */
export const TIMELINE_KEYS = ['created_at', 'accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at'];

/** JSON from a data attribute; anything unreadable is {}. */
export function parseJson(raw) {
    try {
        const value = JSON.parse(raw || '{}');

        return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    } catch {
        return {};
    }
}

const text = (value) => (typeof value === 'string' ? value : null);
const amount = (value) => (typeof value === 'string' || typeof value === 'number' ? String(value) : null);
const isTime = (value) => typeof value === 'string' && value.trim() !== '' && !Number.isNaN(new Date(value).getTime());

/**
 * The order as the page uses it, read from the contract's keys only (any other key is ignored, never shown); null
 * when it is not an order (no id or number).
 */
export function orderView(raw) {
    if (!raw || typeof raw !== 'object' || !Number.isInteger(raw.id) || typeof raw.number !== 'string' || raw.number === '') return null;
    const timeline = raw.timeline && typeof raw.timeline === 'object' ? raw.timeline : {};

    return {
        id: raw.id,
        number: raw.number,
        status: text(raw.status) ?? '',
        createdAt: text(raw.created_at),
        timeline: Object.fromEntries(TIMELINE_KEYS.slice(1).filter((key) => key in timeline).map((key) => [key, text(timeline[key])])),
        rejectionReason: text(raw.rejection_reason),
        subtotal: amount(raw.subtotal),
        deliveryFee: amount(raw.delivery_fee),
        total: amount(raw.total),
        items: (Array.isArray(raw.items) ? raw.items : []).filter((item) => item && typeof item === 'object').map((item) => ({
            productName: text(item.product_name),
            productSku: text(item.product_sku),
            quantity: Number.isInteger(item.quantity) ? item.quantity : null,
            unitPrice: amount(item.unit_price),
            lineTotal: amount(item.line_total),
        })),
        actions: DETAIL_ACTIONS.filter((action) => Array.isArray(raw.actions) && raw.actions.includes(action)),
    };
}

/** The timeline entries with a real time, in lifecycle order: [{ key, value }]. Nothing is invented for a missing one. */
export function timelineEntries(view) {
    if (!view) return [];

    return TIMELINE_KEYS
        .map((key) => ({ key, value: key === 'created_at' ? view.createdAt : view.timeline[key] }))
        .filter(({ value }) => isTime(value));
}

/** What a reload answer means: 'ok' (an order), 'gone' (404), 'session' (401/419), 'forbidden' (403) or 'failed'. */
export function refreshOutcome({ status, ok, json, payload } = {}) {
    if (status === 404) return 'gone';
    if (status === 401 || status === 419) return 'session';
    if (status === 403) return 'forbidden';

    return ok && json && orderView(payload?.order) ? 'ok' : 'failed';
}

/** Reloads the page's order from its own URL as JSON: { outcome, order }; a network failure is 'network'. */
export async function fetchDetails(fetchImpl, url) {
    let response;
    try {
        response = await fetchImpl(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
    } catch {
        return { outcome: 'network', order: null };
    }
    const json = (response.headers?.get?.('content-type') ?? '').includes('application/json');
    let payload = null;
    if (json) {
        try { payload = await response.json(); } catch { payload = null; }
    }
    const outcome = refreshOutcome({ status: response.status, ok: response.ok, json: json && payload !== null, payload });

    return { outcome, order: outcome === 'ok' ? orderView(payload.order) : null };
}

function init() {
    const $ = (hook) => root.querySelector(`[data-${hook}]`);
    const texts = parseJson(root.dataset.detailsTexts);
    const outcomes = actionTexts(root.dataset.orderActionTexts);
    const locale = document.documentElement.lang || 'en';
    const timeZone = root.dataset.timezone || 'UTC';
    const content = $('details-content');
    const refreshing = $('details-refreshing');
    const errorState = $('details-error');
    const errorText = $('details-error-text');
    const retry = $('details-retry');
    const gone = $('details-gone');
    const actionsBox = $('details-actions');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    let view = orderView(parseJson($('order-details')?.textContent));
    let latest = 0;

    const isolated = (value, ltr = false) => {
        const element = document.createElement('bdi');
        if (ltr) element.dir = 'ltr';
        element.textContent = value ?? '—';
        return element;
    };
    const time = (value) => {
        const element = document.createElement('time');
        const shown = formatOrderDate(value, locale, timeZone);
        if (shown) element.dateTime = value;
        element.textContent = shown || '—';
        return element;
    };

    const orderActions = createOrderActions({
        root, endpoint: root.dataset.actionEndpoint, csrfToken, fetchImpl: (...args) => window.fetch(...args),
        reload: () => load({ focus: true }),
    });

    const render = () => {
        const badge = document.createElement('span');
        badge.className = 'vendor-status-badge';
        badge.dataset.status = view.status;
        badge.textContent = texts.statuses?.[view.status] ?? view.status;
        $('details-status').replaceChildren(badge);
        $('details-created').replaceChildren(time(view.createdAt));
        $('details-reason-row').hidden = !view.rejectionReason;
        $('details-reason').replaceChildren(isolated(view.rejectionReason));

        actionsBox.replaceChildren(...view.actions.map((action) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `btn ${ORDER_ACTION_STYLES[action]}`;
            button.dataset.orderAction = action;
            button.textContent = texts.actions?.[action]?.label ?? action;
            const name = numberedLabel(texts.actions?.[action]?.named, view.number);
            if (name) button.setAttribute('aria-label', name);
            button.addEventListener('click', () => orderActions.request(action, { id: view.id, number: view.number }, button));
            return button;
        }));
        actionsBox.hidden = view.actions.length === 0;
        $('details-no-actions').hidden = view.actions.length !== 0;

        const columns = texts.columns ?? {};
        $('details-items').replaceChildren(...view.items.map((item) => {
            const row = document.createElement('tr');
            [
                ['product_name', isolated(item.productName)],
                ['product_sku', isolated(item.productSku, true)],
                ['quantity', isolated(item.quantity === null ? null : String(item.quantity), true)],
                ['unit_price', isolated(formatAmount(item.unitPrice), true)],
                ['line_total', isolated(formatAmount(item.lineTotal), true)],
            ].forEach(([column, value]) => {
                const cell = document.createElement('td');
                cell.dataset.label = columns[column] ?? column;
                cell.append(value);
                row.append(cell);
            });
            return row;
        }));
        $('details-items-table').hidden = view.items.length === 0;
        $('details-no-items').hidden = view.items.length !== 0;

        [['subtotal', view.subtotal], ['delivery_fee', view.deliveryFee], ['total', view.total]].forEach(([key, value]) => {
            root.querySelector(`[data-details-amount="${key}"]`).replaceChildren(isolated(formatAmount(value), true));
        });

        $('details-timeline').replaceChildren(...timelineEntries(view).map(({ key, value }) => {
            const entry = document.createElement('li');
            entry.dataset.timeline = key;
            const label = document.createElement('span');
            label.className = 'vendor-order-timeline-label';
            label.textContent = texts.timeline?.[key] ?? key;
            entry.append(label, time(value));
            return entry;
        }));
    };

    const show = (state) => {
        content.hidden = state !== 'ready';
        errorState.hidden = state !== 'error';
        gone.hidden = state !== 'gone';
    };

    // After an action: focus on the order's first action, else the summary heading; Retry or the "gone" heading otherwise.
    async function load({ focus = false } = {}) {
        const mine = ++latest;
        refreshing.hidden = false;
        content.setAttribute('aria-busy', 'true');
        const { outcome, order } = await fetchDetails((...args) => window.fetch(...args), root.dataset.endpoint);
        if (mine !== latest) return;
        refreshing.hidden = true;
        content.setAttribute('aria-busy', 'false');
        if (outcome === 'ok') {
            view = order;
            render();
            show('ready');
            if (focus) (actionsBox.querySelector('[data-order-action]') ?? $('details-summary-title')).focus();
        } else if (outcome === 'gone') {
            show('gone');
            if (focus) $('details-gone-title').focus();
        } else {
            errorText.textContent = outcomes.errors[outcome] ?? outcomes.errors.failed;
            show('error');
            if (focus) retry.focus();
        }
    }

    retry.addEventListener('click', () => load({ focus: true }));

    if (view) {
        render();
        show('ready');
    } else {
        load();
    }
}

if (root) init();
