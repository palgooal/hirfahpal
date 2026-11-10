// PAGES-03B: the content page editor (resources/js/admin-content-editor.js) over a small fake DOM and a stand-in
// editor; the real Tiptap is checked in the browser. Run by Tests\Feature\Dashboard\ContentPageEditorScriptTest.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    TABLE_TOOLS, TOOLS, bindRovingToolbar, imageDialog, initContentEditor, initLanguageTabs, linkDialog, mediaPathFrom, runTool, safeLinkHref, toolState,
} from '../../resources/js/admin-content-editor.js';

// ------------------------------------------------------------------ a small DOM

class Node {
    constructor(tag = 'div', attributes = {}, doc = null) {
        this.tagName = tag.toUpperCase();
        this.attrs = new Map(Object.entries(attributes).map(([k, v]) => [k, String(v)]));
        this.children = [];
        this.parent = null;
        this.listeners = {};
        this.hidden = false;
        this.disabled = false;
        this.value = '';
        this.textContent = '';
        this.ownerDocument = doc;
        this.classes = new Set();
        const self = this;
        this.classList = { add: (...c) => c.forEach((x) => self.classes.add(x)), contains: (c) => self.classes.has(c) };
        this.dataset = new Proxy({}, {
            get: (_, key) => self.attrs.get('data-' + String(key).replace(/[A-Z]/g, (c) => '-' + c.toLowerCase())),
            set: (_, key, value) => (self.attrs.set('data-' + String(key).replace(/[A-Z]/g, (c) => '-' + c.toLowerCase()), String(value)), true),
        });
    }

    get id() { return this.attrs.get('id') ?? ''; }
    set id(v) { this.attrs.set('id', v); }
    get className() { return [...this.classes].join(' '); }
    set className(v) { this.classes = new Set(v.split(/\s+/).filter(Boolean)); }
    setAttribute(k, v) { this.attrs.set(k, String(v)); }
    getAttribute(k) { return this.attrs.get(k) ?? null; }
    hasAttribute(k) { return this.attrs.has(k); }
    removeAttribute(k) { this.attrs.delete(k); }
    append(...nodes) { nodes.forEach((n) => { n.parent = this; n.ownerDocument ??= this.ownerDocument; this.children.push(n); }); }
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
    focus() { if (this.ownerDocument) this.ownerDocument.activeElement = this; }

    dispatch(type, init = {}) {
        const event = { type, target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...init };
        for (let node = this; node; node = node.parent ?? (node === this.ownerDocument?.root ? this.ownerDocument : null)) {
            (node.listeners[type] ?? []).forEach((fn) => fn(event));
            if (node === this.ownerDocument) break;
        }
        return event;
    }

    *descendants() { for (const child of this.children) { yield child; yield* child.descendants(); } }
    matches(selector) { return selector.split(',').some((s) => matchOne(this, s.trim())); }
    querySelectorAll(selector) { return [...this.descendants()].filter((n) => n.matches(selector)); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
    closest(selector) { for (let n = this; n; n = n.parent) if (n.matches?.(selector)) return n; return null; }
    contains(node) { for (let n = node; n; n = n.parent) if (n === this) return true; return false; }
}

function matchOne(node, selector) {
    const match = selector.match(/^([a-z0-9]+)?(?:#([\w-]+))?((?:\[[^\]]+\])*)$/i);
    if (!match) throw new Error('selector ' + selector);
    const [, tag, id, attrs] = match;
    if (tag && node.tagName !== tag.toUpperCase()) return false;
    if (id && node.id !== id) return false;
    for (const [, name, op, value] of attrs.matchAll(/\[([\w-]+)(?:(\^?=)"([^"]*)")?\]/g)) {
        if (name === 'hidden' && !op) { if (!node.hidden) return false; continue; }
        const actual = node.getAttribute(name);
        if (actual === null || (op === '=' && actual !== value) || (op === '^=' && !actual.startsWith(value))) return false;
    }
    return true;
}

function documentFor() {
    const doc = { listeners: {}, activeElement: null };
    doc.root = new Node('body', {}, doc);
    doc.createElement = (tag) => new Node(tag, {}, doc);
    doc.addEventListener = (type, fn) => (doc.listeners[type] ??= []).push(fn);
    doc.getElementById = (id) => doc.root.querySelector('#' + id);
    return doc;
}

function el(doc, tag, attributes = {}, children = []) {
    const node = new Node(tag, attributes, doc);
    if ('hidden' in attributes) { node.hidden = true; node.attrs.delete('hidden'); }
    node.append(...children);
    return node;
}

// ------------------------------------------------------------------ a stand-in editor

function fakeEditor({ html = '<p>Saved</p>', active = [], can = { undo: true, redo: false }, emptySelection = false } = {}) {
    const listeners = {};
    const calls = [];
    const state = { active: new Set(active) };
    const chainProxy = new Proxy({}, {
        get: (_, name) => (name === 'run' ? () => (calls.push('run'), true) : (...args) => (calls.push(args.length ? [name, ...args] : name), chainProxy)),
    });
    const editor = {
        calls,
        state: { selection: { empty: emptySelection } },
        view: { dom: null },
        html,
        isActive: (name, attrs) => state.active.has(attrs?.level ? `${name}${attrs.level}` : name),
        setActive: (...names) => { state.active = new Set(names); },
        can: () => ({ undo: () => can.undo, redo: () => can.redo }),
        chain: () => chainProxy,
        commands: { focus: () => calls.push('commands.focus') },
        getHTML: () => editor.html,
        getAttributes: (name) => (name === 'image' ? { src: '/storage/media/45/photo.jpg', alt: 'Old description' } : { href: state.active.has('link') ? 'https://example.org' : undefined }),
        on: (type, fn) => (listeners[type] ??= []).push(fn),
        emit: (type) => (listeners[type] ?? []).forEach((fn) => fn()),
    };
    return editor;
}

function editorPlace({ locale = 'ar', dir = 'rtl', editable = 'true', bodySent = 'false', body = '<p>Saved</p>' } = {}) {
    const doc = documentFor();
    const status = el(doc, 'p', { 'data-content-editor-status': '' });
    const preview = el(doc, 'div', { 'data-content-preview': '', lang: locale, 'aria-labelledby': `content-body-${locale}-label`, 'aria-describedby': `content-body-${locale}-hint` });
    const field = el(doc, 'textarea', { 'data-content-body-field': '', name: `translations[${locale}][body_html]` });
    field.value = body;
    field.disabled = true;
    const root = el(doc, 'div', { 'data-content-editor': '', 'data-locale': locale, 'data-editable': editable, 'data-body-sent': bodySent, dir }, [status, preview, field]);
    const hint = el(doc, 'p', { id: `content-body-${locale}-hint`, hidden: '' });
    doc.root.append(root, hint);
    return { doc, root, status, preview, field, hint };
}

const texts = { toolbar: 'Formatting', tableToolbar: 'Table', bold: 'Bold', ready: 'Ready.', failed: 'The editor could not start.', imageRefused: 'Only media library images.' };

function mounted(options = {}, editorOptions = {}) {
    const place = editorPlace(options);
    const editor = fakeEditor({ html: options.body ?? '<p>Saved</p>', ...editorOptions });
    let created = null;
    const result = initContentEditor(place.root, {
        texts,
        origin: 'https://hirfah.test',
        createEditor: (args) => {
            created = args;
            editor.view.dom = el(place.doc, 'div');
            args.element.append(editor.view.dom);
            return editor;
        },
        ...options.init,
    });
    return { ...place, editor, result, created, tool: (id) => result.toolbar.querySelector(`button[data-tool="${id}"]`) ?? result.tableBar.querySelector(`button[data-tool="${id}"]`) };
}

// ------------------------------------------------------------------ addresses

test('links: https, http, mailto, tel and this site; nothing that could run or leave unnoticed', () => {
    for (const ok of ['https://example.org/a?b=1', 'http://example.org', 'mailto:help@hirfah.ps', 'tel:+970590000000', '/content/faq', '#section']) {
        assert.equal(safeLinkHref(ok), ok, ok);
    }
    for (const bad of ['javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example', 'https://user:pass@example.org', 'https://exa mple.org', '/\\evil.example', 'ftp://example.org', 'mailto:nobody', '', null, ' java\tscript:x']) {
        assert.equal(safeLinkHref(bad), null, String(bad));
    }
});

test('images: only media library paths of this site, made relative', () => {
    const origin = 'https://hirfah.test';
    assert.equal(mediaPathFrom('https://hirfah.test/storage/media/2026/10/a.jpg', origin), '/storage/media/2026/10/a.jpg');
    assert.equal(mediaPathFrom('/storage/media/45/photo-1.webp', origin), '/storage/media/45/photo-1.webp');
    for (const bad of ['https://evil.example/storage/media/a.jpg', '//evil.example/storage/media/a.jpg', '/storage/media/../../.env', '/storage/media/%2e%2e/a.jpg',
        '/storage/media/a.jpg?x=1', '/storage/media/a.jpg#x', '/storage/media/a.svg', '/storage/media/a.php', '/uploads/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AAAA', '', null]) {
        assert.equal(mediaPathFrom(bad, origin), null, String(bad));
    }
});

// ------------------------------------------------------------------ the tools

test('tool states: pressed formats, what applies here, table controls only inside a table', () => {
    const editor = fakeEditor({ active: ['bold', 'heading2'], can: { undo: false, redo: true } });
    assert.deepEqual(toolState(editor, 'bold'), { active: true, disabled: false });
    assert.deepEqual(toolState(editor, 'heading2'), { active: true, disabled: false });
    assert.deepEqual(toolState(editor, 'heading3'), { active: false, disabled: false });
    assert.equal(toolState(editor, 'undo').disabled, true);
    assert.equal(toolState(editor, 'redo').disabled, false);
    assert.equal(toolState(editor, 'unlink').disabled, true, 'Remove link applies inside a link only.');
    TABLE_TOOLS.forEach((id) => assert.equal(toolState(editor, id).disabled, true, id));
    assert.equal(toolState(editor, 'table').disabled, false);

    editor.setActive('table', 'link');
    TABLE_TOOLS.forEach((id) => assert.equal(toolState(editor, id).disabled, false, id));
    assert.equal(toolState(editor, 'table').disabled, true, 'No table inside a table.');
    assert.equal(toolState(editor, 'unlink').disabled, false);
});

test('tools run their command; a 3×3 table with a header row; link and image are not run directly', () => {
    const cases = { bold: 'toggleBold', heading3: ['toggleHeading', { level: 3 }], horizontalRule: 'setHorizontalRule', addRow: 'addRowAfter', deleteColumn: 'deleteColumn', undo: 'undo',
        table: ['insertTable', { rows: 3, cols: 3, withHeaderRow: true }] };
    for (const [id, command] of Object.entries(cases)) {
        const editor = fakeEditor();
        assert.equal(runTool(editor, id), true);
        assert.deepEqual(editor.calls, ['focus', command, 'run'], id);
    }
    assert.equal(runTool(fakeEditor(), 'link'), false);
    assert.equal(runTool(fakeEditor(), 'image'), false);
});

test('the toolbar has no colours, fonts, code, custom HTML or embeds', () => {
    const ids = TOOLS.map(([id]) => id);
    assert.deepEqual(ids, ['paragraph', 'heading2', 'heading3', 'heading4', 'bold', 'italic', 'underline', 'strike', 'bulletList', 'orderedList', 'blockquote', 'horizontalRule', 'link', 'unlink', 'table', 'image', 'undo', 'redo']);
    assert.deepEqual(TABLE_TOOLS, ['addRow', 'deleteRow', 'addColumn', 'deleteColumn', 'deleteTable']);
});

test('roving toolbar: one tab stop; arrows follow the reading direction; Home, End, Escape', () => {
    for (const [direction, forward, back] of [['rtl', 'ArrowLeft', 'ArrowRight'], ['ltr', 'ArrowRight', 'ArrowLeft']]) {
        const doc = documentFor();
        const buttons = [0, 1, 2].map((i) => el(doc, 'button', { tabindex: i === 0 ? '0' : '-1' }));
        const bar = el(doc, 'div', {}, buttons);
        let escaped = 0;
        bindRovingToolbar(bar, direction, () => escaped++);

        buttons[0].dispatch('keydown', { key: forward });
        assert.equal(doc.activeElement, buttons[1], direction);
        assert.deepEqual(buttons.map((b) => b.getAttribute('tabindex')), ['-1', '0', '-1']);
        buttons[1].dispatch('keydown', { key: back });
        assert.equal(doc.activeElement, buttons[0]);
        buttons[0].dispatch('keydown', { key: 'End' });
        assert.equal(doc.activeElement, buttons[2]);
        buttons[2].dispatch('keydown', { key: 'Home' });
        assert.equal(doc.activeElement, buttons[0]);
        const event = buttons[0].dispatch('keydown', { key: 'Escape' });
        assert.equal(escaped, 1);
        assert.equal(event.defaultPrevented, true);
        assert.equal(buttons[0].dispatch('keydown', { key: 'a' }).defaultPrevented, false, 'Other keys pass.');
    }
});

// ------------------------------------------------------------------ the language tabs

function languagePage({ initial = 'ar' } = {}) {
    const doc = documentFor();
    const tab = (l) => el(doc, 'button', { id: `language-tab-${l}`, role: 'tab', 'aria-controls': `language-panel-${l}`, 'data-language-tab': l });
    const tabs = [tab('ar'), tab('en')];
    const tablist = el(doc, 'div', { 'data-language-tablist': '', 'data-initial-language': initial, hidden: '' }, tabs);
    const fields = { ar: el(doc, 'input', { id: 'translations-ar-title' }), en: el(doc, 'input', { id: 'translations-en-title' }) };
    const panels = ['ar', 'en'].map((l) => el(doc, 'fieldset', { id: `language-panel-${l}` }, [fields[l]]));
    const summary = el(doc, 'a', { href: '#translations-en-title' });
    const form = el(doc, 'form', {}, [tablist, ...panels]);
    doc.root.append(summary, form);
    return { doc, form, tabs, tablist, panels, fields, summary };
}

test('language tabs: shown, the initial (error) language first, both panels kept in the page', () => {
    const { doc, form, tabs, tablist, panels, fields } = languagePage({ initial: 'en' });
    fields.ar.value = 'unsaved Arabic';
    initLanguageTabs(form, { direction: 'rtl', doc });

    assert.equal(tablist.hidden, false);
    assert.deepEqual(tabs.map((t) => t.getAttribute('aria-selected')), ['false', 'true']);
    assert.deepEqual(panels.map((p) => [p.getAttribute('role'), p.hidden]), [['tabpanel', true], ['tabpanel', false]]);
    assert.equal(panels[0].getAttribute('aria-labelledby'), 'language-tab-ar');

    tabs[1].dispatch('keydown', { key: 'ArrowRight' });
    assert.equal(doc.activeElement, tabs[0], 'RTL: the right arrow goes back to the first tab.');
    assert.deepEqual(panels.map((p) => p.hidden), [false, true]);
    assert.equal(form.contains(fields.en), true, 'The hidden language stays in the form.');
    tabs[1].dispatch('click');
    tabs[0].dispatch('click');
    assert.equal(fields.ar.value, 'unsaved Arabic', 'Switching keeps unsaved edits.');
});

test('an error summary link opens the tab holding its field', () => {
    const { doc, form, panels, summary } = languagePage();
    doc.root.listeners = {};
    summary.parent = doc.root;
    initLanguageTabs(form, { direction: 'ltr', doc });
    assert.equal(panels[1].hidden, true);
    (doc.listeners.click ?? []).forEach((fn) => fn({ target: summary }));
    assert.equal(panels[1].hidden, false);
});

test('without the tab markup nothing changes', () => {
    const doc = documentFor();
    assert.equal(initLanguageTabs(el(doc, 'form'), { doc }), null);
});

// ------------------------------------------------------------------ the editor

test('the editor mounts over the saved content, in its language and direction, with a labelled toolbar', () => {
    const { root, preview, field, status, result, created, tool } = mounted();

    assert.equal(created.content, '<p>Saved</p>', 'Initialised from the saved, cleaned body.');
    assert.deepEqual([created.locale, created.direction, created.labelledBy, created.describedBy], ['ar', 'rtl', 'content-body-ar-label', 'content-body-ar-hint']);
    assert.equal(root.dataset.editorState, 'ready');
    assert.equal(preview.hidden, true);
    assert.equal(status.textContent, 'Ready.');
    assert.equal(result.toolbar.getAttribute('role'), 'toolbar');
    assert.equal(result.toolbar.getAttribute('aria-label'), 'Formatting (ar)');
    assert.equal(field.disabled, true, 'Untouched: not posted.');
    assert.equal(tool('bold').getAttribute('aria-label'), 'Bold');
    assert.equal(tool('bold').getAttribute('aria-pressed'), 'false');
    assert.equal(tool('undo').hasAttribute('aria-pressed'), false);
    assert.equal(tool('image').getAttribute('aria-disabled'), 'true', 'No picker: the image tool only edits a selected image.');
    assert.deepEqual(result.toolbar.querySelectorAll('button').filter((b) => b.getAttribute('tabindex') === '0').length, 1);
    assert.equal(result.tableBar.hidden, true);
});

test('PAGES-03C: the keyboard hint shows once the editor is ready, never when it failed', () => {
    assert.equal(mounted().hint.hidden, false);

    const place = editorPlace();
    const original = console.error;
    console.error = () => {};
    initContentEditor(place.root, { texts, createEditor: () => { throw new Error('boom'); } });
    console.error = original;
    assert.equal(place.hint.hidden, true);
});

test('PAGES-03C: with an image selected, the image tool edits its description in place', () => {
    let opened;
    let picked = 0;
    const { editor, tool } = mounted({ init: { picker: { open: () => picked++ }, dialogs: { image: { open: (o) => (opened = o) } } } });

    editor.setActive('image');
    editor.emit('selectionUpdate');
    tool('image').dispatch('click');
    assert.equal(picked, 0, 'No picker for a selected image.');
    assert.deepEqual([opened.src, opened.alt, opened.update, opened.opener], ['/storage/media/45/photo.jpg', 'Old description', true, tool('image')]);
    opened.onInsert('Hand embroidery from Hebron');
    assert.deepEqual(editor.calls.slice(-3), ['focus', ['updateAttributes', 'image', { alt: 'Hand embroidery from Hebron' }], 'run']);

    // Without the media library too: a selected image can still be described.
    const viewer = mounted({}, { active: ['image'] });
    assert.equal(viewer.tool('image').getAttribute('aria-disabled'), 'false');
});

test('a change enables the field with the editor HTML; pressed states follow the selection', () => {
    const { editor, field, result, tool } = mounted();

    editor.html = '<p>Changed <strong>text</strong></p>';
    editor.setActive('bold');
    editor.emit('update');
    assert.equal(field.disabled, false);
    assert.equal(field.value, '<p>Changed <strong>text</strong></p>');
    assert.equal(tool('bold').getAttribute('aria-pressed'), 'true');

    editor.setActive('table');
    editor.emit('selectionUpdate');
    assert.equal(result.tableBar.hidden, false, 'Table controls inside a table.');
    assert.equal(tool('table').getAttribute('aria-disabled'), 'true');
});

test('a body sent before a failed save is posted again even untouched', () => {
    const { field } = mounted({ bodySent: 'true', body: '<p>Unsaved</p>' });
    assert.equal(field.disabled, false);
    assert.equal(field.value, '<p>Unsaved</p>');
});

test('an editor that fails to start leaves the content read-only and the field disabled', () => {
    const place = editorPlace();
    const original = console.error;
    console.error = () => {};
    const result = initContentEditor(place.root, { texts, createEditor: () => { throw new Error('boom'); } });
    console.error = original;

    assert.equal(result, null);
    assert.equal(place.root.dataset.editorState, 'failed');
    assert.equal(place.field.disabled, true, 'Never posted: the stored body stays.');
    assert.equal(place.field.value, '<p>Saved</p>');
    assert.equal(place.preview.hidden, false);
    assert.equal(place.status.textContent, 'The editor could not start.');
    assert.equal(place.status.getAttribute('role'), 'alert');
    assert.equal(place.root.querySelector('[role="toolbar"]'), null);
});

test('a viewer gets no editor', () => {
    const place = editorPlace({ editable: 'false' });
    assert.equal(initContentEditor(place.root, { texts, createEditor: () => assert.fail('not created') }), null);
    assert.equal(place.field.disabled, true);
});

test('Alt+F10 reaches the toolbar, Escape returns to the text; disabled tools do nothing', () => {
    const { doc, editor, tool } = mounted({ dir: 'ltr', locale: 'en' });

    editor.view.dom.dispatch('keydown', { key: 'F10', altKey: true });
    assert.equal(doc.activeElement, tool('paragraph'));
    tool('paragraph').dispatch('keydown', { key: 'Escape' });
    assert.ok(editor.calls.includes('commands.focus'));

    editor.calls.length = 0;
    tool('unlink').dispatch('click');
    assert.deepEqual(editor.calls, [], 'aria-disabled tools are not run.');
    tool('bold').dispatch('click');
    assert.deepEqual(editor.calls, ['focus', 'toggleBold', 'run']);
});

test('links go through the dialog: a new link on an empty selection, otherwise the selection becomes the link', () => {
    let opened;
    const { editor, tool } = mounted({ init: { dialogs: { link: { open: (o) => (opened = o) } } } }, { emptySelection: true });

    tool('link').dispatch('click');
    assert.equal(opened.href, '');
    assert.equal(opened.opener, tool('link'));
    opened.onApply('https://example.org');
    assert.deepEqual(editor.calls.at(-2), ['insertContent', { type: 'text', text: 'https://example.org', marks: [{ type: 'link', attrs: { href: 'https://example.org' } }] }]);

    editor.state.selection.empty = false;
    tool('link').dispatch('click');
    opened.onApply('/content/faq');
    assert.deepEqual(editor.calls.slice(-3), [['extendMarkRange', 'link'], ['setLink', { href: '/content/faq' }], 'run']);
    opened.onRemove();
    assert.deepEqual(editor.calls.slice(-2), ['unsetLink', 'run']);
});

test('images: the picker callback, a site-relative path, a described image; other addresses are refused', () => {
    let chosen;
    let opened;
    const picker = { open: (callback, from) => (chosen = { callback, from }) };
    const { editor, status, tool } = mounted({ init: { picker, dialogs: { image: { open: (o) => (opened = o) } } } });

    tool('image').dispatch('click');
    assert.equal(chosen.from, tool('image'), 'Focus returns to the image tool.');
    chosen.callback({ id: 45, url: 'https://hirfah.test/storage/media/45/photo.jpg', alt: 'Embroidery' });
    assert.deepEqual([opened.src, opened.alt], ['/storage/media/45/photo.jpg', 'Embroidery']);
    opened.onInsert('Hand embroidery');
    assert.deepEqual(editor.calls.slice(-2), [['setImage', { src: '/storage/media/45/photo.jpg', alt: 'Hand embroidery' }], 'run']);

    opened = null;
    chosen.callback({ url: 'https://evil.example/x.jpg', alt: 'x' });
    assert.equal(opened, null);
    assert.equal(status.textContent, 'Only media library images.');
});

// ------------------------------------------------------------------ the dialogs

function dialogNode(doc, children) {
    const dialog = el(doc, 'dialog', {}, children);
    dialog.open = false;
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
    doc.root.append(dialog);
    return dialog;
}

test('the link dialog: invalid addresses are shown as errors; Enter applies without submitting; focus returns', () => {
    const doc = documentFor();
    const input = el(doc, 'input', { 'data-link-input': '' });
    const error = el(doc, 'p', { 'data-link-error': '', hidden: '' });
    const remove = el(doc, 'button', { 'data-link-remove': '' });
    const dialog = dialogNode(doc, [input, error, el(doc, 'button', { 'data-link-apply': '' }), remove, el(doc, 'button', { 'data-dialog-cancel': '' })]);
    const opener = el(doc, 'button');
    const applied = [];
    const link = linkDialog(dialog);

    link.open({ href: '', opener, onApply: (h) => applied.push(h) });
    assert.equal(dialog.open, true);
    assert.equal(remove.hidden, true, 'Nothing to remove on a new link.');
    assert.equal(doc.activeElement, input);
    input.value = 'javascript:alert(1)';
    const enter = input.dispatch('keydown', { key: 'Enter' });
    assert.equal(enter.defaultPrevented, true, 'Enter never submits the page form.');
    assert.equal(error.hidden, false);
    assert.equal(input.getAttribute('aria-invalid'), 'true');
    assert.deepEqual(applied, []);

    input.value = ' https://example.org ';
    input.dispatch('keydown', { key: 'Enter' });
    assert.deepEqual(applied, ['https://example.org']);
    assert.equal(dialog.open, false);
    assert.equal(doc.activeElement, opener);
});

test('the image dialog requires a description', () => {
    const doc = documentFor();
    const input = el(doc, 'input', { 'data-image-alt': '' });
    const error = el(doc, 'p', { 'data-image-error': '', hidden: '' });
    const preview = el(doc, 'img', { 'data-image-preview': '' });
    const insertButton = el(doc, 'button', { 'data-image-insert': '', 'data-update-label': 'Save description' });
    insertButton.textContent = 'Insert image';
    const dialog = dialogNode(doc, [preview, input, error, insertButton, el(doc, 'button', { 'data-dialog-cancel': '' })]);
    const inserted = [];
    const images = imageDialog(dialog);
    images.open({ src: '/storage/media/45/photo.jpg', alt: '   ', onInsert: (a) => inserted.push(a) });
    assert.equal(insertButton.textContent, 'Insert image');

    assert.equal(preview.src, '/storage/media/45/photo.jpg');
    dialog.querySelector('[data-image-insert]').dispatch('click');
    assert.equal(error.hidden, false);
    assert.deepEqual(inserted, []);
    input.value = 'Hand embroidery';
    input.dispatch('keydown', { key: 'Enter' });
    assert.deepEqual(inserted, ['Hand embroidery']);

    images.open({ src: '/storage/media/45/photo.jpg', alt: 'Old', update: true, onInsert: (a) => inserted.push(a) });
    assert.equal(insertButton.textContent, 'Save description');
    assert.equal(input.value, 'Old');
});
