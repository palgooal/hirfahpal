/*
 * HIRFAH vendor dashboard shell (VUI-01B): sidebar collapse on desktop, the
 * mobile/tablet drawer, and the account dropdown. It toggles the same classes
 * as Able Pro (pc-sidebar-hide, mob-sidebar-active, pc-menu-overlay) so the
 * layout CSS is reused, but it does not load the Able Pro theme scripts and
 * stores nothing: no theme, layout or direction preference.
 */

const desktop = window.matchMedia('(min-width: 1024px)');

function initSidebar() {
    const sidebar = document.getElementById('vendor-sidebar');

    if (!sidebar) {
        return;
    }

    const collapseButton = document.querySelector('[data-vendor-sidebar-toggle]');
    const drawerButton = document.querySelector('[data-vendor-drawer-toggle]');
    const overlay = sidebar.querySelector('[data-vendor-overlay]');

    const isDrawerOpen = () => sidebar.classList.contains('mob-sidebar-active');
    const isCollapsed = () => sidebar.classList.contains('pc-sidebar-hide');

    // A hidden sidebar (collapsed on desktop, closed drawer on mobile) must not keep keyboard focus.
    const syncInert = () => {
        sidebar.inert = desktop.matches ? isCollapsed() : !isDrawerOpen();
    };

    const setDrawer = (open, restoreFocus = false) => {
        sidebar.classList.toggle('mob-sidebar-active', open);
        overlay.hidden = !open;
        drawerButton?.setAttribute('aria-expanded', String(open));
        syncInert();

        if (open) {
            sidebar.querySelector('a[href], button')?.focus();
        } else if (restoreFocus) {
            drawerButton?.focus();
        }
    };

    const setCollapsed = (collapsed) => {
        sidebar.classList.toggle('pc-sidebar-hide', collapsed);

        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', String(!collapsed));
            collapseButton.setAttribute(
                'aria-label',
                collapsed ? collapseButton.dataset.labelExpand : collapseButton.dataset.labelCollapse,
            );
        }

        syncInert();
    };

    collapseButton?.addEventListener('click', () => setCollapsed(!isCollapsed()));
    drawerButton?.addEventListener('click', () => setDrawer(!isDrawerOpen()));
    overlay?.addEventListener('click', () => setDrawer(false, true));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isDrawerOpen()) {
            setDrawer(false, true);
        }
    });

    // Each mode has its own state: leaving a mode resets it.
    desktop.addEventListener('change', () => {
        setDrawer(false);
        setCollapsed(false);
    });

    syncInert();
}

function initDropdowns() {
    document.querySelectorAll('[data-vendor-dropdown]').forEach((root) => {
        const toggle = root.querySelector('[data-vendor-dropdown-toggle]');
        const menu = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;

        if (!menu) {
            return;
        }

        const setOpen = (open) => {
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };

        toggle.addEventListener('click', () => {
            const open = menu.hidden;
            setOpen(open);

            if (open) {
                menu.querySelector('a[href], button')?.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.hidden && !root.contains(event.target)) {
                setOpen(false);
            }
        });

        root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                event.stopPropagation();
                setOpen(false);
                toggle.focus();
            }
        });

        root.addEventListener('focusout', (event) => {
            if (!menu.hidden && event.relatedTarget && !root.contains(event.relatedTarget)) {
                setOpen(false);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDropdowns();
});
