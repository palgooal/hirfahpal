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
    const gallery = editor.querySelector('[data-product-gallery]');
    const galleryStatus = editor.querySelector('[data-product-gallery-status]');
    const galleryError = editor.querySelector('[data-product-gallery-error]');
    const galleryCount = editor.querySelector('[data-product-gallery-count]');
    const panel = editor.querySelector('[data-product-media-library]');
    const panelItems = editor.querySelector('[data-product-media-items]');
    let editingId = null, images = [], mode = 'gallery', target = null, busy = false;
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
    const status = (text) => { galleryStatus.textContent = ''; galleryStatus.textContent = text || ''; };
    const galleryFail = (name) => { galleryError.textContent = errorMessage(name, messages); galleryError.hidden = false; status(''); };
    const clearGalleryError = () => { galleryError.hidden = true; galleryError.textContent = ''; };
    const setBusy = (state) => {
        busy = state;
        gallery.setAttribute('aria-busy', state ? 'true' : 'false');
        gallery.querySelectorAll('button').forEach((button) => { button.disabled = state || (button.dataset.limited === 'true' && images.length >= MAX_IMAGES); });
    };

    const render = () => {
        images = orderImages(images);
        const primary = images[0] ?? null;
        primaryBox().replaceChildren(); galleryBox().replaceChildren();
        const card = (image, isPrimary) => {
            const wrap = document.createElement('article'); wrap.className = 'border rounded p-2 d-inline-flex flex-column gap-2';
            const pic = document.createElement('img'); pic.src = imageUrl(image); pic.alt = image.alt_text || messages.imageAlt; pic.width = 112; pic.height = 112; pic.className = 'object-fit-cover rounded';
            const actions = document.createElement('div'); actions.className = 'd-flex gap-1 flex-wrap';
            const button = (text, className, onClick) => { const node = document.createElement('button'); node.type = 'button'; node.className = `btn btn-sm ${className}`; node.textContent = text; node.onclick = onClick; actions.append(node); };
            button(messages.change, 'btn-light-primary', () => openMedia(isPrimary ? 'primary' : 'replace', image));
            if (!isPrimary) button(messages.setMain, 'btn-light-secondary', () => setPrimary(image));
            button(messages.remove, 'btn-light-danger', () => removeImage(image));
            wrap.append(pic, actions); return wrap;
        };
        if (primary) primaryBox().append(card(primary, true)); else primaryBox().textContent = messages.noMain;
        images.slice(1).forEach((image) => galleryBox().append(card(image, false)));
        if (images.length < 2) galleryBox().textContent = messages.noGallery;
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

    const choose = async (medium) => {
        if (busy) return;
        const plan = planChoice(images, { mode, target });
        if (plan.action === 'refuse') { galleryFail(plan.error); return; }

        if (!editingId) {
            images = applyLocal(images, plan, medium); render(); panel.hidden = true; clearGalleryError();
            status(plan.action === 'replace' ? messages.replaced : messages.added); return;
        }

        const saved = plan.action === 'replace'
            // G7-C2-5: one PATCH with the new path: the count stays, the place and the main image are kept.
            ? await mutate(`${endpoint}/${editingId}/images/${plan.target.id}`, 'PATCH', { path: medium.path, alt_text: medium.alt_text ?? null }, messages.replaced)
            : await mutate(`${endpoint}/${editingId}/images`, 'POST', { path: medium.path, alt_text: medium.alt_text ?? null, is_primary: plan.primary }, messages.added);
        if (saved) panel.hidden = true;
    };

    const openMedia = async (nextMode, image = null) => {
        if (busy) return;
        mode = nextMode; target = image; clearGalleryError();
        if (!target && images.length >= MAX_IMAGES) { galleryFail('limit'); return; }
        editor.querySelector('[data-product-media-title]').textContent = target ? messages.titleReplace : nextMode === 'primary' ? messages.titleMain : messages.titleGallery;
        panel.hidden = false; panelItems.textContent = messages.loading;
        const answer = await fetchJson(editor.dataset.mediaLibraryUrl, { credentials: 'same-origin', headers: headers(false) });
        if (answer.status !== 200) { panelItems.textContent = answer.status === 401 || answer.status === 419 ? messages.session : answer.status === 403 ? messages.forbidden : answer.status === 0 ? messages.network : messages.libraryError; return; }
        const media = Array.isArray(answer.body?.images) ? answer.body.images : [];
        panelItems.replaceChildren();
        media.forEach((medium) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'border rounded p-1'; const pic = document.createElement('img'); pic.src = medium.url; pic.alt = medium.alt_text || messages.mediaAlt; pic.width = 96; pic.height = 96; pic.className = 'object-fit-cover rounded'; button.append(pic); button.onclick = () => choose(medium); panelItems.append(button); });
        if (!media.length) panelItems.textContent = messages.libraryEmpty;
    };

    // Uploads go to the vendor's media library (no product upload exists); the first new image is then chosen.
    const upload = async () => {
        const input = editor.querySelector('[data-product-media-file]'), files = Array.from(input.files || []); if (!files.length || busy) return;
        const data = new FormData(); files.forEach((file) => data.append('files[]', file));
        clearGalleryError(); setBusy(true); status(messages.uploading);
        const answer = await fetchJson(editor.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: headers(false), body: data }); input.value = '';
        setBusy(false); status('');
        if (answer.status !== 201) { galleryFail(answer.status === 422 ? 'uploadInvalid' : interpretImageResponse(answer).error); return; }
        const media = Array.isArray(answer.body?.images) ? answer.body.images : []; if (media[0]) choose(media[0]);
    };

    const close = () => { editor.hidden = true; list.hidden = false; error.hidden = true; form.reset(); field('low_stock_threshold').value = '1'; editingId = null; images = []; clearGalleryError(); status(''); panel.hidden = true; render(); title.textContent = 'Add product'; };
    const payload = () => ({ name: field('name').value.trim(), category_id: number('category_id'), price: number('price'), stock_quantity: number('stock_quantity'), sku: value('sku'), compare_at_price: number('compare_at_price'), low_stock_threshold: number('low_stock_threshold'), weight: number('weight'), short_description: value('short_description'), description: value('description'), images: editingId ? undefined : images.map(({ path, alt_text, sort_order, is_primary }) => ({ path, alt_text, sort_order, is_primary })) });
    window.addEventListener('vendor-resource:loaded', (event) => { if (event.detail.resource !== 'products') return; const selected = field('category_id').value; field('category_id').replaceChildren(new Option('No category', '')); (event.detail.payload?.filters?.categories ?? []).forEach((category) => field('category_id').add(new Option(category.name, String(category.id)))); field('category_id').value = selected; });
    document.addEventListener('click', async (event) => { const button = event.target.closest('[data-product-action]'); if (!button) return; const id = button.dataset.productId; if (button.dataset.productAction === 'delete') { if (!window.confirm('Delete this product? This cannot be undone.')) return; const response = await request(`${endpoint}/${id}`, 'DELETE'); if (!response.ok) { fail('The product could not be deleted.'); showEditor(); return; } window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); return; } const response = await request(`${endpoint}/${id}`); if (!response.ok) return fail('The product could not be loaded.'); const product = (await response.json()).product; form.reset(); ['name', 'category_id', 'price', 'stock_quantity', 'sku', 'compare_at_price', 'low_stock_threshold', 'weight', 'short_description', 'description'].forEach((name) => { field(name).value = product[name] ?? ''; }); editingId = id; images = product.images || []; clearGalleryError(); status(''); panel.hidden = true; render(); title.textContent = 'Edit product'; showEditor(); });
    form.addEventListener('submit', async (event) => { event.preventDefault(); error.hidden = true; const response = await request(editingId ? `${endpoint}/${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload()); if (!response.ok) { const data = await response.json().catch(() => null); fail(Object.values(data?.errors ?? {}).flat().join(' ') || 'The product could not be saved.'); return; } close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.onclick = close);
    editor.querySelector('[data-product-primary-open]').onclick = () => openMedia('primary', images[0] ?? null);
    editor.querySelector('[data-product-gallery-open]').onclick = () => openMedia('gallery');
    editor.querySelector('[data-product-media-close]').onclick = () => { panel.hidden = true; };
    editor.querySelector('[data-product-media-upload]').onclick = () => editor.querySelector('[data-product-media-file]').click();
    editor.querySelector('[data-product-media-file]').onchange = upload;
    const add = document.createElement('button'); add.type = 'button'; add.className = 'btn btn-primary'; add.textContent = 'Add product'; add.onclick = () => { close(); showEditor(); }; document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(add);
    render();
}

if (typeof document !== 'undefined') {
    const editor = document.querySelector('[data-product-editor]');

    if (editor) {
        initEditor(editor);
    }
}
