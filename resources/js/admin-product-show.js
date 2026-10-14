/*
 * HIRFAH admin product details (AD-UI-PROD-M4, DEC-PROD-12), driving resources/views/dashboard/products/show.blade.php.
 *
 * Turns the five groups into ARIA tabs (WAI-ARIA tabs pattern, automatic activation): the arrow keys move to the
 * previous / next tab in the reading direction (Left is "next" in right-to-left pages), Home and End go to the
 * first / last tab, Enter and Space activate the focused tab (a native button click). Only the selected tab is in
 * the tab order (roving tabindex) and only its panel is shown. A link to #product-panel-log (the log's pagination)
 * opens that tab. Without this script the tab list stays hidden and every group is shown in turn.
 */

/** The tab to move to for a key, or null when the key does not move. */
export function nextIndex(key, current, count, direction = 'ltr') {
    if (count < 1) {
        return null;
    }

    const forward = direction === 'rtl' ? 'ArrowLeft' : 'ArrowRight';
    const backward = direction === 'rtl' ? 'ArrowRight' : 'ArrowLeft';

    switch (key) {
        case forward:
            return (current + 1) % count;
        case backward:
            return (current - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

/** The tab a URL fragment points at (#product-panel-KEY or #product-tab-KEY), else the first. */
export function indexFromHash(hash, keys) {
    const match = /^#product-(?:panel|tab)-([a-z]+)$/.exec(String(hash ?? ''));
    const index = match ? keys.indexOf(match[1]) : -1;

    return index >= 0 ? index : 0;
}

export function initProductTabs(root, { direction = 'ltr', hash = '' } = {}) {
    const tablist = root?.querySelector('[data-product-tablist]');
    const tabs = tablist ? [...tablist.querySelectorAll('[role="tab"]')] : [];
    const panels = tabs.map((tab) => root.querySelector(`#${tab.getAttribute('aria-controls')}`));

    if (tabs.length === 0 || panels.some((panel) => !panel)) {
        return null; // Leave the page as it is: every group shown.
    }

    panels.forEach((panel, index) => {
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tabs[index].id);
        panel.setAttribute('tabindex', '0');
    });

    const select = (index, focus = false) => {
        tabs.forEach((tab, i) => {
            const selected = i === index;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.setAttribute('tabindex', selected ? '0' : '-1');
            panels[i].hidden = !selected;
        });

        if (focus) {
            tabs[index].focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(index));
        tab.addEventListener('keydown', (event) => {
            const target = nextIndex(event.key, index, tabs.length, direction);

            if (target !== null) {
                event.preventDefault();
                select(target, true);
            }
        });
    });

    select(indexFromHash(hash, tabs.map((tab) => tab.dataset.productTab)));
    tablist.hidden = false;
    root.classList?.add('is-tabbed');

    return { select };
}

if (typeof document !== 'undefined') {
    const start = () => initProductTabs(document.querySelector('[data-product-tabs]'), {
        direction: document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
        hash: window.location.hash,
    });

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
