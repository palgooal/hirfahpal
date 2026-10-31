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
    };
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

/** VENDOR-PRODUCT-LIST-PAGINATION-01B: a page number: a whole number of at least 1, anything else is page 1. */
export const pageNumber = (value) => {
    const number = typeof value === 'string' && value.trim() !== '' ? Number(value) : value;

    return Number.isInteger(number) && number >= 1 ? number : 1;
};

/** The list endpoint for one page: the endpoint's own query parameters are kept and only page is set. */
export function pageUrl(endpoint, page, base) {
    const url = new URL(endpoint, base);
    url.searchParams.set('page', String(pageNumber(page)));

    return url.toString();
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
    orders: { key: 'orders', columns: [['number', 'Order'], ['status', 'Status'], ['order.customer.name', 'Customer'], ['created_at', 'Date']] },
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

    const render = (items) => {
        head.replaceChildren(); body.replaceChildren();
        const headerRow = document.createElement('tr');
        definition.columns.forEach(([path, label]) => { const th = document.createElement('th'); th.scope = 'col'; th.textContent = columnLabel(path, label, texts); headerRow.append(th); });
        if (['orders', 'products'].includes(root.dataset.resource)) { const th = document.createElement('th'); th.scope = 'col'; th.textContent = texts.actions; headerRow.append(th); }
        head.append(headerRow);
        items.forEach((item) => {
            const row = document.createElement('tr');
            definition.columns.forEach(([path]) => {
                const cell = document.createElement('td');
                const value = valueAt(item, path);
                if (root.dataset.resource === 'products' && path === 'status') {
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
            if (root.dataset.resource === 'orders') {
                const actions = document.createElement('td');
                const addAction = (label, action, reasonRequired = false) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'btn btn-sm btn-light-primary me-1'; button.textContent = label;
                    button.addEventListener('click', async () => {
                        let body = null;
                        if (reasonRequired) { const reason = window.prompt('Reason for rejecting this order:'); if (!reason) return; body = JSON.stringify({ rejection_reason: reason }); }
                        button.disabled = true;
                        try {
                            const response = await fetch(`${root.dataset.endpoint}/${item.id}/${action}`, {
                                method: 'PATCH', credentials: 'same-origin', body,
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, ...(body ? { 'Content-Type': 'application/json' } : {}) },
                            });
                            if (!response.ok) throw new Error('action-failed');
                            load();
                        } catch { button.disabled = false; error.hidden = false; error.textContent = 'The order could not be updated. Please try again.'; }
                    });
                    actions.append(button);
                };
                if (item.status === 'pending') { addAction('Accept', 'accept'); addAction('Reject', 'reject', true); }
                if (['accepted', 'preparing'].includes(item.status)) addAction('Preparing', 'preparing');
                if (['accepted', 'preparing', 'ready_for_delivery'].includes(item.status)) addAction('Ready', 'ready');
                if (!actions.childElementCount) actions.textContent = '—';
                row.append(actions);
            }
            body.append(row);
        });
    };

    // VENDOR-PRODUCT-LIST-PAGINATION-01B: the products list's pager (the page has it for products only); the other lists
    // load, retry and refresh exactly as before.
    const pager = root.dataset.resource === 'products' ? root.querySelector('[data-resource-pager]') : null;
    const previous = pager?.querySelector('[data-resource-previous]');
    const next = pager?.querySelector('[data-resource-next]');
    const pageStatus = pager?.querySelector('[data-resource-page-status]');
    const retry = root.querySelector('[data-resource-retry]');
    let page = 1, latest = 0, busy = false, state = null;

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
        // Only the latest request of the products list counts (a quick second click, a refresh meanwhile).
        const stale = () => pager !== null && mine !== latest;
        if (pager) { page = pageNumber(target); busy = true; showPager(); }
        loading.hidden = false; error.hidden = true; table.hidden = true; empty.hidden = true;
        try {
            const response = await fetch(pager ? pageUrl(root.dataset.endpoint, page, window.location.href) : root.dataset.endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
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
            window.dispatchEvent(new CustomEvent('vendor-resource:loaded', { detail: { resource: root.dataset.resource, payload } }));
            table.hidden = items.length === 0;
            empty.hidden = items.length !== 0;
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
            }
        }
    };
    retry.addEventListener('click', () => load());
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
