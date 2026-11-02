import { createOrderActions } from './vendor-order-actions.js';
// ORD-02A (step 2): the order presentation helpers are shared with the order details page; re-exported here so the
// existing imports keep working.
import { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES } from './vendor-order-shared.js';

export { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES };

const root = typeof document !== 'undefined' ? document.querySelector('[data-vendor-resource-list]') : null;

/**
 * VENDOR-UI-CLEANUP-01A (F-3): the list's action texts, translated by the page (data-list-texts, JSON). A page that
 * gives none (or an unreadable value) keeps the former English texts.
 */
export function listTexts(raw) {
    let given = {};

    try {
        given = JSON.parse(raw || '{}') ?? {};
    } catch {
        given = {};
    }

    const filled = (value) => typeof value === 'string' && value.trim() !== '';
    const pick = (key, fallback) => (filled(given[key]) ? given[key] : fallback);
    // VENDOR-UI-CLEANUP-01B: column labels (by column path), status labels (by status value) and the actions' accessible
    // names (":name"), each only when given as text.
    const strings = (value) => Object.fromEntries(Object.entries(value && typeof value === 'object' ? value : {}).filter(([, text]) => filled(text)));

    return {
        actions: pick('actions', 'Actions'),
        edit: pick('edit', 'Edit'),
        delete: pick('delete', 'Delete'),
        editNamed: pick('editNamed', null),
        deleteNamed: pick('deleteNamed', null),
        columns: strings(given.columns),
        statuses: strings(given.statuses),
        // ORD-01C: the orders list's actions (label and ":number" accessible name) and its action error.
        orderActions: Object.fromEntries(Object.entries(ORDER_ACTION_LABELS).map(([kind, fallback]) => {
            const action = given.orderActions?.[kind];

            return [kind, { label: filled(action?.label) ? action.label : fallback, named: filled(action?.named) ? action.named : null }];
        })),
        actionError: pick('actionError', 'The order could not be updated. Please try again.'),
        // ORD-02A (step 4): the orders list's link to an order's details page (label and ":number" accessible name).
        detailsLink: {
            label: filled(given.detailsLink?.label) ? given.detailsLink.label : 'View details',
            named: filled(given.detailsLink?.named) ? given.detailsLink.named : null,
        },
    };
}

/** ORD-01C: each order action's former English label, kept when the page gives none. */
const ORDER_ACTION_LABELS = { accept: 'Accept', reject: 'Reject', preparing: 'Preparing', ready: 'Ready' };

/**
 * The actions shown for an order status, in this order (each is also its endpoint: orders/{id}/{action}); the same
 * statuses the endpoint accepts them from. ORD-01C changed only how they look, not when they are offered. VEN-BE-020
 * (slice 1): SRS §4.5, Accepted → Preparing → Ready for Delivery, one step at a time and never repeated.
 */
export function orderActionsFor(status) {
    const actions = [];
    if (status === 'pending') actions.push('accept', 'reject');
    if (status === 'accepted') actions.push('preparing');
    if (status === 'preparing') actions.push('ready');

    return actions;
}

/** VENDOR-UI-CLEANUP-01B: a column's header: the page's translation for its path, else the list's own label. */
export const columnLabel = (path, fallback, texts) => texts.columns?.[path] ?? fallback;

/** VENDOR-UI-CLEANUP-01B: a status as shown: its translated label; an unknown value is shown as it is. */
export const statusLabel = (value, texts) => texts.statuses?.[value] ?? value;

/**
 * VENDOR-PRODUCT-PUBLISH-UI-01E: the moderation badges' texts, translated by the page (data-moderation-texts, JSON):
 * archived and suspended, each only when given as text. There is no wording of its own here.
 */
export function moderationTexts(raw) {
    let given = {};

    try {
        given = JSON.parse(raw || '{}') ?? {};
    } catch {
        given = {};
    }

    const filled = (value) => typeof value === 'string' && value.trim() !== '';

    return Object.fromEntries(['archived', 'suspended'].filter((kind) => filled(given[kind])).map((kind) => [kind, given[kind]]));
}

/**
 * VENDOR-PRODUCT-PUBLISH-UI-01E: a row's moderation badges, [kind, text] in a fixed order (archived, then suspended): one
 * per flag that is exactly true, none for an ordinary product, and none for a flag the page gave no text for.
 */
export const moderationBadges = (moderation, texts) => ['archived', 'suspended']
    .filter((kind) => moderation?.[kind] === true && texts?.[kind])
    .map((kind) => [kind, texts[kind]]);

/** ORD-01A: the lists that page (products since VENDOR-PRODUCT-LIST-PAGINATION-01B, orders); the others load once. */
export const isPaged = (resource) => ['products', 'orders'].includes(resource);

/** VENDOR-PRODUCT-LIST-PAGINATION-01B: a page number: a whole number of at least 1, anything else is page 1. */
export const pageNumber = (value) => {
    const number = typeof value === 'string' && value.trim() !== '' ? Number(value) : value;

    return Number.isInteger(number) && number >= 1 ? number : 1;
};

/**
 * The list endpoint for one page: the endpoint's own query parameters are kept and only page is set. ORD-01B: given
 * filters (the orders list), its search, status and needs_response parameters are set from them, an unused one removed.
 */
export function pageUrl(endpoint, page, base, filters = null) {
    const url = new URL(endpoint, base);
    url.searchParams.set('page', String(pageNumber(page)));
    if (filters) {
        FILTER_PARAMS.forEach((key) => url.searchParams.delete(key));
        Object.entries(filterParams(filters)).forEach(([key, value]) => url.searchParams.set(key, value));
    }

    return url.toString();
}

/** ORD-01B: the orders endpoint's own filter parameters (VendorDashboard\OrderController@index). */
const FILTER_PARAMS = ['search', 'status', 'needs_response'];

/**
 * ORD-01B: the orders list's filters, made safe: the search trimmed (a blank one is no search), the status only when it
 * is one of the list's statuses (the select's options), and needs-response only when exactly true.
 */
export function orderFilters({ search, status, needsResponse } = {}, statuses = []) {
    return {
        search: typeof search === 'string' ? search.trim() : '',
        status: typeof status === 'string' && statuses.includes(status) ? status : '',
        needsResponse: needsResponse === true,
    };
}

/** ORD-01B: the filters of a page address (?search=…&status=…&needs_response=1); needs_response as the API reads it. */
export function filtersFromQuery(query, statuses = []) {
    const params = new URLSearchParams(typeof query === 'string' ? query : '');

    return orderFilters({
        search: params.get('search') ?? '',
        status: params.get('status') ?? '',
        needsResponse: ['1', 'true', 'on', 'yes'].includes(params.get('needs_response')),
    }, statuses);
}

/** ORD-01B: the request parameters of the filters in use; an unused filter sends nothing. */
export function filterParams(filters) {
    return Object.fromEntries([
        ['search', filters?.search || null],
        ['status', filters?.status || null],
        ['needs_response', filters?.needsResponse === true ? '1' : null],
    ].filter(([, value]) => value !== null));
}

/** ORD-01B: whether any search or filter is in use (an empty list then says nothing matches). */
export const hasFilters = (filters) => Object.keys(filterParams(filters)).length > 0;

/**
 * ORD-02A (step 4): the orders list's page address query: its filters and its page (page 1 left out), so a reload, the
 * browser's Back and an order's details page can come back to the same list. Other parameters are kept: '' or '?…'.
 */
export function listQuery(query, filters, page) {
    const params = new URLSearchParams(filtersQuery(query, filters).replace(/^\?/, ''));
    params.delete('page');
    if (pageNumber(page) > 1) params.set('page', String(pageNumber(page)));
    const text = params.toString();

    return text ? `?${text}` : '';
}

/**
 * ORD-02A (step 4): an order's details page ({base}/{id}, base being the orders list page) carrying only the list's
 * context: its filters and its page. null without a valid order id or base.
 */
export function detailsUrl(base, id, filters, page, origin) {
    if (typeof base !== 'string' || base === '' || !Number.isInteger(id) || id < 1) return null;
    const url = new URL(`${base.replace(/\/+$/, '')}/${id}`, origin);
    url.search = listQuery('', filters, page);

    return url.toString();
}

/** ORD-01B: the page address's query for the filters (other parameters kept), so a reload keeps them: '' or '?…'. */
export function filtersQuery(query, filters) {
    const params = new URLSearchParams(typeof query === 'string' ? query : '');
    FILTER_PARAMS.forEach((key) => params.delete(key));
    Object.entries(filterParams(filters)).forEach(([key, value]) => params.set(key, value));
    const text = params.toString();

    return text ? `?${text}` : '';
}

/** The pager's state from the API's paginator (current_page, last_page, total); missing or broken values are safe. */
export function pagerState(collection) {
    const page = pageNumber(collection?.current_page);
    const last = pageNumber(collection?.last_page);
    const total = Number.isInteger(collection?.total) && collection.total >= 0 ? collection.total : 0;

    return { page, last, total, hasPrevious: page > 1, hasNext: page < last, shown: last > 1 };
}

/**
 * A page past the end (empty, current_page above last_page: after a deletion, or a stale page number) goes back to
 * the last page; null when the page is fine. The page always goes down, so the recovery cannot loop.
 */
export function recoveryPage(collection) {
    const { page, last } = pagerState(collection);
    const empty = !Array.isArray(collection?.data) || collection.data.length === 0;

    return empty && page > last ? last : null;
}

/** The page to reload after the editor's change: a new product is on page 1 (newest first); otherwise the same page. */
export const refreshPage = (reason, current) => (reason === 'created' ? 1 : pageNumber(current));

/** VENDOR-UI-CLEANUP-01B: an accessible name from a ":name" template (plain text; split/join, so "$" in a name stays). */
export const namedLabel = (template, name) => (template ? template.split(':name').join(String(name ?? '').trim() || '—') : null);

const schema = {
    products: { key: 'products', columns: [['name', 'Product'], ['sku', 'SKU'], ['status', 'Status'], ['price', 'Price'], ['stock_quantity', 'Stock']] },
    // ORD-01C: total is the vendor order's own vendor_orders.total (never the parent order's grand total).
    orders: { key: 'orders', columns: [['number', 'Order'], ['status', 'Status'], ['order.customer.name', 'Customer'], ['created_at', 'Date'], ['total', 'Total']] },
    reviews: { key: 'reviews', columns: [['rating', 'Rating'], ['product.name', 'Product'], ['customer.name', 'Customer'], ['status', 'Status'], ['comment', 'Comment']] },
    returns: { key: 'returns', columns: [['id', '#'], ['status', 'Status'], ['customer.name', 'Customer'], ['reason', 'Reason'], ['created_at', 'Date']] },
    disputes: { key: 'disputes', columns: [['id', '#'], ['type', 'Type'], ['status', 'Status'], ['customer.name', 'Customer'], ['created_at', 'Date']] },
    commissions: { key: 'commissions', columns: [['vendor_order.number', 'Order'], ['amount', 'Amount'], ['status', 'Status'], ['created_at', 'Date']] },
};

function valueAt(item, path) {
    return path.split('.').reduce((value, key) => value && value[key], item) ?? '—';
}

function text(value) {
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
}

function init() {
    const definition = schema[root.dataset.resource];
    const loading = root.querySelector('[data-resource-loading]');
    const error = root.querySelector('[data-resource-error]');
    const table = root.querySelector('[data-resource-table]');
    const empty = root.querySelector('[data-resource-empty]');
    const head = root.querySelector('[data-resource-head]');
    const body = root.querySelector('[data-resource-body]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const texts = listTexts(root.dataset.listTexts);
    const badges = moderationTexts(root.dataset.moderationTexts);
    const isOrders = root.dataset.resource === 'orders';
    // ORD-01C: the load error's own text, put back on each load (an order action error replaces it while shown).
    const loadErrorText = error.textContent;
    const locale = document.documentElement.lang || 'en';
    const timeZone = root.dataset.timezone || 'UTC';

    // ORD-01C: an orders cell, as plain text: the number and the total isolated left to right, the status as the
    // dashboard home's badge (the stored value kept on it), the date in the page language, the customer name isolated.
    const orderCell = (path, value) => {
        if (path === 'status') {
            const badge = document.createElement('span');
            badge.className = 'vendor-status-badge';
            badge.dataset.status = String(value);
            badge.textContent = text(statusLabel(value, texts));
            return badge;
        }
        if (path === 'created_at') {
            const time = document.createElement('time');
            const shown = formatOrderDate(value, locale, timeZone);
            if (shown) time.dateTime = value;
            time.textContent = shown || '—';
            return time;
        }
        const isolated = document.createElement('bdi');
        if (path === 'number' || path === 'total') isolated.dir = 'ltr';
        if (path === 'number') isolated.className = 'vendor-order-number';
        isolated.textContent = path === 'total' ? formatAmount(value) ?? '—' : text(value);
        return isolated;
    };

    const render = (items) => {
        head.replaceChildren(); body.replaceChildren();
        const headerRow = document.createElement('tr');
        definition.columns.forEach(([path, label]) => { const th = document.createElement('th'); th.scope = 'col'; th.textContent = columnLabel(path, label, texts); headerRow.append(th); });
        if (['orders', 'products'].includes(root.dataset.resource)) { const th = document.createElement('th'); th.scope = 'col'; th.textContent = texts.actions; headerRow.append(th); }
        head.append(headerRow);
        items.forEach((item) => {
            const row = document.createElement('tr');
            definition.columns.forEach(([path, label]) => {
                const cell = document.createElement('td');
                const value = valueAt(item, path);
                if (isOrders) {
                    // The column header again, shown before the value when the table is cards (a phone).
                    cell.dataset.label = columnLabel(path, label, texts);
                    cell.append(orderCell(path, value));
                } else if (root.dataset.resource === 'products' && path === 'status') {
                    // VENDOR-UI-CLEANUP-01B: the translated label; the stored value stays as it is, on the cell.
                    cell.dataset.status = String(value);
                    cell.textContent = text(statusLabel(value, texts));
                    // VENDOR-PRODUCT-PUBLISH-UI-01E: the administration's state after it, as separate text badges (a word
                    // apart, so it reads as text too); the publication status stays as it is.
                    moderationBadges(item.moderation, badges).forEach(([kind, label]) => {
                        const badge = document.createElement('span');
                        badge.className = `vendor-moderation-badge is-${kind}`;
                        badge.dataset.moderation = kind;
                        badge.textContent = label;
                        cell.append(' ', badge);
                    });
                } else if (root.dataset.resource === 'products' && path === 'sku' && text(value) !== '—') {
                    // An SKU reads left to right in Arabic too; the cell keeps the column's alignment.
                    const sku = document.createElement('bdi');
                    sku.dir = 'ltr';
                    sku.textContent = text(value);
                    cell.append(sku);
                } else {
                    cell.textContent = text(value);
                }
                row.append(cell);
            });
            if (root.dataset.resource === 'products') {
                const actions = document.createElement('td');
                [[texts.edit, 'edit', texts.editNamed], [texts.delete, 'delete', texts.deleteNamed]].forEach(([label, action, named]) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = action === 'delete' ? 'btn btn-sm btn-light-danger me-1' : 'btn btn-sm btn-light-primary me-1';
                    button.textContent = label; button.dataset.productAction = action; button.dataset.productId = item.id;
                    // VENDOR-UI-CLEANUP-01B: the accessible name says which product ("Edit product: Clay jug"); plain text.
                    const name = namedLabel(named, item.name);
                    if (name) button.setAttribute('aria-label', name);
                    actions.append(button);
                });
                row.append(actions);
            }
            if (isOrders) {
                // ORD-01D: the row says which order it is, so focus can return to it after an action.
                row.dataset.orderId = String(item.id);
                const actions = document.createElement('td');
                actions.dataset.label = texts.actions;
                const group = document.createElement('div');
                group.className = 'vendor-order-actions';
                // ORD-02A (step 4): a plain link to the order's details page (it opens in a new tab like any link),
                // carrying the list's filters and page so the details page can lead back to this list.
                const href = detailsUrl(root.dataset.detailsBase, item.id, filters, page, window.location.href);
                if (href) {
                    const link = document.createElement('a');
                    link.className = 'btn btn-sm btn-light-secondary';
                    link.href = href;
                    link.dataset.orderDetailsLink = '';
                    link.textContent = texts.detailsLink.label;
                    const name = numberedLabel(texts.detailsLink.named, item.number);
                    if (name) link.setAttribute('aria-label', name);
                    group.append(link);
                }
                // ORD-01C: the translated label, the order number in the accessible name ("Accept order VO-1"), and
                // the action's style; what each action sends and when it is offered are unchanged.
                const addAction = (action) => {
                    const { label, named } = texts.orderActions[action];
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = `btn btn-sm ${ORDER_ACTION_STYLES[action]}`; button.textContent = label;
                    button.dataset.orderAction = action;
                    const name = numberedLabel(named, item.number);
                    if (name) button.setAttribute('aria-label', name);
                    // ORD-01D: Accept and Reject ask first (vendor-order-actions.js); Preparing and Ready are sent at once.
                    button.addEventListener('click', () => orderActions.request(action, item, button));
                    group.append(button);
                };
                orderActionsFor(item.status).forEach(addAction);
                if (group.childElementCount) actions.append(group); else actions.textContent = '—';
                row.append(actions);
            }
            body.append(row);
        });
    };

    // VENDOR-PRODUCT-LIST-PAGINATION-01B / ORD-01A: the products and orders lists' pager (the page has it for those two
    // only); the other lists load, retry and refresh exactly as before. An order action reloads the page shown.
    const pager = isPaged(root.dataset.resource) ? root.querySelector('[data-resource-pager]') : null;
    const previous = pager?.querySelector('[data-resource-previous]');
    const next = pager?.querySelector('[data-resource-next]');
    const pageStatus = pager?.querySelector('[data-resource-page-status]');
    const retry = root.querySelector('[data-resource-retry]');
    let page = 1, latest = 0, busy = false, state = null;

    // ORD-01B: the orders list's search and filters (the page has them for orders only; filters stays null for the other
    // lists, so their requests are unchanged). They start from the page address and are sent with every page.
    const filterForm = root.dataset.resource === 'orders' ? root.querySelector('[data-order-filters]') : null;
    const searchInput = filterForm?.querySelector('[data-order-search]');
    const statusSelect = filterForm?.querySelector('[data-order-status]');
    const needsResponse = filterForm?.querySelector('[data-order-needs-response]');
    const emptyFiltered = root.querySelector('[data-resource-empty-filtered]');
    const statuses = statusSelect ? [...statusSelect.options].map((option) => option.value).filter(Boolean) : [];
    let filters = filterForm ? filtersFromQuery(window.location.search, statuses) : null;
    if (filterForm) { searchInput.value = filters.search; statusSelect.value = filters.status; needsResponse.checked = filters.needsResponse; }
    // ORD-02A (step 4): the orders list's page is in its address too: read on load (a bad value is page 1; a page past
    // the end steps back as before), written after each page shown (replaceState, no new history entry).
    if (filterForm) page = pageNumber(new URLSearchParams(window.location.search).get('page'));
    const syncAddress = () => {
        if (!filterForm) return;
        try { window.history.replaceState(window.history.state, '', `${window.location.pathname}${listQuery(window.location.search, filters, page)}${window.location.hash}`); } catch { /* the address is a convenience only */ }
    };

    const showPager = () => {
        if (!pager) return;
        pager.hidden = !state?.shown;
        if (state) pageStatus.textContent = (pager.dataset.pageStatus || '').split(':page').join(String(state.page)).split(':last').join(String(state.last));
        previous.disabled = busy || !state?.hasPrevious;
        next.disabled = busy || !state?.hasNext;
    };

    // focus: after Previous / Next only (never after a background refresh), the same button, or the other one at an end.
    const load = async (target = page, { focus = null } = {}) => {
        const mine = ++latest;
        // Only the latest request of a paged list counts (a quick second click, a refresh meanwhile).
        const stale = () => pager !== null && mine !== latest;
        if (pager) { page = pageNumber(target); busy = true; showPager(); }
        loading.hidden = false; error.hidden = true; table.hidden = true; empty.hidden = true;
        error.textContent = loadErrorText;
        if (emptyFiltered) emptyFiltered.hidden = true;
        try {
            const response = await fetch(pager ? pageUrl(root.dataset.endpoint, page, window.location.href, filters) : root.dataset.endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
            if (stale()) return;
            if (!response.ok) throw new Error('request-failed');
            const payload = await response.json();
            if (stale()) return;
            const collection = payload[definition.key];
            if (pager) {
                const back = recoveryPage(collection);
                if (back !== null && back < page) return load(back, { focus });
                state = pagerState(collection);
                page = state.page;
            }
            const items = Array.isArray(collection?.data) ? collection.data : [];
            render(items);
            syncAddress();
            window.dispatchEvent(new CustomEvent('vendor-resource:loaded', { detail: { resource: root.dataset.resource, payload } }));
            table.hidden = items.length === 0;
            // ORD-01B: with a search or filter in use, an empty list says that nothing matches.
            const filtered = emptyFiltered !== null && hasFilters(filters);
            empty.hidden = items.length !== 0 || filtered;
            if (emptyFiltered) emptyFiltered.hidden = items.length !== 0 || !filtered;
        } catch {
            if (stale()) return;
            error.hidden = false;
            state = null;
        } finally {
            if (!stale()) {
                loading.hidden = true;
                if (pager) {
                    busy = false;
                    showPager();
                    if (focus) {
                        const wanted = focus === 'next' ? next : previous;
                        const other = focus === 'next' ? previous : next;
                        (!error.hidden ? retry : !wanted.disabled ? wanted : !other.disabled ? other : null)?.focus();
                    }
                }
                if (focusOrder !== null) {
                    const id = focusOrder;
                    focusOrder = null;
                    focusAfterAction(id);
                }
            }
        }
    };
    retry.addEventListener('click', () => load());

    // ORD-01D: after an order action the list reloads the page shown; focus then goes to the same order's first action,
    // else to the list heading (the order left the page, or has no action left), or to Refresh when the list failed.
    let focusOrder = null;
    const heading = root.querySelector('.vendor-section-title');
    const focusAfterAction = (id) => {
        if (!error.hidden) { retry.focus(); return; }
        const row = [...body.rows].find((candidate) => candidate.dataset.orderId === String(id));
        const action = row?.querySelector('[data-order-action]');
        if (action) { action.focus(); return; }
        heading.tabIndex = -1;
        heading.focus();
    };
    const orderActions = isOrders ? createOrderActions({
        root, endpoint: root.dataset.endpoint, csrfToken, fetchImpl: (...args) => window.fetch(...args),
        reload: ({ orderId }) => { focusOrder = orderId; load(); },
    }) : null;
    // ORD-01B: Apply (or Enter in the search field) and Clear filters start again from page 1 and keep the page address
    // in step (replaceState, no new history entry); the pager and Refresh keep the filters.
    const applyFilters = (next) => {
        filters = next;
        try { window.history.replaceState(window.history.state, '', `${window.location.pathname}${listQuery(window.location.search, filters, 1)}${window.location.hash}`); } catch { /* the address is a convenience only */ }
        load(1);
    };
    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters(orderFilters({ search: searchInput.value, status: statusSelect.value, needsResponse: needsResponse.checked }, statuses));
    });
    filterForm?.querySelector('[data-order-filters-reset]').addEventListener('click', () => {
        searchInput.value = ''; statusSelect.value = ''; needsResponse.checked = false;
        applyFilters(orderFilters({}, statuses));
    });
    previous?.addEventListener('click', () => { if (!busy && state?.hasPrevious) load(page - 1, { focus: 'previous' }); });
    next?.addEventListener('click', () => { if (!busy && state?.hasNext) load(page + 1, { focus: 'next' }); });
    window.addEventListener('vendor-resource:refresh', (event) => {
        if (event.detail?.resource !== root.dataset.resource) return;
        // Products: a new product is on page 1, a saved or deleted one reloads the page shown (a page left empty steps back).
        load(pager ? refreshPage(event.detail?.reason, page) : undefined);
    });
    load();
}

if (root) init();
