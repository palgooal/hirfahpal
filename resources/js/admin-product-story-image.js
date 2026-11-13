/*
 * HIRFAH admin product story image (PRODUCT-STORY-MEDIA, 7B-4F-2), loaded by
 * resources/views/dashboard/products/form.blade.php with its chooser partials/story-image-dialog.blade.php.
 *
 * One image of the product vendor's own media library, chosen in a native modal dialog that reads the gallery's
 * read-only endpoints (dashboard.products.vendor-media.create / .edit, scoped to the vendor on the server): no
 * upload, no deletion, no path. The field posts two hidden values, never both set: story_image_media_id (a new
 * choice) and remove_story_image (1 clears the stored image); both empty keep it. The server resolves the id in
 * the vendor's library again (ProductManagementController::storyImagePath()).
 *
 * The chooser reuses the gallery's helpers (loader, list URL, choices, error texts) from the shared
 * admin-media/vendor-image-chooser.js, never from admin-product-gallery.js: a page script built from another entry
 * would carry its own copy of that entry, start-up included, so the gallery would be set up twice. This field keeps
 * its own ids and state.
 *
 * Every text comes from the page config (t(), dashboard.Products_* keys); server data only goes through textContent
 * and attributes, never HTML.
 */

import { format, request } from './admin-media/shared.js';
import { createLoader, emptyChoices, errorText, mediaListUrl, renderChoice, safeUrl } from './admin-media/vendor-image-chooser.js';

const SEARCH_DELAY = 400;

// ---------------------------------------------------------------------------------------------
// State (pure: each returns a new object)
// ---------------------------------------------------------------------------------------------

/** The starting state from the page: the stored image, and the choice or removal a refused save brought back. */
export function initialState(config = {}) {
    const chosen = config.chosen && Number.isInteger(config.chosen.id) && config.chosen.id > 0 ? { ...config.chosen } : null;
    const current = config.current && typeof config.current === 'object' ? { ...config.current } : null;

    return { current, chosen, removed: chosen === null && current !== null && config.removed === true };
}

/** The image the field shows: the new choice, else the stored one unless it is being removed. */
export const shownImage = (state) => state.chosen ?? (state.removed ? null : state.current);

/** The two posted values, never both set: an id for a new choice, 1 to clear the stored image, else both empty. */
export function storyFields(state) {
    if (state.chosen) {
        return { mediaId: String(state.chosen.id), remove: '' };
    }

    return { mediaId: '', remove: state.removed && state.current ? '1' : '' };
}

/** A chooser image (the gallery's item: ref "media:{id}", url, name) becomes the choice. */
export function chooseImage(state, item) {
    const id = Number(String(item?.ref ?? '').replace(/^media:/, ''));

    if (!Number.isInteger(id) || id < 1) {
        return state;
    }

    return { ...state, chosen: { id, url: item.url ?? '', name: item.name ?? `#${id}` }, removed: false };
}

/** No story image: a new choice is dropped and the stored image, if any, is marked for removal. */
export const removeImage = (state) => ({ ...state, chosen: null, removed: state.current !== null });

/** A new product's vendor changed: a choice from the previous vendor's library is dropped. */
export const clearChoice = (state) => (state.chosen ? { ...state, chosen: null } : state);

// ---------------------------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------------------------

export function initStoryImage(root, config, win = window) {
    const doc = root.ownerDocument;
    const { editing, mediaUrl, perPage = 24, labels = {}, messages = {} } = config;
    const $ = (id) => doc.getElementById(id);
    const base = win.location.href;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const preview = $('product-story-image-preview');
    const thumb = $('product-story-image-thumb');
    const name = $('product-story-image-name');
    const empty = $('product-story-image-empty');
    const actions = $('product-story-image-actions');
    const chooseButton = $('product-story-image-choose');
    const removeButton = $('product-story-image-remove');
    const vendorFirst = $('product-story-image-vendor-first');
    const mediaInput = $('product-story-image-media-id');
    const removeInput = $('product-story-image-remove-flag');
    const status = $('product-story-image-status');
    const vendor = editing ? null : $('product-vendor');

    let state = initialState(config);

    const vendorId = () => (editing ? '' : String(vendor?.value ?? ''));
    const canChoose = () => editing || /^[1-9][0-9]{0,18}$/.test(vendorId());
    const announce = (text) => {
        status.textContent = '';
        status.textContent = text;
    };

    const render = () => {
        const shown = shownImage(state);
        preview.hidden = !shown;
        empty.hidden = Boolean(shown);

        const url = shown ? safeUrl(shown.url, base) : '';

        if (url) {
            const image = doc.createElement('img');
            image.src = url;
            image.alt = shown.name ?? '';
            image.loading = 'lazy';
            thumb.replaceChildren(image);
        } else {
            const icon = doc.createElement('i');
            icon.className = 'ph-duotone ph-image-broken';
            icon.setAttribute('aria-hidden', 'true');
            thumb.replaceChildren(icon);
        }

        name.textContent = shown?.name ?? '';
        chooseButton.querySelector('span').textContent = shown ? labels.replace : labels.choose;
        chooseButton.disabled = !canChoose();
        removeButton.hidden = !shown;
        vendorFirst.hidden = canChoose();

        const fields = storyFields(state);
        mediaInput.value = fields.mediaId;
        removeInput.value = fields.remove;
    };

    // ---- The chooser ----
    const dialog = $('product-story-dialog');
    const title = $('product-story-dialog-title');
    const search = $('product-story-search');
    const choices = $('product-story-choices');
    const loading = $('product-story-loading');
    const choicesEmpty = $('product-story-choices-empty');
    const choicesEmptyText = $('product-story-choices-empty-text');
    const error = $('product-story-error');
    const errorTextNode = $('product-story-error-text');
    const retry = $('product-story-retry');
    const more = $('product-story-more');
    let opener = null;

    const showState = (which) => {
        loading.hidden = which !== 'loading';
        choicesEmpty.hidden = which !== 'empty';
        error.hidden = which !== 'error';
        choices.setAttribute('aria-busy', which === 'loading' ? 'true' : 'false');
    };

    const choose = (item) => {
        const replaced = shownImage(state);
        state = chooseImage(state, item);
        dialog.close();
        render();
        announce(format(replaced ? labels.replaced : labels.added, { name: replaced?.name ?? item.name }));
        chooseButton.focus();
    };

    const loader = createLoader({
        fetchList: (query, page, signal) => {
            const url = mediaListUrl(mediaUrl, { editing, vendorId: query.vendorId, page, perPage, search: query.search });

            return url ? request(url, { headers, signal }) : Promise.resolve({ status: 422, body: null });
        },
        onChange: (event) => {
            if (event.type === 'reset') {
                choices.replaceChildren();
            }

            if (event.type === 'loading') {
                more.hidden = true;
                showState('loading');
            }

            if (event.type === 'loaded') {
                event.added.forEach((media) => {
                    const taken = state.chosen?.id === media.id;
                    choices.append(renderChoice(doc, media, { messages, base, taken, onChoose: choose }));
                });

                choicesEmptyText.textContent = emptyChoices(loader.state.query.search, messages).text;
                showState(loader.state.items.length === 0 ? 'empty' : 'none');
                more.hidden = !loader.hasMore;
            }

            if (event.type === 'error') {
                errorTextNode.textContent = errorText(event.error, messages);
                retry.hidden = !(event.error === 'network' || event.error === 'failed');
                showState('error');
            }
        },
    });

    const openChooser = () => {
        if (!canChoose()) {
            return;
        }

        opener = chooseButton;
        title.textContent = shownImage(state) ? messages.dialogReplaceTitle : messages.dialogTitle;
        search.value = '';
        loader.reset({ vendorId: vendorId(), search: '' });
        dialog.showModal();
        search.focus();
    };

    dialog.addEventListener('close', () => {
        loader.stop();
        opener?.isConnected && doc.activeElement === doc.body && opener.focus();
        opener = null;
    });
    // A chooser, like the gallery's: the backdrop, Close and Escape (native) close it; nothing is lost.
    dialog.addEventListener('click', (event) => event.target === dialog && dialog.close());
    $('product-story-dialog-close').addEventListener('click', () => dialog.close());
    $('product-story-dialog-cancel').addEventListener('click', () => dialog.close());

    let timer = null;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loader.reset({ vendorId: vendorId(), search: search.value.trim() }), SEARCH_DELAY);
    });
    more.addEventListener('click', () => loader.more());
    retry.addEventListener('click', () => loader.retry());
    chooseButton.addEventListener('click', openChooser);

    removeButton.addEventListener('click', () => {
        const removed = shownImage(state);
        state = removeImage(state);
        render();
        announce(format(labels.removed, { name: removed?.name ?? '' }));
        chooseButton.focus();
    });

    // A new product: the image belongs to the chosen vendor, so another vendor drops the choice.
    vendor?.addEventListener('change', () => {
        const before = state;
        state = clearChoice(state);
        render();

        if (state !== before) {
            announce(labels.cleared);
        }
    });

    actions.hidden = false;
    render();

    return {
        get state() {
            return state;
        },
        loader,
        open: openChooser,
    };
}

function boot() {
    const root = document.querySelector('[data-product-story-image]');
    const config = document.getElementById('product-story-image-config');

    if (root && config) {
        initStoryImage(root, JSON.parse(config.textContent));
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
