/*
 * HIRFAH admin categories list (AD-UI-CAT-01E-3A), driving resources/views/dashboard/categories/index.blade.php,
 * and the category form (AD-UI-CAT-01E-3B, see initCategoryForm below).
 *
 * - Delete asks first, in a native <dialog>: Cancel is the default button, Escape closes it, focus returns to
 *   the delete button. Confirming submits the dialog's form (POST + DELETE + CSRF) to the chosen row with that
 *   row's server-issued confirmation (N-CAT-1). Without this script the row's form opens the server
 *   confirmation page instead; the server decides either way (DEC-CAT-Q3).
 * - A thumbnail that fails to load is replaced by the placeholder, never shown broken.
 * - Result messages can be dismissed.
 *
 * Every text comes from the page (t()); the category name goes through textContent only.
 */

/** The confirmation sentence in parts, so the name can sit in its own <bdi>: [before, after]. */
export function splitTemplate(template) {
    const [before, ...rest] = String(template ?? '').split(':name');

    return [before, rest.join(':name')];
}

/**
 * What the dialog's DELETE form sends for a row (N-CAT-1): its destroy URL and its server-issued confirmation.
 * Null when the row has neither, so the row's own form (the confirmation page) is used instead.
 */
export function deleteTarget(dataset = {}) {
    const action = String(dataset.destroy ?? '');
    const confirmation = String(dataset.confirmation ?? '');

    return action !== '' && confirmation !== '' ? { action, confirmation } : null;
}

export function initCategoriesList(doc = document) {
    const dialog = doc.getElementById('category-delete-dialog');
    const text = doc.getElementById('category-delete-text');
    const deleteForm = dialog?.querySelector('form[data-category-delete-form]');
    const confirmationInput = deleteForm?.querySelector('[data-category-delete-confirmation]');
    let pending = null;

    if (dialog && text && deleteForm && confirmationInput) {
        const [before, after] = splitTemplate(text.dataset.template);

        doc.querySelectorAll('form[data-category-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const target = deleteTarget(form.dataset);

                // No confirmation for this row: let the row's form open the server confirmation page.
                if (!target) {
                    return;
                }

                event.preventDefault();
                pending = { target, trigger: form.querySelector('button[type="submit"]') };

                const name = doc.createElement('bdi');
                name.textContent = form.dataset.name || '';
                text.replaceChildren(before, name, after);
                dialog.showModal();
            });
        });

        dialog.querySelector('[data-category-delete-cancel]').addEventListener('click', () => dialog.close());
        deleteForm.addEventListener('submit', (event) => {
            // Only the chosen row's DELETE, and only once.
            if (!pending || deleteForm.dataset.sending === 'true') {
                event.preventDefault();

                return;
            }

            deleteForm.action = pending.target.action;
            confirmationInput.value = pending.target.confirmation;
            deleteForm.dataset.sending = 'true';
            deleteForm.querySelector('[data-category-delete-confirm]')?.setAttribute('aria-disabled', 'true');
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
        dialog.addEventListener('close', () => {
            pending?.trigger?.focus();
            pending = null;
        });
    }

    // Broken thumbnails fall back to the placeholder (also when the error happened before this script ran).
    doc.querySelectorAll('img[data-category-thumb]').forEach((img) => {
        const fallback = () => {
            img.hidden = true;
            img.nextElementSibling?.removeAttribute('hidden');
        };

        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
            fallback();
        } else {
            img.addEventListener('error', fallback, { once: true });
        }
    });

    doc.querySelectorAll('[data-cat-alert-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('[data-cat-alert]')?.remove());
    });
}

/** One submission at a time: start() is true only for the first call until reset(). */
export function createSubmitGuard() {
    let busy = false;

    return {
        start() {
            if (busy) {
                return false;
            }

            busy = true;

            return true;
        },
        reset() {
            busy = false;
        },
        get busy() {
            return busy;
        },
    };
}

/*
 * Category form (AD-UI-CAT-01E-3B, resources/views/dashboard/categories/form.blade.php): the first submit shows
 * the saving state and any further submit is ignored; the error summary takes focus after a failed save.
 * Without this script the form still posts normally; the server validates either way.
 */
export function initCategoryForm(doc = document, win = typeof window !== 'undefined' ? window : null) {
    const form = doc.querySelector('form[data-category-form]');

    if (!form) {
        return null;
    }

    const button = form.querySelector('[data-category-submit]');
    const label = button?.querySelector('span');
    const idleLabel = label?.textContent ?? '';
    const guard = createSubmitGuard();

    const restore = () => {
        guard.reset();
        form.removeAttribute('aria-busy');

        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-disabled');
        }

        if (label) {
            label.textContent = idleLabel;
        }
    };

    form.addEventListener('submit', (event) => {
        if (!guard.start()) {
            event.preventDefault();

            return;
        }

        form.setAttribute('aria-busy', 'true');

        if (button) {
            button.setAttribute('aria-disabled', 'true');
            // Disabled after the submission has started, so the form still posts.
            setTimeout(() => {
                button.disabled = true;
            }, 0);
        }

        if (label && button?.dataset.saving) {
            label.textContent = button.dataset.saving;
        }
    });

    // Back/forward cache: a page restored after submitting is usable again.
    win?.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            restore();
        }
    });

    doc.getElementById('category-form-errors')?.focus();

    return { guard, restore };
}

if (typeof document !== 'undefined') {
    const init = () => {
        initCategoriesList();
        initCategoryForm();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}
