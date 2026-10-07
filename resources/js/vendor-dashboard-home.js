/*
 * HIRFAH vendor dashboard home (VUI-02B). Loads the existing Overview endpoint
 * (vendor.dashboard.overview, Palgoals backend) and renders ONLY the approved
 * fields. The response is read once into a small view model and then dropped:
 * it is never stored, logged, or copied into the DOM, and everything is
 * rendered with textContent (no HTML from the payload).
 */

const root = document.querySelector('[data-vendor-dashboard-home]');

/** The approved subset of the Overview payload; nothing else is kept. */
function pickApproved(payload) {
    const stats = payload?.stats ?? {};
    const count = (value) => (Number.isInteger(value) && value >= 0 ? value : null);

    return {
        ordersPending: count(stats.orders_pending),
        ordersInProgress: count(stats.orders_in_progress),
        productsActive: count(stats.products_active),
        productsTotal: count(stats.products_total),
        productsLowStock: count(stats.products_low_stock),
        recentOrders: (Array.isArray(payload?.recent_orders) ? payload.recent_orders : []).map((order) => ({
            number: typeof order?.number === 'string' ? order.number : '',
            createdAt: typeof order?.created_at === 'string' ? order.created_at : null,
            status: typeof order?.status === 'string' ? order.status : '',
            itemCount: Array.isArray(order?.items) ? order.items.length : null,
        })),
    };
}

/** Same presentation as the dashboard views (Y-m-d H:i, application timezone), Latin digits. */
function formatDate(value, timeZone) {
    const date = value ? new Date(value) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return '';
    }

    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date).map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}`;
}

function cell(label, child) {
    const td = document.createElement('td');
    td.dataset.label = label;
    td.append(child);

    return td;
}

function initDashboardHome() {
    const i18n = JSON.parse(root.querySelector('[data-home-i18n]').textContent);
    const timeZone = root.dataset.timezone || 'UTC';
    const content = root.querySelector('[data-home-content]');
    const errorPanel = root.querySelector('[data-home-error]');
    const busyRegions = root.querySelectorAll('[data-home-busy]');
    const recentLoading = root.querySelector('[data-recent-loading]');
    const recentEmpty = root.querySelector('[data-recent-empty]');
    const recentTable = root.querySelector('[data-recent-table]');
    const recentBody = root.querySelector('[data-recent-body]');

    const kpiValue = (key) => root.querySelector(`[data-kpi="${key}"]`);
    const totalMeta = root.querySelector('[data-kpi-meta="products_total"]');

    const setBusy = (busy) => busyRegions.forEach((region) => region.setAttribute('aria-busy', String(busy)));

    const showLoading = () => {
        root.dataset.state = 'loading';
        content.hidden = false;
        errorPanel.hidden = true;
        setBusy(true);
    };

    const showError = () => {
        root.dataset.state = 'error';
        content.hidden = true;
        errorPanel.hidden = false;
        setBusy(false);
    };

    const setValue = (element, value) => {
        element.classList.remove('vendor-skeleton', 'vendor-skeleton-value', 'vendor-skeleton-meta');
        element.replaceChildren(document.createTextNode(String(value)));
    };

    const render = (data) => {
        setValue(kpiValue('orders_pending'), data.ordersPending);
        setValue(kpiValue('orders_in_progress'), data.ordersInProgress);
        setValue(kpiValue('products_active'), data.productsActive);
        setValue(kpiValue('products_low_stock'), data.productsLowStock);

        totalMeta.replaceChildren(document.createTextNode(
            data.productsTotal === 0 ? i18n.noProducts : i18n.ofTotal.replace(':total', String(data.productsTotal)),
        ));

        const rows = data.recentOrders.map((order) => {
            const tr = document.createElement('tr');

            // The order number is an identifier: isolate it so its own direction is kept in RTL.
            const number = document.createElement('bdi');
            number.className = 'vendor-order-number';
            number.textContent = order.number;

            const date = document.createElement('bdi');
            date.textContent = formatDate(order.createdAt, timeZone);

            const status = document.createElement('span');
            status.className = 'vendor-status-badge';
            status.textContent = i18n.statuses[order.status] ?? order.status;

            const items = document.createElement('bdi');
            items.textContent = order.itemCount === null ? '' : String(order.itemCount);

            tr.append(
                cell(i18n.columns.number, number),
                cell(i18n.columns.date, date),
                cell(i18n.columns.status, status),
                cell(i18n.columns.items, items),
            );

            return tr;
        });

        recentBody.replaceChildren(...rows);
        recentLoading.hidden = true;
        recentEmpty.hidden = rows.length > 0;
        recentTable.hidden = rows.length === 0;

        root.dataset.state = 'ready';
        setBusy(false);
    };

    const load = async () => {
        showLoading();

        try {
            const response = await fetch(root.dataset.overviewUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error('overview-unavailable');
            }

            const data = pickApproved(await response.json());
            const counts = [data.ordersPending, data.ordersInProgress, data.productsActive, data.productsTotal, data.productsLowStock];

            if (counts.some((value) => value === null)) {
                throw new Error('overview-incomplete');
            }

            render(data);
        } catch {
            // Ordinary load failure: neutral message only, no details, no redirect.
            showError();
        }
    };

    root.querySelector('[data-home-retry]').addEventListener('click', load);

    load();
}

if (root) {
    initDashboardHome();
}
