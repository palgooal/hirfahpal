// AD-UI-CAT-M4-B: how the admin media picker reads the server's answers and what it posts.
// Run by Tests\Feature\MediaLibraryAdminScriptTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { fieldValues, interpretListResponse, listErrorText, uploadedItem } from '../../resources/js/admin-media-picker.js';
import { interpretUploadResponse } from '../../resources/js/admin-media/shared.js';

const messages = {
    forbidden: 'You do not have permission to do this.',
    network: 'The connection failed.',
    loadError: 'The images could not be loaded.',
    uploaded: 'Images uploaded: :count.',
    uploadFailed: 'The images could not be uploaded.',
};

const image = (id, extra = {}) => ({ id, file_type: 'image', mime_type: 'image/png', url: `/m/${id}.png`, ...extra });

test('a list keeps images only, whatever the server sends', () => {
    const outcome = interpretListResponse({ status: 200, body: { current_page: 1, last_page: 3, data: [image(1), { id: 2, file_type: 'document' }, image(3), { file_type: 'image' }, null] } });

    assert.equal(outcome.kind, 'items');
    assert.deepEqual(outcome.items.map((item) => item.id), [1, 3]);
    assert.equal(outcome.lastPage, 3);
});

test('list failures are told apart: 403, network, anything else', () => {
    assert.equal(interpretListResponse({ status: 403, body: null }).kind, 'forbidden');
    assert.equal(interpretListResponse({ status: 0, body: null }).kind, 'network');
    assert.equal(interpretListResponse({ status: 500, body: { message: 'SQLSTATE' } }).kind, 'error');
    assert.equal(interpretListResponse({ status: 200, body: { data: 'nope' } }).kind, 'error');

    assert.equal(listErrorText('forbidden', messages), messages.forbidden);
    assert.equal(listErrorText('network', messages), messages.network);
    assert.equal(listErrorText('error', messages), messages.loadError);
});

test('an upload selects the last uploaded image only', () => {
    assert.equal(uploadedItem({ uploaded: [image(7), image(8)] }).id, 8);
    assert.equal(uploadedItem(image(9)).id, 9);
    assert.equal(uploadedItem({ uploaded: [{ id: 4, file_type: 'document' }] }), null);
    assert.equal(uploadedItem(null), null);
});

test('upload answers: 201, 403, 422 per file, network', () => {
    assert.equal(interpretUploadResponse({ status: 201, body: { uploaded: [image(1)] }, messages }).text, 'Images uploaded: 1.');
    assert.equal(interpretUploadResponse({ status: 403, body: null, messages }).text, messages.forbidden);

    const refused = interpretUploadResponse({ status: 422, body: { errors: { 'files.0': ['The file must be a JPG, PNG, GIF or WebP image.'] } }, fileNames: ['evil.html.png'], messages });
    assert.equal(refused.tone, 'error');
    assert.deepEqual(refused.details, ['evil.html.png: The file must be a JPG, PNG, GIF or WebP image.']);

    const offline = interpretUploadResponse({ status: 0, body: null, messages });
    assert.equal(offline.text, messages.network);
    assert.equal(offline.refresh, false);

    // Without a network message (the library page), status 0 keeps the generic upload failure.
    assert.equal(interpretUploadResponse({ status: 0, body: null, messages: { ...messages, network: undefined } }).text, messages.uploadFailed);
});

test('the posted fields: remove sends remove_image=1 and no id; a choice sends its id and 0', () => {
    assert.deepEqual(fieldValues({ id: 5, removed: false }), { value: '5', remove: '0' });
    assert.deepEqual(fieldValues({ id: 5, removed: true }), { value: '', remove: '1' });
    assert.deepEqual(fieldValues({ id: null, removed: false }), { value: '', remove: '0' });
});
