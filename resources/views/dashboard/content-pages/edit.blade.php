{{--
    PAGES-03A: one content page (content-pages.view to open, content-pages.edit to save). The category form's pieces:
    the page (address, locked for a system page; state, kept published for a system page), then Arabic and English,
    each with its title, summary and SEO texts, and its content shown read-only until the visual editor (PAGES-03B).

    PAGES-03B: each language's content in the visual editor (resources/js/admin-content-editor.js, Tiptap), the two
    languages as tabs. Each [data-content-editor] holds the saved, cleaned content read-only, a status line and a
    hidden, disabled translations[<locale>][body_html]. The script mounts the editor; the field is enabled only once
    the content is changed (or was sent before a failed save), so a body is posted only when edited, and never by an
    editor that failed to start. Without the script (or if it fails) the content stays read-only and saving keeps it.
    Images come from the media library through the picker in callback mode; links and images go through dialogs.

    PAGES-04B: the same form creates a custom page (a new, unsaved $page; content-pages.create): the address is typed
    (checked as sent by StoreContentPageRequest), the state is chosen, and the previews appear once the page is saved.

    PAGES-04C: a custom page's address stays editable until its first publication, then is shown locked.

    PAGES-05C: one tab and panel per language of the registry (StorefrontContentPage::editableLocales(): the source
    language first, then every enabled one), named by the registry and written in its direction (is_rtl). A disabled
    language is not shown, so nothing of it is sent and its saved translation is kept.
--}}
@php
    $cpText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $sanitizer = app(\App\Support\Content\PageContentSanitizer::class);
    $limits = \App\Http\Requests\Dashboard\UpdateContentPageRequest::LIMITS;
    $sourceLocale = \App\Models\StorefrontContentPage::SOURCE_LOCALE;
    $sourceName = \App\Models\StorefrontContentPage::languageName($sourceLocale);
    $languages = collect(\App\Models\StorefrontContentPage::editableLocales())->mapWithKeys(fn (string $locale) => [$locale => [
        \App\Models\StorefrontContentPage::languageName($locale), \App\Models\StorefrontContentPage::directionOf($locale),
    ]])->all();
    $fieldLabels = (new \App\Http\Requests\Dashboard\UpdateContentPageRequest)->attributes();
    $sourceTitle = $page->translations->firstWhere('locale', $sourceLocale)?->title ?: $page->title;
    $newTab = $cpText('Content_Pages_Opens_New_Tab', 'opens in a new tab');
    // The first language with an error opens first.
    $initialLanguage = collect(array_keys($languages))->first(fn ($locale) => collect($errors->keys())->contains(fn ($key) => str_starts_with($key, "translations.{$locale}"))) ?? $sourceLocale;
    $canBrowseMedia = auth('admin')->user()->can('viewAny', \Palgoal\MediaLibrary\Models\Media::class);
    $editorConfig = [
        'texts' => [
            'toolbar' => $cpText('Content_Editor_Toolbar', 'Formatting'),
            'tableToolbar' => $cpText('Content_Editor_Table_Toolbar', 'Table'),
            'paragraph' => $cpText('Content_Editor_Paragraph', 'Paragraph'),
            'heading2' => $cpText('Content_Editor_Heading_2', 'Heading'),
            'heading3' => $cpText('Content_Editor_Heading_3', 'Subheading'),
            'heading4' => $cpText('Content_Editor_Heading_4', 'Small heading'),
            'bold' => $cpText('Content_Editor_Bold', 'Bold'),
            'italic' => $cpText('Content_Editor_Italic', 'Italic'),
            'underline' => $cpText('Content_Editor_Underline', 'Underline'),
            'strike' => $cpText('Content_Editor_Strike', 'Strikethrough'),
            'bulletList' => $cpText('Content_Editor_Bullet_List', 'Bulleted list'),
            'orderedList' => $cpText('Content_Editor_Numbered_List', 'Numbered list'),
            'blockquote' => $cpText('Content_Editor_Quote', 'Quote'),
            'horizontalRule' => $cpText('Content_Editor_Divider', 'Divider'),
            'link' => $cpText('Content_Editor_Link', 'Link'),
            'unlink' => $cpText('Content_Editor_Unlink', 'Remove link'),
            'table' => $cpText('Content_Editor_Table', 'Insert table'),
            'addRow' => $cpText('Content_Editor_Add_Row', 'Add row'),
            'deleteRow' => $cpText('Content_Editor_Delete_Row', 'Delete row'),
            'addColumn' => $cpText('Content_Editor_Add_Column', 'Add column'),
            'deleteColumn' => $cpText('Content_Editor_Delete_Column', 'Delete column'),
            'deleteTable' => $cpText('Content_Editor_Delete_Table', 'Delete table'),
            'image' => $cpText('Content_Editor_Image', 'Image'),
            'undo' => $cpText('Content_Editor_Undo', 'Undo'),
            'redo' => $cpText('Content_Editor_Redo', 'Redo'),
            'ready' => $cpText('Content_Editor_Ready', 'The editor is ready.'),
            'failed' => $cpText('Content_Editor_Failed', 'The editor could not start. The saved content is shown and will be kept when you save.'),
            'imageRefused' => $cpText('Content_Editor_Image_Refused', 'Only images from the media library can be inserted.'),
        ],
        'canBrowseMedia' => $canBrowseMedia,
    ];
@endphp
<x-dashboard-layout>
    <x-slot:pageTitle>{{ ! $page->exists ? $cpText('Content_Pages_Create', 'New page') : str_replace(':title', $sourceTitle, $canEdit ? $cpText('Content_Pages_Edit_Page', 'Edit :title') : $cpText('Content_Pages_View_Page', 'View :title')) }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-cat-form admin-content-page-form" id="admin-content-page-form">
        <div class="admin-media-head">
            <a href="{{ route('dashboard.content-pages.index') }}" class="btn btn-light-secondary">{{ $cpText('Content_Pages_Back', 'Back to pages') }}</a>
        </div>

        @if (session('success'))
            <div class="admin-media-alert is-success" role="status">
                <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                <div class="admin-media-alert-content"><p>{{ session('success') }}</p></div>
            </div>
        @endif

        @if ($errors->any())
            {{-- Error summary: translated field names, each linked to its field (DEC-AD-UI-12). --}}
            <div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="content-page-errors">
                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                <div class="admin-media-alert-content">
                    <p>{{ $cpText('Content_Pages_Form_Errors', 'Please correct the following:') }}</p>
                    <ul>
                        @foreach ($errors->getMessages() as $field => $messages)
                            <li><a href="#{{ str_replace('.', '-', $field) }}">{{ $fieldLabels[$field] ?? $field }}</a>: {{ $messages[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $page->exists ? route('dashboard.content-pages.update', $page) : route('dashboard.content-pages.store') }}" class="card admin-media-panel admin-cat-form-card" novalidate data-content-page-form>
            @csrf
            @if ($page->exists)
                @method('PUT')
            @endif

            <fieldset class="admin-content-page-fields" @disabled(! $canEdit)>
                <fieldset class="admin-cat-form-section">
                    <legend class="admin-cat-form-legend">{{ $cpText('Content_Pages_Section_Page', 'Page') }}</legend>

                    <div class="admin-cat-form-row">
                        <div class="admin-cat-form-field">
                            @if ($page->isSlugLocked())
                                <span class="form-label" id="content-page-slug-label">{{ $cpText('Content_Pages_Field_Slug', 'Address') }}</span>
                                <p class="admin-content-page-slug mb-0" aria-labelledby="content-page-slug-label"><bdi dir="ltr">/pages/{{ $page->slug }}</bdi></p>
                                @if ($page->is_system)
                                    <p class="admin-media-hint mb-0">{{ $cpText('Content_Pages_Slug_Locked', 'A system page keeps its address.') }}</p>
                                @else
                                    <p class="admin-media-hint mb-0" data-slug-locked>{{ $cpText('Content_Pages_Slug_Locked_Published', 'The address is locked: the page has been published, so links to it must keep working.') }}</p>
                                @endif
                            @else
                                {{-- PAGES-04B / 04C: editable until the first publication; the browser pattern only helps, the server decides. --}}
                                @php($slugError = $errors->first('slug'))
                                <label class="form-label" for="slug">{{ $cpText('Content_Pages_Field_Slug', 'Address') }}</label>
                                <input type="text" id="slug" name="slug" value="{{ old('slug', $page->slug) }}" maxlength="{{ \App\Models\StorefrontContentPage::SLUG_MAX }}" pattern="{{ \App\Models\StorefrontContentPage::SLUG_PATTERN }}"
                                    dir="ltr" lang="en" autocomplete="off" autocapitalize="off" spellcheck="false" required aria-required="true" aria-describedby="slug-hint{{ $slugError ? ' slug-error' : '' }}"
                                    @class(['form-control', 'is-invalid' => $slugError]) @if ($slugError) aria-invalid="true" @endif>
                                <p class="admin-media-hint mb-0" id="slug-hint">{{ $cpText('Content_Pages_Slug_Hint', 'The page will be at /pages/ followed by this address. Lowercase English letters, digits and single hyphens, for example our-story. It can no longer be changed once the page is published.') }}</p>
                                @if ($slugError) <p class="admin-cat-form-error" id="slug-error">{{ $slugError }}</p> @endif
                            @endif
                        </div>

                        <div class="admin-cat-form-field admin-cat-form-field-narrow">
                            @if ($page->is_system)
                                <span class="form-label" id="status-label">{{ $cpText('Content_Pages_Field_Status', 'Status') }}</span>
                                <input type="hidden" name="status" value="published">
                                <p class="mb-0" aria-labelledby="status-label"><span class="admin-content-page-status"><i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>{{ $cpText('Content_Pages_Status_Published', 'Published') }}</span></p>
                                <p class="admin-media-hint mb-0" id="status">{{ $cpText('Content_Pages_System_Status_Note', 'System pages stay published: links in the store lead to them.') }}</p>
                            @else
                                <label class="form-label" for="status">{{ $cpText('Content_Pages_Field_Status', 'Status') }}</label>
                                <select id="status" name="status" @class(['form-select', 'is-invalid' => $errors->has('status')])>
                                    @foreach (\App\Models\StorefrontContentPage::STATUSES as $status)
                                        <option value="{{ $status }}" @selected(old('status', $page->status) === $status)>{{ $status === 'published' ? $cpText('Content_Pages_Status_Published', 'Published') : $cpText('Content_Pages_Status_Draft', 'Draft') }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('status') <p class="admin-cat-form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Revealed by admin-content-editor.js; without it every language is shown in turn. Scrolls sideways when the languages do not fit. --}}
                <div class="admin-content-page-tabs" role="tablist" aria-label="{{ $cpText('Content_Editor_Languages', 'Languages') }}" data-language-tablist data-initial-language="{{ $initialLanguage }}" hidden>
                    @foreach ($languages as $locale => [$language, $direction])
                        @php($tabErrors = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, "translations.{$locale}"))->count())
                        <button type="button" class="admin-content-page-tab" role="tab" id="language-tab-{{ $locale }}" aria-controls="language-panel-{{ $locale }}" aria-selected="{{ $locale === $initialLanguage ? 'true' : 'false' }}" tabindex="{{ $locale === $initialLanguage ? '0' : '-1' }}" data-language-tab="{{ $locale }}">
                            <span lang="{{ $locale }}" dir="{{ $direction }}">{{ $language }}</span>
                            @if ($tabErrors > 0)
                                <span class="admin-content-page-tab-alert"><span aria-hidden="true">{{ $tabErrors }}</span><span class="visually-hidden">{{ $cpText('Content_Editor_Has_Errors', 'Contains errors') }}</span></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @foreach ($languages as $locale => [$language, $direction])
                    @php($row = $page->translations->firstWhere('locale', $locale))
                    @php($body = $row ? $sanitizer->sanitize($row->body_html) : ($locale === $sourceLocale ? $sanitizer->sanitize(\App\Support\Content\PageContentSanitizer::fromPlainText($page->body)) : ''))
                    <fieldset class="admin-cat-form-section" id="language-panel-{{ $locale }}" data-content-language="{{ $locale }}" data-content-direction="{{ $direction }}">
                        <legend class="admin-cat-form-legend"><span lang="{{ $locale }}" dir="{{ $direction }}">{{ $language }}</span></legend>
                        <p class="admin-media-hint mb-0" data-language-requirement="{{ $locale === $sourceLocale ? 'required' : 'optional' }}">{{ $locale === $sourceLocale
                            ? $cpText('Content_Pages_Source_Hint', 'Required: a title and content. Visitors see this language when theirs is not complete.')
                            : str_replace(':source', $sourceName, $cpText('Content_Pages_Translation_Hint', 'Optional. Until its title and content are both filled in, visitors see the :source page.')) }}</p>

                        @foreach (['title' => 'input', 'excerpt' => 'textarea'] as $field => $control)
                            @php($name = "translations[{$locale}][{$field}]")
                            @php($id = "translations-{$locale}-{$field}")
                            @php($error = $errors->first("translations.{$locale}.{$field}"))
                            @php($value = old("translations.{$locale}.{$field}", $row?->{$field} ?? ($locale === $sourceLocale ? $page->{$field} : '')))
                            <div class="admin-cat-form-field">
                                <label class="form-label" for="{{ $id }}">{{ $fieldLabels["translations.{$locale}.{$field}"] }}</label>
                                @if ($control === 'input')
                                    <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $limits[$field] }}" lang="{{ $locale }}" dir="{{ $direction }}"
                                        @class(['form-control', 'is-invalid' => $error]) @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif @if ($locale === $sourceLocale && $field === 'title') required aria-required="true" @endif>
                                @else
                                    <textarea id="{{ $id }}" name="{{ $name }}" rows="2" maxlength="{{ $limits[$field] }}" lang="{{ $locale }}" dir="{{ $direction }}"
                                        @class(['form-control', 'is-invalid' => $error]) @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ $value }}</textarea>
                                @endif
                                @if ($error) <p class="admin-cat-form-error" id="{{ $id }}-error">{{ $error }}</p> @endif
                            </div>
                        @endforeach

                        {{-- The content: the editor (PAGES-03B), on top of the saved content shown read-only. --}}
                        @php($bodyError = $errors->first("translations.{$locale}.body_html"))
                        @php($sentBody = old("translations.{$locale}.body_html"))
                        @php($editorBody = is_string($sentBody) ? $sanitizer->sanitize($sentBody) : $body)
                        <div class="admin-cat-form-field">
                            <span class="form-label" id="content-body-{{ $locale }}-label">{{ $fieldLabels["translations.{$locale}.body_html"] }}</span>
                            <div class="admin-content-editor @if ($bodyError) is-invalid @endif" data-content-editor data-locale="{{ $locale }}" data-editable="{{ $canEdit ? 'true' : 'false' }}"
                                data-body-sent="{{ is_string($sentBody) ? 'true' : 'false' }}" lang="{{ $locale }}" dir="{{ $direction }}">
                                <p class="admin-media-hint mb-0" role="status" dir="auto" data-content-editor-status>@if ($canEdit){{ $cpText('Content_Editor_Loading', 'Loading the editor…') }}@endif</p>
                                <div class="admin-content-page-body hirfah-admin-prose" id="content-body-{{ $locale }}" role="region" tabindex="0" lang="{{ $locale }}" dir="{{ $direction }}"
                                    aria-labelledby="content-body-{{ $locale }}-label" aria-describedby="content-body-{{ $locale }}-hint" data-content-preview>
                                    @if ($editorBody !== '')
                                        {!! $editorBody !!}
                                    @else
                                        <p class="admin-media-hint mb-0">{{ $cpText('Content_Pages_Body_Empty', 'No content in this language yet.') }}</p>
                                    @endif
                                </div>
                                <textarea id="translations-{{ $locale }}-body_html" name="translations[{{ $locale }}][body_html]" hidden disabled data-content-body-field>{{ $editorBody }}</textarea>
                            </div>
                            @if ($bodyError)
                                <p class="admin-cat-form-error" id="translations-{{ $locale }}-body_html-error" role="alert">{{ $bodyError }}</p>
                            @endif
                            {{-- PAGES-03C: the keyboard hint only once the editor works (shown by the script). --}}
                            <p class="admin-media-hint mb-0" id="content-body-{{ $locale }}-hint" @if ($canEdit) hidden @endif>{{ $canEdit
                                ? $cpText('Content_Editor_Hint', 'Alt+F10 moves to the formatting tools; Escape returns to the text.')
                                : $cpText('Content_Pages_Body_Readonly_View', 'You can view this content but not change it.') }}</p>
                        </div>

                        <details class="admin-content-page-seo" @if ($errors->has("translations.{$locale}.meta_title") || $errors->has("translations.{$locale}.meta_description") || filled($row?->meta_title) || filled($row?->meta_description)) open @endif>
                            <summary>{{ $fieldLabels["translations.{$locale}.meta_title"] }} · {{ $fieldLabels["translations.{$locale}.meta_description"] }}</summary>
                            <p class="admin-media-hint" id="translations-{{ $locale }}-meta-hint">{{ $cpText('Content_Pages_Meta_Hint', 'Shown in search results. When empty, the title and summary are used. About 60 characters for the title and 160 for the description work best.') }}</p>
                            @foreach (['meta_title' => 'input', 'meta_description' => 'textarea'] as $field => $control)
                                @php($name = "translations[{$locale}][{$field}]")
                                @php($id = "translations-{$locale}-{$field}")
                                @php($error = $errors->first("translations.{$locale}.{$field}"))
                                @php($value = old("translations.{$locale}.{$field}", $row?->{$field} ?? ''))
                                <div class="admin-cat-form-field">
                                    <label class="form-label" for="{{ $id }}">{{ $fieldLabels["translations.{$locale}.{$field}"] }}</label>
                                    @if ($control === 'input')
                                        <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $limits[$field] }}" lang="{{ $locale }}" dir="{{ $direction }}"
                                            @class(['form-control', 'is-invalid' => $error]) aria-describedby="translations-{{ $locale }}-meta-hint{{ $error ? ' '.$id.'-error' : '' }}" @if ($error) aria-invalid="true" @endif>
                                    @else
                                        <textarea id="{{ $id }}" name="{{ $name }}" rows="2" maxlength="{{ $limits[$field] }}" lang="{{ $locale }}" dir="{{ $direction }}"
                                            @class(['form-control', 'is-invalid' => $error]) aria-describedby="translations-{{ $locale }}-meta-hint{{ $error ? ' '.$id.'-error' : '' }}" @if ($error) aria-invalid="true" @endif>{{ $value }}</textarea>
                                    @endif
                                    @if ($error) <p class="admin-cat-form-error" id="{{ $id }}-error">{{ $error }}</p> @endif
                                </div>
                            @endforeach
                        </details>

                        @if ($page->exists)
                        @php($previewLabel = str_replace(':language', $language, $cpText('Content_Pages_Preview_In', 'Preview in :language')))
                        <p class="mb-0">
                            <a href="{{ route('dashboard.content-pages.preview', [$page, $locale]) }}" class="btn btn-light-secondary" target="_blank" rel="noopener"
                                aria-label="{{ $previewLabel }} ({{ $newTab }})">
                                <i class="ph-duotone ph-eye" aria-hidden="true"></i><span>{{ $previewLabel }}</span>
                            </a>
                        </p>
                        @endif
                    </fieldset>
                @endforeach
            </fieldset>

            @if ($canEdit)
                <div class="admin-cat-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                        <span>{{ $cpText('Content_Pages_Save', 'Save page') }}</span>
                    </button>
                </div>
            @endif
        </form>

        @if ($canEdit)
            <script type="application/json" data-content-editor-config>@json($editorConfig)</script>
            {{-- When the editor script never runs (blocked, failed download), "Loading" must not stay: after 10 s an
                 editor that is neither ready nor failed says it could not start; its content is kept on save. --}}
            <script>
                setTimeout(function () {
                    document.querySelectorAll('[data-content-editor][data-editable="true"]:not([data-editor-state]) [data-content-editor-status]').forEach(function (status) {
                        status.textContent = @json($editorConfig['texts']['failed']);
                        status.setAttribute('role', 'alert');
                        status.classList.add('is-error');
                    });
                }, 10000);
            </script>

            <dialog class="admin-media-dialog admin-content-dialog" aria-labelledby="content-link-title" data-link-dialog>
                <div class="admin-content-dialog-body">
                    <h2 class="admin-content-dialog-title" id="content-link-title">{{ $cpText('Content_Editor_Link_Title', 'Link') }}</h2>
                    <label class="form-label" for="content-link-url">{{ $cpText('Content_Editor_Link_Url', 'Address') }}</label>
                    <input type="text" class="form-control" id="content-link-url" dir="ltr" inputmode="url" autocomplete="off" spellcheck="false" aria-describedby="content-link-hint" data-link-input>
                    <p class="admin-media-hint mb-0" id="content-link-hint">{{ $cpText('Content_Editor_Link_Hint', 'https://…, mailto:…, tel:…, or a page of this site starting with /.') }}</p>
                    <p class="admin-cat-form-error" role="alert" data-link-error hidden>{{ $cpText('Content_Editor_Link_Invalid', 'Enter a full address starting with https://, mailto: or tel:, or a page of this site starting with /.') }}</p>
                    <div class="admin-content-dialog-actions">
                        <button type="button" class="btn btn-primary" data-link-apply>{{ $cpText('Content_Editor_Link_Apply', 'Apply') }}</button>
                        <button type="button" class="btn btn-light-secondary" data-link-remove>{{ $cpText('Content_Editor_Unlink', 'Remove link') }}</button>
                        <button type="button" class="btn btn-light-secondary" data-dialog-cancel>{{ $cpText('Content_Editor_Cancel', 'Cancel') }}</button>
                    </div>
                </div>
            </dialog>

            <dialog class="admin-media-dialog admin-content-dialog" aria-labelledby="content-image-title" data-image-dialog>
                <div class="admin-content-dialog-body">
                    <h2 class="admin-content-dialog-title" id="content-image-title">{{ $cpText('Content_Editor_Image_Title', 'Image description') }}</h2>
                    <img src="" alt="" class="admin-content-dialog-image" data-image-preview>
                    <label class="form-label" for="content-image-alt">{{ $cpText('Content_Editor_Image_Alt', 'Alternative text') }}</label>
                    <input type="text" class="form-control" id="content-image-alt" maxlength="250" autocomplete="off" aria-describedby="content-image-hint" data-image-alt>
                    <p class="admin-media-hint mb-0" id="content-image-hint">{{ $cpText('Content_Editor_Image_Hint', 'Describe what the image shows, for visitors who cannot see it.') }}</p>
                    <p class="admin-cat-form-error" role="alert" data-image-error hidden>{{ $cpText('Content_Editor_Image_Alt_Required', 'Describe the image before inserting it.') }}</p>
                    <div class="admin-content-dialog-actions">
                        <button type="button" class="btn btn-primary" data-image-insert data-update-label="{{ $cpText('Content_Editor_Image_Update', 'Save description') }}">{{ $cpText('Content_Editor_Image_Insert', 'Insert image') }}</button>
                        <button type="button" class="btn btn-light-secondary" data-dialog-cancel>{{ $cpText('Content_Editor_Cancel', 'Cancel') }}</button>
                    </div>
                </div>
            </dialog>

            @if ($canBrowseMedia)
                <x-admin.media-picker mode="callback" name="content_page_image" remove-name="content_page_image_remove" label-key="Content_Editor_Image" label-default="Image" :can-remove="false" />
            @endif
        @endif
    </div>

    @push('scripts')
        @vite('resources/js/admin-content-editor.js')
    @endpush
</x-dashboard-layout>
