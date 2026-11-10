/**
 * PAGES-04C: the content pages list (resources/views/dashboard/content-pages/index.blade.php).
 * - [data-copy-url]: copies a published page's public address; the result is announced in [data-copy-status]. The
 *   Clipboard API first; on a page served over plain http (no Clipboard API) a hidden field and the copy command.
 * - [data-delete-url]: opens the one [data-delete-dialog], filled with the page's title and address; its form deletes
 *   the page. The server checks again that the page is still a custom draft.
 * Both kinds of button start hidden and are revealed here. Every text comes from the page.
 */

/** Replaces :title, :address and :url in a text from the page. */
export function fillTemplate(template, values) {
    return String(template ?? '').replace(/:(title|address|url)\b/g, (match, key) => values[key] ?? match);
}

export async function copyText(text, { clipboard = globalThis.navigator?.clipboard, doc = globalThis.document } = {}) {
    if (clipboard?.writeText) {
        try {
            await clipboard.writeText(text);

            return true;
        } catch {
            // Refused (permissions, not focused): try the copy command below.
        }
    }

    if (!doc?.body || typeof doc.execCommand !== 'function') {
        return false;
    }

    const field = doc.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    doc.body.appendChild(field);
    field.select();

    try {
        return doc.execCommand('copy') === true;
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

export function initCopyButtons(list, status, { copy = copyText } = {}) {
    list.querySelectorAll('[data-copy-url]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            const url = button.dataset.copyUrl;
            const copied = await copy(url);

            if (status) {
                status.textContent = '';
                status.textContent = copied ? list.dataset.copied : fillTemplate(list.dataset.copyFailed, { url });
            }
        });
    });
}

export function initDeleteDialog(dialog, buttons) {
    const form = dialog.querySelector('[data-delete-form]');
    const text = dialog.querySelector('[data-delete-text]');
    const cancel = dialog.querySelector('[data-dialog-cancel]');
    const confirm = dialog.querySelector('[data-delete-confirm]');
    let opener = null;

    buttons.forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            opener = button;
            form.setAttribute('action', button.dataset.deleteUrl);
            text.textContent = fillTemplate(text.dataset.template, { title: button.dataset.deleteTitle, address: button.dataset.deleteAddress });
            confirm.disabled = false;
            dialog.showModal();
            // Cancel first: Enter or a stray click never deletes.
            cancel.focus();
        });
    });

    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => opener?.focus());
    form.addEventListener('submit', () => {
        confirm.disabled = true;
    });
}

function start() {
    const list = document.querySelector('[data-content-pages-list]');

    if (!list) {
        return;
    }

    initCopyButtons(list, document.querySelector('[data-copy-status]'));

    const dialog = document.querySelector('[data-delete-dialog]');

    if (dialog && typeof dialog.showModal === 'function') {
        initDeleteDialog(dialog, [...list.querySelectorAll('[data-delete-url]')]);
    }
}

if (typeof document !== 'undefined') {
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
