// G7-C2-5: the vendor product editor's gallery logic (resources/js/vendor-product-editor.js).
// Run by Tests\Feature\VendorProductEditorGalleryTest (node --test), so it is part of php artisan test.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    MAX_IMAGES,
    applyLocal,
    errorMessage,
    format,
    interpretImageResponse,
    orderImages,
    planChoice,
    removeLocal,
    setLocalPrimary,
} from '../../resources/js/vendor-product-editor.js';

const img = (id, sort, primary = false) => ({ id, path: `vendors/media/1/${id}.png`, sort_order: sort, is_primary: primary });
const six = () => [img(1, 0, true), img(2, 1), img(3, 2), img(4, 3), img(5, 4), img(6, 5)];
const medium = { id: 99, path: 'vendors/media/1/new.png', url: '/storage/vendors/media/1/new.png', alt_text: 'New' };
const messages = {
    limit: 'A product can have up to 6 images. Remove or replace an image to add another.',
    library: 'This image is not in your media library, or it was removed. Choose another image.',
    failed: 'Something went wrong. Try again.',
};

test('the gallery shows the main image first, then the saved order', () => {
    assert.deepEqual(orderImages([img(3, 2), img(1, 0), img(2, 1, true)]).map((i) => i.id), [2, 1, 3]);
    assert.deepEqual(orderImages([img(5, 1), img(4, 1)]).map((i) => i.id), [4, 5], 'ties by id; without a main image the first leads');
    assert.deepEqual(orderImages(undefined), []);
    assert.deepEqual(orderImages([null, 'x', img(1, 0, true)]).map((i) => i.id), [1]);
});

test('a successful answer is the server gallery, in order', () => {
    const outcome = interpretImageResponse({ status: 200, body: { product: { images: [img(2, 1), img(1, 0, true)] } } });
    assert.equal(outcome.ok, true);
    assert.deepEqual(outcome.images.map((i) => i.id), [1, 2]);
    assert.deepEqual(interpretImageResponse({ status: 201, body: { product: { images: [] } } }), { ok: true, images: [] });
});

test('errors are named from the status and the field, never from the server text', () => {
    const named = (status, body = null) => interpretImageResponse({ status, body }).error;
    assert.equal(named(422, { message: 'x', errors: { images: ['A product can have up to 6 images.'] } }), 'limit');
    assert.equal(named(422, { errors: { path: ['Each image must be chosen from your media library.'] } }), 'library');
    assert.equal(named(422, { errors: { alt_text: ['too long'] } }), 'invalid');
    assert.equal(named(404), 'gone');
    assert.equal(named(401), 'session');
    assert.equal(named(419), 'session');
    assert.equal(named(403), 'forbidden');
    assert.equal(named(0), 'network');
    assert.equal(named(500, { message: 'SQLSTATE[...] at /var/www/...' }), 'failed');
    assert.equal(errorMessage('limit', messages), messages.limit);
    assert.equal(errorMessage('unknown', messages), messages.failed);
});

test('adding: the first image is the main one; up to six; a seventh is refused before any request', () => {
    assert.deepEqual(planChoice([], { mode: 'gallery', target: null }), { action: 'add', primary: true });
    assert.deepEqual(planChoice([img(1, 0, true)], { mode: 'gallery', target: null }), { action: 'add', primary: false });
    assert.deepEqual(planChoice([img(1, 0, true)], { mode: 'primary', target: null }), { action: 'add', primary: true });
    assert.deepEqual(planChoice(six().slice(0, 5), { mode: 'gallery', target: null }), { action: 'add', primary: false });
    assert.deepEqual(planChoice(six(), { mode: 'gallery', target: null }), { action: 'refuse', error: 'limit' });
    assert.equal(MAX_IMAGES, 6);
});

test('replacing is one action on the chosen image, also with six images', () => {
    const gallery = six();
    assert.deepEqual(planChoice(gallery, { mode: 'replace', target: gallery[3] }), { action: 'replace', target: gallery[3] });
    assert.deepEqual(planChoice(gallery, { mode: 'primary', target: gallery[0] }), { action: 'replace', target: gallery[0] });
});

test('a new product: replacing keeps the place and the main image; adding as main puts it first', () => {
    let local = [];
    local = applyLocal(local, { action: 'add', primary: true }, { path: 'a.png' });
    local = applyLocal(local, { action: 'add', primary: false }, { path: 'b.png' });
    local = applyLocal(local, { action: 'add', primary: false }, { path: 'c.png' });
    assert.deepEqual(local.map((i) => [i.path, i.is_primary, i.sort_order]), [['a.png', true, 0], ['b.png', false, 1], ['c.png', false, 2]]);

    const replacedMiddle = applyLocal(local, { action: 'replace', target: local[1] }, medium);
    assert.deepEqual(replacedMiddle.map((i) => [i.path, i.is_primary]), [['a.png', true], [medium.path, false], ['c.png', false]]);
    const replacedMain = applyLocal(local, { action: 'replace', target: local[0] }, medium);
    assert.deepEqual(replacedMain.map((i) => [i.path, i.is_primary]), [[medium.path, true], ['b.png', false], ['c.png', false]]);

    const asMain = applyLocal(local, { action: 'add', primary: true }, medium);
    assert.deepEqual(asMain.map((i) => [i.path, i.is_primary, i.sort_order]), [[medium.path, true, 0], ['a.png', false, 1], ['b.png', false, 2], ['c.png', false, 3]]);
    assert.equal(applyLocal(local, { action: 'refuse', error: 'limit' }, medium), local);
});

test('a new product: choosing the main image moves it first; removing closes the gap and promotes the next', () => {
    const local = [{ path: 'a.png', is_primary: true }, { path: 'b.png', is_primary: false }, { path: 'c.png', is_primary: false }];

    assert.deepEqual(setLocalPrimary(local, local[2]).map((i) => [i.path, i.is_primary, i.sort_order]), [['c.png', true, 0], ['a.png', false, 1], ['b.png', false, 2]]);
    assert.deepEqual(removeLocal(local, local[0]).map((i) => [i.path, i.is_primary, i.sort_order]), [['b.png', true, 0], ['c.png', false, 1]]);
    assert.deepEqual(removeLocal(local, local[1]).map((i) => [i.path, i.is_primary]), [['a.png', true], ['c.png', false]]);
    assert.deepEqual(removeLocal([local[0]], local[0]), []);
});

test('placeholders are filled, unknown ones kept', () => {
    assert.equal(format(':count of :max images', { count: 6, max: 6 }), '6 of 6 images');
    assert.equal(format(':count من :max صور', { count: 2, max: 6 }), '2 من 6 صور');
    assert.equal(format('Hi :name'), 'Hi :name');
});
