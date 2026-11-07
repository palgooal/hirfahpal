/**
 * VPM-UX-01A: the vendor products page's error-state Retry. It reloads the list exactly as the toolbar's Refresh icon
 * does (the list script's data-resource-retry), so loading, paging and focus stay with vendor-resource-list.js; the
 * error block itself is shown and hidden by CSS from the list script's error message.
 */
const root = document.querySelector('[data-vendor-resource-list][data-resource="products"]');

if (root) {
    root.querySelector('[data-products-error-retry]')?.addEventListener('click', () => {
        root.querySelector('[data-resource-retry]')?.click();
    });
}
