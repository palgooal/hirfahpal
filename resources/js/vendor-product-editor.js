/*
 * The vendor product editor (resources/views/vendor-dashboard/resources.blade.php, resource "products").
 *
 * G7-C2-5: product images come from the vendor's own media library only (uploads go to the library, never to a
 * product). On an existing product every gallery change is one request, and the gallery is redrawn only from the
 * server's answer (product.images, already in order with the main image first): adding is POST .../images, replacing
 * is PATCH .../images/{id} with the new path (one request, so it works with six images and keeps the position and the
 * main image), choosing the main image is PATCH {is_primary: true}, removing is DELETE (the file stays in the
 * library). A refused or failed request leaves the gallery as it was. Texts come from the page (data-product-gallery
 * -messages, translated), never from the server's message.
 */

import { request } from './admin-media/shared.js';
import { cleanSearch, createGallery, listUrl, safeUrl, viewState } from './vendor-media-library.js';

export const MAX_IMAGES = 6;

/** Fills :name placeholders. */
export const format = (template, values = {}) => String(template ?? '').replace(/:([a-z]+)/g, (match, name) => (name in values ? String(values[name]) : match));

/** The gallery as shown: the main image first, then sort_order, then id (the server's order; also tidies legacy data). */
export function orderImages(images) {
    const list = Array.isArray(images) ? images.filter((image) => image && typeof image === 'object') : [];
    const sorted = [...list].sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) || (Number(a.id) || 0) - (Number(b.id) || 0));
    const primary = sorted.find((image) => image.is_primary) ?? sorted[0];

    return primary ? [primary, ...sorted.filter((image) => image !== primary)] : [];
}

/**
 * A gallery request's answer: { ok, images } or { ok: false, error }. The error is a name, never the server's text:
 * limit (six images), library (not an image of the vendor's library, or removed from it), gone (the image or the
 * product no longer exists), session, forbidden, invalid, network, failed.
 */
export function interpretImageResponse({ status, body }) {
    if (status >= 200 && status < 300) {
        return { ok: true, images: orderImages(body?.product?.images) };
    }

    const errors = body && typeof body.errors === 'object' && body.errors !== null ? body.errors : {};
    const error = status === 0 ? 'network'
        : status === 401 || status === 419 ? 'session'
            : status === 403 ? 'forbidden'
                : status === 404 ? 'gone'
                    // VPM-01C: the product already has this image (checked on the server under the product lock).
                    : status === 409 ? 'duplicate'
                    : status === 422 && 'images' in errors ? 'limit'
                        : status === 422 && 'path' in errors ? 'library'
                            : status === 422 ? 'invalid'
                                : 'failed';

    return { ok: false, error };
}

/** VENDOR-PRODUCT-PUBLISH-UI-01B (OPEN-PROD-19, 20): the statuses a vendor chooses; out_of_stock is never one of them. */
export const VENDOR_STATUSES = ['draft', 'active', 'inactive'];

/**
 * The status field's value for a product: draft for a new one (no stored status), the stored status when it is one of
 * the vendor's three, else "" (keep it as it is: a product stored as out_of_stock is never turned into a draft).
 */
export function statusChoice(stored) {
    if (stored === null || stored === undefined || stored === '') return 'draft';

    return VENDOR_STATUSES.includes(stored) ? stored : '';
}

/** The status sent on save: a chosen vendor status, or undefined for "" (left out of the JSON, so the server keeps it). */
export const statusPayload = (value) => (VENDOR_STATUSES.includes(value) ? value : undefined);

/**
 * Shows a product's status in the field. The "keep" option (value "", its text from the field's data-keep-label) is
 * there only while the product's stored status is not one of the vendor's three; it is removed for any other product.
 */
export function setStatusField(select, stored) {
    select.querySelector('option[data-status-keep]')?.remove();
    const value = statusChoice(stored);

    if (value === '') {
        const keep = select.ownerDocument.createElement('option');
        keep.value = '';
        keep.dataset.statusKeep = 'true';
        keep.textContent = select.dataset.keepLabel || '';
        select.prepend(keep);
    }
    select.value = value;
}

/**
 * VENDOR-PRODUCT-PUBLISH-UI-01D (DETAIL-02, DETAIL-03): the editor's mode from the product's moderation flags (UI-01C).
 * archived: read-only, whatever else is true; suspended: editable, with a notice; editable otherwise (flags missing too).
 */
export function editorMode(moderation) {
    if (moderation?.archived === true) return 'archived';

    return moderation?.suspended === true ? 'suspended' : 'editable';
}

/**
 * A refusal that may be the archived one: a 422 that names no field (validation, the six-image limit and the library
 * rule always name one). It is never taken as proof: the product is read again and its flags decide.
 */
export function mayBeArchivedRefusal(status, body) {
    if (status !== 422) return false;
    const errors = body && typeof body.errors === 'object' && body.errors !== null ? body.errors : {};

    return Object.keys(errors).length === 0;
}

/**
 * What a fresh read of the product, after such a refusal, decides: archived (read-only, the refusal announced), other
 * (not archived: the operation's own error), or failed (unreadable: the operation's own failure, never a success).
 */
export function refusalOutcome(fresh) {
    if (!fresh || fresh.status !== 200 || !fresh.body?.product) return 'failed';

    return editorMode(fresh.body.moderation) === 'archived' ? 'archived' : 'other';
}

/** One gallery button's disabled state: always in read-only mode, else while busy, an add at the limit, or a move past an end. */
export const galleryButtonDisabled = ({ readOnly = false, busy = false, limited = false, full = false, boundary = false }) => readOnly || busy || (limited && full) || boundary;

/**
 * Which product the editor is on: every open and close takes a new ticket, and an answer is used only while its ticket
 * is still the current one, so a late answer about one product never changes the editor of another.
 */
export function createTickets() {
    let current = 0;

    return { next: () => ++current, isCurrent: (ticket) => ticket === current };
}

/** The translated text of an error name. */
export const errorMessage = (error, messages) => messages[error] || messages.failed;

/** What a chosen library image does: add, add as the main image, or replace one image (at its place). */
export function planChoice(images, { mode, target }) {
    if (target) {
        return { action: 'replace', target };
    }

    if (images.length >= MAX_IMAGES) {
        return { action: 'refuse', error: 'limit' };
    }

    return { action: 'add', primary: mode === 'primary' || images.length === 0 };
}

/** A new product (nothing saved yet): the same rules on the local list. */
export function applyLocal(images, plan, medium) {
    const item = { path: medium.path, url: medium.url, alt_text: medium.alt_text ?? null };

    if (plan.action === 'replace') {
        return images.map((image) => (image === plan.target ? { ...item, is_primary: image.is_primary } : image));
    }

    if (plan.action !== 'add') {
        return images;
    }

    const next = plan.primary
        ? [{ ...item, is_primary: true }, ...images.map((image) => ({ ...image, is_primary: false }))]
        : [...images, { ...item, is_primary: images.length === 0 }];

    return next.map((image, position) => ({ ...image, sort_order: position }));
}

/** A new product: the main image moves first, the others keep their order. */
export function setLocalPrimary(images, chosen) {
    return [chosen, ...images.filter((image) => image !== chosen)].map((image, position) => ({ ...image, is_primary: position === 0, sort_order: position }));
}

/**
 * VPM-01D: a new product (nothing saved): the image swaps places with its neighbour, up or down; past either end nothing
 * changes. As on the server, the order is then 0..n-1 and the first image is the main one.
 */
export function moveLocal(images, moved, direction) {
    const from = images.indexOf(moved);
    const to = from + (direction === 'up' ? -1 : 1);

    if (from < 0 || to < 0 || to >= images.length) {
        return images;
    }

    const next = [...images];
    [next[from], next[to]] = [next[to], next[from]];

    return next.map((image, position) => ({ ...image, is_primary: position === 0, sort_order: position }));
}

/**
 * VPM-01E: the address of a product image, as the storefront shows it (HomepageMedia::url): a library image's own url,
 * an http(s) path as it is, an "assets/" or "/" path from the public folder, else the file on the public disk. A path
 * that is not a plain relative file path (a scheme other than http(s), a backslash, an empty, "." or ".." segment) gets
 * no address, so the card shows its placeholder instead of asking for something else.
 */
export function productImageUrl(image) {
    // http(s), or a same-site path (one leading slash: "//host/..." would be another site).
    if (typeof image?.url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(image.url)) {
        return image.url;
    }

    const path = typeof image?.path === 'string' ? image.path.trim() : '';

    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    const relative = path.replace(/^\/+/, '');
    const segments = relative.split('/');

    if (relative === '' || relative.includes('\\') || /^[a-z][a-z0-9+.-]*:/i.test(relative) || segments.some((segment) => segment === '' || segment === '.' || segment === '..')) {
        return '';
    }

    const encoded = segments.map(encodeURIComponent).join('/');

    return path.startsWith('/') || relative.startsWith('assets/') ? `/${encoded}` : `/storage/${encoded}`;
}

/**
 * VPM-01E: an image from before the media library: its path is not a file directly in the vendor's own library folder
 * (vendors/media/{vendorId}/), the rule the admin gallery uses for its "not in the vendor library" mark. Only a hint:
 * the server decides what may be attached. An unsaved image always comes from the library.
 */
export function isLegacyImage(image, libraryPrefix) {
    if (!image?.id || typeof libraryPrefix !== 'string' || libraryPrefix === '') {
        return false;
    }

    const path = typeof image.path === 'string' ? image.path : '';
    const file = path.startsWith(libraryPrefix) ? path.slice(libraryPrefix.length) : null;

    return file === null || file === '' || file === '.' || file === '..' || /[/\\]/.test(file);
}

/** VPM-01E: the one note about the limit: none below it, "full" at it, "over" above it (a gallery from before the limit). */
export const limitNote = (count, max = MAX_IMAGES) => (count > max ? 'over' : count === max ? 'full' : null);

/** The card's name: its alt text, else "Product image". */
export const imageLabel = (image, messages) => (typeof image.alt_text === 'string' && image.alt_text.trim() !== '' ? image.alt_text.trim() : messages.imageAlt);

/** VPM-01D: what keeps a card recognisable across a redraw: its id once saved, else its library path (one per product). */
export const imageKey = (image) => (image.id !== undefined && image.id !== null ? `id:${image.id}` : `path:${image.path}`);

/**
 * VPM-01D: after a move the cards are redrawn. Focus goes back to the same button of the same image (found by its
 * key), or to the other direction when that one is now at an end of the list, else to its Change button.
 */
export function focusAfterMove(container, key, action) {
    const card = [...container.querySelectorAll('[data-image-key]')].find((node) => node.dataset.imageKey === key);
    const usable = (node) => (node && !node.disabled ? node : null);
    const target = usable(card?.querySelector(`[data-action="${action}"]`))
        ?? usable(card?.querySelector(`[data-action="${action === 'up' ? 'down' : 'up'}"]`))
        ?? card?.querySelector('[data-action="change"]');
    target?.focus();

    return target ?? null;
}

/** A new product: removing closes the gap; the first remaining image is the main one. */
export function removeLocal(images, removed) {
    return images.filter((image) => image !== removed).map((image, position) => ({ ...image, is_primary: position === 0, sort_order: position }));
}

/**
 * VPM-01A: the text of a failed product save. A 422 leads with the page's translated sentence, then the field details
 * the server gave; any other failure is a page text only. The server's own message is never shown.
 */
export function saveErrorText(status, body, texts, messages) {
    if (status === 422) {
        const details = Object.values(body?.errors ?? {}).flat().filter((text) => typeof text === 'string' && text !== '').join(' ');

        return details ? `${texts.saveInvalid} ${details}` : texts.saveFailed;
    }

    return status === 401 || status === 419 ? messages.session : status === 403 ? messages.forbidden : texts.saveFailed;
}

const icon = (doc, name) => {
    const node = doc.createElement('i');
    node.className = `ph-duotone ${name}`;
    node.setAttribute('aria-hidden', 'true');

    return node;
};

/**
 * VPM-01A: one product image, built like the admin gallery's card (thumbnail, position, name, main badge, actions).
 * Texts go through textContent only; each action button is described by the image name.
 */
export function renderImageCard(doc, image, { index, count = index + 1, isPrimary, url, legacy = false, messages, onChange, onSetMain, onRemove, onMove = () => {} }) {
    const nameId = `vendor-product-image-${index}-name`;
    const label = imageLabel(image, messages);

    const card = doc.createElement('li');
    card.className = isPrimary ? 'vendor-product-gallery-item is-main' : 'vendor-product-gallery-item';
    if (image.id !== undefined && image.id !== null) card.dataset.imageId = String(image.id);
    card.dataset.imageKey = imageKey(image);

    const thumb = doc.createElement('div');
    thumb.className = 'vendor-product-gallery-thumb';
    const meta = doc.createElement('div');

    // VPM-01E: an image whose file cannot be shown (no usable address, or the file is gone) is a placeholder with a
    // note; the card and its actions stay, nothing is removed.
    const unavailable = () => {
        if (card.dataset.missing === 'true') return;
        card.dataset.missing = 'true';
        thumb.replaceChildren(icon(doc, 'ph-image'));
        const note = doc.createElement('span');
        note.className = 'vendor-product-gallery-badge is-missing';
        note.textContent = messages.missing;
        meta.append(note);
    };

    if (url) {
        const pic = doc.createElement('img');
        pic.src = url;
        pic.alt = label;
        pic.loading = 'lazy';
        pic.onerror = unavailable;
        thumb.append(pic);
    }

    meta.className = 'vendor-product-gallery-meta';
    const position = doc.createElement('span');
    position.className = 'vendor-product-gallery-position';
    position.textContent = format(messages.position, { position: index + 1 });
    const name = doc.createElement('bdi');
    name.className = 'vendor-product-gallery-name';
    name.id = nameId;
    name.textContent = label;
    name.title = label;
    meta.append(position, name);

    if (isPrimary) {
        const badge = doc.createElement('span');
        badge.className = 'vendor-product-gallery-badge is-main';
        badge.textContent = messages.mainBadge;
        meta.append(badge);
    }

    // VPM-01E: an image from before the media library (outside the vendor's own folder), as the admin gallery marks it.
    if (legacy) {
        const badge = doc.createElement('span');
        badge.className = 'vendor-product-gallery-badge is-legacy';
        badge.textContent = messages.legacy;
        meta.append(badge);
    }

    if (!url) {
        unavailable();
    }

    const actions = doc.createElement('div');
    actions.className = 'vendor-product-gallery-actions';
    const button = (action, text, iconName, onClick, boundary = false) => {
        const node = doc.createElement('button');
        node.type = 'button';
        node.className = 'btn btn-sm btn-light-secondary';
        node.dataset.action = action;
        node.setAttribute('aria-describedby', nameId);
        // VPM-01D: a move past either end is unavailable; setBusy() keeps it disabled after a request.
        node.disabled = boundary;
        node.dataset.boundary = boundary ? 'true' : 'false';
        const caption = doc.createElement('span');
        caption.textContent = text;
        node.append(icon(doc, iconName), caption);
        node.onclick = onClick;
        actions.append(node);
    };

    // VPM-01D: the admin gallery's order of actions: move up, move down, then the vendor's change / main / remove.
    button('up', messages.moveUp, 'ph-arrow-up', (event) => onMove('up', event), index === 0);
    button('down', messages.moveDown, 'ph-arrow-down', (event) => onMove('down', event), index >= count - 1);
    button('change', messages.change, 'ph-swap', onChange);
    if (!isPrimary) button('main', messages.setMain, 'ph-star', onSetMain);
    button('remove', messages.remove, 'ph-x', onRemove);

    card.append(thumb, meta, actions);

    return card;
}

// ---------------------------------------------------------------------------------------------
// VPM-01B: the media picker dialog (the admin product gallery's chooser, for the vendor's own library)
// ---------------------------------------------------------------------------------------------

/** VENDOR-MEDIA-02B: the most images one upload request may carry (MediaLibraryController::MAX_FILES_PER_UPLOAD). */
export const MAX_UPLOAD_FILES = 10;

const textOf = (value) => (typeof value === 'string' ? value.trim() : '');

/** The dialog title of the mode: replacing an image, choosing the main image, or adding a gallery image. */
export const pickerTitle = (messages, { mode, target }) => (target ? messages.titleReplace : mode === 'primary' ? messages.titleMain : messages.titleGallery);

/** A library image is already on the product when one of the product's images has its path. */
export const isAttached = (images, medium) => typeof medium?.path === 'string' && images.some((image) => image.path === medium.path);

/**
 * VPM-01C: what a library image is for the picker's mode. 'current': the image being replaced (choosing it keeps the
 * slot as it is, allowed); 'attached': another image of the product has it (refused by the server, so not choosable);
 * null: free. Exact paths; the target is compared by identity, so a historical duplicate of the slot stays its own.
 */
export function choiceState(images, medium, target = null) {
    if (typeof medium?.path !== 'string') {
        return null;
    }

    if (target && target.path === medium.path) {
        return 'current';
    }

    return images.some((image) => image !== target && image.path === medium.path) ? 'attached' : null;
}

/** What an upload may send: nothing chosen does nothing; more than ten files is refused before anything is sent. */
export function uploadPlan(files) {
    const list = Array.from(files ?? []);

    if (list.length === 0) {
        return { ok: false, error: null };
    }

    return list.length > MAX_UPLOAD_FILES ? { ok: false, error: 'uploadMax' } : { ok: true, files: list };
}

/** A refused upload's error name: too many files (files.max), an invalid file, else as for the image requests. */
export function uploadError({ status, body }) {
    if (status === 422) {
        return body?.errors && typeof body.errors === 'object' && 'files' in body.errors ? 'uploadMax' : 'uploadInvalid';
    }

    return interpretImageResponse({ status, body }).error;
}

/** The translated text of a listing error. */
export const pickerErrorText = (error, messages) => ({ network: messages.network, session: messages.session, forbidden: messages.forbidden, invalid: messages.invalid })[error] || messages.libraryError;

/**
 * One library image in the picker, built like the admin chooser card (square thumbnail whose button chooses it, name
 * below). VPM-01C: an image another slot of the product has ('attached') says so and cannot be chosen; the image being
 * replaced ('current') says so and can be chosen (the slot stays as it is).
 */
export function renderChoice(doc, medium, { messages, base, state = null, busy = false, onChoose }) {
    const label = textOf(medium.alt_text) || messages.mediaAlt;
    const url = safeUrl(medium.url, base);
    const card = doc.createElement('li');
    card.className = state === 'attached' ? 'vendor-media-card is-taken' : state === 'current' ? 'vendor-media-card is-current' : 'vendor-media-card';
    card.dataset.id = String(medium.id ?? '');
    card.dataset.state = state ?? 'free';

    const thumb = doc.createElement('div');
    thumb.className = 'vendor-media-thumb';
    const choose = doc.createElement('button');
    choose.type = 'button';
    choose.className = 'vendor-media-open';
    choose.setAttribute('aria-label', format(messages.choose, { name: label }));
    choose.disabled = busy || state === 'attached';
    choose.dataset.blocked = state === 'attached' ? 'true' : 'false';
    choose.onclick = () => onChoose(medium);

    if (url) {
        const image = doc.createElement('img');
        image.src = url;
        image.alt = '';
        image.loading = 'lazy';
        choose.append(image);
    } else {
        choose.append(icon(doc, 'ph-image'));
    }

    thumb.append(choose);

    const meta = doc.createElement('div');
    meta.className = 'vendor-media-meta';
    const name = doc.createElement('bdi');
    name.className = 'vendor-media-name';
    name.textContent = label;
    name.title = label;
    meta.append(name);

    if (state) {
        const note = doc.createElement('span');
        note.className = state === 'attached' ? 'vendor-product-picker-taken' : 'vendor-product-picker-current';
        note.id = `vendor-product-picker-${state}-${card.dataset.id}`;
        note.textContent = state === 'attached' ? messages.attached : messages.current;
        choose.setAttribute('aria-describedby', note.id);
        meta.append(note);
    }

    card.append(thumb, meta);

    return card;
}

/**
 * The picker dialog: one page of 24 of the vendor's own images at a time (page, per_page, search; never the whole
 * library), with search, paging, states, the "already in this product" note and the upload button. open() shows it
 * for a mode and remembers the button that opened it; closing (button, Escape or backdrop) gives focus back to that
 * button, or to fallback() when it is gone (a replaced card is redrawn). Choosing and uploading are the editor's.
 */
export function initPicker(root, { url, messages, stateOf, onChoose, onUpload }, win = window) {
    const doc = root.ownerDocument;
    const $ = (name) => root.querySelector(`[data-product-media-${name}]`);
    const base = win.location.href;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const ui = {
        title: $('title'), close: $('close'), form: $('search-form'), search: $('search'), summary: $('summary'), status: $('status'), alert: $('alert'),
        loading: $('loading'), error: $('error'), errorText: $('error-text'), retry: $('retry'), empty: $('empty'), noResults: $('no-results'),
        grid: $('items'), pager: $('pager'), previous: $('previous'), next: $('next'), pageStatus: $('page-status'), upload: $('upload'), file: $('file'),
    };
    let opener = null, fallback = null, busy = false, focusSummary = false, timer = null;

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

        if (shown === 'loading') {
            ui.previous.disabled = true;
            ui.next.disabled = true;

            return;
        }

        if (shown === 'error') {
            ui.errorText.textContent = pickerErrorText(state.error, messages);
            ui.retry.hidden = !(state.error === 'network' || state.error === 'failed');
            ui.summary.textContent = '';
            focusSummary = false;
        } else {
            ui.grid.replaceChildren(...state.images.map((medium) => renderChoice(doc, medium, { messages, base, state: stateOf(medium), busy, onChoose })));
            ui.summary.textContent = format(messages.libraryCount, { count: state.total });
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

    const say = (text) => { ui.status.textContent = ''; ui.status.textContent = text || ''; };
    const alert = (text) => { ui.alert.textContent = text || ''; ui.alert.hidden = !text; };
    const close = () => {
        if (root.open) {
            root.close();
        }
    };

    root.addEventListener('close', () => {
        clearTimeout(timer);
        const back = opener?.isConnected && !opener.disabled ? opener : fallback?.();
        opener = null;
        back?.focus();
    });
    // A click on the backdrop (the dialog itself, outside its content) closes it, as Escape does.
    root.addEventListener('click', (event) => {
        if (event.target === root) {
            close();
        }
    });
    ui.close.addEventListener('click', close);

    const search = () => {
        clearTimeout(timer);
        const term = cleanSearch(ui.search.value);

        if (term !== gallery.state.search || gallery.state.error !== null || gallery.state.page === 0) {
            gallery.load({ page: 1, search: term });
        }
    };

    ui.form.addEventListener('submit', (event) => {
        event.preventDefault();
        search();
    });
    ui.search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, SEARCH_DELAY);
    });

    const turn = (step) => {
        if (!gallery.state.loading) {
            focusSummary = true;
            gallery.load({ page: gallery.state.page + step, search: gallery.state.search });
        }
    };

    ui.previous.addEventListener('click', () => gallery.hasPrevious && turn(-1));
    ui.next.addEventListener('click', () => gallery.hasNext && turn(1));
    ui.retry.addEventListener('click', () => gallery.retry());
    ui.upload.addEventListener('click', () => !busy && ui.file.click());
    ui.file.addEventListener('change', () => {
        const files = Array.from(ui.file.files || []);
        ui.file.value = '';
        onUpload(files);
    });

    return {
        gallery,
        close,
        say,
        alert,
        get isOpen() {
            return Boolean(root.open);
        },
        open({ title, from = null, fallback: back = null }) {
            opener = from;
            fallback = back;
            ui.title.textContent = title;
            ui.search.value = '';
            say('');
            alert('');
            root.showModal();
            ui.search.focus();
            gallery.load({ page: 1, search: '' });
        },
        /** After an upload: the first page again (newest first), with the current search. */
        refresh: () => gallery.load({ page: 1, search: gallery.state.search }),
        setBusy(state) {
            busy = state;
            root.setAttribute('aria-busy', state ? 'true' : 'false');
            ui.upload.disabled = state;
            ui.grid.querySelectorAll?.('button').forEach((button) => { button.disabled = state || button.dataset.blocked === 'true'; });
        },
    };
}

const SEARCH_DELAY = 400;

const fetchJson = async (url, options) => {
    try {
        const response = await fetch(url, options);

        return { status: response.status, body: await response.json().catch(() => null) };
    } catch {
        return { status: 0, body: null };
    }
};

function initEditor(editor) {
    const form = editor.querySelector('[data-product-form]');
    const error = editor.querySelector('[data-product-error]');
    const title = editor.querySelector('[data-product-editor-title]');
    const endpoint = editor.dataset.endpoint;
    const token = form.querySelector('input[name="_token"]').value;
    const list = document.querySelector('[data-vendor-resource-list]');
    const messages = JSON.parse(editor.querySelector('[data-product-gallery-messages]')?.textContent || '{}');
    const texts = JSON.parse(editor.querySelector('[data-product-editor-messages]')?.textContent || '{}');
    const gallery = editor.querySelector('[data-product-gallery]');
    const galleryStatus = editor.querySelector('[data-product-gallery-status]');
    const galleryError = editor.querySelector('[data-product-gallery-error]');
    const galleryCount = editor.querySelector('[data-product-gallery-count]');
    const primaryOpen = editor.querySelector('[data-product-primary-open]');
    const galleryOpen = editor.querySelector('[data-product-gallery-open]');
    let editingId = null, images = [], mode = 'gallery', target = null, busy = false, picker = null, readOnly = false;
    // VENDOR-PRODUCT-PUBLISH-UI-01D: the notices, the save button, and the product the editor is on (createTickets()).
    const notices = { archived: editor.querySelector('[data-product-notice="archived"]'), suspended: editor.querySelector('[data-product-notice="suspended"]') };
    const saveButton = editor.querySelector('[data-product-save]');
    const tickets = createTickets();
    let session = tickets.next();
    const field = (name) => form.elements.namedItem(name);
    const value = (name) => field(name).value.trim() || null;
    const number = (name) => field(name).value === '' ? null : Number(field(name).value);
    const headers = (json) => ({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, ...(json ? { 'Content-Type': 'application/json' } : {}) });
    const request = (url, method = 'GET', body = null) => fetch(url, { method, credentials: 'same-origin', headers: headers(Boolean(body)), ...(body ? { body: JSON.stringify(body) } : {}) });
    const send = (url, method, body = null) => fetchJson(url, { method, credentials: 'same-origin', headers: headers(Boolean(body)), ...(body ? { body: JSON.stringify(body) } : {}) });
    const fail = (text) => { error.textContent = text; error.hidden = false; };
    const imageUrl = productImageUrl;
    const libraryPrefix = editor.dataset.libraryPrefix || '';
    const galleryLimit = editor.querySelector('[data-product-gallery-limit]');
    const primaryBox = () => editor.querySelector('[data-product-primary-image]');
    const galleryBox = () => editor.querySelector('[data-product-gallery-images]');
    const showEditor = () => { list.hidden = true; editor.hidden = false; editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); };

    // ---- Gallery states: one at a time, announced; an error stays until the next action. ----
    // VPM-01B: while the picker is open, the same texts also show inside it (the section behind it is covered).
    const status = (text) => { galleryStatus.textContent = ''; galleryStatus.textContent = text || ''; if (picker?.isOpen) picker.say(text); };
    const galleryFail = (name) => { galleryError.textContent = errorMessage(name, messages); galleryError.hidden = false; status(''); if (picker?.isOpen) picker.alert(galleryError.textContent); };
    const clearGalleryError = () => { galleryError.hidden = true; galleryError.textContent = ''; picker?.alert(''); };
    const setBusy = (state) => {
        busy = state;
        gallery.setAttribute('aria-busy', state ? 'true' : 'false');
        // UI-01D: read-only is part of every button's state, so ending a request never enables one in read-only mode.
        gallery.querySelectorAll('button').forEach((button) => {
            button.disabled = galleryButtonDisabled({ readOnly, busy: state, limited: button.dataset.limited === 'true', full: images.length >= MAX_IMAGES, boundary: button.dataset.boundary === 'true' });
        });
        picker?.setBusy(state || readOnly);
    };
    // Nothing changes the product while a request runs or while it is read-only; every action asks this first.
    const locked = () => busy || readOnly;

    /**
     * UI-01D: the one place the mode is set. archived: every section (its fields, the status and the gallery's buttons)
     * is disabled, Save is hidden and the picker is closed; the notices show the mode. Cancel stays.
     */
    const applyMode = (next) => {
        readOnly = next === 'archived';
        editor.dataset.mode = next;
        notices.archived.hidden = next !== 'archived';
        notices.suspended.hidden = next !== 'suspended';
        form.querySelectorAll(':scope > fieldset').forEach((section) => { section.disabled = readOnly; });
        saveButton.hidden = readOnly;
        saveButton.disabled = readOnly;
        if (readOnly) picker?.close();
        setBusy(busy);
    };
    // A new product, another product, or none: a new ticket, and no request of the previous one counts as running.
    const begin = () => { session = tickets.next(); busy = false; return session; };
    const focusCard = (key, action) => focusAfterMove(gallery, key, action);

    const render = () => {
        images = orderImages(images);
        const primary = images[0] ?? null;
        // VPM-01A: the admin gallery's cards in a list, or its dashed empty note. Same actions as before.
        const card = (image, index) => renderImageCard(document, image, {
            index, count: images.length, isPrimary: index === 0, url: imageUrl(image), legacy: isLegacyImage(image, libraryPrefix), messages,
            onChange: (event) => openMedia(index === 0 ? 'primary' : 'replace', image, event?.currentTarget ?? null),
            onSetMain: () => setPrimary(image),
            onRemove: () => removeImage(image),
            onMove: (direction) => moveImage(image, direction),
        });
        const listOf = (cards) => { const node = document.createElement('ol'); node.className = 'vendor-product-gallery-list'; node.append(...cards); return node; };
        const empty = (text) => { const node = document.createElement('p'); node.className = 'vendor-product-gallery-empty'; node.textContent = text; return node; };
        primaryBox().replaceChildren(primary ? listOf([card(primary, 0)]) : empty(messages.noMain));
        galleryBox().replaceChildren(images.length > 1 ? listOf(images.slice(1).map((image, at) => card(image, at + 1))) : empty(messages.noGallery));
        galleryCount.textContent = format(messages.count, { count: images.length, max: MAX_IMAGES });
        // VPM-01E: one note at or above the limit (a gallery from before it shows every image; nothing is removed).
        const note = limitNote(images.length);
        const noteText = note === 'over' ? format(messages.overMax, { count: images.length, max: MAX_IMAGES }) : note === 'full' ? format(messages.full, { max: MAX_IMAGES }) : '';
        if (galleryLimit.textContent !== noteText) galleryLimit.textContent = noteText;
        galleryLimit.hidden = noteText === '';
        galleryLimit.dataset.state = note ?? 'none';
        setBusy(busy);
    };

    // One saved-product request: busy while it runs; the gallery changes only from a successful answer.
    // UI-01D: an answer that comes after another product was opened changes nothing; a 422 naming no field is checked.
    const mutate = async (url, method, body, done) => {
        if (readOnly) return false;
        const ticket = session;
        clearGalleryError(); setBusy(true); status(messages.saving);
        const answer = await send(url, method, body);
        if (!tickets.isCurrent(ticket)) return false;
        setBusy(false);
        const outcome = interpretImageResponse(answer);
        if (!outcome.ok && mayBeArchivedRefusal(answer.status, answer.body)) {
            status('');
            await recheck(ticket, () => galleryFail(outcome.error));
            return false;
        }
        if (!outcome.ok) { galleryFail(outcome.error); return false; }
        images = outcome.images; render(); status(done);
        return true;
    };

    // UI-01D: the product as the server has it, its mode included (opening, and after an archived refusal).
    const fill = ({ product, moderation }) => {
        form.reset();
        ['name', 'category_id', 'price', 'stock_quantity', 'sku', 'compare_at_price', 'low_stock_threshold', 'weight', 'short_description', 'description'].forEach((name) => { field(name).value = product[name] ?? ''; });
        setStatusField(field('status'), product.status);
        images = product.images || [];
        clearGalleryError(); status(''); picker.close();
        applyMode(editorMode(moderation));
        render();
    };
    const load = (id) => fetchJson(`${endpoint}/${id}`, { credentials: 'same-origin', headers: headers(false) });

    /**
     * UI-01D: after a refused change that may be the archived refusal, the product is read again. Archived: the editor
     * shows it read-only and announces the refusal (focus moves to the notice). Not archived: the operation's own error
     * (and the mode the flags give now). Unreadable: the operation's own failure. Nothing is retried.
     */
    const recheck = async (ticket, otherwise) => {
        const id = editingId;
        const fresh = await load(id);
        if (!tickets.isCurrent(ticket) || editingId !== id) return;
        const outcome = refusalOutcome(fresh);
        if (outcome === 'archived') {
            fill(fresh.body);
            fail(notices.archived.dataset.refused);
            notices.archived.focus();
            return;
        }
        if (outcome === 'other') applyMode(editorMode(fresh.body.moderation));
        otherwise();
    };

    const setPrimary = async (image) => {
        if (locked()) return;
        if (!editingId) { images = setLocalPrimary(images, image); render(); status(messages.mainChanged); return; }
        await mutate(`${endpoint}/${editingId}/images/${image.id}`, 'PATCH', { is_primary: true }, messages.mainChanged);
    };

    // VPM-01D: one step up or down. A new product reorders its local list; a saved one sends one PATCH .../move and is
    // redrawn from the server's answer only (a refused or failed move leaves the gallery as it was).
    const moveImage = async (image, direction) => {
        if (locked()) return;
        const key = imageKey(image);
        const moved = () => {
            const position = images.findIndex((other) => imageKey(other) === key);
            if (position >= 0) status(format(messages.moved, { name: imageLabel(images[position], messages), position: position + 1 }));
        };

        if (!editingId || !image.id) {
            images = moveLocal(images, image, direction); clearGalleryError(); render(); moved(); focusCard(key, direction);
            return;
        }

        const saved = await mutate(`${endpoint}/${editingId}/images/${image.id}/move`, 'PATCH', { direction }, '');
        if (saved) moved();
        focusCard(key, direction);
    };

    const removeImage = async (image) => {
        if (locked() || !window.confirm(messages.confirmRemove)) return;
        if (!editingId || !image.id) { images = removeLocal(images, image); render(); status(messages.removed); return; }
        await mutate(`${endpoint}/${editingId}/images/${image.id}`, 'DELETE', null, messages.removed);
    };

    // Returns whether the image was added or replaced; the picker closes then (VPM-01B).
    const choose = async (medium) => {
        if (locked()) return false;
        // VPM-01C: an image another slot has is not chosen (the picker disables it; the server refuses it with 409).
        if (choiceState(images, medium, target) === 'attached') { galleryFail('duplicate'); return false; }
        const plan = planChoice(images, { mode, target });
        if (plan.action === 'refuse') { galleryFail(plan.error); return false; }

        if (!editingId) {
            images = applyLocal(images, plan, medium); render(); picker.close(); clearGalleryError();
            status(plan.action === 'replace' ? messages.replaced : messages.added); return true;
        }

        const saved = plan.action === 'replace'
            // G7-C2-5: one PATCH with the new path: the count stays, the place and the main image are kept.
            ? await mutate(`${endpoint}/${editingId}/images/${plan.target.id}`, 'PATCH', { path: medium.path, alt_text: medium.alt_text ?? null }, messages.replaced)
            : await mutate(`${endpoint}/${editingId}/images`, 'POST', { path: medium.path, alt_text: medium.alt_text ?? null, is_primary: plan.primary }, messages.added);
        if (saved) picker.close();
        return saved;
    };

    // VPM-01B: the picker opens for the mode (main image, gallery image, or replacing `image`) and remembers the
    // button that opened it; when that button is gone after a redraw, focus goes back to the section's own button.
    const openMedia = (nextMode, image = null, from = null) => {
        if (locked()) return;
        mode = nextMode; target = image; clearGalleryError();
        if (!target && images.length >= MAX_IMAGES) { galleryFail('limit'); return; }
        picker.open({
            title: pickerTitle(messages, { mode, target }),
            from,
            fallback: () => (mode === 'primary' || galleryOpen.disabled ? primaryOpen : galleryOpen),
        });
    };

    // Uploads go to the vendor's media library (no product upload exists); the first new image is then chosen.
    const upload = async (files) => {
        if (locked()) return;
        const plan = uploadPlan(files);
        if (!plan.ok) { if (plan.error) galleryFail(plan.error); return; }
        const data = new FormData(); plan.files.forEach((file) => data.append('files[]', file));
        const ticket = session;
        clearGalleryError(); setBusy(true); status(messages.uploading);
        const answer = await fetchJson(editor.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: headers(false), body: data });
        // UI-01D: the files are in the library either way; nothing is attached to a product the editor has left.
        if (!tickets.isCurrent(ticket)) return;
        setBusy(false); status('');
        if (answer.status !== 201) { galleryFail(uploadError(answer)); return; }
        const media = Array.isArray(answer.body?.images) ? answer.body.images : [];
        // The new images are in the library either way; the picker shows them, and the first one is chosen.
        picker.refresh();
        if (media[0] && await choose(media[0])) status(`${messages.added} ${format(messages.uploaded, { count: media.length })}`);
    };

    const close = () => { begin(); editor.hidden = true; list.hidden = false; error.hidden = true; form.reset(); field('low_stock_threshold').value = '1'; setStatusField(field('status'), null); editingId = null; images = []; applyMode('editable'); clearGalleryError(); status(''); picker.close(); render(); title.textContent = texts.add; };
    const payload = () => ({ name: field('name').value.trim(), category_id: number('category_id'), price: number('price'), stock_quantity: number('stock_quantity'), sku: value('sku'), compare_at_price: number('compare_at_price'), low_stock_threshold: number('low_stock_threshold'), weight: number('weight'), short_description: value('short_description'), description: value('description'), status: statusPayload(field('status').value), images: editingId ? undefined : images.map(({ path, alt_text, sort_order, is_primary }) => ({ path, alt_text, sort_order, is_primary })) });
    window.addEventListener('vendor-resource:loaded', (event) => { if (event.detail.resource !== 'products') return; const selected = field('category_id').value; field('category_id').replaceChildren(new Option(texts.noCategory, '')); (event.detail.payload?.filters?.categories ?? []).forEach((category) => field('category_id').add(new Option(category.name, String(category.id)))); field('category_id').value = selected; });
    document.addEventListener('click', async (event) => { const button = event.target.closest('[data-product-action]'); if (!button) return; const id = button.dataset.productId; if (button.dataset.productAction === 'delete') { if (!window.confirm(texts.confirmDelete)) return; const response = await request(`${endpoint}/${id}`, 'DELETE'); if (!response.ok) { fail(texts.deleteFailed); showEditor(); return; } window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products', reason: 'deleted' } })); return; }
        // UI-01D: a new ticket while it loads (nothing can be changed meanwhile); only the latest product opened is shown.
        const ticket = begin(); setBusy(true);
        const answer = await load(id);
        if (!tickets.isCurrent(ticket)) return;
        setBusy(false);
        if (answer.status !== 200 || !answer.body?.product) return fail(texts.loadFailed);
        error.hidden = true; editingId = id; fill(answer.body); title.textContent = texts.edit; showEditor();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        // UI-01D: never in read-only mode, nor while a request of this product runs; an answer after a switch is dropped.
        if (locked()) return;
        error.hidden = true;
        const ticket = session;
        const answer = await send(editingId ? `${endpoint}/${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload());
        if (!tickets.isCurrent(ticket)) return;
        const failed = () => fail(saveErrorText(answer.status, answer.body, texts, messages));
        if (answer.status < 200 || answer.status >= 300) {
            if (editingId && mayBeArchivedRefusal(answer.status, answer.body)) return recheck(ticket, failed);
            return failed();
        }
        // VENDOR-PRODUCT-LIST-PAGINATION-01B: the list shows page 1 after a new product, the same page after a save.
        const reason = editingId ? 'saved' : 'created';
        close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products', reason } }));
    });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.onclick = close);
    picker = initPicker(editor.querySelector('[data-product-media-library]'), {
        url: editor.dataset.mediaLibraryUrl,
        messages,
        stateOf: (medium) => choiceState(images, medium, target),
        onChoose: choose,
        onUpload: upload,
    });
    primaryOpen.onclick = () => openMedia('primary', images[0] ?? null, primaryOpen);
    galleryOpen.onclick = () => openMedia('gallery', null, galleryOpen);
    const add = document.createElement('button'); add.type = 'button'; add.className = 'btn btn-primary'; add.textContent = texts.add; add.onclick = () => { close(); showEditor(); }; document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(add);
    applyMode('editable');
    render();
}

if (typeof document !== 'undefined') {
    const editor = document.querySelector('[data-product-editor]');

    if (editor) {
        initEditor(editor);
    }
}
