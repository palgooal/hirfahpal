// Password visibility toggle for the admin login page. It only switches the
// input type; the password is never read, stored or sent from here.
export function initPasswordToggles(doc = document) {
    doc.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = doc.getElementById(button.getAttribute('aria-controls'));

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            button.setAttribute('aria-label', reveal ? button.dataset.labelHide : button.dataset.labelShow);
            button.querySelector('[data-icon-show]')?.classList.toggle('hidden', reveal);
            button.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !reveal);
        });
    });
}

// Opt-in submit lock (VEN-F1, form[data-submit-lock]): the first submit posts
// normally and shows the loading label; any further submit (double click,
// Enter) is cancelled. Forms without the attribute are left alone, and without
// this script the form still posts normally.
export function initSubmitLocks(doc = document, win = typeof window !== 'undefined' ? window : null) {
    const locks = [];

    doc.querySelectorAll('form[data-submit-lock]').forEach((form) => {
        const button = form.querySelector('button[type="submit"]');
        const label = button?.querySelector('span');
        const status = form.querySelector('[data-submit-status]');
        const idleLabel = label?.textContent ?? '';
        let busy = false;

        const restore = () => {
            busy = false;
            form.removeAttribute('aria-busy');
            button?.removeAttribute('aria-disabled');

            if (label) {
                label.textContent = idleLabel;
            }

            if (status) {
                status.textContent = '';
            }
        };

        form.addEventListener('submit', (event) => {
            if (busy) {
                event.preventDefault();

                return;
            }

            // The browser does not fire submit for an invalid form; this keeps it that way.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            busy = true;
            form.setAttribute('aria-busy', 'true');
            // aria-disabled only: the button keeps focus and the submission is not cancelled.
            button?.setAttribute('aria-disabled', 'true');

            const loading = button?.dataset.loadingLabel;

            if (loading) {
                if (label) {
                    label.textContent = loading;
                }

                if (status) {
                    status.textContent = loading;
                }
            }
        });

        locks.push({ form, restore });
    });

    // Back/forward cache: a page restored after submitting is usable again.
    win?.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            locks.forEach((lock) => lock.restore());
        }
    });

    return locks;
}

if (typeof document !== 'undefined') {
    initPasswordToggles(document);
    initSubmitLocks(document, window);
}
