/*
 * HIRFAH admin media library page (AD-UI-CAT-M3), loaded by the published view
 * resources/views/vendor/media-library/media.blade.php instead of the package's
 * public/vendor/media-library/js/media-library.js, which stays untouched (so republishing
 * the package assets never changes this behaviour).
 *
 * Every text comes from the page config (t(), keys dashboard.Media_*); there is no
 * hard-coded wording here. The interpret* functions turn a server answer into what the
 * page shows and are pure, so they are tested on their own (tests/js). A refused request
 * is never shown as a success, and nothing is removed from the page unless the server
 * deleted it.
 */

import { detailText, format, formatBytes, formatDate, interpretUploadResponse, listParts, request, validationMessages } from './admin-media/shared.js';

// Re-exported for the tests and any caller of this module (AD-UI-CAT-M4-B moved them to admin-media/shared.js).
export { detailText, format, formatBytes, formatDate, interpretUploadResponse, listParts, validationMessages };

/**
 * The outcome of a delete request (single or bulk).
 *
 * @param {{status: number, body: object|null, bulk: boolean, names?: Map<number, string>, messages: object}} input
 * @returns {{tone: 'success'|'warning'|'error', text: string, details: Array<string|{template: string, names: string[]}>, refresh: boolean, inUseIds: number[]}}
 */
export function interpretDeleteResponse({ status, body, bulk, names = new Map(), messages }) {
    const outcome = { tone: 'error', text: messages.deleteFailed, details: [], refresh: false, inUseIds: [] };

    if (status === 200) {
        const deleted = Number.isInteger(body?.deleted) ? body.deleted : null;

        outcome.tone = 'success';
        outcome.refresh = true;
        // Bulk: the number the server actually deleted, never the number requested.
        outcome.text = bulk ? format(messages.deletedCount, { count: deleted ?? 0 }) : messages.deleted;

        if (Array.isArray(body?.file_cleanup_failed) && body.file_cleanup_failed.length > 0) {
            outcome.tone = 'warning';
            outcome.details.push(messages.cleanupWarning);
        }

        return outcome;
    }

    if (status === 409) {
        outcome.inUseIds = Array.isArray(body?.in_use) ? body.in_use.filter(Number.isInteger) : [];
        outcome.text = typeof body?.message === 'string' && body.message !== '' ? body.message : messages.inUse;

        if (bulk && outcome.inUseIds.length > 0) {
            // Kept structured: the page renders each name in its own <bdi> (see renderDetail).
            outcome.details.push({ template: messages.inUseItems, names: outcome.inUseIds.map((id) => names.get(id) ?? `#${id}`) });
        }

        return outcome;
    }

    if (status === 404 && !bulk) {
        // Gone already (another admin deleted it): reload instead of claiming this request deleted it.
        return { ...outcome, tone: 'warning', text: messages.alreadyDeleted, refresh: true };
    }

    if (status === 403) {
        return { ...outcome, text: messages.forbidden };
    }

    if (status === 422) {
        return { ...outcome, details: validationMessages(body) };
    }

    return outcome;
}

/** The outcome of saving the details form; field errors stay with their fields. */
export function interpretSaveResponse({ status, body, messages }) {
    if (status === 200) {
        return { tone: 'success', text: messages.saved, fieldErrors: {}, refresh: true };
    }

    if (status === 403) {
        return { tone: 'error', text: messages.forbidden, fieldErrors: {}, refresh: false };
    }

    const fieldErrors = status === 422 && body?.errors ? Object.fromEntries(Object.entries(body.errors).map(([field, list]) => [field, [].concat(list)[0]])) : {};

    return { tone: 'error', text: messages.saveFailed, fieldErrors, refresh: false };
}

// ---------------------------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------------------------

const SUCCESS_TIMEOUT = 5000;

export function initMediaLibrary(root, config) {
    const doc = root.ownerDocument;
    const { urls, csrf, locale, perPage, can, messages } = config;
    const $ = (id) => doc.getElementById(id);

    const grid = $('media-grid');
    const loading = $('media-loading');
    const empty = $('media-empty');
    const emptyText = $('media-empty-text');
    const error = $('media-error');
    const more = $('media-load-more');
    const count = $('media-count');
    const alerts = $('media-alerts');
    const search = $('media-search');
    const selectionBar = $('media-selection');
    const selectionCount = $('media-selection-count');
    const dialog = $('media-details');
    const form = $('media-details-form');
    const saveButton = $('media-save');

    const items = new Map();
    const selected = new Set();
    const inUse = new Set();
    const state = { page: 1, lastPage: 1, search: '', loading: false, current: null };

    const headers = (json = false) => ({
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        ...(json ? { 'Content-Type': 'application/json' } : {}),
    });
    const itemUrl = (id) => urls.item.replace('__ID__', encodeURIComponent(id));

    // A detail line. Names (server data) only ever go through textContent, each isolated in its own <bdi>.
    const renderDetail = (detail) => {
        const entry = doc.createElement('li');

        if (typeof detail === 'string') {
            entry.textContent = detail;

            return entry;
        }

        const [before, after = ''] = detail.template.split(':names');
        entry.append(before);
        listParts(detail.names, locale).forEach((part) => {
            if (part.type === 'element') {
                const name = doc.createElement('bdi');
                name.textContent = part.value;
                entry.append(name);
            } else {
                entry.append(part.value);
            }
        });
        entry.append(after);

        return entry;
    };

    // ---- Messages: inline, success announced politely and removed after 5 s, errors stay ----
    const notify = ({ tone, text, details = [] }) => {
        const alert = doc.createElement('div');
        alert.className = `admin-media-alert is-${tone}`;
        alert.setAttribute('role', tone === 'success' ? 'status' : 'alert');

        const icon = doc.createElement('i');
        icon.className = `ph-duotone ${tone === 'success' ? 'ph-check-circle' : tone === 'warning' ? 'ph-warning' : 'ph-info'}`;
        icon.setAttribute('aria-hidden', 'true');

        const content = doc.createElement('div');
        content.className = 'admin-media-alert-content';
        const title = doc.createElement('p');
        title.textContent = text;
        content.append(title);

        if (details.length > 0) {
            const list = doc.createElement('ul');
            details.forEach((detail) => list.append(renderDetail(detail)));
            content.append(list);
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
            let timer = setTimeout(() => alert.remove(), SUCCESS_TIMEOUT);
            const pause = () => clearTimeout(timer);
            const resume = () => {
                timer = setTimeout(() => alert.remove(), SUCCESS_TIMEOUT);
            };
            alert.addEventListener('mouseenter', pause);
            alert.addEventListener('mouseleave', resume);
            alert.addEventListener('focusin', pause);
            alert.addEventListener('focusout', resume);
        }
    };

    // ---- Selection ----
    const syncSelection = () => {
        if (!selectionBar) {
            return;
        }

        selectionBar.hidden = selected.size === 0;
        selectionCount.textContent = format(messages.selected, { count: selected.size });
    };

    const markInUse = (ids) => {
        ids.forEach((id) => inUse.add(id));
        grid.querySelectorAll('[data-id]').forEach((card) => {
            const badge = card.querySelector('.admin-media-badge');

            if (badge) {
                badge.hidden = !inUse.has(Number(card.dataset.id));
            }
        });
    };

    // ---- Grid ----
    const nameOf = (item) => item.file_original_name || item.file_name || `#${item.id}`;

    const renderCard = (item) => {
        const name = nameOf(item);
        const card = doc.createElement('li');
        card.className = 'admin-media-card';
        card.dataset.id = String(item.id);

        const thumb = doc.createElement('div');
        thumb.className = 'admin-media-thumb';

        const open = doc.createElement('button');
        open.type = 'button';
        open.className = 'admin-media-open';
        open.setAttribute('aria-label', format(messages.open, { name }));
        const image = doc.createElement('img');
        image.src = item.url || '';
        image.alt = '';
        image.loading = 'lazy';
        open.append(image);
        open.addEventListener('click', () => openDetails(item.id, open));
        thumb.append(open);

        const badge = doc.createElement('span');
        badge.className = 'admin-media-badge';
        badge.textContent = messages.inUseBadge;
        badge.hidden = !inUse.has(item.id);
        thumb.append(badge);

        if (can.delete) {
            const label = doc.createElement('label');
            label.className = 'admin-media-check';
            const checkbox = doc.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.checked = selected.has(item.id);
            checkbox.setAttribute('aria-label', format(messages.select, { name }));
            checkbox.addEventListener('change', () => {
                checkbox.checked ? selected.add(item.id) : selected.delete(item.id);
                card.classList.toggle('is-selected', checkbox.checked);
                syncSelection();
            });
            card.classList.toggle('is-selected', checkbox.checked);
            label.append(checkbox);
            thumb.append(label);
        }

        const meta = doc.createElement('div');
        meta.className = 'admin-media-meta';
        const title = doc.createElement('bdi');
        title.className = 'admin-media-name';
        title.textContent = name;
        title.title = name;
        const facts = doc.createElement('span');
        facts.className = 'admin-media-facts-line';
        const uploader = doc.createElement('bdi');
        uploader.textContent = item.uploader_name || messages.unknownUploader;
        facts.append(`${formatBytes(item.size, locale)} · `, uploader);
        meta.append(title, facts);

        card.append(thumb, meta);

        return card;
    };

    const showState = (which) => {
        loading.hidden = which !== 'loading';
        empty.hidden = which !== 'empty';
        error.hidden = which !== 'error';
        grid.setAttribute('aria-busy', which === 'loading' ? 'true' : 'false');
    };

    const load = async (page = 1) => {
        if (state.loading) {
            return;
        }

        state.loading = true;
        more.hidden = true;

        if (page === 1) {
            grid.replaceChildren();
            items.clear();
        }

        showState('loading');

        const params = new URLSearchParams({ page: String(page), per_page: String(perPage), type: 'image' });

        if (state.search) {
            params.set('search', state.search);
        }

        const { status, body } = await request(`${urls.index}?${params}`, { headers: headers() });
        state.loading = false;

        if (status !== 200 || !Array.isArray(body?.data)) {
            showState('error');

            return;
        }

        state.page = body.current_page || page;
        state.lastPage = body.last_page || 1;
        body.data.forEach((item) => {
            items.set(item.id, item);
            grid.append(renderCard(item));
        });

        count.textContent = format(messages.count, { count: body.total ?? items.size });

        if (items.size === 0) {
            emptyText.textContent = state.search ? messages.emptySearch : messages.empty;
            showState('empty');
        } else {
            showState('none');
        }

        more.hidden = state.page >= state.lastPage;
    };

    const reload = () => {
        selected.forEach((id) => {
            if (!items.has(id)) {
                selected.delete(id);
            }
        });

        return load(1).then(syncSelection);
    };

    // ---- Details dialog ----
    const fields = ['file_original_name', 'title', 'alt', 'caption', 'description'];

    const clearFieldErrors = () => fields.forEach((field) => {
        const message = $(`media-error-${field}`);
        const control = $(`media-field-${field}`);
        message.hidden = true;
        message.textContent = '';
        control.removeAttribute('aria-invalid');
    });

    const openDetails = (id, opener) => {
        const item = items.get(id);

        if (!item) {
            return;
        }

        state.current = { item, opener };
        clearFieldErrors();
        $('media-details-image').src = item.url || '';
        $('media-details-image').alt = item.alt || '';
        $('media-details-type').textContent = (item.file_extension || '').toUpperCase() || item.mime_type || '—';
        $('media-details-size').textContent = formatBytes(item.size, locale);
        $('media-details-dimensions').textContent = item.width && item.height ? `${item.width} × ${item.height}` : '—';
        $('media-details-uploader').textContent = item.uploader_name || messages.unknownUploader;
        $('media-details-date').textContent = formatDate(item.created_at, locale);
        fields.forEach((field) => {
            $(`media-field-${field}`).value = item[field] ?? '';
        });

        dialog.showModal();
    };

    const closeDetails = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    dialog.addEventListener('close', () => {
        state.current?.opener?.isConnected && state.current.opener.focus();
        state.current = null;
    });
    // A click on the backdrop lands on the dialog element itself.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDetails();
        }
    });
    $('media-details-close').addEventListener('click', closeDetails);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!can.edit || !state.current || saveButton.disabled) {
            return;
        }

        const label = saveButton.textContent;
        saveButton.disabled = true;
        saveButton.textContent = messages.saving;
        clearFieldErrors();

        const payload = Object.fromEntries(fields.map((field) => [field, $(`media-field-${field}`).value.trim() || null]));
        const { status, body } = await request(itemUrl(state.current.item.id), { method: 'PUT', headers: headers(true), body: JSON.stringify(payload) });
        const outcome = interpretSaveResponse({ status, body, messages });

        saveButton.disabled = false;
        saveButton.textContent = label;

        Object.entries(outcome.fieldErrors).forEach(([field, message]) => {
            const target = $(`media-error-${field}`);

            if (target) {
                target.textContent = message;
                target.hidden = false;
                $(`media-field-${field}`).setAttribute('aria-invalid', 'true');
            }
        });

        if (outcome.tone === 'success') {
            Object.assign(state.current.item, payload);
            closeDetails();
            notify(outcome);
            reload();
        } else if (Object.keys(outcome.fieldErrors).length === 0) {
            notify(outcome);
        }
    });

    $('media-copy-link').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(state.current?.item.url || '');
            notify({ tone: 'success', text: messages.copied });
        } catch {
            notify({ tone: 'error', text: messages.copyFailed });
        }
    });

    // ---- Delete ----
    const remove = async (ids, bulk) => {
        const confirmText = bulk ? format(messages.confirmBulkDelete, { count: ids.length }) : messages.confirmDelete;

        if (!window.confirm(confirmText)) {
            return;
        }

        const { status, body } = bulk
            ? await request(urls.bulk, { method: 'DELETE', headers: headers(true), body: JSON.stringify({ ids }) })
            : await request(itemUrl(ids[0]), { method: 'DELETE', headers: headers() });

        const names = new Map([...items.values()].map((item) => [item.id, nameOf(item)]));
        const outcome = interpretDeleteResponse({ status, body, bulk, names, messages });

        if (outcome.inUseIds.length > 0) {
            // Nothing was deleted: the cards stay, the used ones are marked.
            markInUse(outcome.inUseIds);
        }

        notify(outcome);

        if (outcome.refresh) {
            closeDetails();
            ids.forEach((id) => selected.delete(id));
            await reload();
        }
    };

    $('media-delete')?.addEventListener('click', () => state.current && remove([state.current.item.id], false));
    $('media-bulk-delete')?.addEventListener('click', () => selected.size > 0 && remove([...selected], true));
    $('media-selection-clear')?.addEventListener('click', () => {
        selected.clear();
        grid.querySelectorAll('.admin-media-check input').forEach((checkbox) => {
            checkbox.checked = false;
        });
        grid.querySelectorAll('.admin-media-card').forEach((card) => card.classList.remove('is-selected'));
        syncSelection();
    });

    // ---- Upload ----
    const uploadButton = $('media-upload-button');
    const uploadInput = $('media-upload-input');
    const dropzone = $('media-dropzone');

    const upload = async (fileList) => {
        const files = [...(fileList || [])];

        if (!can.create || files.length === 0) {
            return;
        }

        const data = new FormData();
        files.forEach((file) => data.append('files[]', file));

        // Only the label changes; the icon stays.
        const labelElement = uploadButton.querySelector('span');
        const label = labelElement.textContent;
        uploadButton.disabled = true;
        labelElement.textContent = messages.uploading;

        const { status, body } = await request(urls.store, { method: 'POST', headers: headers(), body: data });

        uploadButton.disabled = false;
        labelElement.textContent = label;

        const outcome = interpretUploadResponse({ status, body, fileNames: files.map((file) => file.name), messages });
        notify(outcome);

        if (outcome.refresh) {
            await reload();
        }
    };

    uploadButton?.addEventListener('click', () => uploadInput.click());
    uploadInput?.addEventListener('change', () => {
        upload(uploadInput.files);
        uploadInput.value = '';
    });

    if (dropzone) {
        ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-active');
        }));
        ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-active');
        }));
        dropzone.addEventListener('drop', (event) => upload(event.dataTransfer?.files));
    }

    // ---- Search, paging, retry ----
    let debounce = null;
    $('media-filters').addEventListener('submit', (event) => event.preventDefault());
    search.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            state.search = search.value.trim();
            load(1);
        }, 400);
    });
    $('media-search-clear').addEventListener('click', () => {
        search.value = '';
        state.search = '';
        load(1);
        search.focus();
    });
    more.addEventListener('click', () => load(state.page + 1));
    $('media-retry').addEventListener('click', () => load(state.page > 1 && items.size > 0 ? state.page + 1 : 1));

    syncSelection();
    load(1);

    return { load, notify, items, selected };
}

function boot() {
    const root = document.getElementById('admin-media-library');
    const config = document.getElementById('media-library-config');

    if (root && config) {
        initMediaLibrary(root, JSON.parse(config.textContent));
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
