/*
 * HIRFAH vendor media page (ADMIN-MEDIA-ORGANIZATION-01 P4), loaded by
 * resources/views/dashboard/vendor-media/index.blade.php. A read-only view of the vendor libraries: every vendor is
 * a virtual folder (P3 dashboard.vendor-media.folders), a folder lists that vendor's images
 * (dashboard.vendor-media.media). Nothing here uploads, edits, deletes or selects; the server checks media.view and
 * vendors.view on every request.
 *
 * Every text comes from the page config (t(), keys dashboard.Media_*); there is no hard-coded wording. Server data
 * only ever goes through textContent and attributes, never HTML, and a server message is never shown as is. The open
 * folder is the ?vendor=ID query (History API), so refresh, Back and Forward keep it. Each list drops the answer of a
 * request it has moved past (new search, other vendor), so a slow answer never shows in the wrong view.
 */

import { format, formatBytes, formatDate, request } from './admin-media/shared.js';

export { format };

export const VENDOR_PARAM = 'vendor';

/** A vendor id as the route accepts it (dashboard.vendor-media.media). */
const VENDOR_ID = /^[1-9][0-9]{0,18}$/;

const SEARCH_DELAY = 400;

const textOf = (value) => (typeof value === 'string' ? value.trim() : '');

// ---------------------------------------------------------------------------------------------
// URLs and the ?vendor= state
// ---------------------------------------------------------------------------------------------

/**
 * The folder a page URL points at: {id} for a valid ?vendor=, {invalid: true} for any other value.
 *
 * @returns {{id: string|null, invalid: boolean}}
 */
export function vendorFromHref(href) {
    let value = null;

    try {
        value = new URL(href).searchParams.get(VENDOR_PARAM);
    } catch {
        return { id: null, invalid: false };
    }

    if (value === null) {
        return { id: null, invalid: false };
    }

    return VENDOR_ID.test(value) ? { id: value, invalid: false } : { id: null, invalid: true };
}

/** The page URL with the folder set (or removed for null); other query parameters are kept. */
export function hrefForVendor(href, vendorId) {
    const url = new URL(href);

    if (vendorId === null || vendorId === undefined) {
        url.searchParams.delete(VENDOR_PARAM);
    } else {
        url.searchParams.set(VENDOR_PARAM, String(vendorId));
    }

    url.hash = '';

    return url.toString();
}

export function foldersUrl(base, { page = 1, perPage, search = '', status = '', hasMedia = false } = {}) {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });

    if (search) {
        params.set('search', search);
    }

    if (status) {
        params.set('status', status);
    }

    if (hasMedia) {
        params.set('has_media', '1');
    }

    return `${base}?${params}`;
}

/** One folder's images; null for an id the route would refuse (nothing is requested then). */
export function mediaUrl(template, vendorId, { page = 1, perPage, search = '' } = {}) {
    if (!VENDOR_ID.test(String(vendorId))) {
        return null;
    }

    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });

    if (search) {
        params.set('search', search);
    }

    return `${template.replace('__ID__', encodeURIComponent(String(vendorId)))}?${params}`;
}

/** An image address the page may show or link to: http(s) only, else ''. */
export function safeUrl(value, base) {
    if (typeof value !== 'string' || value.trim() === '') {
        return '';
    }

    try {
        const url = new URL(value, base);

        return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
    } catch {
        return '';
    }
}

// ---------------------------------------------------------------------------------------------
// Server answers
// ---------------------------------------------------------------------------------------------

/**
 * A P3 list answer. Errors are named, never carried as the server's own message.
 *
 * @returns {{ok: true, data: object[], page: number, lastPage: number, total: number|null, vendor: object|null}|{ok: false, error: string}}
 */
export function interpretList({ status, body }) {
    if (status === 200 && body && Array.isArray(body.data)) {
        const page = Number.isInteger(body.current_page) && body.current_page > 0 ? body.current_page : 1;
        const lastPage = Number.isInteger(body.last_page) && body.last_page >= page ? body.last_page : page;
        const total = Number.isInteger(body.total) && body.total >= 0 ? body.total : null;
        const vendor = body.vendor && typeof body.vendor === 'object' ? body.vendor : null;

        return { ok: true, data: body.data.filter((item) => item && typeof item === 'object'), page, lastPage, total, vendor };
    }

    const error = status === 0 ? 'network'
        : status === 401 || status === 419 ? 'session'
            : status === 403 ? 'forbidden'
                : status === 404 ? 'notFound'
                    : status === 422 ? 'invalid'
                        : 'failed';

    return { ok: false, error };
}

/** The translated text of an error; fallback is the list's own "could not be loaded". */
export function errorText(error, messages, fallback) {
    const texts = {
        network: messages.network,
        session: messages.sessionExpired,
        forbidden: messages.forbidden,
        notFound: messages.notFound,
        invalid: messages.invalidRequest,
    };

    return texts[error] || fallback;
}

/** Only a lost connection or a server failure is worth trying again as is. */
export const canRetry = (error) => error === 'network' || error === 'failed';

// ---------------------------------------------------------------------------------------------
// What the page shows
// ---------------------------------------------------------------------------------------------

/** A status badge: the translated label and its tone class; unknown values stay readable. */
export function statusBadge(group, value, statuses) {
    const entry = statuses?.[group]?.[value];

    if (entry) {
        return { text: entry.label, tone: entry.tone };
    }

    if (group === 'approval' && (value === null || value === undefined || value === '')) {
        return { text: statuses?.noProfile ?? '—', tone: 'is-muted' };
    }

    return { text: textOf(String(value ?? '')) || '—', tone: 'is-muted' };
}

/** A folder card: the store name (else the vendor's name), the vendor's name when it differs, badges, count. */
export function folderView(folder, statuses) {
    const name = textOf(folder.vendor_name);
    const label = textOf(folder.label) || name || `#${folder.vendor_id}`;

    return {
        id: String(folder.vendor_id),
        label,
        secondary: name !== '' && name !== label ? name : '',
        account: statusBadge('account', folder.status, statuses),
        approval: statusBadge('approval', folder.approval_status, statuses),
        count: Number.isInteger(folder.media_count) && folder.media_count >= 0 ? folder.media_count : 0,
    };
}

export const imageName = (item) => textOf(item.file_original_name) || `#${item.id}`;

/**
 * The read-only details of one image, P3 fields only. Empty values read "Not set".
 *
 * @returns {Array<{key: string, label: string, value: string, ltr?: boolean}>}
 */
export function detailFacts(item, { locale, messages }) {
    const text = (value) => textOf(value) || messages.notSet;
    const size = Number.isFinite(Number(item.size)) && item.size !== null && item.size !== '' ? formatBytes(item.size, locale) : messages.notSet;
    const dimensions = Number.isInteger(item.width) && Number.isInteger(item.height) && item.width > 0 && item.height > 0
        ? `${item.width} × ${item.height}`
        : messages.notSet;
    const date = item.created_at ? formatDate(item.created_at, locale) : '—';

    return [
        { key: 'name', label: messages.fieldOriginalName, value: text(item.file_original_name) },
        { key: 'alt', label: messages.fieldAlt, value: text(item.alt) },
        { key: 'title', label: messages.fieldTitle, value: text(item.title) },
        { key: 'type', label: messages.fieldType, value: text(item.mime_type), ltr: true },
        { key: 'size', label: messages.fieldSize, value: size },
        { key: 'dimensions', label: messages.fieldDimensions, value: dimensions, ltr: true },
        { key: 'date', label: messages.fieldUploadedAt, value: date === '—' ? messages.notSet : date },
    ];
}

// ---------------------------------------------------------------------------------------------
// Paged lists with "Load more"
// ---------------------------------------------------------------------------------------------

/**
 * One paged list. reset(query) starts again from page 1 and drops any answer still on its way (its generation is
 * over, and the request is aborted when AbortController exists); more() asks for the next page once, never while a
 * request runs or after the last page; retry() repeats the page that failed. Items already shown are kept on a
 * failure and never repeated (keyed by id). onChange receives {type: 'reset'|'loading'|'loaded'|'error', ...}.
 */
export function createListLoader({ fetchList, onChange = () => {}, keyOf = (item) => String(item.id) }) {
    const state = { query: null, items: [], keys: new Set(), page: 0, lastPage: 1, total: null, vendor: null, loading: false, error: null, generation: 0 };
    let controller = null;

    const run = async (page) => {
        const generation = state.generation;
        controller = typeof AbortController === 'function' ? new AbortController() : null;
        state.loading = true;
        state.error = null;
        onChange({ type: 'loading', page });

        const response = await fetchList(state.query, page, controller?.signal);

        if (generation !== state.generation) {
            return false;
        }

        controller = null;
        state.loading = false;
        const outcome = interpretList(response ?? { status: 0, body: null });

        if (!outcome.ok) {
            state.error = outcome.error;
            onChange({ type: 'error', error: outcome.error, page });

            return false;
        }

        state.page = outcome.page;
        state.lastPage = outcome.lastPage;
        state.total = outcome.total;
        state.vendor = outcome.vendor;

        const added = outcome.data.filter((item) => {
            const key = keyOf(item);

            if (state.keys.has(key)) {
                return false;
            }

            state.keys.add(key);
            state.items.push(item);

            return true;
        });

        onChange({ type: 'loaded', added, page: outcome.page });

        return true;
    };

    const stop = () => {
        state.generation += 1;
        controller?.abort();
        controller = null;
        state.loading = false;
    };

    return {
        state,
        get hasMore() {
            return state.page > 0 && state.page < state.lastPage && state.error === null;
        },
        reset(query) {
            stop();
            Object.assign(state, { query, items: [], keys: new Set(), page: 0, lastPage: 1, total: null, vendor: null, error: null });
            onChange({ type: 'reset' });

            return run(1);
        },
        more() {
            if (state.loading || state.query === null || state.page === 0 || state.page >= state.lastPage) {
                return Promise.resolve(false);
            }

            return run(state.page + 1);
        },
        retry() {
            if (state.loading || state.query === null || state.error === null) {
                return Promise.resolve(false);
            }

            return run(state.page + 1);
        },
        stop,
    };
}

// ---------------------------------------------------------------------------------------------
// History: the open folder is ?vendor=ID
// ---------------------------------------------------------------------------------------------

/**
 * open(id) pushes ?vendor=ID; toList() goes back when the folder was opened from the list (so Back and the
 * page's own button agree), else pushes the list URL; clearInvalid() replaces a bad ?vendor= with the list.
 * onRoute({id, invalid}, how) runs after each change, including Back and Forward (popstate).
 */
export function createRouter(win, onRoute) {
    const current = () => vendorFromHref(win.location.href);

    win.addEventListener('popstate', () => onRoute(current(), 'pop'));

    return {
        current,
        open(vendorId) {
            win.history.pushState({ vendorMedia: 'folder', fromList: true }, '', hrefForVendor(win.location.href, vendorId));
            onRoute(current(), 'push');
        },
        toList() {
            if (win.history.state?.fromList) {
                win.history.back();

                return;
            }

            win.history.pushState({ vendorMedia: 'list' }, '', hrefForVendor(win.location.href, null));
            onRoute(current(), 'push');
        },
        clearInvalid() {
            win.history.replaceState({ vendorMedia: 'list' }, '', hrefForVendor(win.location.href, null));
        },
    };
}

// ---------------------------------------------------------------------------------------------
// Rendering (DOM APIs only; server strings go through textContent)
// ---------------------------------------------------------------------------------------------

const element = (doc, tag, className, text) => {
    const node = doc.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};

const icon = (doc, name) => {
    const node = element(doc, 'i', `ph-duotone ${name}`);
    node.setAttribute('aria-hidden', 'true');

    return node;
};

/** A badge with its meaning for screen readers ("Account status: Active"). */
export function renderBadge(doc, badge, caption) {
    const node = element(doc, 'span', `admin-vendor-media-badge ${badge.tone}`);
    node.append(element(doc, 'span', 'visually-hidden', `${caption}: `), badge.text);

    return node;
}

/** A folder card: a real link to ?vendor=ID, so it also opens in a new tab. */
export function renderFolderCard(doc, view, { href, messages, onOpen }) {
    const card = element(doc, 'li', 'admin-vendor-media-folder-item');
    card.dataset.vendorId = view.id;

    const link = element(doc, 'a', 'admin-vendor-media-folder');
    link.href = href;
    link.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        onOpen(view.id);
    });

    const body = element(doc, 'span', 'admin-vendor-media-folder-body');
    body.append(element(doc, 'bdi', 'admin-vendor-media-folder-name', view.label));

    if (view.secondary) {
        body.append(element(doc, 'bdi', 'admin-vendor-media-folder-vendor', view.secondary));
    }

    const badges = element(doc, 'span', 'admin-vendor-media-badges');
    badges.append(renderBadge(doc, view.account, messages.accountStatus), renderBadge(doc, view.approval, messages.approvalStatus));
    body.append(badges, element(doc, 'span', 'admin-vendor-media-folder-count', format(messages.count, { count: view.count })));

    link.append(icon(doc, 'ph-folder-simple'), body);
    card.append(link);

    return card;
}

/** An image card: opens the read-only details; no selection, no actions. */
export function renderImageCard(doc, item, { messages, locale, base, onOpen }) {
    const name = imageName(item);
    const card = element(doc, 'li', 'admin-media-card admin-vendor-media-card');
    card.dataset.id = String(item.id);

    const thumb = element(doc, 'div', 'admin-media-thumb');
    const open = element(doc, 'button', 'admin-media-open');
    open.type = 'button';
    open.setAttribute('aria-label', format(messages.open, { name }));
    const image = element(doc, 'img');
    image.src = safeUrl(item.url, base);
    image.alt = '';
    image.loading = 'lazy';
    open.append(image);
    open.addEventListener('click', () => onOpen(item, open));
    thumb.append(open);

    const meta = element(doc, 'div', 'admin-media-meta');
    const title = element(doc, 'bdi', 'admin-media-name', name);
    title.title = name;
    meta.append(title, element(doc, 'span', 'admin-media-facts-line', formatBytes(item.size, locale)));

    card.append(thumb, meta);

    return card;
}

/** The details list: <div><dt>label</dt><dd>value</dd></div> per fact. */
export function renderFacts(doc, list, facts) {
    list.replaceChildren(...facts.map((fact) => {
        const row = element(doc, 'div');
        row.dataset.fact = fact.key;
        const value = element(doc, 'dd');
        const content = element(doc, 'bdi', '', fact.value);

        if (fact.ltr) {
            value.setAttribute('dir', 'ltr');
        }

        value.append(content);
        row.append(element(doc, 'dt', '', fact.label), value);

        return row;
    }));
}

// ---------------------------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------------------------

export function initVendorMedia(root, config, win = window) {
    const doc = root.ownerDocument;
    const { urls, locale, perPage, statuses, messages } = config;
    const $ = (id) => doc.getElementById(id);
    const base = win.location.href;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const folderViews = new Map();
    const notice = $('vendor-media-notice');

    // ---- One list's elements and states ----
    const listUi = (prefix) => ({
        grid: $(`vendor-media-${prefix}-grid`),
        loading: $(`vendor-media-${prefix}-loading`),
        empty: $(`vendor-media-${prefix}-empty`),
        emptyText: $(`vendor-media-${prefix}-empty-text`),
        error: $(`vendor-media-${prefix}-error`),
        errorText: $(`vendor-media-${prefix}-error-text`),
        retry: $(`vendor-media-${prefix}-retry`),
        more: $(`vendor-media-${prefix}-more`),
        count: $(`vendor-media-${prefix}-count`),
    });

    const showState = (ui, which) => {
        ui.loading.hidden = which !== 'loading';
        ui.empty.hidden = which !== 'empty';
        ui.error.hidden = which !== 'error';
        ui.grid.setAttribute('aria-busy', which === 'loading' ? 'true' : 'false');
    };

    const folderUi = listUi('folder');
    const imageUi = listUi('image');

    const showNotice = (text) => {
        notice.textContent = text;
        notice.hidden = text === '';
    };

    // ---- Folders ----
    const folderSearch = $('vendor-media-folder-search');
    const statusFilter = $('vendor-media-status');
    const hasMediaFilter = $('vendor-media-has-media');
    let lastOpened = null;

    const folderQuery = () => ({ search: folderSearch.value.trim(), status: statusFilter.value, hasMedia: hasMediaFilter.checked });

    const folders = createListLoader({
        keyOf: (folder) => String(folder.vendor_id),
        fetchList: (query, page, signal) => request(foldersUrl(urls.folders, { page, perPage: perPage.folders, ...query }), { headers, signal }),
        onChange: (event) => {
            if (event.type === 'reset') {
                folderUi.grid.replaceChildren();
                folderUi.count.textContent = '';
            }

            if (event.type === 'loading') {
                folderUi.more.hidden = true;
                showState(folderUi, 'loading');
            }

            if (event.type === 'loaded') {
                event.added.forEach((folder) => {
                    const view = folderView(folder, statuses);
                    folderViews.set(view.id, view);
                    folderUi.grid.append(renderFolderCard(doc, view, { href: hrefForVendor(win.location.href, view.id), messages, onOpen: (id) => router.open(id) }));
                });

                const { query, items, total } = folders.state;
                folderUi.count.textContent = format(messages.vendorsCount, { count: total ?? items.length });
                folderUi.emptyText.textContent = query.search || query.status || query.hasMedia ? messages.vendorsEmptySearch : messages.vendorsEmpty;
                showState(folderUi, items.length === 0 ? 'empty' : 'none');
                folderUi.more.hidden = !folders.hasMore;
            }

            if (event.type === 'error') {
                folderUi.errorText.textContent = errorText(event.error, messages, messages.vendorsLoadError);
                folderUi.retry.hidden = !canRetry(event.error);
                showState(folderUi, 'error');
                folderUi.more.hidden = true;
            }
        },
    });

    // ---- One vendor's images ----
    const imageSearch = $('vendor-media-image-search');
    const vendorName = $('vendor-media-vendor-name');
    const vendorSecondary = $('vendor-media-vendor-secondary');
    const vendorBadges = $('vendor-media-vendor-badges');
    const vendorTitle = $('vendor-media-vendor-title');
    const imageItems = new Map();

    const showVendor = (view) => {
        vendorName.textContent = view.label;
        vendorSecondary.textContent = view.secondary;
        vendorSecondary.hidden = view.secondary === '';
        vendorBadges.replaceChildren(renderBadge(doc, view.account, messages.accountStatus), renderBadge(doc, view.approval, messages.approvalStatus));
        imageUi.grid.setAttribute('aria-label', format(messages.imagesOf, { name: view.label }));
    };

    const images = createListLoader({
        fetchList: (query, page, signal) => {
            const url = mediaUrl(urls.media, query.vendorId, { page, perPage: perPage.media, search: query.search });

            return url ? request(url, { headers, signal }) : Promise.resolve({ status: 404, body: null });
        },
        onChange: (event) => {
            if (event.type === 'reset') {
                imageItems.clear();
                imageUi.grid.replaceChildren();
                imageUi.count.textContent = '';
            }

            if (event.type === 'loading') {
                imageUi.more.hidden = true;
                showState(imageUi, 'loading');
            }

            if (event.type === 'loaded') {
                const { query, items, total, vendor } = images.state;

                if (vendor) {
                    const known = folderViews.get(String(vendor.vendor_id));
                    showVendor(folderView({ vendor_name: known?.secondary || '', media_count: known?.count, ...vendor }, statuses));
                }

                event.added.forEach((item) => {
                    imageItems.set(String(item.id), item);
                    imageUi.grid.append(renderImageCard(doc, item, { messages, locale, base, onOpen: openDetails }));
                });

                imageUi.count.textContent = format(messages.count, { count: total ?? items.length });
                imageUi.emptyText.textContent = query.search ? messages.imagesEmptySearch : messages.folderEmpty;
                showState(imageUi, items.length === 0 ? 'empty' : 'none');
                imageUi.more.hidden = !images.hasMore;
            }

            if (event.type === 'error') {
                imageUi.errorText.textContent = errorText(event.error, messages, messages.imagesLoadError);
                imageUi.retry.hidden = !canRetry(event.error);
                showState(imageUi, 'error');
                imageUi.more.hidden = true;
            }
        },
    });

    // ---- Views ----
    const foldersSection = $('vendor-media-folders');
    const imagesSection = $('vendor-media-images');

    const showList = (how) => {
        images.stop();
        closeDetails();
        imagesSection.hidden = true;
        foldersSection.hidden = false;

        // The list keeps its search, filters and loaded pages; it is loaded the first time it is shown.
        if (folders.state.query === null) {
            folders.reset(folderQuery());
        }

        if (how !== 'start') {
            const card = lastOpened ? folderUi.grid.querySelector(`[data-vendor-id="${lastOpened}"] a`) : null;
            (card ?? folderSearch).focus();
        }
    };

    const showFolder = (vendorId, how) => {
        showNotice('');
        foldersSection.hidden = true;
        imagesSection.hidden = false;
        lastOpened = vendorId;

        // Back / Forward to the folder already loaded shows it as it was; anything else loads it again.
        const sameFolder = images.state.query?.vendorId === vendorId && images.state.error === null && images.state.page > 0;

        if (sameFolder) {
            showState(imageUi, images.state.items.length === 0 ? 'empty' : 'none');
            imageUi.more.hidden = !images.hasMore;
        } else {
            imageSearch.value = '';
            const known = folderViews.get(vendorId);

            if (known) {
                showVendor(known);
            } else {
                // Unknown until the server answers: no guessed badges.
                vendorName.textContent = `#${vendorId}`;
                vendorSecondary.hidden = true;
                vendorBadges.replaceChildren();
            }

            images.reset({ vendorId, search: '' });
        }

        if (how !== 'start') {
            vendorTitle.focus();
        }
    };

    const route = ({ id, invalid }, how) => {
        if (invalid) {
            router.clearInvalid();
            showList(how);
            showNotice(messages.notFound);

            return;
        }

        id ? showFolder(id, how) : showList(how);
    };

    const router = createRouter(win, route);

    // ---- Details (read-only) ----
    const dialog = $('vendor-media-details');
    const preview = $('vendor-media-details-image');
    const facts = $('vendor-media-details-facts');
    const original = $('vendor-media-open-original');
    let opener = null;

    function openDetails(item, from) {
        opener = from;
        const url = safeUrl(item.url, base);
        preview.src = url;
        preview.alt = textOf(item.alt);
        original.href = url || '#';
        original.hidden = url === '';
        renderFacts(doc, facts, detailFacts(item, { locale, messages }));
        dialog.showModal();
    }

    function closeDetails() {
        if (dialog.open) {
            dialog.close();
        }
    }

    dialog.addEventListener('close', () => {
        opener?.isConnected && opener.focus();
        opener = null;
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDetails();
        }
    });
    $('vendor-media-details-close').addEventListener('click', closeDetails);
    $('vendor-media-details-done').addEventListener('click', closeDetails);

    // ---- Search, filters, paging, retry ----
    const debounced = (callback) => {
        let timer = null;

        return () => {
            clearTimeout(timer);
            timer = setTimeout(callback, SEARCH_DELAY);
        };
    };

    $('vendor-media-folder-filters').addEventListener('submit', (event) => event.preventDefault());
    folderSearch.addEventListener('input', debounced(() => folders.reset(folderQuery())));
    statusFilter.addEventListener('change', () => folders.reset(folderQuery()));
    hasMediaFilter.addEventListener('change', () => folders.reset(folderQuery()));
    $('vendor-media-folder-clear').addEventListener('click', () => {
        folderSearch.value = '';
        folders.reset(folderQuery());
        folderSearch.focus();
    });
    folderUi.more.addEventListener('click', () => folders.more());
    folderUi.retry.addEventListener('click', () => folders.retry());

    $('vendor-media-image-filters').addEventListener('submit', (event) => event.preventDefault());
    const searchImages = () => images.state.query && images.reset({ vendorId: images.state.query.vendorId, search: imageSearch.value.trim() });
    imageSearch.addEventListener('input', debounced(searchImages));
    $('vendor-media-image-clear').addEventListener('click', () => {
        imageSearch.value = '';
        searchImages();
        imageSearch.focus();
    });
    imageUi.more.addEventListener('click', () => images.more());
    imageUi.retry.addEventListener('click', () => images.retry());
    $('vendor-media-back').addEventListener('click', () => router.toList());

    route(router.current(), 'start');

    return { folders, images, router };
}

function boot() {
    const root = document.getElementById('admin-vendor-media');
    const config = document.getElementById('vendor-media-config');

    if (root && config) {
        initVendorMedia(root, JSON.parse(config.textContent));
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
