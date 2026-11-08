/*
 * HIRFAH admin settings tabs (SETTINGS-UX-01), driving resources/views/dashboard/settings/index.blade.php.
 *
 * The same WAI-ARIA tabs pattern as the product details (admin-product-show.js, whose key handling it reuses):
 * automatic activation, arrow keys in the reading direction, Home / End, roving tabindex, only the selected panel
 * shown. Site availability keeps its own form; the general and footer panels share the one settings form, which is
 * hidden while the site availability tab is selected. Without this script the tab list stays hidden and every panel
 * is shown in turn.
 *
 * The first tab shown: the one the server names (validation errors, site availability message), else the URL
 * fragment (#site-availability, #settings-panel-KEY or #settings-tab-KEY), else the first. The settings form's action
 * carries the selected tab as its fragment, which the browser keeps through the redirect after saving.
 *
 * SETTINGS-UX-02B: a fragment reached while already on the page (the header's site state badge, an error summary
 * link to a field such as #footer[x_url]) opens its tab too: on hashchange, and on the click itself, which also covers
 * a link to the fragment already in the address bar (no hashchange then).
 *
 * SETTINGS-UX-03: the footer groups are native disclosures (<details>, keyboard and state handled by the browser;
 * a closed group's fields are still submitted). They are rendered open, so every field shows without JavaScript; here
 * all are closed except the first and those holding validation errors. Several may stay open. Reaching a field (error
 * summary link, a required field the browser rejects) opens its group.
 */

import { nextIndex } from './admin-product-show.js';

/** The tab key a URL fragment points at, or null. */
export function keyFromHash(hash, keys) {
    const value = String(hash ?? '');

    if (value === '#site-availability') {
        return keys.includes('availability') ? 'availability' : null;
    }

    const match = /^#settings-(?:panel|tab)-([a-z]+)$/.exec(value);

    return match && keys.includes(match[1]) ? match[1] : null;
}

/** The element id a fragment names (#footer%5Bx_url%5D → footer[x_url]), or null. */
export function idFromHash(hash) {
    const value = String(hash ?? '');

    if (value.length < 2 || value[0] !== '#') {
        return null;
    }

    try {
        return decodeURIComponent(value.slice(1));
    } catch {
        return value.slice(1);
    }
}

/** Closes every footer group except those marked to stay open or holding errors. Returns the groups. */
export function initSettingsGroups(root) {
    const groups = root ? [...root.querySelectorAll('[data-settings-group]')] : [];

    groups.forEach((group) => {
        group.open = group.dataset.settingsGroupOpen === 'true' || Number(group.dataset.settingsGroupErrors ?? 0) > 0;
    });

    return groups;
}

/** Opens the disclosure holding an element, if it is closed. */
export function openGroupOf(element) {
    const group = element?.closest?.('details');

    if (group && !group.open) {
        group.open = true;
    }
}

/** The index of the first tab: the server's choice, else the fragment's, else the first. */
export function initialIndex(keys, initial = '', hash = '') {
    const key = keys.includes(initial) ? initial : keyFromHash(hash, keys);
    const index = key ? keys.indexOf(key) : -1;

    return index >= 0 ? index : 0;
}

export function initSettingsTabs(root, { direction = 'ltr', hash = '', history = null, win = null } = {}) {
    const tablist = root?.querySelector('[data-settings-tablist]');
    const tabs = tablist ? [...tablist.querySelectorAll('[role="tab"]')] : [];
    const panels = tabs.map((tab) => root.querySelector(`#${tab.getAttribute('aria-controls')}`));
    const form = root?.querySelector('[data-settings-form]') ?? null;
    // The settings form with its messages (error summary, success notice); the form alone if there is no wrapper.
    const formArea = root?.querySelector('[data-settings-form-area]') ?? form;

    if (tabs.length === 0 || panels.some((panel) => !panel)) {
        return null; // Leave the page as it is: every panel shown.
    }

    const keys = tabs.map((tab) => tab.dataset.settingsTab);
    const action = form ? String(form.getAttribute('action') ?? '').split('#')[0] : '';

    panels.forEach((panel, index) => {
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tabs[index].id);
        panel.setAttribute('tabindex', '0');
    });

    const select = (index, { focus = false, remember = false } = {}) => {
        tabs.forEach((tab, i) => {
            const selected = i === index;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.setAttribute('tabindex', selected ? '0' : '-1');
            panels[i].hidden = !selected;
        });

        if (form) {
            const inForm = form.contains(panels[index]);
            formArea.hidden = !inForm; // Its messages and save button belong to the general and footer panels only.

            if (inForm) {
                form.setAttribute('action', `${action}#settings-panel-${keys[index]}`);
            }
        }

        if (remember && history) {
            history.replaceState(null, '', `#settings-panel-${keys[index]}`);
        }

        if (focus) {
            tabs[index].focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(index, { remember: true }));
        tab.addEventListener('keydown', (event) => {
            const target = nextIndex(event.key, index, tabs.length, direction);

            if (target !== null) {
                event.preventDefault();
                select(target, { focus: true, remember: true });
            }
        });
    });

    // A required field left empty in a hidden panel: open its panel so the browser can point at it.
    form?.addEventListener('invalid', (event) => {
        const index = panels.findIndex((panel) => panel.contains(event.target));

        if (index >= 0 && panels[index].hidden) {
            select(index);
        }

        openGroupOf(event.target);
    }, true);

    // Opens the tab a fragment names, or the tab holding the element it names (focused). True when it did.
    const reveal = (fragment) => {
        const key = keyFromHash(fragment, keys);

        if (key) {
            select(keys.indexOf(key));

            return true;
        }

        const id = idFromHash(fragment);
        const target = id ? win?.document?.getElementById(id) : null;
        const index = target ? panels.findIndex((panel) => panel.contains(target)) : -1;

        if (index < 0) {
            return false;
        }

        select(index);
        openGroupOf(target);
        target.focus?.();

        return true;
    };

    if (win) {
        win.addEventListener('hashchange', () => reveal(win.location.hash));
        win.document?.addEventListener('click', (event) => {
            const link = event.target?.closest?.('a[href*="#"]');

            if (!link) {
                return;
            }

            const url = new URL(link.href, win.location.href);

            // Only links to this page; before the browser scrolls, so the target is no longer hidden.
            if (url.origin === win.location.origin && url.pathname === win.location.pathname && url.hash) {
                reveal(url.hash);
            }
        });
    }

    select(initialIndex(keys, root.dataset?.initialTab ?? '', hash));
    tablist.hidden = false;
    root.classList?.add('is-tabbed');

    return { select };
}

if (typeof document !== 'undefined') {
    const start = () => {
        const root = document.querySelector('[data-settings-tabs]');

        initSettingsGroups(root);
        initSettingsTabs(root, {
            direction: document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
            hash: window.location.hash,
            history: window.history,
            win: window,
        });
    };

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
