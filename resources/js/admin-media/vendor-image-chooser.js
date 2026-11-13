/*
 * The vendor image chooser helpers (PRODUCT-MEDIA-01 P5-C), moved here unchanged from admin-product-gallery.js
 * (PRODUCT-STORY-MEDIA, 7B-4F-2A) so the product gallery and the story image share one copy: the list URL of
 * dashboard.products.vendor-media.* (read only, scoped to the product vendor on the server), its answers, a paged
 * loader, the error and empty texts, and one chooser image. Pure functions and DOM building only: no start-up, no
 * listener on the page, no wording (every text comes from the caller's config).
 */

import { format } from './shared.js';

const textOf = (value) => (typeof value === 'string' ? value.trim() : '');

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

/** An address the page may show: http(s) only, else ''. */
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

/**
 * The chooser's list URL. A new product names its vendor (vendor_id); an existing one does not, the server uses the
 * product's stored vendor. Null when a new product has no vendor yet.
 */
export function mediaListUrl(base, { editing, vendorId = '', page = 1, perPage = 24, search = '' }) {
    if (!editing && !/^[1-9][0-9]{0,18}$/.test(String(vendorId))) {
        return null;
    }

    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });

    if (!editing) {
        params.set('vendor_id', String(vendorId));
    }

    if (search) {
        params.set('search', search);
    }

    return `${base}?${params}`;
}

/** A chooser answer; errors are named, never carried as the server's own message. */
export function interpretList({ status, body }) {
    if (status === 200 && body && Array.isArray(body.data)) {
        const page = Number.isInteger(body.current_page) && body.current_page > 0 ? body.current_page : 1;
        const lastPage = Number.isInteger(body.last_page) && body.last_page >= page ? body.last_page : page;

        return { ok: true, data: body.data.filter((item) => item && Number.isInteger(item.id)), page, lastPage };
    }

    const error = status === 0 ? 'network'
        : status === 401 || status === 419 ? 'session'
            : status === 403 ? 'forbidden'
                : status === 422 ? 'invalid'
                    : 'failed';

    return { ok: false, error };
}

/**
 * ADMIN-P5-UX-01: what the chooser says when it has no image. An empty library also explains that product images
 * come from the vendor's library, never the admin one; a search without results does not.
 */
export const emptyChoices = (search, messages) => (search
    ? { text: messages.libraryEmptySearch, hint: false }
    : { text: messages.libraryEmpty, hint: true });

export function errorText(error, messages) {
    return { network: messages.network, session: messages.session, forbidden: messages.forbidden, invalid: messages.invalid }[error] || messages.loadError;
}

/** A chooser image as a gallery item. */
export function itemFromMedia(media, base) {
    return {
        ref: `media:${media.id}`,
        url: safeUrl(media.url, base),
        name: textOf(media.file_original_name) || `#${media.id}`,
        alt: textOf(media.alt),
        legacy: false,
    };
}

/**
 * Pages of the chooser. reset() starts again and drops any answer still on its way; more() asks for the next page
 * once (never while a request runs or after the last page); retry() repeats the failed page.
 */
export function createLoader({ fetchList, onChange = () => {} }) {
    const state = { query: null, items: [], ids: new Set(), page: 0, lastPage: 1, loading: false, error: null, generation: 0 };
    let controller = null;

    const run = async (page) => {
        const generation = state.generation;
        controller = typeof AbortController === 'function' ? new AbortController() : null;
        state.loading = true;
        state.error = null;
        onChange({ type: 'loading' });

        const response = await fetchList(state.query, page, controller?.signal);

        if (generation !== state.generation) {
            return false;
        }

        state.loading = false;
        const outcome = interpretList(response ?? { status: 0, body: null });

        if (!outcome.ok) {
            state.error = outcome.error;
            onChange({ type: 'error', error: outcome.error });

            return false;
        }

        state.page = outcome.page;
        state.lastPage = outcome.lastPage;
        const added = outcome.data.filter((item) => !state.ids.has(item.id) && state.ids.add(item.id));
        state.items.push(...added);
        onChange({ type: 'loaded', added });

        return true;
    };

    return {
        state,
        get hasMore() {
            return state.page > 0 && state.page < state.lastPage && state.error === null;
        },
        reset(query) {
            state.generation += 1;
            controller?.abort();
            Object.assign(state, { query, items: [], ids: new Set(), page: 0, lastPage: 1, loading: false, error: null });
            onChange({ type: 'reset' });

            return run(1);
        },
        more() {
            return state.loading || state.query === null || state.page === 0 || state.page >= state.lastPage ? Promise.resolve(false) : run(state.page + 1);
        },
        retry() {
            return state.loading || state.query === null || state.error === null ? Promise.resolve(false) : run(state.page + 1);
        },
        stop() {
            state.generation += 1;
            controller?.abort();
            state.loading = false;
        },
    };
}

/** One chooser image: a button naming the image; images already in the gallery are shown but cannot be chosen. */
export function renderChoice(doc, media, { messages, base, taken, onChoose }) {
    const item = itemFromMedia(media, base);
    const card = element(doc, 'li', 'admin-media-card admin-product-gallery-choice');
    card.dataset.id = String(media.id);

    const choose = element(doc, 'button', 'admin-media-open');
    choose.type = 'button';
    choose.setAttribute('aria-label', format(messages.choose, { name: item.name }));
    choose.disabled = taken;

    const image = element(doc, 'img');
    image.src = item.url;
    image.alt = '';
    image.loading = 'lazy';
    choose.append(image);
    choose.addEventListener('click', () => onChoose(item, card));

    const meta = element(doc, 'div', 'admin-media-meta');
    const name = element(doc, 'bdi', 'admin-media-name', item.name);
    name.title = item.name;
    meta.append(name);

    if (taken) {
        meta.append(element(doc, 'span', 'admin-media-facts-line', messages.inGallery));
    }

    const thumb = element(doc, 'div', 'admin-media-thumb');
    thumb.append(choose);
    card.append(thumb, meta);

    return card;
}
