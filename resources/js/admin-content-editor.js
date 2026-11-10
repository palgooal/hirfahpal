/*
 * HIRFAH content page editor (PAGES-03B), driving resources/views/dashboard/content-pages/edit.blade.php.
 *
 * - The two languages are ARIA tabs (the product details pattern: arrows in the reading direction, Home / End). Both
 *   panels stay in the page, so unsaved edits survive switching. The first language with an error opens first, and
 *   an error summary link opens the tab of its field.
 * - Each [data-content-editor] gets a Tiptap editor (StarterKit, image, table) over the saved, cleaned content, in its
 *   language and direction. Its hidden translations[<locale>][body_html] stays disabled until the content is changed
 *   (or was sent before a failed save): an untouched body is not posted, and an editor that fails to start never is;
 *   the content then stays read-only and the server keeps it. The server cleans every body (PageContentSanitizer).
 * - The toolbar: role="toolbar", one tab stop (roving tabindex), arrows in the reading direction, Home / End,
 *   aria-pressed for formats, aria-disabled for what does not apply; Alt+F10 reaches it from the text, Escape goes
 *   back. Table controls appear only inside a table.
 * - Links through a dialog (https, http, mailto, tel, or a page of this site); images from the media library through
 *   the picker in callback mode, as site-relative paths, with a required description. With an image selected, the
 *   image tool edits its description instead (PAGES-03C).
 * - The keyboard hint under the content is shown only once the editor is ready.
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { nextIndex } from './admin-product-show.js';
import { initMediaPicker } from './admin-media-picker.js';

/** A link address the page may use (as the server's rules), or null. */
export function safeLinkHref(value) {
    const href = String(value ?? '').trim();

    if (href === '' || href.startsWith('//') || /[\s\x00-\x1F\x7F\\]/.test(href)) {
        return null;
    }

    if (href.startsWith('/') || href.startsWith('#')) {
        return href;
    }

    if (/^mailto:[^@\s]+@[^@\s]+$/i.test(href) || /^tel:\+?[0-9().\- ]{3,}$/i.test(href)) {
        return href;
    }

    try {
        const url = new URL(href);

        return ['http:', 'https:'].includes(url.protocol) && url.hostname !== '' && !url.username && !url.password ? href : null;
    } catch {
        return null;
    }
}

const MEDIA_PATH = /^\/storage\/media\/(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:jpe?g|png|webp|gif)$/i;

/** The site-relative path of a media library image address, or null (another site, a query, traversal...). */
export function mediaPathFrom(value, origin) {
    const raw = String(value ?? '').trim();

    if (raw === '' || /[\s\x00-\x1F\x7F\\%]/.test(raw) || raw.startsWith('//')) {
        return null;
    }

    let url;
    try {
        url = new URL(raw, origin);
    } catch {
        return null;
    }

    if (url.origin !== new URL(origin).origin || url.search !== '' || url.hash !== '' || url.username || url.password) {
        return null;
    }

    const path = url.pathname;

    return /(^|\/)\.{1,2}(\/|$)/.test(path) || !MEDIA_PATH.test(path) ? null : path;
}

/**
 * The toolbar: [id, icon, label key, kind]. kind: "toggle" (aria-pressed), "action", "table" (only inside a table),
 * "picker" (only with the media library).
 */
export const TOOLS = [
    ['paragraph', 'ph-paragraph', 'paragraph', 'toggle'],
    ['heading2', 'ph-text-h-two', 'heading2', 'toggle'],
    ['heading3', 'ph-text-h-three', 'heading3', 'toggle'],
    ['heading4', 'ph-text-h-four', 'heading4', 'toggle'],
    ['bold', 'ph-text-b', 'bold', 'toggle'],
    ['italic', 'ph-text-italic', 'italic', 'toggle'],
    ['underline', 'ph-text-underline', 'underline', 'toggle'],
    ['strike', 'ph-text-strikethrough', 'strike', 'toggle'],
    ['bulletList', 'ph-list-bullets', 'bulletList', 'toggle'],
    ['orderedList', 'ph-list-numbers', 'orderedList', 'toggle'],
    ['blockquote', 'ph-quotes', 'blockquote', 'toggle'],
    ['horizontalRule', 'ph-minus', 'horizontalRule', 'action'],
    ['link', 'ph-link', 'link', 'toggle'],
    ['unlink', 'ph-link-break', 'unlink', 'action'],
    ['table', 'ph-table', 'table', 'action'],
    ['image', 'ph-image', 'image', 'picker'],
    ['undo', 'ph-arrow-counter-clockwise', 'undo', 'action'],
    ['redo', 'ph-arrow-clockwise', 'redo', 'action'],
];

export const TABLE_TOOLS = ['addRow', 'deleteRow', 'addColumn', 'deleteColumn', 'deleteTable'];

/** Whether a toolbar entry is on, and whether it applies here, for an editor (or a stand-in with isActive / can). */
export function toolState(editor, id) {
    const inTable = editor.isActive('table');
    const active = {
        paragraph: () => editor.isActive('paragraph'),
        heading2: () => editor.isActive('heading', { level: 2 }),
        heading3: () => editor.isActive('heading', { level: 3 }),
        heading4: () => editor.isActive('heading', { level: 4 }),
        link: () => editor.isActive('link'),
    }[id]?.() ?? (['bold', 'italic', 'underline', 'strike', 'bulletList', 'orderedList', 'blockquote'].includes(id) ? editor.isActive(id) : false);
    const enabled = {
        unlink: () => editor.isActive('link'),
        table: () => !inTable,
        undo: () => editor.can().undo(),
        redo: () => editor.can().redo(),
    }[id]?.() ?? (TABLE_TOOLS.includes(id) ? inTable : true);

    return { active, disabled: !enabled };
}

/** Runs a toolbar entry (except link and image, which open dialogs). */
export function runTool(editor, id) {
    const chain = editor.chain().focus();
    const commands = {
        paragraph: () => chain.setParagraph(),
        heading2: () => chain.toggleHeading({ level: 2 }),
        heading3: () => chain.toggleHeading({ level: 3 }),
        heading4: () => chain.toggleHeading({ level: 4 }),
        bold: () => chain.toggleBold(),
        italic: () => chain.toggleItalic(),
        underline: () => chain.toggleUnderline(),
        strike: () => chain.toggleStrike(),
        bulletList: () => chain.toggleBulletList(),
        orderedList: () => chain.toggleOrderedList(),
        blockquote: () => chain.toggleBlockquote(),
        horizontalRule: () => chain.setHorizontalRule(),
        unlink: () => chain.extendMarkRange('link').unsetLink(),
        table: () => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }),
        addRow: () => chain.addRowAfter(),
        deleteRow: () => chain.deleteRow(),
        addColumn: () => chain.addColumnAfter(),
        deleteColumn: () => chain.deleteColumn(),
        deleteTable: () => chain.deleteTable(),
        undo: () => chain.undo(),
        redo: () => chain.redo(),
    };

    return commands[id] ? commands[id]().run() : false;
}

/** The toolbar entry to focus for a key (roving tabindex), or null. */
export function toolbarTarget(key, index, count, direction) {
    return nextIndex(key, index, count, direction);
}

/** Moves focus among a toolbar's buttons with the arrow keys (reading direction), Home and End. */
export function bindRovingToolbar(toolbar, direction, onEscape) {
    const buttons = () => [...toolbar.querySelectorAll('button')].filter((button) => !button.hidden && !button.closest('[hidden]'));

    const focusAt = (index) => {
        const list = buttons();
        list.forEach((button, i) => button.setAttribute('tabindex', i === index ? '0' : '-1'));
        list[index]?.focus();
    };

    toolbar.addEventListener('keydown', (event) => {
        const list = buttons();
        const index = list.indexOf(event.target);

        if (index < 0) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape?.();

            return;
        }

        const target = toolbarTarget(event.key, index, list.length, direction);

        if (target !== null) {
            event.preventDefault();
            focusAt(target);
        }
    });

    toolbar.addEventListener('focusin', (event) => {
        const list = buttons();
        const index = list.indexOf(event.target);

        if (index >= 0) {
            list.forEach((button, i) => button.setAttribute('tabindex', i === index ? '0' : '-1'));
        }
    });

    return { focusAt, buttons };
}

/** The language tabs (both panels stay in the page, so their content and editors are kept). */
export function initLanguageTabs(form, { direction = 'ltr', doc = null } = {}) {
    const tablist = form?.querySelector('[data-language-tablist]');
    const tabs = tablist ? [...tablist.querySelectorAll('[role="tab"]')] : [];
    const panels = tabs.map((tab) => form.querySelector(`#${tab.getAttribute('aria-controls')}`));

    if (tabs.length === 0 || panels.some((panel) => !panel)) {
        return null;
    }

    panels.forEach((panel, index) => {
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tabs[index].id);
    });

    const select = (index, focus = false) => {
        tabs.forEach((tab, i) => {
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
            tab.setAttribute('tabindex', i === index ? '0' : '-1');
            panels[i].hidden = i !== index;
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

    // An error summary link opens the tab holding its field, before the browser moves to it.
    doc?.addEventListener('click', (event) => {
        const link = event.target?.closest?.('a[href^="#translations-"]');
        const target = link ? doc.getElementById(link.getAttribute('href').slice(1)) : null;
        const index = target ? panels.findIndex((panel) => panel.contains(target)) : -1;

        if (index >= 0) {
            select(index);
        }
    });

    const initial = tabs.findIndex((tab) => tab.dataset.languageTab === tablist.dataset.initialLanguage);
    select(initial >= 0 ? initial : 0);
    tablist.hidden = false;

    return { select };
}

function defaultCreateEditor({ element, content, locale, direction, labelledBy, describedBy }) {
    return new Editor({
        element,
        content,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: false,
                    linkOnPaste: false,
                    HTMLAttributes: { rel: 'noopener noreferrer', target: null },
                    isAllowedUri: (url) => safeLinkHref(url) !== null,
                },
            }),
            Image.configure({ inline: false, allowBase64: false }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        editorProps: {
            attributes: {
                class: 'admin-content-editor-surface hirfah-admin-prose',
                lang: locale,
                dir: direction,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-labelledby': labelledBy,
                'aria-describedby': describedBy,
            },
        },
    });
}

/**
 * Mounts the editor on one [data-content-editor]. Returns { editor } or null (no editor: the read-only content and
 * the disabled field stay as they are). createEditor is replaceable for tests.
 */
export function initContentEditor(root, { texts = {}, dialogs = {}, picker = null, origin = '', createEditor = defaultCreateEditor } = {}) {
    const doc = root.ownerDocument;
    const field = root.querySelector('[data-content-body-field]');
    const preview = root.querySelector('[data-content-preview]');
    const status = root.querySelector('[data-content-editor-status]');
    const locale = root.dataset.locale;
    const direction = root.getAttribute('dir') || 'ltr';

    if (!field || root.dataset.editable !== 'true') {
        return null;
    }

    const toolbar = doc.createElement('div');
    toolbar.className = 'admin-content-editor-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', `${texts.toolbar ?? 'Formatting'} (${preview?.getAttribute('lang') ?? locale})`);
    const tableBar = doc.createElement('div');
    tableBar.className = 'admin-content-editor-toolbar is-table';
    tableBar.setAttribute('role', 'toolbar');
    tableBar.setAttribute('aria-label', texts.tableToolbar ?? 'Table');
    tableBar.hidden = true;
    const mount = doc.createElement('div');
    mount.className = 'admin-content-editor-mount';

    let editor;
    try {
        editor = createEditor({
            element: mount,
            content: field.value,
            locale,
            direction,
            labelledBy: preview?.getAttribute('aria-labelledby') ?? '',
            describedBy: preview?.getAttribute('aria-describedby') ?? '',
        });
    } catch (error) {
        // The saved content stays shown and the field stays disabled: saving keeps the stored body.
        if (status) {
            status.textContent = texts.failed ?? '';
            status.setAttribute('role', 'alert');
            status.classList.add('is-error');
        }
        root.dataset.editorState = 'failed';
        console.error('[content-editor]', error);

        return null;
    }

    const buttons = new Map();
    const makeButton = (id, icon, label, bar) => {
        const button = doc.createElement('button');
        button.type = 'button';
        button.className = icon ? 'admin-content-editor-tool' : 'admin-content-editor-tool is-text';
        button.dataset.tool = id;
        button.setAttribute('aria-label', label);
        button.title = label;
        button.setAttribute('tabindex', '-1');
        if (icon) {
            const i = doc.createElement('i');
            i.className = `ph-duotone ${icon}`;
            i.setAttribute('aria-hidden', 'true');
            button.append(i);
        } else {
            button.textContent = label;
        }
        bar.append(button);
        buttons.set(id, button);

        return button;
    };

    TOOLS.forEach(([id, icon, label, kind]) => {
        const button = makeButton(id, icon, texts[label] ?? id, toolbar);
        if (kind === 'toggle') {
            button.setAttribute('aria-pressed', 'false');
        }
    });
    TABLE_TOOLS.forEach((id) => makeButton(id, null, texts[id] ?? id, tableBar));
    toolbar.querySelector('button')?.setAttribute('tabindex', '0');
    tableBar.querySelector('button')?.setAttribute('tabindex', '0');

    const refresh = () => {
        buttons.forEach((button, id) => {
            const { active, disabled } = toolState(editor, id);
            if (button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
            // Without the media library, the image tool only edits the description of a selected image.
            const unavailable = id === 'image' && !picker && !editor.isActive('image');
            button.setAttribute('aria-disabled', disabled || unavailable ? 'true' : 'false');
        });
        tableBar.hidden = !editor.isActive('table');
    };

    // The body is posted once changed (or when it was sent before a failed save).
    const markChanged = () => {
        field.disabled = false;
        field.value = editor.getHTML();
    };

    if (root.dataset.bodySent === 'true') {
        markChanged();
    }

    editor.on('update', () => {
        markChanged();
        refresh();
    });
    editor.on('selectionUpdate', refresh);
    editor.on('transaction', refresh);

    const focusEditor = () => editor.commands.focus();
    const roving = bindRovingToolbar(toolbar, direction, focusEditor);
    bindRovingToolbar(tableBar, direction, focusEditor);

    editor.view.dom.addEventListener('keydown', (event) => {
        if (event.altKey && event.key === 'F10') {
            event.preventDefault();
            roving.focusAt(Math.max(0, roving.buttons().findIndex((button) => button.getAttribute('tabindex') === '0')));
        }
    });

    const onTool = (event) => {
        const button = event.target.closest('button[data-tool]');

        if (!button || button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        const id = button.dataset.tool;

        if (id === 'link') {
            dialogs.link?.open({
                href: editor.getAttributes('link').href ?? '',
                opener: button,
                onApply: (href) => {
                    if (editor.state.selection.empty && !editor.isActive('link')) {
                        editor.chain().focus().insertContent({ type: 'text', text: href, marks: [{ type: 'link', attrs: { href } }] }).run();
                    } else {
                        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
                    }
                },
                onRemove: () => editor.chain().focus().extendMarkRange('link').unsetLink().run(),
            });

            return;
        }

        if (id === 'image' && editor.isActive('image')) {
            const { src, alt } = editor.getAttributes('image');
            dialogs.image?.open({
                src,
                alt: alt ?? '',
                opener: button,
                update: true,
                onInsert: (description) => editor.chain().focus().updateAttributes('image', { alt: description }).run(),
            });

            return;
        }

        if (id === 'image') {
            picker?.open((choice) => {
                const src = mediaPathFrom(choice.url, origin);

                if (!src) {
                    status && (status.textContent = texts.imageRefused ?? '');

                    return;
                }

                dialogs.image?.open({
                    src,
                    alt: choice.alt ?? '',
                    opener: button,
                    onInsert: (alt) => editor.chain().focus().setImage({ src, alt }).run(),
                });
            }, button);

            return;
        }

        runTool(editor, id);
    };
    toolbar.addEventListener('click', onTool);
    tableBar.addEventListener('click', onTool);

    // The editor replaces the read-only content.
    if (preview) {
        preview.hidden = true;
    }
    root.append(toolbar, tableBar, mount);
    if (status) {
        status.textContent = texts.ready ?? '';
        status.classList.add('visually-hidden');
    }
    const hint = doc.getElementById?.(preview?.getAttribute('aria-describedby') ?? '');
    if (hint) {
        hint.hidden = false;
    }
    root.dataset.editorState = 'ready';
    refresh();

    return { editor, toolbar, tableBar, field };
}

/** The link dialog: an address checked by safeLinkHref(); Enter applies, never submits the page form. */
export function linkDialog(dialog) {
    if (!dialog) {
        return null;
    }

    const input = dialog.querySelector('[data-link-input]');
    const error = dialog.querySelector('[data-link-error]');
    let current = null;

    const apply = () => {
        const href = safeLinkHref(input.value);

        if (!href) {
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.focus();

            return;
        }

        const done = current;
        dialog.close();
        done?.onApply(href);
    };

    dialog.querySelector('[data-link-apply]').addEventListener('click', apply);
    dialog.querySelector('[data-link-remove]').addEventListener('click', () => {
        const done = current;
        dialog.close();
        done?.onRemove();
    });
    dialog.querySelector('[data-dialog-cancel]').addEventListener('click', () => dialog.close());
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            apply();
        }
    });
    dialog.addEventListener('close', () => current?.opener?.focus());

    return {
        open(options) {
            current = options;
            input.value = options.href ?? '';
            error.hidden = true;
            input.removeAttribute('aria-invalid');
            dialog.querySelector('[data-link-remove]').hidden = !options.href;
            dialog.showModal();
            input.focus();
        },
    };
}

/** The image dialog: the chosen image and its required description. */
export function imageDialog(dialog) {
    if (!dialog) {
        return null;
    }

    const input = dialog.querySelector('[data-image-alt]');
    const error = dialog.querySelector('[data-image-error]');
    const image = dialog.querySelector('[data-image-preview]');
    const insertButton = dialog.querySelector('[data-image-insert]');
    const labels = { insert: insertButton.textContent, update: insertButton.dataset.updateLabel ?? insertButton.textContent };
    let current = null;

    const insert = () => {
        const alt = input.value.trim();

        if (alt === '') {
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.focus();

            return;
        }

        const done = current;
        dialog.close();
        done?.onInsert(alt);
    };

    insertButton.addEventListener('click', insert);
    dialog.querySelector('[data-dialog-cancel]').addEventListener('click', () => dialog.close());
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            insert();
        }
    });
    dialog.addEventListener('close', () => current?.opener?.focus());

    return {
        open(options) {
            current = options;
            image.src = options.src;
            image.alt = '';
            insertButton.textContent = options.update ? labels.update : labels.insert;
            input.value = options.alt ?? '';
            error.hidden = true;
            input.removeAttribute('aria-invalid');
            dialog.showModal();
            input.focus();
        },
    };
}

function start() {
    const form = document.querySelector('[data-content-page-form]');

    if (!form) {
        return;
    }

    const direction = document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
    initLanguageTabs(form, { direction, doc: document });

    const configElement = document.querySelector('[data-content-editor-config]');
    const config = configElement ? JSON.parse(configElement.textContent) : { texts: {} };
    const pickerRoot = document.querySelector('[data-admin-media-picker][data-picker-mode="callback"]');
    const picker = config.canBrowseMedia && pickerRoot ? initMediaPicker(pickerRoot) : null;
    const dialogs = {
        link: linkDialog(document.querySelector('[data-link-dialog]')),
        image: imageDialog(document.querySelector('[data-image-dialog]')),
    };

    form.querySelectorAll('[data-content-editor]').forEach((root) => {
        initContentEditor(root, { texts: config.texts, dialogs, picker: picker?.open ? picker : null, origin: window.location.origin });
    });
}

if (typeof document !== 'undefined') {
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
