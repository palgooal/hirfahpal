// AD-UI-CAT-M3: how the admin media library page reads the server's answers.
// Run by Tests\Feature\MediaLibraryAdminScriptTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    detailText,
    format,
    formatBytes,
    formatDate,
    interpretDeleteResponse,
    listParts,
    interpretSaveResponse,
    interpretUploadResponse,
    validationMessages,
} from '../../resources/js/admin-media-library.js';

const messages = {
    deleted: 'The media item was deleted.',
    deletedCount: 'Images deleted: :count.',
    deleteFailed: 'The image could not be deleted.',
    inUse: 'This media is used and cannot be deleted.',
    inUseItems: 'Used: :names',
    alreadyDeleted: 'This image was already deleted.',
    cleanupWarning: 'Some files could not be removed.',
    forbidden: 'You do not have permission to do this.',
    uploaded: 'Images uploaded: :count.',
    uploadFailed: 'The images could not be uploaded.',
    saved: 'The image details were saved.',
    saveFailed: 'The changes could not be saved.',
};

const names = new Map([[1, 'bowl.png'], [2, 'vase.png'], [3, 'cup.png']]);

// ---- delete ----

test('a single delete that succeeded is a success and reloads the grid', () => {
    const outcome = interpretDeleteResponse({ status: 200, body: { message: 'x' }, bulk: false, messages, locale: 'en' });

    assert.equal(outcome.tone, 'success');
    assert.equal(outcome.text, messages.deleted);
    assert.equal(outcome.refresh, true);
    assert.deepEqual(outcome.inUseIds, []);
});

test('a bulk delete reports the number the server deleted, not the number requested', () => {
    const outcome = interpretDeleteResponse({ status: 200, body: { deleted: 1 }, bulk: true, messages, locale: 'en' });

    assert.equal(outcome.tone, 'success');
    assert.equal(outcome.text, 'Images deleted: 1.');
});

test('file_cleanup_failed turns the result into a warning that says so', () => {
    for (const bulk of [false, true]) {
        const outcome = interpretDeleteResponse({ status: 200, body: { deleted: 2, file_cleanup_failed: [4] }, bulk, messages, locale: 'en' });

        assert.equal(outcome.tone, 'warning');
        assert.deepEqual(outcome.details, [messages.cleanupWarning]);
        assert.equal(outcome.refresh, true, 'The records are gone, so the grid is reloaded.');
    }
});

test('409 is an error that keeps every card and shows the server message', () => {
    const outcome = interpretDeleteResponse({ status: 409, body: { message: 'Used by a category.', in_use: [2] }, bulk: false, names, messages, locale: 'en' });

    assert.equal(outcome.tone, 'error');
    assert.equal(outcome.text, 'Used by a category.');
    assert.equal(outcome.refresh, false);
    assert.deepEqual(outcome.inUseIds, [2]);
    assert.deepEqual(outcome.details, [], 'A single item needs no list.');
});

test('409 on a bulk delete names the used images as a structured list, without technical detail', () => {
    const outcome = interpretDeleteResponse({ status: 409, body: { message: 'In use.', in_use: [1, 3, 9] }, bulk: true, names, messages });

    assert.equal(outcome.tone, 'error');
    assert.equal(outcome.refresh, false);
    assert.deepEqual(outcome.inUseIds, [1, 3, 9]);
    assert.deepEqual(outcome.details, [{ template: 'Used: :names', names: ['bowl.png', 'cup.png', '#9'] }], 'Names stay data; the page isolates each one.');
    assert.match(detailText(outcome.details[0], 'en'), /^Used: bowl\.png, cup\.png,? and #9$/);
});

test('list parts keep every name whole and separate it from the locale separators', () => {
    const mixed = ['vase-blue.png', 'وعاء-فخار.png', '<img src=x>.png'];

    for (const locale of ['ar', 'en']) {
        const parts = listParts(mixed, locale);

        assert.deepEqual(parts.filter((part) => part.type === 'element').map((part) => part.value), mixed, locale);
        assert.ok(parts.filter((part) => part.type === 'literal').every((part) => !mixed.some((name) => part.value.includes(name))), locale);
    }

    // Arabic joins with "و" as a separate literal part, never glued into a name.
    assert.ok(listParts(['a.png', 'b.png'], 'ar').some((part) => part.type === 'literal' && part.value.includes('و')));
});

test('409 without a message falls back to the translated text', () => {
    const outcome = interpretDeleteResponse({ status: 409, body: null, bulk: false, messages, locale: 'en' });

    assert.equal(outcome.text, messages.inUse);
    assert.deepEqual(outcome.inUseIds, []);
});

test('a single 404 means another admin deleted it: a warning and a reload, never a success', () => {
    const single = interpretDeleteResponse({ status: 404, body: null, bulk: false, messages, locale: 'en' });
    assert.equal(single.tone, 'warning');
    assert.equal(single.text, messages.alreadyDeleted);
    assert.equal(single.refresh, true);

    const bulk = interpretDeleteResponse({ status: 404, body: null, bulk: true, messages, locale: 'en' });
    assert.equal(bulk.tone, 'error');
    assert.equal(bulk.refresh, false);
});

test('every refused or failed delete is an error and removes nothing', () => {
    for (const [status, text] of [[403, messages.forbidden], [422, messages.deleteFailed], [419, messages.deleteFailed], [500, messages.deleteFailed], [0, messages.deleteFailed]]) {
        for (const bulk of [false, true]) {
            const outcome = interpretDeleteResponse({ status, body: { message: 'SQLSTATE secret', deleted: 3 }, bulk, messages, locale: 'en' });

            assert.equal(outcome.tone, 'error', `${status}`);
            assert.equal(outcome.text, text, `${status}`);
            assert.equal(outcome.refresh, false, `${status}`);
            assert.ok(!outcome.text.includes('SQLSTATE'), 'Server internals are never shown for a failure.');
        }
    }
});

test('a 422 on bulk delete lists the validation messages', () => {
    const outcome = interpretDeleteResponse({ status: 422, body: { errors: { 'ids.0': ['The ids.0 field has a duplicate value.'] } }, bulk: true, messages, locale: 'en' });

    assert.deepEqual(outcome.details, ['The ids.0 field has a duplicate value.']);
});

// ---- upload ----

test('an upload reports how many images the server stored', () => {
    assert.equal(interpretUploadResponse({ status: 201, body: { uploaded: [{}, {}] }, messages }).text, 'Images uploaded: 2.');
    assert.equal(interpretUploadResponse({ status: 201, body: { id: 5 }, messages }).text, 'Images uploaded: 1.');
});

test('a refused upload names the file each error belongs to', () => {
    const outcome = interpretUploadResponse({
        status: 422,
        body: { errors: { 'files.1': ['The file must be a JPG, PNG, GIF or WebP image.'], image: ['Too large.'] } },
        fileNames: ['a.png', 'evil.html'],
        messages,
    });

    assert.equal(outcome.tone, 'error');
    assert.equal(outcome.refresh, false);
    assert.deepEqual(outcome.details, ['evil.html: The file must be a JPG, PNG, GIF or WebP image.', 'a.png: Too large.']);
});

test('a forbidden or failed upload is never a success', () => {
    assert.equal(interpretUploadResponse({ status: 403, body: null, messages }).text, messages.forbidden);

    for (const status of [0, 413, 500]) {
        const outcome = interpretUploadResponse({ status, body: null, messages });
        assert.equal(outcome.tone, 'error');
        assert.equal(outcome.text, messages.uploadFailed);
        assert.equal(outcome.refresh, false);
    }
});

// ---- save ----

test('saving keeps field errors with their fields', () => {
    assert.equal(interpretSaveResponse({ status: 200, body: {}, messages }).tone, 'success');

    const invalid = interpretSaveResponse({ status: 422, body: { errors: { alt: ['Too long.'], title: ['Bad.', 'Worse.'] } }, messages });
    assert.equal(invalid.tone, 'error');
    assert.deepEqual(invalid.fieldErrors, { alt: 'Too long.', title: 'Bad.' });

    assert.equal(interpretSaveResponse({ status: 403, body: null, messages }).text, messages.forbidden);
    assert.equal(interpretSaveResponse({ status: 500, body: null, messages }).text, messages.saveFailed);
});

// ---- helpers ----

test('placeholders are replaced everywhere they appear', () => {
    assert.equal(format(':count of :count', { count: 3 }), '3 of 3');
    assert.equal(format('No placeholder'), 'No placeholder');
});

test('validation messages are flattened in order', () => {
    assert.deepEqual(validationMessages({ errors: { a: ['one', 'two'], b: ['three'] } }), ['one', 'two', 'three']);
    assert.deepEqual(validationMessages(null), []);
});

test('sizes, lists and dates follow the page locale', () => {
    assert.equal(formatBytes(512, 'en'), '512 byte');
    assert.equal(formatBytes(1536, 'en'), '1.5 kB');
    assert.equal(formatBytes(-1, 'en'), '—');
    assert.notEqual(formatBytes(1536, 'ar'), formatBytes(1536, 'en'));
    assert.equal(detailText({ template: 'Used: :names', names: ['a', 'b'] }, 'en'), 'Used: a and b');
    assert.equal(detailText('plain', 'en'), 'plain');
    assert.equal(formatDate('not a date', 'en'), '—');
    assert.match(formatDate('2026-10-11T09:30:00Z', 'en'), /2026/);
});
