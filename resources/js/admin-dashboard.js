/*
 * HIRFAH admin dashboard shell (AD-UI-01C-4A): desktop collapse to an icon rail with
 * tooltips, the mobile drawer from the start side (RTL and LTR), and the header menus
 * (AD-UI-01D-4). State lives in the DOM only: nothing is read from or written to localStorage,
 * so the sidebar is expanded again after every reload.
 *
 * Inactive by default. It takes over only when the sidebar opts in with
 * data-admin-sidebar="managed" AND Able Pro's script.js is not loaded; while script.js
 * still drives #sidebar-hide and #mobile-collapse, no listener, overlay or tooltip is
 * created (AD-UI-01C-4B switches the layout over).
 */

const desktop = window.matchMedia('(min-width: 1024px)');

const COLLAPSED = 'admin-sidebar-collapsed';
const DRAWER_OPEN = 'mob-sidebar-active';

/** True only when the sidebar opted in and the legacy template script is absent. */
export function shouldManageSidebar(doc = document, win = window) {
    const sidebar = doc.getElementById('admin-sidebar');

    if (!sidebar || sidebar.dataset.adminSidebar !== 'managed') {
        return false;
    }

    // script.js declares these as global functions; both scripts must never drive the sidebar together.
    if (typeof win.menu_click === 'function' || typeof win.rm_menu === 'function') {
        console.warn('[admin-dashboard] Sidebar opted in while Able Pro script.js is loaded; keeping the legacy behaviour.');

        return false;
    }

    return true;
}

/*
 * component.js (collapse toggles, alert dismiss) calls the global slideUp/slideDown that
 * script.js defines. Without script.js they are provided here with the same contract: the
 * target is shown with an inline display value, or hidden; component.js then cleans up.
 */
export function provideSlideHelpers(win = window) {
    if (typeof win.slideUp !== 'function') {
        win.slideUp = (target) => {
            target.style.display = 'none';
        };
    }

    if (typeof win.slideDown !== 'function') {
        win.slideDown = (target) => {
            target.style.removeProperty('display');

            if (win.getComputedStyle(target).display === 'none') {
                target.style.display = 'block';
            }
        };
    }

    // component.js also calls this from script.js when it closes a modal.
    if (typeof win.removeClassByPrefix !== 'function') {
        win.removeClassByPrefix = (node, prefix) => {
            [...node.classList].filter((value) => value.startsWith(prefix)).forEach((value) => node.classList.remove(value));
        };
    }
}

/** Removes the template pre-loader once the page has loaded (script.js did this before). */
export function removeLoader(doc = document, win = window) {
    const remove = () => doc.querySelector('.loader-bg')?.remove();

    if (doc.readyState === 'complete') {
        remove();
    } else {
        win.addEventListener('load', remove, { once: true });
    }
}

function setLabel(button, label) {
    if (button && label) {
        button.setAttribute('aria-label', label);
    }
}

/** Single tooltip element, outside the sidebar so its overflow never clips it. */
function createTooltip(doc) {
    const tooltip = doc.createElement('div');
    tooltip.className = 'admin-sidebar-tooltip';
    // The link keeps its own (visually hidden) name, so the tooltip is hidden from assistive technology.
    tooltip.setAttribute('aria-hidden', 'true');
    tooltip.hidden = true;
    doc.body.appendChild(tooltip);

    return tooltip;
}

// Time allowed to move the pointer from a link onto its tooltip (WCAG 1.4.13, hoverable).
const TOOLTIP_HIDE_DELAY = 150;

export function initSidebar(doc = document, win = window) {
    const sidebar = doc.getElementById('admin-sidebar');
    const collapseButton = doc.getElementById('sidebar-hide');
    const drawerButton = doc.getElementById('mobile-collapse');
    const overlay = sidebar.querySelector('[data-admin-overlay]');
    const isRtl = () => doc.documentElement.dir === 'rtl';

    const isCollapsed = () => sidebar.classList.contains(COLLAPSED);
    const isDrawerOpen = () => sidebar.classList.contains(DRAWER_OPEN);

    [collapseButton, drawerButton].forEach((button) => button?.setAttribute('aria-controls', sidebar.id));

    // ---- Tooltips (collapsed rail only) ----
    // Shown on hover and on keyboard focus; stays while the pointer is over the link or the
    // tooltip itself; Escape dismisses it.
    const tooltip = createTooltip(doc);
    let hideTimer = null;
    let anchor = null;

    const cancelHide = () => {
        win.clearTimeout(hideTimer);
        hideTimer = null;
    };

    const hideTooltip = () => {
        cancelHide();
        tooltip.hidden = true;
        anchor = null;
    };

    const scheduleHide = () => {
        cancelHide();
        hideTimer = win.setTimeout(() => {
            // Keyboard focus keeps the tooltip of the focused link.
            if (anchor && doc.activeElement === anchor) {
                return;
            }

            hideTooltip();
        }, TOOLTIP_HIDE_DELAY);
    };

    const showTooltip = (link) => {
        const label = link.querySelector('.pc-mtext')?.textContent.trim();

        if (!isCollapsed() || !desktop.matches || !label) {
            return;
        }

        cancelHide();
        anchor = link;
        tooltip.textContent = label;
        tooltip.hidden = false;

        const rect = link.getBoundingClientRect();
        const gap = 8;
        const margin = 8;
        const top = rect.top + rect.height / 2 - tooltip.offsetHeight / 2;
        tooltip.style.top = `${Math.round(Math.min(Math.max(top, margin), win.innerHeight - tooltip.offsetHeight - margin))}px`;

        // Inline-end side of the rail: right of it in LTR, left of it in RTL.
        if (isRtl()) {
            tooltip.style.left = '';
            tooltip.style.right = `${Math.round(doc.documentElement.clientWidth - rect.left + gap)}px`;
        } else {
            tooltip.style.right = '';
            tooltip.style.left = `${Math.round(rect.right + gap)}px`;
        }
    };

    sidebar.querySelectorAll('.pc-link').forEach((link) => {
        link.addEventListener('mouseenter', () => showTooltip(link));
        link.addEventListener('mouseleave', scheduleHide);
        link.addEventListener('focus', () => showTooltip(link));
        link.addEventListener('blur', hideTooltip);
    });

    tooltip.addEventListener('mouseenter', cancelHide);
    tooltip.addEventListener('mouseleave', scheduleHide);

    sidebar.querySelector('.navbar-content')?.addEventListener('scroll', hideTooltip, { passive: true });

    // ---- Desktop collapse ----
    const setCollapsed = (collapsed) => {
        sidebar.classList.toggle(COLLAPSED, collapsed);
        hideTooltip();

        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', String(!collapsed));
            setLabel(collapseButton, collapsed ? collapseButton.dataset.labelExpand : collapseButton.dataset.labelCollapse);
        }
    };

    // ---- Mobile drawer ----
    // A closed drawer is off-screen and must not keep keyboard focus.
    const syncInert = () => {
        sidebar.inert = !desktop.matches && !isDrawerOpen();
    };

    const setDrawer = (open, restoreFocus = false) => {
        sidebar.classList.toggle(DRAWER_OPEN, open);

        if (overlay) {
            overlay.hidden = !open;
        }

        drawerButton?.setAttribute('aria-expanded', String(open));
        setLabel(drawerButton, open ? drawerButton?.dataset.labelClose : drawerButton?.dataset.labelOpen);
        syncInert();

        if (open) {
            sidebar.querySelector('a[href], button')?.focus();
        } else if (restoreFocus) {
            drawerButton?.focus();
        }
    };

    collapseButton?.addEventListener('click', (event) => {
        event.preventDefault();
        setCollapsed(!isCollapsed());
    });

    drawerButton?.addEventListener('click', (event) => {
        event.preventDefault();
        setDrawer(!isDrawerOpen());
    });

    overlay?.addEventListener('click', () => setDrawer(false, true));

    doc.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (!tooltip.hidden) {
            hideTooltip();
        }

        if (isDrawerOpen()) {
            setDrawer(false, true);
        }
    });

    // Each mode has its own state: leaving a mode resets it to expanded / closed.
    desktop.addEventListener('change', () => {
        setDrawer(false);
        setCollapsed(false);
    });

    setCollapsed(false);
    setDrawer(false);

    return { setCollapsed, setDrawer, tooltip };
}

/*
 * Header menus (AD-UI-01D-4): language and account, as disclosures (a button with aria-expanded and
 * aria-controls; the panel holds plain links and a form, so no ARIA menu roles). The open state is
 * Able Pro's drp-show class, so the existing CSS shows and hides the panel (display:none when closed,
 * so closed items are not focusable); Popper keeps the placement component.js used. The triggers carry
 * no data-pc-toggle, so component.js does not bind them.
 */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';

export function initHeaderDropdowns(doc = document, win = window) {
    const menus = [...doc.querySelectorAll('[data-admin-dropdown]')]
        .map((root) => {
            const toggle = root.querySelector('[data-admin-dropdown-toggle]');
            const panel = toggle ? doc.getElementById(toggle.getAttribute('aria-controls')) : null;

            return toggle && panel && root.contains(panel) ? { root, toggle, panel, popper: null } : null;
        })
        .filter(Boolean);

    const isOpen = (menu) => menu.root.classList.contains('drp-show');

    const close = (menu, restoreFocus = false) => {
        if (!isOpen(menu)) {
            return;
        }

        menu.root.classList.remove('drp-show');
        menu.toggle.setAttribute('aria-expanded', 'false');
        menu.popper?.destroy();
        menu.popper = null;
        menu.panel.removeAttribute('style');

        if (restoreFocus) {
            menu.toggle.focus();
        }
    };

    const open = (menu) => {
        menus.filter((other) => other !== menu).forEach((other) => close(other));

        menu.root.classList.add('drp-show');
        menu.toggle.setAttribute('aria-expanded', 'true');

        if (typeof win.Popper?.createPopper === 'function') {
            menu.popper = win.Popper.createPopper(menu.toggle, menu.panel, { placement: 'bottom-end' });
        }

        // Move into the panel: the first link or button (the current language is plain text).
        menu.panel.querySelector(FOCUSABLE)?.focus();
    };

    menus.forEach((menu) => {
        menu.toggle.setAttribute('aria-expanded', 'false');

        // Click, Enter and Space all arrive as a click on the button.
        menu.toggle.addEventListener('click', (event) => {
            event.preventDefault();

            if (isOpen(menu)) {
                close(menu);
            } else {
                open(menu);
            }
        });

        menu.toggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && !isOpen(menu)) {
                event.preventDefault();
                open(menu);
            }
        });

        menu.root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen(menu)) {
                event.preventDefault();
                event.stopPropagation();
                close(menu, true);
            }
        });

        // Tab / Shift+Tab out of the menu closes it.
        menu.root.addEventListener('focusout', (event) => {
            if (isOpen(menu) && event.relatedTarget && !menu.root.contains(event.relatedTarget)) {
                close(menu);
            }
        });
    });

    if (menus.length > 0) {
        doc.addEventListener('click', (event) => {
            menus.filter((menu) => isOpen(menu) && !menu.root.contains(event.target)).forEach((menu) => close(menu));
        });
    }

    return { menus, open, close };
}

function initAdminCreateForm() {
    document.querySelectorAll('[data-admin-create-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-admin-save-button]');
            const label = form.querySelector('[data-admin-save-label]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');

            if (label) {
                label.textContent = 'Saving…';
            }
        });
    });
}

function boot() {
    // Header menus do not depend on the sidebar opt-in.
    initHeaderDropdowns();
    initAdminCreateForm();

    if (!shouldManageSidebar()) {
        return;
    }

    provideSlideHelpers();
    removeLoader();
    initSidebar();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
