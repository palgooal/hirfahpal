const editor = document.querySelector('[data-product-editor]');

if (editor) {
    const form = editor.querySelector('[data-product-form]');
    const error = editor.querySelector('[data-product-error]');
    const title = editor.querySelector('[data-product-editor-title]');
    const endpoint = editor.dataset.endpoint;
    const token = form.querySelector('input[name="_token"]').value;
    const list = document.querySelector('[data-vendor-resource-list]');
    let editingId = null, images = [], mode = 'gallery', replacing = null;
    const field = (name) => form.elements.namedItem(name);
    const value = (name) => field(name).value.trim() || null;
    const number = (name) => field(name).value === '' ? null : Number(field(name).value);
    const request = (url, method = 'GET', body = null) => fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const fail = (text) => { error.textContent = text; error.hidden = false; };
    const imageUrl = (image) => image.url || `/storage/${image.path}`;
    const primaryBox = () => editor.querySelector('[data-product-primary-image]');
    const galleryBox = () => editor.querySelector('[data-product-gallery-images]');
    const showEditor = () => { list.hidden = true; editor.hidden = false; editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); };

    const render = () => {
        const primary = images.find((image) => image.is_primary) || images[0] || null;
        primaryBox().replaceChildren(); galleryBox().replaceChildren();
        const card = (image, isPrimary) => {
            const wrap = document.createElement('article'); wrap.className = 'border rounded p-2 d-inline-flex flex-column gap-2';
            const pic = document.createElement('img'); pic.src = imageUrl(image); pic.alt = image.alt_text || 'Product image'; pic.width = 112; pic.height = 112; pic.className = 'object-fit-cover rounded';
            const actions = document.createElement('div'); actions.className = 'd-flex gap-1 flex-wrap';
            const change = document.createElement('button'); change.type = 'button'; change.className = 'btn btn-sm btn-light-primary'; change.textContent = 'Change'; change.onclick = () => openMedia(isPrimary ? 'primary' : 'replace', image); actions.append(change);
            if (!isPrimary) { const main = document.createElement('button'); main.type = 'button'; main.className = 'btn btn-sm btn-light-secondary'; main.textContent = 'Set as main'; main.onclick = () => setPrimary(image); actions.append(main); }
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-light-danger'; remove.textContent = 'Delete'; remove.onclick = () => removeImage(image); actions.append(remove);
            wrap.append(pic, actions); return wrap;
        };
        if (primary) primaryBox().append(card(primary, true)); else primaryBox().textContent = 'No main image selected.';
        const gallery = images.filter((image) => image !== primary);
        gallery.forEach((image) => galleryBox().append(card(image, false)));
        if (!gallery.length) galleryBox().textContent = 'No gallery images selected.';
    };
    const setPrimary = async (image) => {
        if (!editingId) { images.forEach((item) => { item.is_primary = item === image; }); render(); return; }
        const response = await request(`${endpoint}/${editingId}/images/${image.id}`, 'PATCH', { is_primary: true, alt_text: image.alt_text, sort_order: image.sort_order });
        if (!response.ok) return fail('The main image could not be changed.'); images = (await response.json()).product.images || []; render();
    };
    const removeImage = async (image) => {
        if (!window.confirm('Delete this image?')) return;
        if (!editingId || !image.id) { images = images.filter((item) => item !== image); if (!images.some((item) => item.is_primary) && images[0]) images[0].is_primary = true; render(); return; }
        const response = await request(`${endpoint}/${editingId}/images/${image.id}`, 'DELETE');
        if (!response.ok) return fail('The image could not be deleted.'); images = (await response.json()).product.images || []; render();
    };
    const choose = async (medium) => {
        const primary = mode === 'primary' || replacing?.is_primary;
        const data = { path: medium.path, url: medium.url, alt_text: medium.alt_text, sort_order: images.length, is_primary: primary };
        if (!editingId) {
            if (replacing) images = images.filter((image) => image !== replacing);
            if (primary) images.forEach((image) => { image.is_primary = false; }); images.push(data); render(); editor.querySelector('[data-product-media-library]').hidden = true; return;
        }
        const response = await request(`${endpoint}/${editingId}/images`, 'POST', data);
        if (!response.ok) return fail('The image could not be added.'); const body = await response.json();
        if (replacing?.id) { const deleted = await request(`${endpoint}/${editingId}/images/${replacing.id}`, 'DELETE'); if (!deleted.ok) return fail('The new image was added, but the old image could not be removed.'); images = (await deleted.json()).product.images || []; } else images = body.product.images || [];
        render(); editor.querySelector('[data-product-media-library]').hidden = true;
    };
    const openMedia = async (nextMode, image = null) => {
        mode = nextMode; replacing = image;
        const panel = editor.querySelector('[data-product-media-library]'), items = editor.querySelector('[data-product-media-items]');
        editor.querySelector('[data-product-media-title]').textContent = nextMode === 'primary' ? 'Choose the main product image' : image ? 'Choose a replacement image' : 'Choose a gallery image'; panel.hidden = false; items.textContent = 'Loading…';
        const response = await request(editor.dataset.mediaLibraryUrl); if (!response.ok) { items.textContent = 'Media could not be loaded.'; return; }
        const media = (await response.json()).images || []; items.replaceChildren();
        media.forEach((medium) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'border rounded p-1'; const pic = document.createElement('img'); pic.src = medium.url; pic.alt = medium.alt_text || 'Media image'; pic.width = 96; pic.height = 96; pic.className = 'object-fit-cover rounded'; button.append(pic); button.onclick = () => choose(medium); items.append(button); });
        if (!media.length) items.textContent = 'No uploaded images yet. Upload one from this window.';
    };
    const upload = async () => {
        const input = editor.querySelector('[data-product-media-file]'), files = Array.from(input.files || []); if (!files.length) return;
        const data = new FormData(); files.forEach((file) => data.append('files[]', file));
        const response = await fetch(editor.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, body: data }); input.value = '';
        if (!response.ok) return fail('The image could not be uploaded to media.'); const media = (await response.json()).images || []; if (media[0]) choose(media[0]);
    };
    const close = () => { editor.hidden = true; list.hidden = false; error.hidden = true; form.reset(); field('low_stock_threshold').value = '1'; editingId = null; images = []; render(); title.textContent = 'Add product'; };
    const payload = () => ({ name: field('name').value.trim(), category_id: number('category_id'), price: number('price'), stock_quantity: number('stock_quantity'), sku: value('sku'), compare_at_price: number('compare_at_price'), low_stock_threshold: number('low_stock_threshold'), weight: number('weight'), short_description: value('short_description'), description: value('description'), images: editingId ? undefined : images.map(({ path, alt_text, sort_order, is_primary }) => ({ path, alt_text, sort_order, is_primary })) });
    window.addEventListener('vendor-resource:loaded', (event) => { if (event.detail.resource !== 'products') return; const selected = field('category_id').value; field('category_id').replaceChildren(new Option('No category', '')); (event.detail.payload?.filters?.categories ?? []).forEach((category) => field('category_id').add(new Option(category.name, String(category.id)))); field('category_id').value = selected; });
    document.addEventListener('click', async (event) => { const button = event.target.closest('[data-product-action]'); if (!button) return; const id = button.dataset.productId; if (button.dataset.productAction === 'delete') { if (!window.confirm('Delete this product? This cannot be undone.')) return; const response = await request(`${endpoint}/${id}`, 'DELETE'); if (!response.ok) { fail('The product could not be deleted.'); showEditor(); return; } window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); return; } const response = await request(`${endpoint}/${id}`); if (!response.ok) return fail('The product could not be loaded.'); const product = (await response.json()).product; form.reset(); ['name', 'category_id', 'price', 'stock_quantity', 'sku', 'compare_at_price', 'low_stock_threshold', 'weight', 'short_description', 'description'].forEach((name) => { field(name).value = product[name] ?? ''; }); editingId = id; images = product.images || []; render(); title.textContent = 'Edit product'; showEditor(); });
    form.addEventListener('submit', async (event) => { event.preventDefault(); error.hidden = true; const response = await request(editingId ? `${endpoint}/${editingId}` : endpoint, editingId ? 'PUT' : 'POST', payload()); if (!response.ok) { const data = await response.json().catch(() => null); fail(Object.values(data?.errors ?? {}).flat().join(' ') || 'The product could not be saved.'); return; } close(); window.dispatchEvent(new CustomEvent('vendor-resource:refresh', { detail: { resource: 'products' } })); });
    editor.querySelectorAll('[data-product-editor-close]').forEach((button) => button.onclick = close);
    editor.querySelector('[data-product-primary-open]').onclick = () => openMedia(images.some((image) => image.is_primary) ? 'replace' : 'primary', images.find((image) => image.is_primary));
    editor.querySelector('[data-product-gallery-open]').onclick = () => openMedia('gallery'); editor.querySelector('[data-product-media-close]').onclick = () => { editor.querySelector('[data-product-media-library]').hidden = true; }; editor.querySelector('[data-product-media-upload]').onclick = () => editor.querySelector('[data-product-media-file]').click(); editor.querySelector('[data-product-media-file]').onchange = upload;
    const add = document.createElement('button'); add.type = 'button'; add.className = 'btn btn-primary'; add.textContent = 'Add product'; add.onclick = () => { close(); showEditor(); }; document.querySelector('[data-vendor-resource-list] [data-resource-retry]').before(add);
}
