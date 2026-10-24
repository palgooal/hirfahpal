/*
 * HIRFAH admin media picker (AD-UI-CAT-M4-B), driving resources/views/components/admin/media-picker.blade.php.
 * Chooses one image from the media library (its JSON API, type=image), can upload into it, previews, replaces
 * and unlinks. It only ever writes two form fields; the server decides (CategoryManagementController).
 *
 * Every text comes from the component config (t()); server data (file names, URLs) only goes through
 * textContent and DOM properties, never HTML. The dialog is a native <dialog>: Escape closes it, focus stays
 * inside while it is open and returns to the button that opened it.
 */
import { format, formatBytes, interpretUploadResponse, request } from './admin-media/shared.js';

/** Where a list request ends up: {kind: 'items'|'forbidden'|'network'|'error', ...}. */
export function interpretListResponse({ status, body }) {
    if (status === 200 && Array.isArray(body?.data)) {
        return {
            kind: 'items',
            // Only images, whatever the server sends.
            items: body.data.filter((item) => item && item.file_type === 'image' && Number.isInteger(item.id)),
            page: body.current_page || 1,
            lastPage: body.last_page || 1,
        };
    }

    if (status === 403) {
        return { kind: 'forbidden' };
    }

    return { kind: status === 0 ? 'network' : 'error' };
}

/** The message shown for a list failure. */
export function listErrorText(kind, messages) {
    return { forbidden: messages.forbidden, network: messages.network }[kind] ?? messages.loadError;
}

/** The media item an upload answer selects: the last uploaded image, or null. */
export function uploadedItem(body) {
    const items = Array.isArray(body?.uploaded) ? body.uploaded : body?.id ? [body] : [];

    return items.filter((item) => item?.file_type === 'image' && Number.isInteger(item.id)).at(-1) ?? null;
}

/**
 * The form fields for a state of the picker. "remove" sends remove_image=1 with no id; a chosen image sends
 * its id and remove_image=0; "keep" leaves the current value.
 */
export function fieldValues(state) {
    if (state.removed) {
        return { value: '', remove: '1' };
    }

    return { value: state.id ? String(state.id) : '', remove: '0' };
}

const SUCCESS_TIMEOUT = 5000;

export function initMediaPicker(root) {
    const doc = root.ownerDocument;
    const $ = (selector) => root.querySelector(selector);
    const configElement = $('[data-picker-config]');
    const config = configElement ? JSON.parse(configElement.textContent) : null;

    const valueInput = $('[data-picker-value]');
    const removeInput = $('[data-picker-remove]');
    const image = $('[data-picker-image]');
    const placeholder = $('[data-picker-placeholder]');
    const nameLine = $('[data-picker-name]');
    const removedNote = $('[data-picker-removed-note]');
    const openButton = $('[data-picker-open]');
    const openLabel = $('[data-picker-open-label]');
    const removeButton = $('[data-picker-remove-button]');
    const undoButton = $('[data-picker-undo-remove]');

    // What the form shows and posts; initial values come from the server.
    const initial = { id: valueInput.value ? Number(valueInput.value) : null, src: image.getAttribute('src') || '', alt: image.alt, name: nameLine.textContent.trim() };
    const state = { id: initial.id, removed: removeInput.value === '1' };
    // From the element itself: without media.view the page has no picker config, and removing must still work.
    const noneText = nameLine.dataset.none ?? '';

    const showName = (name) => {
        nameLine.replaceChildren();

        if (name) {
            const bdi = doc.createElement('bdi');
            bdi.textContent = name;
            nameLine.append(bdi);
        } else {
            nameLine.textContent = noneText;
        }
    };

    const render = ({ src = '', alt = '', name = '' } = {}) => {
        const shown = Boolean(state.id) && !state.removed;
        const fields = fieldValues(state);
        valueInput.value = fields.value;
        removeInput.value = fields.remove;

        image.hidden = !shown;
        placeholder.hidden = shown;

        if (shown) {
            image.src = src;
            image.alt = alt;
            showName(name);
        } else {
            showName(null);
        }

        removedNote.hidden = !state.removed;
        removeButton && (removeButton.hidden = !shown);
        undoButton && (undoButton.hidden = !state.removed);

        if (openLabel) {
            openLabel.textContent = shown ? openLabel.dataset.replace : openLabel.dataset.choose;
        }
    };

    // A stale media row or a missing public-storage link must not leave a broken-image icon in the category form.
    image.addEventListener('error', () => {
        image.hidden = true;
        placeholder.hidden = false;
    });

    let current = { src: initial.src, alt: initial.alt, name: state.id && !state.removed ? initial.name : '' };

    removeButton?.addEventListener('click', () => {
        // Unlink only: the media item and its file stay in the library.
        state.removed = true;
        render();
        undoButton?.focus();
    });

    undoButton?.addEventListener('click', () => {
        state.removed = false;
        state.id = initial.id;
        current = { src: initial.src, alt: initial.alt, name: initial.name };
        render(current);
        removeButton?.focus();
    });

    if (!config || !openButton) {
        return { state };
    }

    // ---- Dialog ----
    const { urls, csrf, locale, perPage, can, messages } = config;
    const dialog = $('[data-picker-dialog]');
    const grid = $('[data-picker-grid]');
    const loading = $('[data-picker-loading]');
    const empty = $('[data-picker-empty]');
    const emptyText = $('[data-picker-empty-text]');
    const errorBox = $('[data-picker-error]');
    const errorText = $('[data-picker-error-text]');
    const more = $('[data-picker-more]');
    const search = $('[data-picker-search]');
    const alerts = $('[data-picker-alerts]');
    const selection = $('[data-picker-selection]');
    const confirmButton = $('[data-picker-confirm]');
    const uploadButton = $('[data-picker-upload]');
    const fileInput = $('[data-picker-file]');

    const list = { page: 1, lastPage: 1, search: '', loading: false, items: new Map() };
    let chosen = null;

    const headers = () => ({ Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' });
    const nameOf = (item) => item.file_original_name || item.file_name || `#${item.id}`;

    const notify = ({ tone, text, details = [] }) => {
        const alert = doc.createElement('div');
        alert.className = `admin-media-alert is-${tone}`;
        alert.setAttribute('role', tone === 'success' ? 'status' : 'alert');

        const icon = doc.createElement('i');
        icon.className = `ph-duotone ${tone === 'success' ? 'ph-check-circle' : 'ph-info'}`;
        icon.setAttribute('aria-hidden', 'true');

        const content = doc.createElement('div');
        content.className = 'admin-media-alert-content';
        const title = doc.createElement('p');
        title.textContent = text;
        content.append(title);

        if (details.length > 0) {
            const lines = doc.createElement('ul');
            details.forEach((detail) => {
                const entry = doc.createElement('li');
                entry.textContent = detail;
                lines.append(entry);
            });
            content.append(lines);
        }

        const close = doc.createElement('button');
        close.type = 'button';
        close.className = 'admin-media-icon-button';
        close.setAttribute('aria-label', messages.dismiss);
        const closeIcon = doc.createElement('i');
        closeIcon.className = 'ph-duotone ph-x';
        closeIcon.setAttribute('aria-hidden', 'true');
        close.append(closeIcon);
        close.addEventListener('click', () => alert.remove());

        alert.append(icon, content, close);
        alerts.prepend(alert);

        if (tone === 'success') {
            setTimeout(() => alert.remove(), SUCCESS_TIMEOUT);
        }
    };

    const choose = (item) => {
        chosen = item;
        grid.querySelectorAll('[data-id]').forEach((card) => {
            const selected = Number(card.dataset.id) === item?.id;
            card.classList.toggle('is-selected', selected);
            card.querySelector('button')?.setAttribute('aria-pressed', String(selected));
        });
        confirmButton.disabled = !item;
        selection.textContent = item ? format(messages.selected, { name: nameOf(item) }) : '';
    };

    const renderCard = (item) => {
        const name = nameOf(item);
        const card = doc.createElement('li');
        card.className = 'admin-media-card';
        card.dataset.id = String(item.id);

        const thumb = doc.createElement('div');
        thumb.className = 'admin-media-thumb';
        const button = doc.createElement('button');
        button.type = 'button';
        button.className = 'admin-media-open';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', format(messages.select, { name }));
        const img = doc.createElement('img');
        img.src = item.url || '';
        img.alt = '';
        img.loading = 'lazy';
        img.addEventListener('error', () => {
            img.remove();
            button.classList.add('admin-media-thumb-missing');
        });
        button.append(img);
        button.addEventListener('click', () => choose(item));
        button.addEventListener('dblclick', () => {
            choose(item);
            confirm();
        });
        thumb.append(button);

        const meta = doc.createElement('div');
        meta.className = 'admin-media-meta';
        const title = doc.createElement('bdi');
        title.className = 'admin-media-name';
        title.textContent = name;
        title.title = name;
        const facts = doc.createElement('span');
        facts.className = 'admin-media-facts-line';
        facts.textContent = formatBytes(item.size, locale);
        meta.append(title, facts);

        card.append(thumb, meta);

        return card;
    };

    const showState = (which) => {
        loading.hidden = which !== 'loading';
        empty.hidden = which !== 'empty';
        errorBox.hidden = which !== 'error';
        grid.setAttribute('aria-busy', which === 'loading' ? 'true' : 'false');
    };

    const load = async (page = 1) => {
        if (list.loading) {
            return;
        }

        list.loading = true;
        more.hidden = true;

        if (page === 1) {
            grid.replaceChildren();
            list.items.clear();
        }

        showState('loading');

        const params = new URLSearchParams({ page: String(page), per_page: String(perPage), type: 'image' });

        if (list.search) {
            params.set('search', list.search);
        }

        const outcome = interpretListResponse(await request(`${urls.index}?${params}`, { headers: headers() }));
        list.loading = false;

        if (outcome.kind !== 'items') {
            errorText.textContent = listErrorText(outcome.kind, messages);
            showState('error');

            return;
        }

        list.page = outcome.page;
        list.lastPage = outcome.lastPage;
        outcome.items.forEach((item) => {
            list.items.set(item.id, item);
            grid.append(renderCard(item));
        });

        if (chosen) {
            choose(list.items.get(chosen.id) ?? chosen);
        }

        if (list.items.size === 0) {
            emptyText.textContent = list.search ? messages.emptySearch : messages.empty;
            showState('empty');
        } else {
            showState('none');
        }

        more.hidden = list.page >= list.lastPage;
    };

    const confirm = () => {
        if (!chosen) {
            return;
        }

        state.id = chosen.id;
        state.removed = false;
        current = { src: chosen.url || '', alt: chosen.alt || '', name: nameOf(chosen) };
        render(current);
        dialog.close();
    };

    openButton.addEventListener('click', () => {
        chosen = null;
        confirmButton.disabled = true;
        selection.textContent = '';
        alerts.replaceChildren();
        dialog.showModal();
        load(1);
    });

    // Focus goes back to the button that opened the dialog, however it was closed (Escape included).
    dialog.addEventListener('close', () => openButton.focus());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    $('[data-picker-close]').addEventListener('click', () => dialog.close());
    $('[data-picker-cancel]').addEventListener('click', () => dialog.close());
    confirmButton.addEventListener('click', confirm);

    let debounce = null;
    search.addEventListener('keydown', (event) => {
        // The dialog sits inside the category form: Enter must not submit it.
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
    search.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            list.search = search.value.trim();
            load(1);
        }, 400);
    });
    $('[data-picker-search-clear]').addEventListener('click', () => {
        search.value = '';
        list.search = '';
        load(1);
        search.focus();
    });
    more.addEventListener('click', () => load(list.page + 1));
    $('[data-picker-retry]').addEventListener('click', () => load(list.items.size > 0 ? list.page + 1 : 1));

    // ---- Upload (media.create) ----
    if (can.upload && uploadButton && fileInput) {
        uploadButton.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files?.[0];
            fileInput.value = '';

            if (!file) {
                return;
            }

            const data = new FormData();
            data.append('files[]', file);
            const label = uploadButton.querySelector('span');
            const text = label.textContent;
            uploadButton.disabled = true;
            label.textContent = messages.uploading;

            const { status, body } = await request(urls.store, { method: 'POST', headers: headers(), body: data });

            uploadButton.disabled = false;
            label.textContent = text;

            const outcome = interpretUploadResponse({ status, body, fileNames: [file.name], messages });
            notify(outcome);

            if (outcome.refresh) {
                const item = uploadedItem(body);
                search.value = '';
                list.search = '';
                await load(1);

                if (item) {
                    choose(list.items.get(item.id) ?? item);
                }
            }
        });
    }

    return { state, load, choose, confirm };
}

function boot() {
    document.querySelectorAll('[data-admin-media-picker]').forEach((root) => initMediaPicker(root));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
