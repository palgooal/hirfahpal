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

/** FOOTER-SOCIAL-02B: the order after moving one platform up or down (unchanged at either end). */
export function moveKey(order, key, direction) {
    const from = order.indexOf(key);
    const to = direction === 'up' ? from - 1 : from + 1;

    if (from < 0 || to < 0 || to >= order.length) {
        return order;
    }

    const next = [...order];
    [next[from], next[to]] = [next[to], next[from]];

    return next;
}

/** A message with its :platform, :position and :count filled in. */
export function fillText(template, values) {
    return String(template ?? '').replace(/:(platform|position|count)/g, (match, name) => (values[name] ?? match));
}

/**
 * FOOTER-SOCIAL-02B: the footer's social links in the settings form (partials/social-links.blade.php). Without this
 * script every platform's row is shown with an order field. Here only the added platforms are shown; Move up / Move
 * down / Remove buttons and an Add platform control manage them, the order fields are hidden and kept in step (1 to n
 * for the added platforms, then the others), and each change is announced in the live region. Nothing is submitted:
 * the buttons are type="button" and the form posts the rows as they are.
 */
export function initSocialLinks(root) {
    const list = root?.querySelector('[data-social-list]');

    if (!list) {
        return null;
    }

    const rows = [...list.querySelectorAll('[data-social-row]')];
    const byKey = new Map(rows.map((row) => [row.dataset.socialRow, row]));
    const select = root.querySelector('[data-social-add-select]');
    const addBox = root.querySelector('[data-social-add]');
    const empty = root.querySelector('[data-social-empty]');
    const status = root.querySelector('[data-social-status]');
    const texts = { moved: root.dataset?.textMoved, added: root.dataset?.textAdded, removed: root.dataset?.textRemoved };
    let shown = rows.filter((row) => row.dataset.socialConfigured === '1').map((row) => row.dataset.socialRow);

    const part = (row, selector) => row.querySelector(selector);
    const announce = (template, key) => {
        if (status) {
            status.textContent = fillText(template, {
                platform: byKey.get(key)?.dataset.socialName ?? key,
                position: shown.indexOf(key) + 1,
                count: shown.length,
            });
        }
    };

    const render = () => {
        const hidden = rows.map((row) => row.dataset.socialRow).filter((key) => !shown.includes(key));

        [...shown, ...hidden].forEach((key, index) => {
            const row = byKey.get(key);
            const at = shown.indexOf(key);
            const up = part(row, '[data-social-move="up"]');
            const down = part(row, '[data-social-move="down"]');
            const position = part(row, '[data-social-position]');

            list.appendChild(row);
            row.hidden = at < 0;
            row.dataset.socialConfigured = at < 0 ? '0' : '1';

            if (position) {
                position.value = String(index + 1);
            }
            if (up) {
                up.disabled = at <= 0;
            }
            if (down) {
                down.disabled = at < 0 || at === shown.length - 1;
            }
        });

        if (select) {
            [...select.options].filter((option) => option.value).forEach((option) => {
                option.hidden = shown.includes(option.value);
                option.disabled = option.hidden;
            });

            if (shown.includes(select.value)) {
                select.value = '';
            }
        }

        if (addBox) {
            addBox.hidden = shown.length === rows.length;
        }
        if (empty) {
            empty.hidden = shown.length > 0;
        }
    };

    const move = (key, direction) => {
        shown = moveKey(shown, key, direction);
        render();

        const row = byKey.get(key);
        const pressed = part(row, `[data-social-move="${direction}"]`);
        const other = part(row, `[data-social-move="${direction === 'up' ? 'down' : 'up'}"]`);
        (pressed && !pressed.disabled ? pressed : other)?.focus();
        announce(texts.moved, key);
    };

    const remove = (key) => {
        const row = byKey.get(key);
        const url = part(row, '[data-social-url]');
        const enabled = part(row, '[data-social-enabled]');

        if (url) {
            url.value = ''; // A platform without an address is not saved.
        }
        if (enabled) {
            enabled.checked = true;
        }

        announce(texts.removed, key);
        shown = shown.filter((item) => item !== key);
        render();
        (addBox && !addBox.hidden ? select : null)?.focus();
    };

    const add = (key) => {
        if (!byKey.has(key) || shown.includes(key)) {
            return;
        }

        const row = byKey.get(key);
        const enabled = part(row, '[data-social-enabled]');

        if (enabled) {
            enabled.checked = true;
        }

        shown = [...shown, key];
        render();
        part(row, '[data-social-url]')?.focus();
        announce(texts.added, key);
    };

    rows.forEach((row) => {
        const key = row.dataset.socialRow;
        const order = part(row, '[data-social-order-field]');
        const actions = part(row, '[data-social-actions]');

        if (order) {
            order.hidden = !part(order, '.is-invalid'); // Kept in view only when it holds an error.
        }
        if (actions) {
            actions.hidden = false;
        }

        part(row, '[data-social-move="up"]')?.addEventListener('click', () => move(key, 'up'));
        part(row, '[data-social-move="down"]')?.addEventListener('click', () => move(key, 'down'));
        part(row, '[data-social-remove]')?.addEventListener('click', () => remove(key));
    });

    root.querySelector('[data-social-add-button]')?.addEventListener('click', () => add(select?.value ?? ''));
    render();

    return { add, remove, move, shown: () => [...shown] };
}

/**
 * The index of the first tab: the server's choice, else the fragment's, else the suggested one (FOOTER-SOCIAL-02D: a
 * saved social link needing attention), else the first.
 */
export function initialIndex(keys, initial = '', hash = '', suggested = '') {
    const key = keys.includes(initial) ? initial : (keyFromHash(hash, keys) ?? (keys.includes(suggested) ? suggested : null));
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

    select(initialIndex(keys, root.dataset?.initialTab ?? '', hash, root.dataset?.suggestedTab ?? ''));
    tablist.hidden = false;
    root.classList?.add('is-tabbed');

    return { select };
}

if (typeof document !== 'undefined') {
    const start = () => {
        const root = document.querySelector('[data-settings-tabs]');

        initSettingsGroups(root);
        initSocialLinks(document.querySelector('[data-social-links]'));
        initSettingsTabs(root, {
            direction: document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
            hash: window.location.hash,
            history: window.history,
            win: window,
        });
    };

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
