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
                    : status === 422 && 'images' in errors ? 'limit'
                        : status === 422 && 'path' in errors ? 'library'
                            : status === 422 ? 'invalid'
                                : 'failed';

    return { ok: false, error };
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
export function renderImageCard(doc, image, { index, isPrimary, url, messages, onChange, onSetMain, onRemove }) {
    const nameId = `vendor-product-image-${index}-name`;
    const label = typeof image.alt_text === 'string' && image.alt_text.trim() !== '' ? image.alt_text.trim() : messages.imageAlt;

    const card = doc.createElement('li');
    card.className = isPrimary ? 'vendor-product-gallery-item is-main' : 'vendor-product-gallery-item';
    if (image.id !== undefined && image.id !== null) card.dataset.imageId = String(image.id);

    const thumb = doc.createElement('div');
    thumb.className = 'vendor-product-gallery-thumb';
    const pic = doc.createElement('img');
    pic.src = url;
    pic.alt = label;
    pic.loading = 'lazy';
    thumb.append(pic);

    const meta = doc.createElement('div');
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

    const actions = doc.createElement('div');
    actions.className = 'vendor-product-gallery-actions';
    const button = (action, text, iconName, onClick) => {
        const node = doc.createElement('button');
        node.type = 'button';
        node.className = 'btn btn-sm btn-light-secondary';
        node.dataset.action = action;
        node.setAttribute('aria-describedby', nameId);
        const caption = doc.createElement('span');
        caption.textContent = text;
        node.append(icon(doc, iconName), caption);
        node.onclick = onClick;
        actions.append(node);
    };

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
 * below). An image already on the product says so; it stays selectable (duplicates are a later phase).
 */
export function renderChoice(doc, medium, { messages, base, taken, disabled = false, onChoose }) {
    const label = textOf(medium.alt_text) || messages.mediaAlt;
    const url = safeUrl(medium.url, base);
    const card = doc.createElement('li');
    card.className = taken ? 'vendor-media-card is-taken' : 'vendor-media-card';
    card.dataset.id = String(medium.id ?? '');

    const thumb = doc.createElement('div');
    thumb.className = 'vendor-media-thumb';
    const choose = doc.createElement('button');
    choose.type = 'button';
    choose.className = 'vendor-media-open';
    choose.setAttribute('aria-label', format(messages.choose, { name: label }));
    choose.disabled = disabled;
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

    if (taken) {
        const note = doc.createElement('span');
        note.className = 'vendor-product-picker-taken';
        note.id = `vendor-product-picker-taken-${card.dataset.id}`;
        note.textContent = messages.attached;
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
export function initPicker(root, { url, messages, isTaken, onChoose, onUpload }, win = window) {
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
            ui.grid.replaceChildren(...state.images.map((medium) => renderChoice(doc, medium, { messages, base, taken: isTaken(medium), disabled: busy, onChoose })));
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
            ui.grid.querySelectorAll?.('button').forEach((button) => { button.disabled = state; });
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
    let editingId = null, images = [], mode = 'gallery', target = null, busy = false, picker = null;
    const field = (name) => form.elements.namedItem(name);
    const value = (name) => field(name).value.trim() || null;
    const number = (name) => field(name).value === '' ? null : Number(field(name).value);
    const headers = (json) => ({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, ...(json ? { 'Content-Type': 'application/json' } : {}) });
    const request = (url, method = 'GET', body = null) => fetch(url, { method, credentials: 'same-origin', headers: headers(Boolean(body)), ...(body ? { body: JSON.stringify(body) } : {}) });
    const send = (url, method, body = null) => fetchJson(url, { method, credentials: 'same-origin', headers: headers(Boolean(body)), ...(body ? { body: JSON.stringify(body) } : {}) });
    const fail = (text) => { error.textContent = text; error.hidden = false; };
    const imageUrl = (image) => image.url || `/storage/${image.path}`;
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
        gallery.querySelectorAll('button').forEach((button) => { button.disabled = state || (button.dataset.limited === 'true' && images.length >= MAX_IMAGES); });
        picker?.setBusy(state);
    };

    const render = () => {
        images = orderImages(images);
        const primary = images[0] ?? null;
        // VPM-01A: the admin gallery's cards in a list, or its dashed empty note. Same actions as before.
        const card = (image, index) => renderImageCard(document, image, {
            index, isPrimary: index === 0, url: imageUrl(image), messages,
            onChange: (event) => openMedia(index === 0 ? 'primary' : 'replace', image, event?.currentTarget ?? null),
            onSetMain: () => setPrimary(image),
            onRemove: () => removeImage(image),
        });
        const listOf = (cards) => { const node = document.createElement('ol'); node.className = 'vendor-product-gallery-list'; node.append(...cards); return node; };
        const empty = (text) => { const node = document.createElement('p'); node.className = 'vendor-product-gallery-empty'; node.textContent = text; return node; };
        primaryBox().replaceChildren(primary ? listOf([card(primary, 0)]) : empty(messages.noMain));
        galleryBox().replaceChildren(images.length > 1 ? listOf(images.slice(1).map((image, at) => card(image, at + 1))) : empty(messages.noGallery));
        galleryCount.textContent = format(messages.count, { count: images.length, max: MAX_IMAGES });
        setBusy(busy);
    };

    // One saved-product request: busy while it runs; the gallery changes only from a successful answer.
    const mutate = async (url, method, body, done) => {
        clearGalleryError(); setBusy(true); status(messages.saving);
        const outcome = interpretImageResponse(await send(url, method, body));
        setBusy(false);
        if (!outcome.ok) { galleryFail(outcome.error); return false; }
        images = outcome.images; render(); status(done);
        return true;
    };

    const setPrimary = async (image) => {
        if (busy) return;
        if (!editingId) { images = setLocalPrimary(images, image); render(); status(messages.mainChanged); return; }
        await mutate(`${endpoint}/${editingId}/images/${image.id}`, 'PATCH', { is_primary: true }, messages.mainChanged);
    };

    const removeImage = async (image) => {
        if (busy || !window.confirm(messages.confirmRemove)) return;
        if (!editingId || !image.id) { images = removeLocal(images, image); render(); status(messages.removed); return; }
        await mutate(`${endpoint}/${editingId}/images/${image.id}`, 'DELETE', null, messages.removed);
    };

    // Returns whether the image was added or replaced; the picker closes then (VPM-01B).
    const choose = async (medium) => {
        if (busy) return false;
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
        if (busy) return;
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
        if (busy) return;
        const plan = uploadPlan(files);
        if (!plan.ok) { if (plan.error) galleryFail(plan.error); return; }
        const data = new FormData(); plan.files.forEach((file) => data.append('files[]', file));
        clearGalleryError(); setBusy(true); status(messages.uploading);
        const answer = await fetchJson(editor.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: headers(false), body: data });
        setBusy(false); status('');
        if (answer.status !== 201) { galleryFail(uploadError(answer)); return; }
        const media = Array.isArray(answer.body?.images) ? answer.body.images : [];
        // The new images are in the library either way; the picker shows them, and the first one is chosen.
        picker.refresh();
        if (media[0] && await choose(media[0])) status(`${messages.added} ${format(messages.uploaded, { count: media.length })}`);
    };

    const close = () => { editor.hidden = true; list.hidden = false; error.hidden = true; form.reset(); field('low_stock_threshold').value = '1'; editingId = null; images = []; clearGalleryError(); status(''); picker.close(); render(); title.textContent = texts.add; };
    const payload = () => ({ name: field('name').value.trim(), category_id: number('category_id'), price: number('price'), stock_quantity: number('stock_quantity'), sku: value('sku'), compare_at_price: number('compare_at_price'), low_stock_threshold: number('low_stock_threshold'), weight: number('weight'), short_description: value('short_description'), description: value('description'), images: editingId ? undefined : images.map(({ path, alt_text, sort_order, is_primary }) => ({ path, alt_text, sort_order, is_primary })) });
    window.addEventListener('vendor-resource:loaded', (event) => { if (event.detail.resource !== 'products') return; const selected = field('category_id').value; field('category_id').replaceChildren(new Option(texts.noCategory, '')); (event.detail.payload?.filters?.categories ?? []).forEach((category) => field('category_id').add(new Option(category.name, String(category.id)))); field('category_id').value = selected; });
    document.addEventListener('click', async (event) => { const button = event.target.closest('[data-product-action]'); if (!button) return; const id = button.dataset.productId; if (button.dataset.productAction === 'delete') { if (!window.confirm(texts.confirmDelete)) return; const response = await request(`${endpoint}/${id}`, 'DELETE'); if (!response.ok) { fail(texts.deleteFailed); showEditor(); return; } window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); return; } const response = await request(`${endpoint}/${id}`); if (!response.ok) return fail(texts.loadFailed); const product = (await response.json()).product; form.reset(); ['name', 'category_id', 'price', 'stock_quantity', 'sku', 'compare_at_price', 'low_stock_threshold', 'weight', 'short_description', 'description'].forEach((name) => { field(name).value = product[name] ?? ''; }); editingId = id; images = product.images || []; clearGalleryError(); status(''); picker.close(); render(); title.textContent = texts.edit; showEditor(); });
    form.addEventListener('submit', async (event) => { event.preventDefault(); error.hidden = true; const response = await request(editingId ? `${endpoint}/${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload()); if (!response.ok) { const data = await response.json().catch(() => null); fail(saveErrorText(response.status, data, texts, messages)); return; } close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.onclick = close);
    picker = initPicker(editor.querySelector('[data-product-media-library]'), {
        url: editor.dataset.mediaLibraryUrl,
        messages,
        isTaken: (medium) => isAttached(images, medium),
        onChoose: choose,
        onUpload: upload,
    });
    primaryOpen.onclick = () => openMedia('primary', images[0] ?? null, primaryOpen);
    galleryOpen.onclick = () => openMedia('gallery', null, galleryOpen);
    const add = document.createElement('button'); add.type = 'button'; add.className = 'btn btn-primary'; add.textContent = texts.add; add.onclick = () => { close(); showEditor(); }; document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(add);
    render();
}

if (typeof document !== 'undefined') {
    const editor = document.querySelector('[data-product-editor]');

    if (editor) {
        initEditor(editor);
    }
}
