/*
 * ORD-02A (step 2): the vendor order presentation helpers the orders list (vendor-resource-list.js, which re-exports
 * them) and the order details page share. Moved unchanged from vendor-resource-list.js (ORD-01C). Pure functions and
 * constants only: nothing here touches the document or sends a request.
 */

/** ORD-01C: the vendor's light button per action: Reject apart as the destructive one, the next steps secondary. */
export const ORDER_ACTION_STYLES = { accept: 'btn-light-primary', reject: 'btn-light-danger', preparing: 'btn-light-secondary', ready: 'btn-light-secondary' };

/** ORD-01C: an accessible name from a ":number" template (plain text; split/join, so "$" stays as typed). */
export const numberedLabel = (template, number) => (template ? template.split(':number').join(String(number ?? '').trim() || '—') : null);

/**
 * ORD-01C: an order's creation time in the page language (its month name and word order; English "Oct 15, 2026,
 * 12:05") and the application timezone, with Latin digits and a 24-hour clock as on the dashboard home;
 * '' when there is no valid time. Display only: the stored timestamp is unchanged.
 */
export function formatOrderDate(value, locale, timeZone) {
    const date = typeof value === 'string' && value.trim() !== '' ? new Date(value) : null;
    if (!date || Number.isNaN(date.getTime())) return '';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', numberingSystem: 'latn' };
    try {
        return new Intl.DateTimeFormat(locale || 'en', { ...options, timeZone: timeZone || 'UTC' }).format(date);
    } catch {
        return new Intl.DateTimeFormat('en', { ...options, timeZone: 'UTC' }).format(date);
    }
}

/** ORD-01C: an amount as the admin list shows it (two decimals, grouped, Latin digits); null when it is not a number. */
export function formatAmount(value) {
    const number = typeof value === 'number' ? value : (typeof value === 'string' && value.trim() !== '' ? Number(value) : NaN);

    return Number.isFinite(number) ? new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number) : null;
}
