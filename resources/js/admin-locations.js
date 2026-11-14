/*
 * HIRFAH admin locations page (LOC-UI-7B-2), driving resources/views/dashboard/settings/locations.blade.php.
 *
 * - The add links ([data-location-open]) open their dialog as a modal instead of loading ?add=…; Cancel and Close
 *   ([data-location-close]) close it instead of loading the page. Escape closes it (native); a click on the
 *   backdrop does not (D4), so typed text is not lost. Focus goes to the first field to fill and comes back to the
 *   link that opened the dialog. A city's shortcut (data-city-id) chooses that city first (D3).
 * - A dialog the server opened (after a failed save, or an add link opened in a new tab) becomes a modal, focus on
 *   its first invalid field; its values and messages stay the server's.
 * - Each form posts once (createSubmitGuard), showing its saving label; a page restored from the back/forward cache
 *   can be submitted again.
 * - The search filters the cities and the unassigned group by city or governorate name, in the page (no request):
 *   letter case, diacritics and the Arabic letter variants are ignored. The count is announced in the status line.
 *
 * Without this script (or without <dialog> support) every link still works through the server.
 * Every text comes from the page; names are only read from data attributes, never written as HTML.
 */
import { createSubmitGuard } from './admin-categories.js';

/**
 * The form of a name or a query that the search compares: lower case, without diacritics (Latin accents, Arabic
 * harakat, tatweel), alef with hamza or madda as alef (NFKD splits off the mark), teh marbuta as heh, alef maksura
 * as yeh, spaces collapsed.
 */
export function normalizeSearch(value) {
    return String(value ?? '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f\u0610-\u061a\u064b-\u065f\u0670\u06d6-\u06ed\u0640]/g, '')
        .replace(/\u0671/g, '\u0627')
        .replace(/\u0629/g, '\u0647')
        .replace(/\u0649/g, '\u064a')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** Whether one of the names (one per line: a city and its governorates) contains the query; an empty query matches. */
export function matchesSearch(names, query) {
    const needle = normalizeSearch(query);

    if (needle === '') {
        return true;
    }

    return String(names ?? '').split('\n').some((name) => normalizeSearch(name).includes(needle));
}

/** Hides the groups that do not match and returns how many are shown. */
export function filterGroups(groups, query) {
    let shown = 0;

    groups.forEach((group) => {
        const match = matchesSearch(group.dataset.locationNames, query);
        group.hidden = !match;
        shown += match ? 1 : 0;
    });

    return shown;
}

export function fillCount(template, count) {
    return String(template ?? '').replace(':count', String(count));
}

/** Where focus goes in a dialog: its first invalid field, else the first empty one, else the first field. */
export function firstField(dialog) {
    const invalid = dialog.querySelector('[aria-invalid="true"]');

    if (invalid) {
        return invalid;
    }

    const fields = Array.from(dialog.querySelectorAll('select, input:not([type="hidden"])'));

    return fields.find((field) => field.value === '') ?? fields[0] ?? null;
}

/** A click the browser should handle itself (a new tab or window): the link then opens the dialog on the server. */
function opensElsewhere(event) {
    return event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey;
}

export function initLocationsPage(doc = document, win = typeof window !== 'undefined' ? window : null) {
    const page = doc.getElementById('admin-locations');

    if (!page) {
        return null;
    }

    const dialogs = Array.from(page.querySelectorAll('dialog.admin-locations-dialog'));
    const modal = dialogs.length > 0 && dialogs.every((dialog) => typeof dialog.showModal === 'function');
    const returnTo = new Map();
    const mainTrigger = (dialog) => page.querySelector(`.admin-locations-actions [aria-controls="${dialog.id}"]`);

    const open = (dialog, trigger) => {
        // A dialog the server opened is shown in the page, not as a modal: reopened as one.
        if (dialog.open) {
            dialog.close();
        }

        returnTo.set(dialog, trigger ?? mainTrigger(dialog));
        dialog.showModal();
        firstField(dialog)?.focus();
    };

    if (modal) {
        page.querySelectorAll('[data-location-open]').forEach((trigger) => {
            const dialog = doc.getElementById(trigger.getAttribute('aria-controls'));

            if (!dialog) {
                return;
            }

            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.addEventListener('click', (event) => {
                if (opensElsewhere(event)) {
                    return;
                }

                event.preventDefault();

                const select = dialog.querySelector('select[name="city_id"]');

                if (select && trigger.dataset.cityId) {
                    select.value = trigger.dataset.cityId;
                }

                open(dialog, trigger);
            });
        });

        dialogs.forEach((dialog) => {
            const closes = Array.from(dialog.querySelectorAll('[data-location-close]'));

            closes.forEach((link) => {
                link.addEventListener('click', (event) => {
                    if (opensElsewhere(event)) {
                        return;
                    }

                    event.preventDefault();
                    dialog.close();
                });
            });

            // No click handler on the backdrop (D4): only Cancel, Close and Escape close a form dialog.
            dialog.addEventListener('close', () => {
                // The close of a server-opened dialog that was just reopened as a modal.
                if (dialog.open) {
                    return;
                }

                const trigger = returnTo.get(dialog);
                returnTo.delete(dialog);

                // An add link's ?add=… would reopen the dialog on reload: back to the page's own address.
                const home = closes[0]?.getAttribute('href');

                if (home && win?.location?.search && typeof win.history?.replaceState === 'function') {
                    win.history.replaceState(win.history.state, '', home);
                }

                trigger?.focus();
            });
        });

        const opened = dialogs.find((dialog) => dialog.open);

        if (opened) {
            open(opened, mainTrigger(opened));
        }
    }

    page.querySelectorAll('[data-add-governorate-row]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.admin-cat-form-field');
            const inputs = field?.querySelector('[data-governorate-inputs]');
            const template = field?.querySelector('template[data-governorate-row-template]');

            if (!inputs || !template) {
                return;
            }

            const row = template.content.firstElementChild?.cloneNode(true);

            if (!(row instanceof Element)) {
                return;
            }

            inputs.insertBefore(row, button);
            row.querySelector('input')?.focus();
        });
    });

    const forms = Array.from(page.querySelectorAll('form[data-location-form]')).map((form) => {
        const button = form.querySelector('button[type="submit"]');
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

        // The browser's own checks (required, maxlength) run before this event: an invalid form never locks.
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

        return { form, guard, restore };
    });

    // Back/forward cache: a page restored after submitting is usable again.
    win?.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            forms.forEach(({ restore }) => restore());
        }
    });

    const search = page.querySelector('[data-location-search]');
    const input = search?.querySelector('input[type="search"]');
    const status = search?.querySelector('[role="status"]');
    const groups = Array.from(page.querySelectorAll('[data-location-group]'));
    const noResults = page.querySelector('[data-location-no-results]');
    let update = null;

    if (search && input && groups.length > 0) {
        update = () => {
            const searching = normalizeSearch(input.value) !== '';
            const shown = filterGroups(groups, input.value);

            if (status) {
                status.textContent = searching ? fillCount(status.dataset.template, shown) : '';
            }

            if (noResults) {
                noResults.hidden = !searching || shown > 0;
            }
        };

        search.hidden = false;
        input.addEventListener('input', update);

        // A value the browser restored (back/forward) filters at once.
        if (input.value) {
            update();
        }
    }

    page.querySelectorAll('[data-location-alert-close]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => button.closest('[data-location-alert]')?.remove());
    });

    return { modal, open, forms, update };
}

if (typeof document !== 'undefined') {
    const init = () => initLocationsPage();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}
