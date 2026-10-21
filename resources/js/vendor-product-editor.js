const editor = document.querySelector('[data-product-editor]');

if (editor) {
    const form = editor.querySelector('[data-product-form]');
    const error = editor.querySelector('[data-product-error]');
    const title = editor.querySelector('[data-product-editor-title]');
    const endpoint = editor.dataset.endpoint;
    const token = form.querySelector('input[name="_token"]').value;
    let editingId = null;

    const field = (name) => form.elements.namedItem(name);
    const optional = (name) => field(name).value.trim() || null;
    const numberOrNull = (name) => field(name).value === '' ? null : Number(field(name).value);

    const show = () => { editor.hidden = false; editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
    const close = () => { editor.hidden = true; error.hidden = true; error.textContent = ''; form.reset(); field('low_stock_threshold').value = '1'; editingId = null; title.textContent = 'Add product'; };
    const reportError = (message) => { error.textContent = message; error.hidden = false; };
    const payload = () => ({
        name: field('name').value.trim(), category_id: numberOrNull('category_id'), price: numberOrNull('price'), stock_quantity: numberOrNull('stock_quantity'),
        status: field('status').value, sku: optional('sku'), compare_at_price: numberOrNull('compare_at_price'), low_stock_threshold: numberOrNull('low_stock_threshold'),
        weight: numberOrNull('weight'), short_description: optional('short_description'), description: optional('description'), is_featured: false,
    });
    const request = (url, method = 'GET', body = null) => fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const uploadImages = async (product) => {
        const files = Array.from(field('images').files);
        for (const file of files) {
            const data = new FormData(); data.append('file', file); data.append('alt_text', product.name);
            const response = await fetch(`${endpoint}/${product.id}/upload-image`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, body: data });
            if (!response.ok) throw new Error('image-upload-failed');
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
        editingId = id; title.textContent = 'Edit product'; show();
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
            await uploadImages((await response.json()).product);
            close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } }));
        } catch {
            reportError('The product was saved, but one or more images could not be uploaded.');
        }
    });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.addEventListener('click', close));
    const opener = document.createElement('button'); opener.type = 'button'; opener.className = 'btn btn-primary'; opener.textContent = 'Add product';
    opener.addEventListener('click', () => { close(); show(); });
    document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(opener);
}
