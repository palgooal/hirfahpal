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
    renderImageCard,
    saveErrorText,
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

// ---- VPM-01A: the admin-style image card and the translated save errors ----

class FakeElement {
    constructor(tag) {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.attributes = {};
        this.dataset = {};
        this.text = '';
        this.className = '';
    }

    set textContent(value) {
        this.children = [];
        this.text = String(value);
    }

    get textContent() {
        return this.text + this.children.map((child) => child.textContent).join('');
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    all() {
        return this.children.flatMap((child) => [child, ...child.all()]);
    }
}

const doc = { createElement: (tag) => new FakeElement(tag) };
const cardTexts = { position: 'Image :position', mainBadge: 'Main image', imageAlt: 'Product image', change: 'Change', setMain: 'Set as main', remove: 'Remove' };
const card = (image, index, calls = []) => renderImageCard(doc, image, {
    index,
    isPrimary: index === 0,
    url: '/storage/vendors/media/1/a.png',
    messages: cardTexts,
    onChange: () => calls.push('change'),
    onSetMain: () => calls.push('main'),
    onRemove: () => calls.push('remove'),
});
const actions = (node) => node.children[2].children;

test('the main image card has the admin layout, position 1, the main badge and no "set as main"', () => {
    const calls = [];
    const node = card({ id: 7, path: 'vendors/media/1/a.png', alt_text: 'Clay jug', is_primary: true }, 0, calls);
    const [thumb, meta, bar] = node.children;

    assert.equal(node.tagName, 'LI');
    assert.equal(node.className, 'vendor-product-gallery-item is-main');
    assert.equal(node.dataset.imageId, '7');
    assert.deepEqual([thumb.className, meta.className, bar.className], ['vendor-product-gallery-thumb', 'vendor-product-gallery-meta', 'vendor-product-gallery-actions']);
    assert.equal(thumb.children[0].src, '/storage/vendors/media/1/a.png');
    assert.equal(thumb.children[0].alt, 'Clay jug');
    assert.equal(thumb.children[0].loading, 'lazy');
    assert.deepEqual(meta.children.map((child) => child.textContent), ['Image 1', 'Clay jug', 'Main image']);
    assert.equal(meta.children[2].className, 'vendor-product-gallery-badge is-main');
    assert.deepEqual(actions(node).map((button) => button.dataset.action), ['change', 'remove']);

    actions(node).forEach((button) => button.onclick());
    assert.deepEqual(calls, ['change', 'remove']);
});

test('a gallery card has its position, no badge, and change / set as main / remove described by its name', () => {
    const calls = [];
    const node = card({ id: 9, path: 'products/legacy.png', alt_text: '', is_primary: false }, 3, calls);
    const meta = node.children[1];

    assert.equal(node.className, 'vendor-product-gallery-item');
    assert.deepEqual(meta.children.map((child) => child.textContent), ['Image 4', 'Product image'], 'Without alt text the name is "Product image".');
    assert.equal(meta.children[1].id, 'vendor-product-image-3-name');
    assert.deepEqual(actions(node).map((button) => button.dataset.action), ['change', 'main', 'remove']);
    assert.deepEqual(actions(node).map((button) => button.textContent), ['Change', 'Set as main', 'Remove']);
    for (const button of actions(node)) {
        assert.equal(button.type, 'button');
        assert.equal(button.className, 'btn btn-sm btn-light-secondary');
        assert.equal(button.getAttribute('aria-describedby'), 'vendor-product-image-3-name');
        assert.equal(button.children[0].getAttribute('aria-hidden'), 'true');
    }

    actions(node).forEach((button) => button.onclick());
    assert.deepEqual(calls, ['change', 'main', 'remove']);
});

test('names stay text in a card: markup in alt text is never parsed', () => {
    const hostile = '<img src=x onerror=alert(1)>';
    const node = card({ id: 1, path: 'p.png', alt_text: hostile, is_primary: true }, 0);
    const name = node.children[1].children[1];

    assert.equal(name.tagName, 'BDI');
    assert.equal(name.textContent, hostile);
    assert.equal(name.children.length, 0);
    assert.deepEqual(node.all().map((child) => child.tagName), ['DIV', 'IMG', 'DIV', 'SPAN', 'BDI', 'SPAN', 'DIV', 'BUTTON', 'I', 'SPAN', 'BUTTON', 'I', 'SPAN']);
});

test('a failed save reads the page texts; a 422 adds the field details, never the server message', () => {
    const texts = { saveFailed: 'The product could not be saved.', saveInvalid: 'The product could not be saved. Check these details:' };
    const messages = { session: 'Your session has ended.', forbidden: 'You do not have permission to do this.' };

    assert.equal(saveErrorText(422, { message: 'SERVER', errors: { price: ['The price field is required.'], sku: ['Taken.'] } }, texts, messages), 'The product could not be saved. Check these details: The price field is required. Taken.');
    assert.equal(saveErrorText(422, { message: 'SERVER' }, texts, messages), texts.saveFailed);
    assert.equal(saveErrorText(401, null, texts, messages), messages.session);
    assert.equal(saveErrorText(419, null, texts, messages), messages.session);
    assert.equal(saveErrorText(403, { message: 'SERVER' }, texts, messages), messages.forbidden);
    assert.equal(saveErrorText(500, { message: 'SQLSTATE' }, texts, messages), texts.saveFailed);
});

test('the six-image limit still refuses a seventh and allows replacing at six', () => {
    const six = Array.from({ length: MAX_IMAGES }, (_, i) => ({ id: i + 1, path: `vendors/media/1/${i}.png`, sort_order: i, is_primary: i === 0 }));

    assert.deepEqual(planChoice(six, { mode: 'gallery', target: null }), { action: 'refuse', error: 'limit' });
    assert.deepEqual(planChoice(six, { mode: 'primary', target: six[0] }), { action: 'replace', target: six[0] });
});
