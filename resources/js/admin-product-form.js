/*
 * HIRFAH admin product form (7B-8F-3), loaded by resources/views/dashboard/products/form.blade.php.
 *
 * After a refused save the server renders the error summary (#product-form-errors: role="alert", tabindex="-1",
 * a link to each field). Focus moves to it once the page is ready, so keyboard and screen reader users start at the
 * list. Its autofocus attribute stays as the fallback without this script; browsers may skip autofocus, so it is not
 * relied on. Focus is never taken from an element the user (or the browser) has already focused, and nothing
 * happens when the page has no summary. No wording here.
 */

/** Focuses the server's error summary; true when it ends up focused. */
export function focusErrorSummary(doc = document) {
    const summary = doc.getElementById('product-form-errors');

    if (!summary) {
        return false;
    }

    const active = doc.activeElement;

    if (active && active !== summary && active !== doc.body && active !== doc.documentElement) {
        return false;
    }

    summary.focus();

    return doc.activeElement === summary;
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => focusErrorSummary());
    } else {
        focusErrorSummary();
    }
}
