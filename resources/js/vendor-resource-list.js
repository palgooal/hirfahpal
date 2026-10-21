const root = document.querySelector('[data-vendor-resource-list]');

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

    const render = (items) => {
        head.replaceChildren(); body.replaceChildren();
        const headerRow = document.createElement('tr');
        definition.columns.forEach(([, label]) => { const th = document.createElement('th'); th.scope = 'col'; th.textContent = label; headerRow.append(th); });
        if (['orders', 'products'].includes(root.dataset.resource)) { const th = document.createElement('th'); th.scope = 'col'; th.textContent = 'Actions'; headerRow.append(th); }
        head.append(headerRow);
        items.forEach((item) => {
            const row = document.createElement('tr');
            definition.columns.forEach(([path]) => { const cell = document.createElement('td'); cell.textContent = text(valueAt(item, path)); row.append(cell); });
            if (root.dataset.resource === 'products') {
                const actions = document.createElement('td');
                [['Edit', 'edit'], ['Delete', 'delete']].forEach(([label, action]) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = action === 'delete' ? 'btn btn-sm btn-light-danger me-1' : 'btn btn-sm btn-light-primary me-1';
                    button.textContent = label; button.dataset.productAction = action; button.dataset.productId = item.id;
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

    const load = async () => {
        loading.hidden = false; error.hidden = true; table.hidden = true; empty.hidden = true;
        try {
            const response = await fetch(root.dataset.endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('request-failed');
            const payload = await response.json();
            const collection = payload[definition.key];
            const items = Array.isArray(collection?.data) ? collection.data : [];
            render(items);
            window.dispatchEvent(new CustomEvent('vendor-resource:loaded', { detail: { resource: root.dataset.resource, payload } }));
            table.hidden = items.length === 0;
            empty.hidden = items.length !== 0;
        } catch {
            error.hidden = false;
        } finally {
            loading.hidden = true;
        }
    };
    root.querySelector('[data-resource-retry]').addEventListener('click', load);
    window.addEventListener('vendor-resource:refresh', (event) => {
        if (event.detail?.resource === root.dataset.resource) load();
    });
    load();
}

if (root) init();
