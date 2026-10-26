const editor = document.querySelector('[data-product-editor]');

if (editor) {
    const form = editor.querySelector('[data-product-form]');
    const error = editor.querySelector('[data-product-error]');
    const title = editor.querySelector('[data-product-editor-title]');
    const endpoint = editor.dataset.endpoint;
    const token = form.querySelector('input[name="_token"]').value;
    let editingId = null;
    let images = [];

    const field = (name) => form.elements.namedItem(name);
    const optional = (name) => field(name).value.trim() || null;
    const numberOrNull = (name) => field(name).value === '' ? null : Number(field(name).value);
    const tags = (name) => field(name).value.split(',').map((value) => value.trim()).filter(Boolean);

    const show = () => { editor.hidden = false; editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
    const imageManager = () => editor.querySelector('[data-product-images-current]');
    const renderImages = () => {
        const manager = imageManager();
        if (!manager) return;
        manager.replaceChildren();
        images.forEach((image) => {
            const card = document.createElement('div'); card.className = 'border rounded p-2 d-inline-flex flex-column gap-2';
            const preview = document.createElement('img'); preview.src = image.url || `/storage/${image.path}`; preview.alt = image.alt_text || 'Product image'; preview.width = 96; preview.height = 96; preview.className = 'object-fit-cover rounded';
            const actions = document.createElement('div'); actions.className = 'd-flex gap-1';
            const primary = document.createElement('button'); primary.type = 'button'; primary.className = 'btn btn-sm btn-light-primary'; primary.textContent = image.is_primary ? 'Main image' : 'Set as main'; primary.disabled = Boolean(image.is_primary);
            primary.addEventListener('click', async () => {
                const response = await request(`${endpoint}/${editingId}/images/${image.id}`, 'PATCH', { is_primary: true, alt_text: image.alt_text, sort_order: image.sort_order });
                if (!response.ok) return reportError('The main image could not be changed.');
                images = (await response.json()).product.images || []; renderImages();
            });
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-light-danger'; remove.textContent = 'Delete';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Delete this image?')) return;
                const response = await request(`${endpoint}/${editingId}/images/${image.id}`, 'DELETE');
                if (!response.ok) return reportError('The image could not be deleted.');
                images = (await response.json()).product.images || []; renderImages();
            });
            actions.append(primary, remove); card.append(preview, actions); manager.append(card);
        });
    };
    const showMediaLibrary = async () => {
        if (!editingId) { reportError('Save the product before choosing existing media.'); return; }
        const panel = editor.querySelector('[data-product-media-library]');
        const items = editor.querySelector('[data-product-media-items]');
        panel.hidden = false; items.textContent = 'Loading…';
        const response = await request(editor.dataset.mediaLibraryUrl);
        if (!response.ok) { items.textContent = 'Media could not be loaded.'; return; }
        const media = (await response.json()).images || [];
        items.replaceChildren();
        media.forEach((medium) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'border rounded p-1';
            const preview = document.createElement('img'); preview.src = medium.url; preview.alt = medium.alt_text || 'Media image'; preview.width = 96; preview.height = 96; preview.className = 'object-fit-cover rounded';
            button.append(preview); button.addEventListener('click', async () => {
                const response = await request(`${endpoint}/${editingId}/images`, 'POST', { path: medium.path, alt_text: medium.alt_text, sort_order: images.length, is_primary: images.length === 0 });
                if (!response.ok) { reportError('The image could not be added.'); return; }
                images = (await response.json()).product.images || []; renderImages(); panel.hidden = true;
            }); items.append(button);
        });
        if (!media.length) items.textContent = 'No uploaded images yet.';
    };
    const close = () => { editor.hidden = true; error.hidden = true; error.textContent = ''; form.reset(); field('low_stock_threshold').value = '1'; editingId = null; images = []; renderImages(); title.textContent = 'Add product'; };
    const reportError = (message) => { error.textContent = message; error.hidden = false; };
    const payload = () => ({
        name: field('name').value.trim(), category_id: numberOrNull('category_id'), price: numberOrNull('price'), stock_quantity: numberOrNull('stock_quantity'),
        status: field('status').value, sku: optional('sku'), compare_at_price: numberOrNull('compare_at_price'), low_stock_threshold: numberOrNull('low_stock_threshold'),
        weight: numberOrNull('weight'), short_description: optional('short_description'), description: optional('description'), is_featured: false,
        is_limited_edition: field('is_limited_edition').checked, highlight_tags: tags('highlight_tags'), craft_tags: tags('craft_tags'),
    });
    const request = (url, method = 'GET', body = null) => fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const uploadImages = async (product) => {
        const files = Array.from(field('images').files);
        if (!files.length) return;
        const data = new FormData(); files.forEach((file) => data.append('files[]', file));
        const response = await fetch(editor.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, body: data });
        if (!response.ok) throw new Error('media-upload-failed');
        const media = (await response.json()).images || [];
        for (const image of media) {
            const attached = await request(`${endpoint}/${product.id}/images`, 'POST', { path: image.path, alt_text: image.alt_text || product.name, sort_order: images.length, is_primary: images.length === 0 });
            if (!attached.ok) throw new Error('image-attach-failed');
            images.push((await attached.json()).image);
        }
    };

    window.addEventListener('vendor-resource:loaded', (event) => {
        if (event.detail.resource !== 'products') return;
        const categories = event.detail.payload?.filters?.categories ?? [];
        const selected = field('category_id').value;
        field('category_id').replaceChildren(new Option('No category', ''));
        categories.forEach((category) => field('category_id').add(new Option(category.name, String(category.id))));
        field('category_id').value = selected;
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-product-action]');
        if (!button) return;
        const id = button.dataset.productId;
        if (button.dataset.productAction === 'delete') {
            if (!window.confirm('Delete this product? This cannot be undone.')) return;
            const response = await request(`${endpoint}/${id}`, 'DELETE');
            if (!response.ok) { reportError('The product could not be deleted.'); show(); return; }
            window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } }));
            return;
        }
        const response = await request(`${endpoint}/${id}`);
        if (!response.ok) { reportError('The product could not be loaded.'); show(); return; }
        const product = (await response.json()).product;
        form.reset();
        ['name', 'category_id', 'price', 'stock_quantity', 'status', 'sku', 'compare_at_price', 'low_stock_threshold', 'weight', 'short_description', 'description'].forEach((name) => { field(name).value = product[name] ?? ''; });
        field('is_limited_edition').checked = Boolean(product.is_limited_edition);
        field('highlight_tags').value = (product.highlight_tags || []).join(', ');
        field('craft_tags').value = (product.craft_tags || []).join(', ');
        editingId = id; images = product.images || []; renderImages(); title.textContent = 'Edit product'; show();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault(); error.hidden = true;
        const response = await request(editingId ? `${endpoint}/${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload());
        if (!response.ok) {
            const data = await response.json().catch(() => null);
            reportError(Object.values(data?.errors ?? {}).flat().join(' ') || 'The product could not be saved.');
            return;
        }
        try {
            const product = (await response.json()).product;
            await uploadImages(product);
            if (editingId) { const refreshed = await request(`${endpoint}/${editingId}`); images = refreshed.ok ? (await refreshed.json()).product.images || [] : images; renderImages(); }
            close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } }));
        } catch {
            reportError('The product was saved, but one or more images could not be uploaded.');
        }
    });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.addEventListener('click', close));
    editor.querySelector('[data-product-media-open]').addEventListener('click', showMediaLibrary);
    editor.querySelector('[data-product-media-close]').addEventListener('click', () => { editor.querySelector('[data-product-media-library]').hidden = true; });
    const opener = document.createElement('button'); opener.type = 'button'; opener.className = 'btn btn-primary'; opener.textContent = 'Add product';
    opener.addEventListener('click', () => { close(); show(); });
    document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(opener);
}
