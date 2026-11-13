/*
 * HIRFAH admin product gallery (PRODUCT-MEDIA-01 P5-C), loaded by resources/views/dashboard/products/form.blade.php
 * with the partials gallery.blade.php (the field) and gallery-dialog.blade.php (the chooser).
 *
 * An ordered list of up to six images from the product vendor's own media library; the first is the main image.
 * P5-HARDEN-01: a product saved elsewhere with more than six images shows all of them, never a truncated list (a
 * truncated list would unlink the rest on save). Above the limit nothing can be added, removing and moving stay
 * available, a warning says so, and the server refuses the list until it has six or fewer.
 * The admin adds, replaces, removes and moves images with buttons (keyboard first, no drag only). Nothing is uploaded
 * or deleted here: removing an image only unlinks it, and the chooser reads dashboard.products.vendor-media.* (read
 * only, scoped on the server to the product's vendor). Once the list changes, the form carries the P5-B contract:
 * gallery_sync=1 and gallery[] ("existing:{id}" / "media:{id}") in order, with gallery_fingerprint when editing; the
 * server checks every reference and refuses a list that changed meanwhile.
 *
 * Every text comes from the page config (t(), keys dashboard.Products_Gallery_*); server data only goes through
 * textContent and attributes, never HTML, and a server message is never shown as is.
 */

import { format, request } from './admin-media/shared.js';
// 7B-4F-2A: the chooser helpers live in one shared module (also used by the story image); same names re-exported.
import {
    createLoader,
    emptyChoices,
    errorText,
    interpretList,
    itemFromMedia,
    mediaListUrl,
    renderChoice,
    safeUrl,
} from './admin-media/vendor-image-chooser.js';

export { format };
export { createLoader, emptyChoices, errorText, interpretList, itemFromMedia, mediaListUrl, renderChoice, safeUrl };

const SEARCH_DELAY = 400;

const textOf = (value) => (typeof value === 'string' ? value.trim() : '');

// ---------------------------------------------------------------------------------------------
// The list (pure: each returns a new array, the input is never changed)
// ---------------------------------------------------------------------------------------------

/** Two items are the same image when they have the same reference or show the same file. */
export const sameImage = (a, b) => a.ref === b.ref || (Boolean(a.url) && a.url === b.url);

/** Adds an item at the end, unless the list is full or already has that image. */
export function addItem(items, item, max) {
    return items.length >= max || items.some((other) => sameImage(other, item)) ? items : [...items, item];
}

/** Puts an item in place of the one at index, unless another position already has that image. */
export function replaceItem(items, index, item) {
    if (index < 0 || index >= items.length || items.some((other, at) => at !== index && sameImage(other, item))) {
        return items;
    }

    return items.map((current, at) => (at === index ? item : current));
}

export function removeItem(items, index) {
    return index < 0 || index >= items.length ? items : items.filter((_, at) => at !== index);
}

/** Moves the item at index by delta (-1 up, +1 down); out of range does nothing. */
export function moveItem(items, index, delta) {
    const target = index + delta;

    if (index < 0 || index >= items.length || target < 0 || target >= items.length) {
        return items;
    }

    const next = [...items];
    [next[index], next[target]] = [next[target], next[index]];

    return next;
}

/** The starting list, every image kept: never cut to the limit, so a save cannot drop the ones past it. */
export const initialItems = (items) => (Array.isArray(items) ? [...items] : []);

/** What the limit allows: adding below it only, the full note at it, the over note above it. */
export const limitState = (count, max) => ({ canAdd: count < max, full: count === max, over: count > max });

/** The P5-B references, in order. */
export const galleryRefs = (items) => items.map((item) => item.ref);

// ---------------------------------------------------------------------------------------------
// URLs and answers
// ---------------------------------------------------------------------------------------------

/** What an image shows to screen readers: its alternative text, else its name. */
export const altText = (item) => textOf(item.alt) || textOf(item.name);

// ---------------------------------------------------------------------------------------------
// Rendering (DOM APIs only)
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

/** One gallery image with its actions; the buttons are described by the image name and position. */
export function renderItem(doc, item, index, count, { messages, base, idPrefix = 'product-gallery-item' }) {
    const nameId = `${idPrefix}-${index}-name`;
    const card = element(doc, 'li', 'admin-product-gallery-item');
    card.dataset.ref = item.ref;

    const thumb = element(doc, 'div', 'admin-product-gallery-thumb');
    const url = safeUrl(item.url, base);

    if (url) {
        const image = element(doc, 'img');
        image.src = url;
        image.alt = altText(item);
        image.loading = 'lazy';
        thumb.append(image);
    } else {
        thumb.append(icon(doc, 'ph-image-broken'));
    }

    const meta = element(doc, 'div', 'admin-product-gallery-meta');
    const name = element(doc, 'bdi', 'admin-product-gallery-name', item.name);
    name.id = nameId;
    const position = element(doc, 'span', 'admin-product-gallery-position', format(messages.position, { position: index + 1 }));
    meta.append(position, name);

    if (index === 0) {
        meta.append(element(doc, 'span', 'admin-product-gallery-badge is-main', messages.main));
    }

    if (item.legacy) {
        meta.append(element(doc, 'span', 'admin-product-gallery-badge is-legacy', messages.legacy));
    }

    const actions = element(doc, 'div', 'admin-product-gallery-actions');
    const button = (action, label, iconName, disabled = false) => {
        const node = element(doc, 'button', 'btn btn-sm btn-light-secondary');
        node.type = 'button';
        node.dataset.action = action;
        node.disabled = disabled;
        node.setAttribute('aria-describedby', nameId);
        node.append(icon(doc, iconName), element(doc, 'span', '', label));

        return node;
    };

    actions.append(
        button('up', messages.moveUp, 'ph-arrow-up', index === 0),
        button('down', messages.moveDown, 'ph-arrow-down', index === count - 1),
        button('replace', messages.replace, 'ph-swap'),
        button('remove', messages.remove, 'ph-x'),
    );

    card.append(thumb, meta, actions);

    return card;
}

// ---------------------------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------------------------

export function initProductGallery(root, config, win = window) {
    const doc = root.ownerDocument;
    const { editing, max, mediaUrl, perPage, messages } = config;
    const $ = (id) => doc.getElementById(id);
    const base = win.location.href;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const list = $('product-gallery-list');
    const count = $('product-gallery-count');
    const empty = $('product-gallery-empty');
    const add = $('product-gallery-add');
    const full = $('product-gallery-full');
    const over = $('product-gallery-over');
    const vendorFirst = $('product-gallery-vendor-first');
    const inputs = $('product-gallery-inputs');
    const status = $('product-gallery-status');
    const vendor = editing ? null : $('product-vendor');

    let items = initialItems(config.items);
    let dirty = Boolean(config.restored);

    const vendorId = () => (editing ? '' : String(vendor?.value ?? ''));
    const canChoose = () => editing || /^[1-9][0-9]{0,18}$/.test(vendorId());
    const announce = (text) => {
        status.textContent = '';
        status.textContent = text;
    };

    // The P5-B fields, only once the list changed: an untouched list leaves the product's images alone.
    const writeInputs = () => {
        if (!dirty) {
            inputs.replaceChildren();

            return;
        }

        const hidden = (name, value) => {
            const node = doc.createElement('input');
            node.type = 'hidden';
            node.name = name;
            node.value = value;

            return node;
        };

        inputs.replaceChildren(hidden('gallery_sync', '1'), ...galleryRefs(items).map((ref) => hidden('gallery[]', ref)));
    };

    const render = () => {
        list.replaceChildren(...items.map((item, index) => renderItem(doc, item, index, items.length, { messages, base })));
        count.textContent = format(messages.count, { count: items.length, max });
        empty.hidden = items.length > 0;
        add.hidden = false;
        const limit = limitState(items.length, max);
        add.disabled = !limit.canAdd || !canChoose();
        full.hidden = !limit.full;
        over.hidden = !limit.over;
        over.textContent = limit.over ? format(messages.overMax, { count: items.length, max }) : '';
        vendorFirst.hidden = canChoose();
        writeInputs();
    };

    const change = (next, text, focus) => {
        if (next === items) {
            return;
        }

        items = next;
        dirty = true;
        render();
        announce(text);
        focus?.();
    };

    // Keeps the keyboard where it was: the same action on the moved image, else the next sensible control.
    const focusAction = (ref, action) => {
        const card = [...list.children].find((node) => node.dataset.ref === ref);
        const button = card?.querySelector(`[data-action="${action}"]`);
        (button && !button.disabled ? button : card?.querySelector('[data-action]:not([disabled])') ?? add)?.focus();
    };

    list.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-action]');
        const card = button?.closest('[data-ref]');

        if (!button || !card || button.disabled) {
            return;
        }

        const index = items.findIndex((item) => item.ref === card.dataset.ref);
        const item = items[index];
        const action = button.dataset.action;

        if (action === 'up' || action === 'down') {
            const next = moveItem(items, index, action === 'up' ? -1 : 1);
            change(next, format(messages.moved, { name: item.name, position: index + (action === 'up' ? 0 : 2) }), () => focusAction(item.ref, action));
        } else if (action === 'remove') {
            const next = removeItem(items, index);
            const neighbour = next[index] ?? next[index - 1];
            change(next, format(messages.removed, { name: item.name }), () => (neighbour ? focusAction(neighbour.ref, 'remove') : add.focus()));
        } else if (action === 'replace') {
            openChooser(index, button);
        }
    });

    // ---- The chooser ----
    const dialog = $('product-gallery-dialog');
    const title = $('product-gallery-dialog-title');
    const search = $('product-gallery-search');
    const choices = $('product-gallery-choices');
    const loading = $('product-gallery-loading');
    const choicesEmpty = $('product-gallery-choices-empty');
    const choicesEmptyText = $('product-gallery-choices-empty-text');
    const choicesEmptyHint = $('product-gallery-choices-empty-hint');
    const error = $('product-gallery-error');
    const errorTextNode = $('product-gallery-error-text');
    const retry = $('product-gallery-retry');
    const more = $('product-gallery-more');
    let target = null;
    let opener = null;

    const showState = (which) => {
        loading.hidden = which !== 'loading';
        choicesEmpty.hidden = which !== 'empty';
        error.hidden = which !== 'error';
        choices.setAttribute('aria-busy', which === 'loading' ? 'true' : 'false');
    };

    const choose = (item) => {
        if (target === null) {
            const next = addItem(items, item, max);
            dialog.close();
            change(next, format(messages.added, { name: item.name }), () => focusAction(item.ref, 'remove'));
        } else {
            const replaced = items[target];
            const next = replaceItem(items, target, item);
            dialog.close();
            change(next, format(messages.replaced, { name: replaced.name }), () => focusAction(item.ref, 'replace'));
        }
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
                    const candidate = itemFromMedia(media, base);
                    const taken = items.some((item, at) => at !== target && sameImage(item, candidate));
                    choices.append(renderChoice(doc, media, { messages, base, taken, onChoose: choose }));
                });

                const empty = emptyChoices(loader.state.query.search, messages);
                choicesEmptyText.textContent = empty.text;
                choicesEmptyHint.hidden = !empty.hint;
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

    function openChooser(index, from) {
        if (!canChoose()) {
            return;
        }

        target = index;
        opener = from;
        title.textContent = index === null ? messages.dialogTitle : messages.dialogReplaceTitle;
        search.value = '';
        loader.reset({ vendorId: vendorId(), search: '' });
        dialog.showModal();
        search.focus();
    }

    dialog.addEventListener('close', () => {
        loader.stop();
        opener?.isConnected && doc.activeElement === doc.body && opener.focus();
        opener = null;
    });
    dialog.addEventListener('click', (event) => event.target === dialog && dialog.close());
    $('product-gallery-dialog-close').addEventListener('click', () => dialog.close());
    $('product-gallery-dialog-cancel').addEventListener('click', () => dialog.close());

    let timer = null;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loader.reset({ vendorId: vendorId(), search: search.value.trim() }), SEARCH_DELAY);
    });
    more.addEventListener('click', () => loader.more());
    retry.addEventListener('click', () => loader.retry());
    add.addEventListener('click', () => openChooser(null, add));

    // A new product: the images belong to the chosen vendor, so another vendor clears them.
    vendor?.addEventListener('change', () => {
        if (items.length > 0) {
            change([], messages.cleared);
        } else {
            render();
        }
    });

    render();

    return { get items() { return items; }, loader };
}

function boot() {
    const root = document.querySelector('[data-product-gallery]');
    const config = document.getElementById('product-gallery-config');

    if (root && config) {
        initProductGallery(root, JSON.parse(config.textContent));
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
