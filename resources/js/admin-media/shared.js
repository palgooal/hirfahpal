/*
 * Shared by the admin media library page (resources/js/admin-media-library.js) and the media picker
 * (resources/js/admin-media-picker.js), AD-UI-CAT-M4-B. Pure helpers only: no DOM, no wording (every text
 * comes from the page config), so they are tested on their own (tests/js).
 */

/** Replaces :name placeholders. */
export function format(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (text, [key, value]) => text.split(`:${key}`).join(String(value)),
        String(template ?? ''),
    );
}

/** Laravel validation errors as a flat list, in field order. */
export function validationMessages(body) {
    const errors = body && typeof body.errors === 'object' && body.errors !== null ? body.errors : {};

    return Object.values(errors).flat().filter((message) => typeof message === 'string' && message !== '');
}

/** Human size in the page locale (bytes, kB, MB). */
export function formatBytes(bytes, locale) {
    const value = Number(bytes);

    if (!Number.isFinite(value) || value < 0) {
        return '—';
    }

    const [unit, amount] = value >= 1024 * 1024 ? ['megabyte', value / (1024 * 1024)] : value >= 1024 ? ['kilobyte', value / 1024] : ['byte', value];

    try {
        return new Intl.NumberFormat(locale, { style: 'unit', unit, maximumFractionDigits: unit === 'byte' ? 0 : 1 }).format(amount);
    } catch {
        return `${amount.toFixed(unit === 'byte' ? 0 : 1)} ${unit}`;
    }
}

/**
 * A list as the page locale joins it, in parts: {type: 'element', value} for each item, {type: 'literal', value}
 * for the separators. The page puts each element in its own <bdi>, so an Arabic separator never runs into a
 * Latin file name (and the other way round).
 */
export function listParts(values, locale) {
    try {
        return new Intl.ListFormat(locale, { style: 'long', type: 'conjunction' }).formatToParts(values);
    } catch {
        return values.flatMap((value, index) => (index === 0 ? [] : [{ type: 'literal', value: ', ' }]).concat({ type: 'element', value }));
    }
}

/** The plain text of a detail (a string, or a {template, names} list of names). */
export function detailText(detail, locale) {
    if (typeof detail === 'string') {
        return detail;
    }

    return format(detail.template, { names: listParts(detail.names, locale).map((part) => part.value).join('') });
}

/** A date in the page locale, or a dash. */
export function formatDate(value, locale) {
    const date = value ? new Date(value) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    } catch {
        return date.toISOString();
    }
}

/**
 * The outcome of an upload request. Status 0 is a network failure (no answer); it uses messages.network when
 * the page provides it.
 *
 * @param {{status: number, body: object|null, fileNames: string[], messages: object}} input
 */
export function interpretUploadResponse({ status, body, fileNames = [], messages }) {
    if (status === 201) {
        const count = Array.isArray(body?.uploaded) ? body.uploaded.length : body?.id ? 1 : 0;

        return { tone: 'success', text: format(messages.uploaded, { count }), details: [], refresh: true };
    }

    const outcome = { tone: 'error', text: messages.uploadFailed, details: [], refresh: false };

    if (status === 0 && messages.network) {
        return { ...outcome, text: messages.network };
    }

    if (status === 403) {
        return { ...outcome, text: messages.forbidden };
    }

    if (status === 422 && body?.errors) {
        // files.N / image errors name the file they belong to.
        outcome.details = Object.entries(body.errors).flatMap(([field, list]) => {
            const index = /^files\.(\d+)$/.exec(field)?.[1];
            const name = index !== undefined ? fileNames[Number(index)] : field === 'image' ? fileNames[0] : null;

            return (Array.isArray(list) ? list : [list]).map((message) => (name ? `${name}: ${message}` : String(message)));
        });
    }

    return outcome;
}

/** The images accepted by the library (EnsureSafeMediaUpload), for file inputs. */
export const IMAGE_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp';

/** fetch() that never throws: {status, body}; status 0 when there was no answer. */
export async function request(url, options) {
    try {
        const response = await fetch(url, options);
        let body = null;

        try {
            body = await response.json();
        } catch {
            body = null;
        }

        return { status: response.status, body };
    } catch {
        return { status: 0, body: null };
    }
}
