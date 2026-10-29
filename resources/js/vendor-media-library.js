/*
 * HIRFAH vendor Media Library page (VENDOR-MEDIA-04B), loaded by resources/views/vendor-dashboard/media.blade.php.
 * A read-only gallery of the vendor's own images. It reads vendor.dashboard.products.media.library one page at a
 * time (page, per_page=24, search), never the whole library; the server keeps the listing to the vendor's own
 * folder. Nothing here uploads, edits or deletes.
 *
 * Every text comes from the page's i18n JSON; there is no hard-coded wording. Server data only goes through
 * textContent and attributes, never HTML; an image address must be http(s), and a server message is never shown as
 * is. An answer the page has moved past (a newer search or page) is dropped, so a slow answer never shows in the
 * wrong view.
 */

import { format, request } from './admin-media/shared.js';

export { format };

/** VENDOR-MEDIA-04B: the page size; the listing accepts up to 50. */
export const PER_PAGE = 24;

/** The listing refuses a longer search (ListMediaLibraryRequest::MAX_SEARCH). */
export const SEARCH_MAX = 100;

const SEARCH_DELAY = 400;

const textOf = (value) => (typeof value === 'string' ? value.trim() : '');

/** A search as the listing accepts it: trimmed, at most SEARCH_MAX characters (code points, as mb_strlen counts). */
export const cleanSearch = (value) => Array.from(textOf(value)).slice(0, SEARCH_MAX).join('').trim();

// ---------------------------------------------------------------------------------------------
// Requests and answers
// ---------------------------------------------------------------------------------------------

/** One page of the listing; page and per_page are always sent, so the whole library is never asked for. */
export function listUrl(base, { page = 1, search = '' } = {}) {
    const params = new URLSearchParams({ page: String(Number.isInteger(page) && page > 0 ? page : 1), per_page: String(PER_PAGE) });
    const term = cleanSearch(search);

    if (term !== '') {
        params.set('search', term);
    }

    return `${base}${base.includes('?') ? '&' : '?'}${params}`;
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

const positive = (value) => Number.isInteger(value) && value > 0;

/**
 * A paged listing answer ({images, pagination}). Errors are named, never carried as the server's own message; a
 * 200 without the paged shape is a failure too.
 *
 * @returns {{ok: true, images: object[], page: number, lastPage: number, total: number}|{ok: false, error: string}}
 */
export function interpretPage({ status, body } = {}) {
    const pagination = body?.pagination;

    if (status === 200 && Array.isArray(body?.images) && pagination && typeof pagination === 'object') {
        const page = positive(pagination.current_page) ? pagination.current_page : 1;
        const lastPage = positive(pagination.last_page) ? pagination.last_page : 1;
        const total = Number.isInteger(pagination.total) && pagination.total >= 0 ? pagination.total : 0;

        return { ok: true, images: body.images.filter((item) => item && typeof item === 'object'), page, lastPage, total };
    }

    const error = status === 0 ? 'network'
        : status === 401 || status === 419 ? 'session'
            : status === 403 ? 'forbidden'
                : status === 422 ? 'invalid'
                    : 'failed';

    return { ok: false, error };
}

/** The translated text of an error. */
export function errorText(error, messages) {
    const texts = { network: messages.network, session: messages.session, forbidden: messages.forbidden, invalid: messages.invalid };

    return texts[error] || messages.loadError;
}

/** Only a lost connection or a server failure is worth trying again as is. */
export const canRetry = (error) => error === 'network' || error === 'failed';

/** What a card shows: the alt text (the server falls back to the original file name), else "Untitled image". */
export function imageView(item, { messages, base }) {
    return {
        id: String(item.id ?? ''),
        label: textOf(item.alt_text) || messages.untitled,
        url: safeUrl(item.url, base),
    };
}

// ---------------------------------------------------------------------------------------------
// The paged gallery
// ---------------------------------------------------------------------------------------------

/**
 * load({page, search}) asks for one page and drops any answer still on its way (its generation is over, and the
 * request is aborted when AbortController exists). A page past the end (the library shrank meanwhile) shows the
 * last page instead. retry() repeats the last request. onChange receives {type: 'loading'|'loaded'|'error'}.
 */
export function createGallery({ fetchPage, onChange = () => {} }) {
    const state = { requested: null, search: '', page: 0, lastPage: 1, total: 0, images: [], loading: false, error: null, generation: 0 };
    let controller = null;

    const load = async ({ page = 1, search = state.search } = {}) => {
        state.generation += 1;
        const generation = state.generation;
        controller?.abort();
        controller = typeof AbortController === 'function' ? new AbortController() : null;
        state.requested = { page, search: cleanSearch(search) };
        state.loading = true;
        state.error = null;
        onChange({ type: 'loading' });

        const response = await fetchPage(state.requested, controller?.signal);

        if (generation !== state.generation) {
            return false;
        }

        controller = null;
        state.loading = false;
        const outcome = interpretPage(response ?? { status: 0, body: null });

        if (!outcome.ok) {
            state.error = outcome.error;
            onChange({ type: 'error', error: outcome.error });

            return false;
        }

        if (outcome.images.length === 0 && outcome.total > 0 && page > outcome.lastPage) {
            return load({ page: outcome.lastPage, search: state.requested.search });
        }

        Object.assign(state, { search: state.requested.search, page: outcome.page, lastPage: outcome.lastPage, total: outcome.total, images: outcome.images });
        onChange({ type: 'loaded' });

        return true;
    };

    return {
        state,
        load,
        retry: () => (state.requested && !state.loading ? load(state.requested) : Promise.resolve(false)),
        get hasPrevious() {
            return state.page > 1;
        },
        get hasNext() {
            return state.page > 0 && state.page < state.lastPage;
        },
    };
}

/** Which part of the page is shown: loading, error, empty (no images at all), noResults (none for a search), images. */
export function viewState(state) {
    if (state.loading) {
        return 'loading';
    }

    if (state.error !== null) {
        return 'error';
    }

    if (state.images.length === 0) {
        return state.search === '' ? 'empty' : 'noResults';
    }

    return 'images';
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

/**
 * One image, built like the admin library's card (VENDOR-MEDIA-04B-UI): a square thumbnail whose button opens the
 * preview, and the name below. The thumbnail is decorative; the button names the image.
 */
export function renderCard(doc, view, { messages, onOpen }) {
    const card = element(doc, 'li', 'vendor-media-card');
    card.dataset.id = view.id;

    const thumb = element(doc, 'div', 'vendor-media-thumb');
    const open = element(doc, 'button', 'vendor-media-open');
    open.type = 'button';
    open.setAttribute('aria-label', format(messages.open, { name: view.label }));
    open.addEventListener('click', () => onOpen(view, open));

    if (view.url) {
        const image = element(doc, 'img');
        image.src = view.url;
        image.alt = '';
        image.loading = 'lazy';
        image.decoding = 'async';
        open.append(image);
    } else {
        const icon = element(doc, 'i', 'ph-duotone ph-image');
        icon.setAttribute('aria-hidden', 'true');
        open.append(icon);
    }

    thumb.append(open);

    const meta = element(doc, 'div', 'vendor-media-meta');
    const name = element(doc, 'bdi', 'vendor-media-name', view.label);
    name.title = view.label;
    meta.append(name);
    card.append(thumb, meta);

    return card;
}

// ---------------------------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------------------------

export function initMediaLibrary(root, { url, messages }, win = window) {
    const doc = root.ownerDocument;
    const $ = (name) => root.querySelector(`[data-media-${name}]`);
    const base = win.location.href;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const ui = {
        form: $('search-form'),
        search: $('search'),
        clear: $('clear'),
        summary: $('summary'),
        loading: $('loading'),
        error: $('error'),
        errorText: $('error-text'),
        retry: $('retry'),
        empty: $('empty'),
        noResults: $('no-results'),
        grid: $('grid'),
        pager: $('pager'),
        previous: $('previous'),
        next: $('next'),
        pageStatus: $('page-status'),
        dialog: $('dialog'),
        dialogImage: $('dialog-image'),
        dialogCaption: $('dialog-caption'),
        dialogOriginal: $('dialog-original'),
        dialogClose: $('dialog-close'),
    };

    let focusSummary = false;
    let opener = null;

    const render = () => {
        const { state } = gallery;
        const shown = viewState(state);
        root.dataset.state = shown;

        ui.loading.hidden = shown !== 'loading';
        ui.error.hidden = shown !== 'error';
        ui.empty.hidden = shown !== 'empty';
        ui.noResults.hidden = shown !== 'noResults';
        ui.grid.hidden = shown !== 'images';
        ui.grid.setAttribute('aria-busy', shown === 'loading' ? 'true' : 'false');

        if (shown === 'error') {
            ui.errorText.textContent = errorText(state.error, messages);
            ui.retry.hidden = !canRetry(state.error);
            ui.summary.textContent = '';
            focusSummary = false;
        }

        if (shown === 'loading') {
            // The pager stays where it is, without acting, until the page arrives.
            ui.previous.disabled = true;
            ui.next.disabled = true;

            return;
        }

        if (shown !== 'error') {
            ui.grid.replaceChildren(...state.images.map((item) => renderCard(doc, imageView(item, { messages, base }), { messages, onOpen: openPreview })));
            ui.summary.textContent = format(messages.count, { count: state.total });
        }

        ui.pager.hidden = shown !== 'images' || state.lastPage <= 1;
        ui.previous.disabled = !gallery.hasPrevious;
        ui.next.disabled = !gallery.hasNext;
        ui.pageStatus.textContent = format(messages.pageStatus, { page: state.page, last: state.lastPage });

        if (focusSummary) {
            focusSummary = false;
            ui.summary.focus();
        }
    };

    const gallery = createGallery({
        fetchPage: ({ page, search }, signal) => request(listUrl(url, { page, search }), { credentials: 'same-origin', headers, signal }),
        onChange: render,
    });

    // ---- Preview (read-only) ----
    function openPreview(view, from) {
        opener = from;
        ui.dialogImage.src = view.url;
        ui.dialogImage.alt = view.label;
        ui.dialogImage.hidden = view.url === '';
        ui.dialogCaption.textContent = view.label;
        ui.dialogOriginal.href = view.url || '#';
        ui.dialogOriginal.hidden = view.url === '';

        ui.dialog.showModal();
    }

    const closePreview = () => {
        if (ui.dialog.open) {
            ui.dialog.close();
        }
    };

    ui.dialog.addEventListener('close', () => {
        if (opener?.isConnected) {
            opener.focus();
        }

        opener = null;
    });
    // A click on the backdrop (the dialog itself, outside its content) closes it, as Escape does.
    ui.dialog.addEventListener('click', (event) => {
        if (event.target === ui.dialog) {
            closePreview();
        }
    });
    ui.dialogClose.addEventListener('click', closePreview);

    // ---- Search, paging, retry ----
    let timer = null;

    const search = () => {
        clearTimeout(timer);
        const term = cleanSearch(ui.search.value);
        ui.clear.hidden = term === '';

        if (term !== gallery.state.search || gallery.state.error !== null || gallery.state.page === 0) {
            gallery.load({ page: 1, search: term });
        }
    };

    ui.form.addEventListener('submit', (event) => {
        event.preventDefault();
        search();
    });
    ui.search.addEventListener('input', () => {
        ui.clear.hidden = cleanSearch(ui.search.value) === '';
        clearTimeout(timer);
        timer = setTimeout(search, SEARCH_DELAY);
    });
    ui.clear.addEventListener('click', () => {
        ui.search.value = '';
        search();
        ui.search.focus();
    });

    const turn = (step) => {
        if (gallery.state.loading) {
            return;
        }

        focusSummary = true;
        gallery.load({ page: gallery.state.page + step, search: gallery.state.search });
    };

    ui.previous.addEventListener('click', () => gallery.hasPrevious && turn(-1));
    ui.next.addEventListener('click', () => gallery.hasNext && turn(1));
    ui.retry.addEventListener('click', () => gallery.retry());

    gallery.load({ page: 1, search: '' });

    return { gallery, close: closePreview };
}

function boot() {
    const root = document.querySelector('[data-vendor-media-library]');
    const i18n = root?.querySelector('[data-media-i18n]');

    if (root && i18n) {
        initMediaLibrary(root, { url: root.dataset.listUrl, messages: JSON.parse(i18n.textContent) });
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
