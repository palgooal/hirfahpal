document.addEventListener('DOMContentLoaded', () => {
    /**
     * ----------------------------------------------------------------------
     *  Global configuration (shared with the main Media Library)
     * ----------------------------------------------------------------------
     * MEDIA_CONFIG is expected to be defined globally from Blade:
     *   window.MEDIA_CONFIG = { baseUrl: '/admin/media', csrfToken: '...' }
     */
    const mediaConfig = window.MEDIA_CONFIG || {};
    const baseUrl = mediaConfig.baseUrl || '/admin/media';
    const csrfToken =
        mediaConfig.csrfToken ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /**
     * ----------------------------------------------------------------------
     *  Core DOM elements for the Media Picker Modal
     * ----------------------------------------------------------------------
     */
    const backdropEl = document.getElementById('media-picker-backdrop');
    const modalEl = document.getElementById('media-picker-modal');
    const gridEl = document.getElementById('media-picker-grid');
    const loadingEl = document.getElementById('media-picker-loading');
    const emptyEl = document.getElementById('media-picker-empty');
    const loadMoreBtnEl = document.getElementById('media-picker-load-more');

    const searchInputEl = document.getElementById('media-picker-search');
    const filterButtons = document.querySelectorAll('.media-picker-filter-btn');

    const selectionCountEl = document.getElementById('media-picker-selection-count');
    const clearSelectionBtnEl = document.getElementById('media-picker-clear');
    const cancelBtnEl = document.getElementById('media-picker-cancel');
    const closeBtnEl = document.getElementById('media-picker-close');
    const confirmBtnEl = document.getElementById('media-picker-confirm');

    /**
     * Elements for uploading media directly from inside the popup
     */
    const uploadBtnEl = document.getElementById('media-picker-upload-btn');
    const fileInputEl = document.getElementById('media-picker-file-input');

    /**
     * Drag & Drop area inside the popup
     * - Allows dropping files directly to upload
     */
    const popupDropzoneEl = document.getElementById('media-picker-dropzone');

    // If there is no modal on the page, the picker is not used here.
    if (!modalEl || !gridEl) {
        return;
    }

    /**
     * ----------------------------------------------------------------------
     *  Internal state
     * ----------------------------------------------------------------------
     */
    let pickerOpen = false;
    let currentPage = 1;
    let lastPage = 1;
    let currentFilterType = '';
    let currentSearch = '';
    let isLoading = false;

    // Information about the field that opened the picker
    let currentTargetInputId = null;
    let currentPreviewContainerId = null;
    let isMultiple = false;
    let currentStoreValue = 'id';

    /**
     * Map of selected media items
     * - Key: media ID
     * - Value: { id, url, name, file_type, mime_type }
     */
    const selectedItems = new Map();

    /**
     * ----------------------------------------------------------------------
     *  Utility: Debounce helper
     * ----------------------------------------------------------------------
     * Ensures a function is not called too frequently (used for search input)
     */
    const debounce = (fn, delay = 300) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), delay);
        };
    };

    /**
     * ----------------------------------------------------------------------
     *  Utility: Simple Toast Notification
     * ----------------------------------------------------------------------
     * Displays small notifications in the bottom corner of the screen.
     */
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
            container.className =
                'fixed bottom-4 right-4 rtl:left-4 rtl:right-auto z-[99999] space-y-2';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className =
            `pointer-events-auto min-w-[200px] max-w-xs rounded-xl px-4 py-3 text-sm shadow-lg ring-1 ring-black/5 opacity-0 translate-y-2 transition-all duration-200 ${colors[type] || colors.info}`;
        el.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="mt-0.5">${message}</span>
                <button class="ml-auto text-white/70 hover:text-white" aria-label="Close">&times;</button>
            </div>
        `;

        container.appendChild(el);

        requestAnimationFrame(() => {
            el.classList.remove('opacity-0', 'translate-y-2');
            el.classList.add('opacity-100', 'translate-y-0');
        });

        const timeout = setTimeout(dismiss, 2500);
        function dismiss() {
            el.classList.remove('opacity-100', 'translate-y-0');
            el.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => el.remove(), 200);
        }

        el.querySelector('button')?.addEventListener('click', () => {
            clearTimeout(timeout);
            dismiss();
        });
    };

    /**
     * ----------------------------------------------------------------------
     *  Modal Handling: Open / Close
     * ----------------------------------------------------------------------
     */

    /**
     * Opens the media picker with a given configuration:
     * - targetInputId: hidden input where the selected IDs will be stored
     * - previewContainerId: container where thumbnails will be rendered
     * - multiple: whether multiple selection is allowed
     */
    const openPicker = (config) => {
        currentTargetInputId = config.targetInputId;
        currentPreviewContainerId = config.previewContainerId;
        isMultiple = config.multiple;
        currentStoreValue = config.storeValue || 'id';

        // Reset state for fresh view every time the picker opens
        currentPage = 1;
        lastPage = 1;
        currentFilterType = '';
        currentSearch = '';
        selectedItems.clear();
        updateSelectionUI();
        gridEl.innerHTML = '';
        if (emptyEl) emptyEl.classList.add('hidden');

        // Let JS fully control the visibility of "Load more" button
        if (loadMoreBtnEl) {
            loadMoreBtnEl.classList.add('hidden');
        }

        // Show modal and backdrop
        backdropEl.classList.remove('hidden');
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        pickerOpen = true;

        // Initial media load
        loadMedia(1, false);
    };

    /**
     * Closes the media picker modal and hides the overlay.
     * Does not clear the form values; it only hides the UI.
     */
    const closePicker = () => {
        pickerOpen = false;
        backdropEl.classList.add('hidden');
        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
    };

    /**
     * ----------------------------------------------------------------------
     *  Loading State Helper
     * ----------------------------------------------------------------------
     */

    /**
     * Toggles the loading state and optionally clears the grid.
     */
    const setLoading = (state, reset = false) => {
        isLoading = state;
        if (reset) {
            gridEl.innerHTML = '';
        }

        if (state) {
            if (loadingEl) loadingEl.classList.remove('hidden');
            if (emptyEl) emptyEl.classList.add('hidden');
            if (loadMoreBtnEl) loadMoreBtnEl.classList.add('hidden');
        } else {
            if (loadingEl) loadingEl.classList.add('hidden');
        }
    };

    /**
     * ----------------------------------------------------------------------
     *  Fetching Media Items from the API
     * ----------------------------------------------------------------------
     * Loads paginated media items, optionally appending to the grid.
     */
    const loadMedia = async (page = 1, append = false) => {
        if (isLoading) return;
        setLoading(true, !append);

        const params = new URLSearchParams();
        params.set('page', page);
        // Small per_page so we always have multiple pages to test with
        params.set('per_page', '8');
        if (currentFilterType) params.set('type', currentFilterType);
        if (currentSearch) params.set('search', currentSearch);
        // Cache-busting param
        params.set('_', Date.now().toString());

        try {
            const res = await fetch(`${baseUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!res.ok) {
                throw new Error('Failed to load media for picker');
            }

            const json = await res.json();

            currentPage = json.current_page || 1;
            lastPage = json.last_page || 1;
            const items = json.data || [];

            if (!append) {
                gridEl.innerHTML = '';
            }

            if (!items.length && currentPage === 1) {
                if (emptyEl) emptyEl.classList.remove('hidden');
            } else {
                if (emptyEl) emptyEl.classList.add('hidden');
            }

            renderMediaItems(items);

            // Handle "Load more" button visibility and state
            if (loadMoreBtnEl) {
                loadMoreBtnEl.classList.remove('hidden');

                if (currentPage < lastPage && items.length > 0) {
                    loadMoreBtnEl.disabled = false;
                    loadMoreBtnEl.textContent = 'تحميل المزيد من الوسائط';
                } else {
                    loadMoreBtnEl.disabled = true;
                    loadMoreBtnEl.textContent = 'لا يوجد المزيد من الوسائط';
                }
            }
        } catch (e) {
            console.error(e);
            showToast('حدث خطأ أثناء تحميل الوسائط.', 'error');
        } finally {
            setLoading(false);
        }
    };

    /**
     * ----------------------------------------------------------------------
     *  Rendering Media Items inside the Grid
     * ----------------------------------------------------------------------
     * Creates clickable buttons for each media item (image or generic file).
     */
    const renderMediaItems = (items) => {
        items.forEach((item) => {
            const isImage =
                item.file_type === 'image' ||
                (item.mime_type && item.mime_type.startsWith('image/'));

            const imageUrl = item.url || `/storage/${item.file_path}`;
            const name =
                item.file_original_name || item.file_name || 'بدون اسم';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'media-picker-item group relative w-full aspect-square rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-gray-50 dark:bg-gray-900 text-left';
            btn.dataset.id = item.id;

            if (selectedItems.has(item.id)) {
                btn.classList.add('ring-2', 'ring-indigo-500');
            }

            // Built via DOM APIs (not innerHTML) so that a malicious
            // file_original_name / file_extension value can never be
            // interpreted as HTML (stored XSS prevention).
            if (isImage) {
                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = name;
                img.className = 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-200';
                btn.appendChild(img);
            } else {
                const wrap = document.createElement('div');
                wrap.className = 'w-full h-full flex items-center justify-center text-[11px] text-gray-500 dark:text-gray-300';
                const badge = document.createElement('span');
                badge.className = 'px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700';
                badge.textContent = (item.file_extension || '').toUpperCase() || 'FILE';
                wrap.appendChild(badge);
                btn.appendChild(wrap);
            }

            const overlay = document.createElement('div');
            overlay.className = 'absolute inset-x-0 bottom-0 bg-black/40 text-[10px] text-white px-2 py-1 truncate';
            overlay.textContent = name;
            btn.appendChild(overlay);

            // Click handler for selecting/deselecting this item
            btn.addEventListener('click', () => {
                const alreadySelected = selectedItems.has(item.id);

                if (isMultiple) {
                    // Multiple selection mode: toggle the item
                    if (alreadySelected) {
                        selectedItems.delete(item.id);
                        btn.classList.remove('ring-2', 'ring-indigo-500');
                    } else {
                        selectedItems.set(item.id, {
                            id: item.id,
                            url: imageUrl,
                            path: item.file_path || '',
                            name,
                            file_type: item.file_type,
                            mime_type: item.mime_type,
                        });
                        btn.classList.add('ring-2', 'ring-indigo-500');
                    }
                } else {
                    // Single selection mode: clear all, then select this one
                    selectedItems.clear();
                    document
                        .querySelectorAll('.media-picker-item')
                        .forEach((el) =>
                            el.classList.remove('ring-2', 'ring-indigo-500')
                        );

                    selectedItems.set(item.id, {
                        id: item.id,
                        url: imageUrl,
                        path: item.file_path || '',
                        name,
                        file_type: item.file_type,
                        mime_type: item.mime_type,
                    });
                    btn.classList.add('ring-2', 'ring-indigo-500');
                }

                updateSelectionUI();
            });

            gridEl.appendChild(btn);
        });
    };

    /**
     * ----------------------------------------------------------------------
     *  Selection UI Helpers
     * ----------------------------------------------------------------------
     */

    /**
     * Updates counters, "clear" button, and confirm button state
     * based on how many items are currently selected.
     */
    const updateSelectionUI = () => {
        const count = selectedItems.size;
        if (selectionCountEl) {
            selectionCountEl.textContent = String(count);
        }

        if (clearSelectionBtnEl) {
            if (count > 0) {
                clearSelectionBtnEl.classList.remove('hidden');
            } else {
                clearSelectionBtnEl.classList.add('hidden');
            }
        }

        // Disable "Use selected items" button when nothing is selected
        if (confirmBtnEl) {
            confirmBtnEl.disabled = count === 0;
        }
    };

    /**
     * Clears all selected items and removes highlight from all tiles.
     */
    const clearSelection = () => {
        selectedItems.clear();
        document
            .querySelectorAll('.media-picker-item')
            .forEach((el) =>
                el.classList.remove('ring-2', 'ring-indigo-500')
            );
        updateSelectionUI();
    };

    /**
     * ----------------------------------------------------------------------
     *  Applying the Selection back to the Form
     * ----------------------------------------------------------------------
     * Called when the user confirms their selection.
     * - Writes the selected values into the hidden input (comma-separated)
     * - Renders thumbnails in the preview container (if provided)
     */
    const applySelection = () => {
        if (!currentTargetInputId) {
            closePicker();
            return;
        }

        const targetInput = document.getElementById(currentTargetInputId);
        const previewContainer = currentPreviewContainerId
            ? document.getElementById(currentPreviewContainerId)
            : null;

        const items = Array.from(selectedItems.values());
        const ids = items.map((item) => item.id);
        const values = items
            .map((item) => {
                if (currentStoreValue === 'url') return item.url || '';
                if (currentStoreValue === 'path') return item.path || '';
                return item.id;
            })
            .filter((value) => value !== null && value !== undefined && String(value).trim() !== '');

        // Store selected values in hidden input:
        // - id mode   => "1" or "1,5,9"
        // - path mode => "media/2026/03/file.png"
        // - url mode  => "https://..."
        if (targetInput) {
            targetInput.value = isMultiple ? values.join(',') : (values[0] ?? '');
            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Render preview images (small thumbnails)
        if (previewContainer) {
            previewContainer.innerHTML = '';
            items.forEach((item) => {
                const wrapper = document.createElement('div');
                wrapper.className =
                    'relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900';

                const img = document.createElement('img');
                img.src = item.url;
                img.alt = item.name || '';
                img.className = 'w-full h-full object-cover';

                wrapper.appendChild(img);
                previewContainer.appendChild(wrapper);
            });
        }

        // Bridge event for custom integrations (full payload)
        window.dispatchEvent(
            new CustomEvent('media-picker-confirmed', {
                detail: {
                    items,
                    ids,
                    values,
                    storeValue: currentStoreValue,
                    targetInputId: currentTargetInputId,
                },
            })
        );

        // Backward-compatible event (single item + files list)
        const first = items[0] || null;
        if (first) {
            window.dispatchEvent(
                new CustomEvent('media-selected', {
                    detail: {
                        id: first.id,
                        url: first.url || '',
                        path: first.path || '',
                        name: first.name || '',
                        file: first,
                        files: items,
                        values,
                        storeValue: currentStoreValue,
                        targetInputId: currentTargetInputId,
                    },
                })
            );
        }

        closePicker();
    };

    /**
     * ----------------------------------------------------------------------
     *  Uploading Files from inside the Popup
     * ----------------------------------------------------------------------
     * This is used both by:
     * - Clicking "Upload New Image" button (file input)
     * - Drag & Drop area (drop event)
     */
    const uploadFilesFromPicker = async (files) => {
        if (!files || !files.length) return;
        if (!csrfToken) {
            console.error('CSRF token missing');
            showToast('تعذر رفع الملف: مشكلة في الحماية (CSRF).', 'error');
            return;
        }

        const formData = new FormData();
        Array.from(files).forEach((file) => formData.append('files[]', file));

        try {
            const res = await fetch(baseUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (!res.ok) {
                throw new Error('Upload failed');
            }

            const data = await res.json();

            let newlyUploaded = [];
            if (Array.isArray(data)) {
                newlyUploaded = data;
            } else if (Array.isArray(data.uploaded)) {
                newlyUploaded = data.uploaded;
            } else if (data && typeof data === 'object' && data.id) {
                newlyUploaded = [data];
            }

            showToast('تم رفع الصورة بنجاح.', 'success');

            // Auto-select uploaded items
            if (newlyUploaded.length > 0) {
                // In single-select mode, only use the last uploaded file
                if (!isMultiple) {
                    newlyUploaded = [newlyUploaded[newlyUploaded.length - 1]];
                }

                selectedItems.clear();

                newlyUploaded.forEach((item) => {
                    const imageUrl = item.url || `/storage/${item.file_path}`;
                    const name =
                        item.file_original_name || item.file_name || 'بدون اسم';

                    selectedItems.set(item.id, {
                        id: item.id,
                        url: imageUrl,
                        path: item.file_path || '',
                        name,
                        file_type: item.file_type,
                        mime_type: item.mime_type,
                    });
                });

                updateSelectionUI();
            }

            // Reload media grid to include the new uploads
            currentPage = 1;
            lastPage = 1;
            await loadMedia(1, false);
        } catch (e) {
            console.error(e);
            showToast('فشل رفع الصورة، حاول مرة أخرى.', 'error');
        }
    };

    /**
     * ----------------------------------------------------------------------
     *  Event Bindings
     * ----------------------------------------------------------------------
     */

    /**
     * Open buttons are handled through event delegation so the picker also works
     * for forms injected later (for example inline editors loaded via AJAX).
     */
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.btn-open-media-picker');
        if (!btn) {
            return;
        }

        const targetInputId = btn.dataset.targetInput;
        const previewContainerId = btn.dataset.targetPreview || null;
        const multiple = btn.dataset.multiple === 'true';
        const storeValue = btn.dataset.storeValue || 'id';

        if (!targetInputId) {
            console.warn('[MediaPicker] data-target-input is not defined on button:', btn);
            return;
        }

        openPicker({
            targetInputId,
            previewContainerId,
            multiple,
            storeValue,
        });
    });

    // Close modal via "Cancel" button
    if (cancelBtnEl) {
        cancelBtnEl.addEventListener('click', () => closePicker());
    }

    // Close modal via "X" button
    if (closeBtnEl) {
        closeBtnEl.addEventListener('click', () => closePicker());
    }

    // Close modal by clicking on the backdrop
    if (backdropEl) {
        backdropEl.addEventListener('click', () => closePicker());
    }

    // "Clear selection" button
    if (clearSelectionBtnEl) {
        clearSelectionBtnEl.addEventListener('click', (e) => {
            e.preventDefault();
            clearSelection();
        });
    }

    // "Use selected items" button
    if (confirmBtnEl) {
        confirmBtnEl.addEventListener('click', (e) => {
            e.preventDefault();
            applySelection();
        });
    }

    /**
     * Search input with debounce:
     * - Waits 400ms after user stops typing before firing a new request.
     */
    if (searchInputEl) {
        searchInputEl.addEventListener(
            'input',
            debounce((e) => {
                currentSearch = e.target.value.trim();
                currentPage = 1;
                loadMedia(1, false);
            }, 400)
        );
    }

    /**
     * Filter buttons:
     * - Change the media type (image, video, document, other, etc.)
     * - Reload the grid from page 1.
     */
    if (filterButtons.length) {
        filterButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                filterButtons.forEach((b) =>
                    b.classList.remove(
                        'bg-indigo-50',
                        'border-indigo-500',
                        'text-indigo-600'
                    )
                );
                btn.classList.add(
                    'bg-indigo-50',
                    'border-indigo-500',
                    'text-indigo-600'
                );

                currentFilterType = btn.dataset.type || '';
                currentPage = 1;
                loadMedia(1, false);
            });
        });
    }

    /**
     * "Load more" pagination button
     * - Loads the next page and appends items to the grid.
     */
    if (loadMoreBtnEl) {
        loadMoreBtnEl.addEventListener('click', () => {
            if (!isLoading && currentPage < lastPage) {
                loadMedia(currentPage + 1, true);
            }
        });
    }

    /**
     * Upload button inside the popup:
     * - Triggers the hidden file input.
     */
    if (uploadBtnEl && fileInputEl) {
        uploadBtnEl.addEventListener('click', () => {
            fileInputEl.click();
        });

        fileInputEl.addEventListener('change', (e) => {
            uploadFilesFromPicker(e.target.files);
            // Reset input to allow re-uploading the same file if needed
            e.target.value = '';
        });
    }

    /**
     * ----------------------------------------------------------------------
     *  Drag & Drop inside the popup (media-picker-dropzone)
     * ----------------------------------------------------------------------
     * Supports:
     *  - Clicking on the dropzone to open the file picker
     *  - Dragging files over the zone
     *  - Dropping files to upload them directly
     */
    if (popupDropzoneEl && fileInputEl) {
        // Clicking the dropzone opens the file picker
        popupDropzoneEl.addEventListener('click', (e) => {
            // Allow clicking anywhere inside the dropzone
            if (e.target === popupDropzoneEl || popupDropzoneEl.contains(e.target)) {
                fileInputEl.click();
            }
        });

        // Highlight dropzone on drag enter/over
        ['dragenter', 'dragover'].forEach(eventName => {
            popupDropzoneEl.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                popupDropzoneEl.classList.add('border-indigo-400', 'bg-indigo-50');
            });
        });

        // Remove highlight on drag leave/drop
        ['dragleave', 'drop'].forEach(eventName => {
            popupDropzoneEl.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                popupDropzoneEl.classList.remove('border-indigo-400', 'bg-indigo-50');
            });
        });

        // Handle dropped files
        popupDropzoneEl.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt?.files;
            if (files && files.length) {
                uploadFilesFromPicker(files);
            }
        });
    }
});
