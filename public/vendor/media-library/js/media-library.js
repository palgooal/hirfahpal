document.addEventListener('DOMContentLoaded', () => {
    const { baseUrl, csrfToken } = window.MEDIA_CONFIG || {};

    // عناصر DOM الأساسية
    const gridEl          = document.getElementById('media-grid');
    const dropzoneEl      = document.getElementById('dropzone');
    const fileInputEl     = document.getElementById('file-input');
    const uploadBtnEl     = document.getElementById('btn-upload');
    const loadMoreBtnEl   = document.getElementById('btn-load-more');
    const loadingEl       = document.getElementById('media-loading');
    const emptyEl         = document.getElementById('media-empty');
    const searchInputEl   = document.getElementById('search-input');
    const filterButtons   = document.querySelectorAll('.filter-btn');

    // عناصر الـ Modal
    const modalEl         = document.getElementById('media-modal');
    const backdropEl      = document.getElementById('modal-backdrop');
    const modalCloseBtn   = document.getElementById('btn-modal-close');
    const detailsPreviewEl      = document.getElementById('details-preview');
    const detailsTypeEl         = document.getElementById('details-type');
    const detailsSizeEl         = document.getElementById('details-size');
    const detailsDimensionsEl   = document.getElementById('details-dimensions');
    const detailsPathEl         = document.getElementById('details-path');
    const detailsOriginalNameEl = document.getElementById('details-original-name');
    const detailsTitleEl        = document.getElementById('details-title');
    const detailsAltEl          = document.getElementById('details-alt');
    const detailsCaptionEl      = document.getElementById('details-caption');
    const detailsDescriptionEl  = document.getElementById('details-description');
    const detailsFormEl         = document.getElementById('details-form');
    const deleteBtnEl           = document.getElementById('btn-delete');
    const copyUrlBtnEl          = document.getElementById('btn-copy-url');

    // شريط التحديد المتعدد
    const selectionBarEl      = document.getElementById('selection-bar');
    const selectionCountEl    = document.getElementById('selection-count');
    const clearSelectionBtnEl = document.getElementById('btn-clear-selection');
    const bulkDeleteBtnEl     = document.getElementById('btn-bulk-delete');

    if (!gridEl || !baseUrl) return;

    // الحالة الداخلية
    let currentPage       = 1;
    let lastPage          = 1;
    let currentFilterType = '';
    let currentSearch     = '';
    let isLoading         = false;
    let selectedMedia     = null;
    const selectedItems   = new Map();

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    const debounce = (fn, delay = 300) => {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    };

    const formatBytes = (bytes) => {
        if (!bytes && bytes !== 0) return '—';
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
    };

    const showToast = (message, type = 'info') => {
        const colors = {
            info: 'bg-slate-900 text-white',
            success: 'bg-emerald-600 text-white',
            warning: 'bg-amber-500 text-white',
            error: 'bg-rose-600 text-white',
        };
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed bottom-4 start-4 z-[200] space-y-2';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `pointer-events-auto min-w-[200px] max-w-xs rounded-xl px-4 py-3 text-sm shadow-lg ring-1 ring-black/5 opacity-0 translate-y-2 transition-all duration-200 ${colors[type] || colors.info}`;
        toast.innerHTML = `<div class="flex items-start gap-3"><span class="mt-0.5">${message}</span><button class="ms-auto text-white/70 hover:text-white">&times;</button></div>`;
        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.replace('opacity-0', 'opacity-100');
            toast.classList.replace('translate-y-2', 'translate-y-0');
        });
        const timeout = setTimeout(dismiss, 2500);
        function dismiss() {
            toast.classList.replace('opacity-100', 'opacity-0');
            toast.classList.replace('translate-y-0', 'translate-y-2');
            setTimeout(() => toast.parentNode?.removeChild(toast), 200);
        }
        toast.querySelector('button')?.addEventListener('click', () => { clearTimeout(timeout); dismiss(); });
    };

    const setLoading = (state, reset = false) => {
        isLoading = state;
        if (reset) gridEl.innerHTML = '';
        if (state) {
            loadingEl.classList.remove('hidden');
            emptyEl.classList.add('hidden');
            loadMoreBtnEl.classList.add('hidden');
        } else {
            loadingEl.classList.add('hidden');
        }
    };

    // ─────────────────────────────────────────────
    // Modal: فتح وإغلاق
    // ─────────────────────────────────────────────

    const openModal = (item) => {
        selectedMedia = item;

        // تعبئة البيانات
        const isImage = item.file_type === 'image' || (item.mime_type && item.mime_type.startsWith('image/'));
        detailsPreviewEl.src = isImage ? item.url : '';
        detailsTypeEl.textContent    = item.mime_type || item.file_type || '—';
        detailsSizeEl.textContent    = item.readable_size || formatBytes(item.size);
        detailsDimensionsEl.textContent = (item.width && item.height) ? `${item.width} × ${item.height}` : '—';
        detailsPathEl.textContent    = item.path || '';

        detailsOriginalNameEl.value  = item.name || '';
        detailsTitleEl.value         = item.title || '';
        detailsAltEl.value           = item.alt || '';
        detailsCaptionEl.value       = item.caption || '';
        detailsDescriptionEl.value   = item.description || '';

        // إظهار الـ modal
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        selectedMedia = null;
    };

    // ─────────────────────────────────────────────
    // Multi-select UI
    // ─────────────────────────────────────────────

    const updateSelectionUI = () => {
        const count = selectedItems.size;
        if (selectionCountEl) selectionCountEl.textContent = String(count);
        if (selectionBarEl) {
            if (count > 0) selectionBarEl.classList.remove('hidden');
            else selectionBarEl.classList.add('hidden');
        }
    };

    const toggleItemSelection = (itemId, itemData, checkboxEl) => {
        if (selectedItems.has(itemId)) {
            selectedItems.delete(itemId);
            checkboxEl.checked = false;
            // إزالة ring من الكارد
            gridEl.querySelector(`[data-id="${itemId}"]`)?.classList.remove('ring-2', 'ring-indigo-500');
        } else {
            selectedItems.set(itemId, itemData);
            checkboxEl.checked = true;
            gridEl.querySelector(`[data-id="${itemId}"]`)?.classList.add('ring-2', 'ring-indigo-500');
        }
        updateSelectionUI();
    };

    const clearSelection = () => {
        selectedItems.clear();
        document.querySelectorAll('.media-item').forEach(el => el.classList.remove('ring-2', 'ring-indigo-500'));
        document.querySelectorAll('.media-checkbox').forEach(cb => { cb.checked = false; });
        updateSelectionUI();
    };

    // ─────────────────────────────────────────────
    // تحميل البيانات
    // ─────────────────────────────────────────────

    const loadMedia = async (page = 1, append = false) => {
        if (isLoading) return;
        setLoading(true, !append);

        const params = new URLSearchParams({ page, _: Date.now() });
        if (currentFilterType) params.set('type', currentFilterType);
        if (currentSearch)     params.set('search', currentSearch);

        try {
            const res = await fetch(`${baseUrl}?${params}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load media');

            const json = await res.json();
            currentPage = json.current_page || 1;
            lastPage    = json.last_page    || 1;
            const items = json.data || [];

            if (!append) gridEl.innerHTML = '';

            if (!items.length && currentPage === 1) emptyEl.classList.remove('hidden');
            else emptyEl.classList.add('hidden');

            renderMediaItems(items);

            if (currentPage < lastPage) loadMoreBtnEl.classList.remove('hidden');
            else loadMoreBtnEl.classList.add('hidden');

        } catch (e) {
            console.error(e);
            showToast('حدث خطأ أثناء تحميل الوسائط.', 'error');
        } finally {
            setLoading(false);
        }
    };

    // ─────────────────────────────────────────────
    // رسم الشبكة
    // ─────────────────────────────────────────────

    const renderMediaItems = (items) => {
        items.forEach((item) => {
            const isImage  = item.file_type === 'image' || (item.mime_type && item.mime_type.startsWith('image/'));
            const imageUrl = item.url || `/storage/${item.file_path}`;
            const name     = item.file_original_name || item.file_name || 'بدون اسم';

            const itemData = {
                id: item.id, url: imageUrl, name,
                mime_type: item.mime_type, size: item.size,
                width: item.width, height: item.height,
                path: item.file_path, file_type: item.file_type,
                alt: item.alt, title: item.title,
                caption: item.caption, description: item.description,
                readable_size: item.readable_size,
            };

            // الكارد الخارجي
            const card = document.createElement('div');
            card.className = 'media-item group relative w-full aspect-square rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-gray-50 dark:bg-gray-900';
            card.dataset.id = item.id;
            if (selectedItems.has(item.id)) card.classList.add('ring-2', 'ring-indigo-500');

            // زر فتح الـ Modal (يغطي الكارد بالكامل)
            const openBtn = document.createElement('button');
            openBtn.type = 'button';
            openBtn.className = 'absolute inset-0 w-full h-full focus:outline-none';
            openBtn.setAttribute('aria-label', `فتح تفاصيل ${name}`);

            // Built via DOM APIs (not innerHTML) so that a malicious
            // file_original_name / file_extension value can never be
            // interpreted as HTML (stored XSS prevention).
            if (isImage) {
                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = name;
                img.className = 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-200 pointer-events-none';
                openBtn.appendChild(img);
            } else {
                const wrap = document.createElement('div');
                wrap.className = 'w-full h-full flex items-center justify-center pointer-events-none';
                const badge = document.createElement('span');
                badge.className = 'px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[11px] text-gray-500 dark:text-gray-300';
                badge.textContent = (item.file_extension || '').toUpperCase() || 'FILE';
                wrap.appendChild(badge);
                openBtn.appendChild(wrap);
            }

            // Overlay اسم الملف
            const nameOverlay = document.createElement('div');
            nameOverlay.className = 'pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-2 pb-1.5 pt-4 text-[10px] text-white truncate';
            nameOverlay.textContent = name;

            // Checkbox للتحديد المتعدد (يظهر عند hover أو عند التحديد)
            const checkWrapper = document.createElement('label');
            const isChecked = selectedItems.has(item.id);
            checkWrapper.className = `absolute top-1.5 start-1.5 z-10 flex cursor-pointer transition-opacity ${isChecked ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}`;
            checkWrapper.innerHTML = `
                <input type="checkbox" class="media-checkbox sr-only" data-id="${item.id}" ${isChecked ? 'checked' : ''}>
                <span class="flex h-5 w-5 items-center justify-center rounded-md border-2 shadow-sm transition-all
                    ${isChecked
                        ? 'bg-indigo-500 border-indigo-500'
                        : 'bg-white/80 border-white hover:border-indigo-400'}">
                    ${isChecked ? `<svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>` : ''}
                </span>
            `;

            // حدث النقر على الـ checkbox: تحديد بدون فتح modal
            const checkboxInput = checkWrapper.querySelector('input');
            checkboxInput.addEventListener('change', (e) => {
                e.stopPropagation();
                toggleItemSelection(item.id, itemData, checkboxInput);

                // تحديث مظهر الـ span
                const span = checkWrapper.querySelector('span');
                if (checkboxInput.checked) {
                    span.className = 'flex h-5 w-5 items-center justify-center rounded-md border-2 shadow-sm transition-all bg-indigo-500 border-indigo-500';
                    span.innerHTML = `<svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`;
                    checkWrapper.classList.remove('opacity-0', 'group-hover:opacity-100');
                    checkWrapper.classList.add('opacity-100');
                } else {
                    span.className = 'flex h-5 w-5 items-center justify-center rounded-md border-2 shadow-sm transition-all bg-white/80 border-white hover:border-indigo-400';
                    span.innerHTML = '';
                    checkWrapper.classList.remove('opacity-100');
                    checkWrapper.classList.add('opacity-0', 'group-hover:opacity-100');
                }
            });

            // حدث النقر على الصورة: فتح modal
            openBtn.addEventListener('click', () => openModal(itemData));

            card.appendChild(openBtn);
            card.appendChild(nameOverlay);
            card.appendChild(checkWrapper);
            gridEl.appendChild(card);
        });
    };

    // ─────────────────────────────────────────────
    // رفع الملفات
    // ─────────────────────────────────────────────

    const uploadFiles = async (files) => {
        if (!files?.length) return;
        const formData = new FormData();
        Array.from(files).forEach(f => formData.append('files[]', f));
        loadingEl.classList.remove('hidden');
        try {
            const res = await fetch(baseUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData,
            });
            if (!res.ok) throw new Error('Upload failed');
            showToast('تم رفع الملفات بنجاح.', 'success');
            currentPage = 1; lastPage = 1;
            await loadMedia(1, false);
        } catch (e) {
            console.error(e);
            showToast('فشل رفع الملفات.', 'error');
        } finally {
            loadingEl.classList.add('hidden');
        }
    };

    // ─────────────────────────────────────────────
    // حفظ التعديلات
    // ─────────────────────────────────────────────

    const updateDetails = async () => {
        if (!selectedMedia) return;

        const payload = {
            file_original_name: detailsOriginalNameEl.value || null,
            alt:         detailsAltEl.value         || null,
            title:       detailsTitleEl.value        || null,
            caption:     detailsCaptionEl.value      || null,
            description: detailsDescriptionEl.value  || null,
        };

        try {
            const res = await fetch(`${baseUrl}/${selectedMedia.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('Update failed');

            // تحديث محلي في selectedItems
            const updatedId = selectedMedia.id;
            if (selectedItems.has(updatedId)) {
                const existing = selectedItems.get(updatedId);
                selectedItems.set(updatedId, { ...existing, ...payload, name: payload.file_original_name || existing.name });
            }

            showToast('تم حفظ التعديلات.', 'success');
            closeModal();
            await loadMedia(currentPage);

        } catch (e) {
            console.error(e);
            showToast('فشل حفظ التعديلات.', 'error');
        }
    };

    // ─────────────────────────────────────────────
    // حذف ملف واحد
    // ─────────────────────────────────────────────

    const deleteCurrent = async () => {
        if (!selectedMedia) return;
        if (!confirm('هل أنت متأكد من حذف هذا الملف؟')) return;

        try {
            const res = await fetch(`${baseUrl}/${selectedMedia.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Delete failed');

            if (selectedItems.has(selectedMedia.id)) {
                selectedItems.delete(selectedMedia.id);
                updateSelectionUI();
            }

            showToast('تم حذف الملف.', 'success');
            closeModal();
            currentPage = 1;
            await loadMedia(1);
        } catch (e) {
            console.error(e);
            showToast('فشل حذف الملف.', 'error');
        }
    };

    // ─────────────────────────────────────────────
    // حذف جماعي (طلب واحد)
    // ─────────────────────────────────────────────

    const bulkDeleteSelected = async () => {
        if (selectedItems.size === 0) return;
        if (!confirm(`هل أنت متأكد من حذف ${selectedItems.size} عنصر؟`)) return;

        const ids = Array.from(selectedItems.keys());
        try {
            const res = await fetch(`${baseUrl}/bulk`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ ids }),
            });
            if (!res.ok) throw new Error('Bulk delete failed');

            showToast(`تم حذف ${ids.length} عنصر.`, 'success');
            selectedItems.clear();
            updateSelectionUI();
            currentPage = 1;
            await loadMedia(1);
        } catch (e) {
            console.error(e);
            showToast('فشل الحذف الجماعي.', 'error');
        }
    };

    // ─────────────────────────────────────────────
    // أحداث الواجهة
    // ─────────────────────────────────────────────

    // رفع الملفات
    uploadBtnEl?.addEventListener('click', () => fileInputEl.click());
    fileInputEl?.addEventListener('change', (e) => uploadFiles(e.target.files));

    // Drag & Drop
    if (dropzoneEl) {
        ['dragenter', 'dragover'].forEach(evt => dropzoneEl.addEventListener(evt, (e) => {
            e.preventDefault(); e.stopPropagation();
            dropzoneEl.classList.add('border-indigo-500', 'bg-indigo-50/60');
        }));
        ['dragleave', 'drop'].forEach(evt => dropzoneEl.addEventListener(evt, (e) => {
            e.preventDefault(); e.stopPropagation();
            dropzoneEl.classList.remove('border-indigo-500', 'bg-indigo-50/60');
        }));
        dropzoneEl.addEventListener('drop', (e) => uploadFiles(e.dataTransfer.files));
    }

    // تحميل المزيد
    loadMoreBtnEl?.addEventListener('click', () => {
        if (currentPage < lastPage) loadMedia(currentPage + 1, true);
    });

    // الفلاتر
    filterButtons.forEach(btn => btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('bg-indigo-50', 'border-indigo-500', 'text-indigo-600'));
        btn.classList.add('bg-indigo-50', 'border-indigo-500', 'text-indigo-600');
        currentFilterType = btn.dataset.filterType || '';
        currentPage = 1;
        loadMedia(1);
    }));

    // البحث
    searchInputEl?.addEventListener('input', debounce((e) => {
        currentSearch = e.target.value.trim();
        currentPage = 1;
        loadMedia(1);
    }, 400));

    // إغلاق الـ Modal
    modalCloseBtn?.addEventListener('click', closeModal);
    backdropEl?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    // حفظ التعديلات
    detailsFormEl?.addEventListener('submit', (e) => { e.preventDefault(); updateDetails(); });

    // حذف ملف
    deleteBtnEl?.addEventListener('click', (e) => { e.preventDefault(); deleteCurrent(); });

    // نسخ الرابط
    copyUrlBtnEl?.addEventListener('click', () => {
        if (!selectedMedia?.url) return;
        navigator.clipboard.writeText(selectedMedia.url)
            .then(() => showToast('تم نسخ الرابط.', 'success'))
            .catch(() => showToast('فشل نسخ الرابط.', 'error'));
    });

    // إلغاء التحديد
    clearSelectionBtnEl?.addEventListener('click', clearSelection);

    // حذف المحدد
    bulkDeleteBtnEl?.addEventListener('click', bulkDeleteSelected);

    // ─────────────────────────────────────────────
    // تحميل أولي
    // ─────────────────────────────────────────────
    updateSelectionUI();

    // تفعيل زر "الكل" افتراضياً
    document.querySelector('.filter-btn[data-filter-type=""]')
        ?.classList.add('bg-indigo-50', 'border-indigo-500', 'text-indigo-600');

    loadMedia(1);
});
