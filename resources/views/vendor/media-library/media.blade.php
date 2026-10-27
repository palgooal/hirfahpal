{{--
    HIRFAH admin media library (AD-UI-CAT-M3). Published override of the palgoal/media-library page: it renders
    inside the admin shell (config media-library.layout = dashboard-layout) with the HIRFAH identity, translated
    through t() (keys dashboard.Media_*, AdminMediaTranslationSeeder) and driven by resources/js/admin-media-library.js.
    The package's own public/vendor/media-library/js/media-library.js is no longer loaded by this page and stays
    untouched, so republishing the package assets cannot change it. Do NOT republish the views with --force:
    this file would be replaced by the package's Tailwind CDN page (MediaLibraryAdminPageTest fails if it is).

    Buttons follow the admin's media.* abilities; the server checks them again on every request.
--}}
@php
    $mediaGate = \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user());
    $mediaProbe = new \Palgoal\MediaLibrary\Models\Media;
    $mediaCan = [
        'create' => $mediaGate->allows('create', \Palgoal\MediaLibrary\Models\Media::class),
        'edit' => $mediaGate->allows('update', $mediaProbe),
        'delete' => $mediaGate->allows('delete', $mediaProbe),
    ];
    // ADMIN-MEDIA-ORGANIZATION-01 P4: the Admin / Vendor media switcher, for admins who may open both sections.
    $mediaAdmin = auth('admin')->user();
    $mediaSections = (bool) ($mediaAdmin?->isSuperAdmin() || ($mediaAdmin?->hasAbility('media.view') && $mediaAdmin->hasAbility('vendors.view')));
    $mediaMaxMb = rtrim(rtrim(number_format(((int) config('media-library.max_upload_size_kb', 10240)) / 1024, 1, '.', ''), '0'), '.');

    // t() without auto-creating the key (as MediaLibraryController does): until AdminMediaTranslationSeeder runs
    // the English defaults are shown, and no Arabic row is ever created with English text.
    $mediaText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $mediaConfig = [
        'urls' => [
            'index' => route('media-library.media.index'),
            'store' => route('media-library.media.store'),
            'bulk' => route('media-library.media.bulk-destroy'),
            'item' => route('media-library.media.update', ['id' => '__ID__']),
        ],
        'csrf' => csrf_token(),
        'locale' => str_replace('_', '-', app()->getLocale()),
        'perPage' => 24,
        'can' => $mediaCan,
        'messages' => [
            'loading' => $mediaText('Media_Loading', 'Loading images…'),
            'empty' => $mediaText('Media_Empty', 'No images yet.'),
            'emptySearch' => $mediaText('Media_Empty_Search', 'No images match your search.'),
            'count' => $mediaText('Media_Count', 'Images: :count'),
            'select' => $mediaText('Media_Select_Item', 'Select :name'),
            'open' => $mediaText('Media_Open_Details', 'Details of :name'),
            'inUseBadge' => $mediaText('Media_In_Use_Badge', 'In use'),
            'unknownUploader' => $mediaText('Media_Unknown_Uploader', 'Unknown'),
            'uploading' => $mediaText('Media_Uploading', 'Uploading…'),
            'uploaded' => $mediaText('Media_Uploaded_Count', 'Images uploaded: :count.'),
            'uploadFailed' => $mediaText('Media_Upload_Failed', 'The images could not be uploaded.'),
            'saving' => $mediaText('Media_Saving', 'Saving…'),
            'saved' => $mediaText('Media_Saved', 'The image details were saved.'),
            'saveFailed' => $mediaText('Media_Save_Failed', 'The changes could not be saved.'),
            'confirmDelete' => $mediaText('Media_Confirm_Delete', 'Delete this image? This cannot be undone.'),
            'confirmBulkDelete' => $mediaText('Media_Confirm_Bulk_Delete', 'Delete the selected images (:count)? This cannot be undone.'),
            'deleted' => $mediaText('Media_deleted', 'The media item was deleted.'),
            'deletedCount' => $mediaText('Media_Deleted_Count', 'Images deleted: :count.'),
            'deleteFailed' => $mediaText('Media_Delete_Failed', 'The image could not be deleted.'),
            'inUse' => $mediaText('Media_in_use', 'This media is used and cannot be deleted. Remove it from where it is used first.'),
            'inUseItems' => $mediaText('Media_In_Use_Items', 'Used: :names'),
            'alreadyDeleted' => $mediaText('Media_Already_Deleted', 'This image was already deleted.'),
            'cleanupWarning' => $mediaText('Media_Cleanup_Warning', 'The records were deleted, but some files could not be removed from storage. They were logged for cleanup.'),
            'forbidden' => $mediaText('Media_Forbidden', 'You do not have permission to do this.'),
            'copied' => $mediaText('Media_Link_Copied', 'Link copied.'),
            'copyFailed' => $mediaText('Media_Copy_Failed', 'The link could not be copied.'),
            'dismiss' => $mediaText('Media_Dismiss', 'Dismiss'),
            'selected' => $mediaText('Media_Selected_Count', 'Selected: :count'),
        ],
    ];
@endphp

<x-dynamic-component :component="config('media-library.layout') ?: 'dashboard-layout'">
    <div class="col-span-12 admin-media" id="admin-media-library" data-can-create="{{ $mediaCan['create'] ? 'true' : 'false' }}" data-can-edit="{{ $mediaCan['edit'] ? 'true' : 'false' }}" data-can-delete="{{ $mediaCan['delete'] ? 'true' : 'false' }}">

        @if ($mediaSections)
            @include('dashboard.media.partials.sections', ['current' => 'admin', 'sectionText' => $mediaText])
        @endif

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $mediaText('Media_Intro', 'Upload and manage the images used in the admin and the store.') }}</p>

            @if ($mediaCan['create'])
                <div class="admin-media-head-actions">
                    <button type="button" class="btn btn-primary" id="media-upload-button">
                        <i class="ph-duotone ph-upload-simple" aria-hidden="true"></i>
                        <span>{{ $mediaText('Media_Upload', 'Upload images') }}</span>
                    </button>
                    <input type="file" id="media-upload-input" class="visually-hidden" tabindex="-1" aria-hidden="true" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                </div>
            @endif
        </div>

        {{-- Result messages: inline, announced; errors stay until dismissed (DEC-AD-UI-14). --}}
        <div class="admin-media-alerts" id="media-alerts"></div>

        @if ($mediaCan['create'])
            <div class="admin-media-dropzone" id="media-dropzone">
                <i class="ph-duotone ph-image" aria-hidden="true"></i>
                <p class="admin-media-dropzone-title">{{ $mediaText('Media_Dropzone', 'Drop images here to upload them') }}</p>
                <p class="admin-media-dropzone-hint">{{ str_replace(':size', $mediaMaxMb, $mediaText('Media_Upload_Hint', 'JPG, PNG, GIF or WebP, up to :size MB each.')) }}</p>
            </div>
        @endif

        <div class="card admin-media-panel">
            {{-- Filter bar (5.3): search; the library lists images only. --}}
            <form class="admin-media-filters" id="media-filters" role="search">
                <label class="form-label visually-hidden" for="media-search">{{ $mediaText('Media_Search_Label', 'Search images') }}</label>
                <div class="admin-media-search">
                    <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control" id="media-search" autocomplete="off" placeholder="{{ $mediaText('Media_Search_Placeholder', 'Search by name, title or caption') }}">
                </div>
                <button type="button" class="btn btn-light-secondary" id="media-search-clear">{{ $mediaText('Media_Search_Clear', 'Clear search') }}</button>
                <p class="admin-media-count" id="media-count" aria-live="polite"></p>
            </form>

            @if ($mediaCan['delete'])
                {{-- Bulk actions (5.6): backed by the bulk delete endpoint and the media.delete ability. --}}
                <div class="admin-media-selection" id="media-selection" hidden>
                    <span id="media-selection-count"></span>
                    <div class="admin-media-selection-actions">
                        <button type="button" class="btn btn-sm btn-light-secondary" id="media-selection-clear">{{ $mediaText('Media_Clear_Selection', 'Clear selection') }}</button>
                        <button type="button" class="btn btn-sm btn-light-danger" id="media-bulk-delete">
                            <i class="ph-duotone ph-trash" aria-hidden="true"></i>
                            <span>{{ $mediaText('Media_Delete_Selected', 'Delete selected') }}</span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="card-body">
                <ul class="admin-media-grid" id="media-grid" aria-busy="true" aria-label="{{ $mediaText('Media_Grid_Label', 'Images') }}"></ul>

                {{-- Inline states (5.12). --}}
                <div class="admin-media-state" id="media-loading" role="status">
                    <span class="admin-media-spinner" aria-hidden="true"></span>
                    <span>{{ $mediaText('Media_Loading', 'Loading images…') }}</span>
                </div>
                <div class="admin-media-state" id="media-empty" hidden>
                    <i class="ph-duotone ph-images" aria-hidden="true"></i>
                    <span id="media-empty-text"></span>
                </div>
                <div class="admin-media-state admin-media-state-error" id="media-error" role="alert" hidden>
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <span>{{ $mediaText('Media_Load_Error', 'The images could not be loaded.') }}</span>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="media-retry">
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ $mediaText('Media_Retry', 'Try again') }}</span>
                    </button>
                </div>

                <div class="admin-media-more">
                    <button type="button" class="btn btn-light-secondary" id="media-load-more" hidden>{{ $mediaText('Media_Load_More', 'Load more') }}</button>
                </div>
            </div>
        </div>

        {{-- Details: a native modal dialog (focus kept inside, Escape closes, focus returns to the opener). --}}
        <dialog class="admin-media-dialog" id="media-details" aria-labelledby="media-details-title">
            <div class="admin-media-dialog-header">
                <h2 class="admin-media-dialog-title" id="media-details-title">{{ $mediaText('Media_Details_Title', 'Image details') }}</h2>
                <button type="button" class="admin-media-icon-button" id="media-details-close" aria-label="{{ $mediaText('Media_Close', 'Close') }}">
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <div class="admin-media-dialog-body">
                <div class="admin-media-dialog-preview">
                    <div class="admin-media-dialog-image"><img id="media-details-image" src="" alt=""></div>
                    <dl class="admin-media-facts">
                        <div><dt>{{ $mediaText('Media_Field_Type', 'Type') }}</dt><dd id="media-details-type"></dd></div>
                        <div><dt>{{ $mediaText('Media_Field_Size', 'Size') }}</dt><dd id="media-details-size"></dd></div>
                        <div><dt>{{ $mediaText('Media_Field_Dimensions', 'Dimensions') }}</dt><dd id="media-details-dimensions" dir="ltr"></dd></div>
                        <div><dt>{{ $mediaText('Media_Field_Uploader', 'Uploaded by') }}</dt><dd><bdi id="media-details-uploader"></bdi></dd></div>
                        <div><dt>{{ $mediaText('Media_Field_Uploaded_At', 'Uploaded on') }}</dt><dd id="media-details-date"></dd></div>
                    </dl>
                    <button type="button" class="btn btn-sm btn-light-secondary w-100" id="media-copy-link">
                        <i class="ph-duotone ph-copy" aria-hidden="true"></i>
                        <span>{{ $mediaText('Media_Copy_Link', 'Copy link') }}</span>
                    </button>
                </div>

                <form class="admin-media-dialog-form" id="media-details-form" novalidate>
                    @foreach ([
                        ['file_original_name', 'Media_Field_Original_Name', 'File name', 'input'],
                        ['title', 'Media_Field_Title', 'Title', 'input'],
                        ['alt', 'Media_Field_Alt', 'Alternative text', 'input'],
                        ['caption', 'Media_Field_Caption', 'Caption', 'textarea'],
                        ['description', 'Media_Field_Description', 'Description', 'textarea'],
                    ] as [$field, $key, $default, $control])
                        <div class="admin-media-field">
                            <label class="form-label" for="media-field-{{ $field }}">{{ $mediaText($key, $default) }}</label>
                            @if ($control === 'textarea')
                                <textarea class="form-control" id="media-field-{{ $field }}" name="{{ $field }}" rows="2" dir="auto" @readonly(! $mediaCan['edit']) aria-describedby="media-error-{{ $field }}{{ $field === 'alt' ? ' media-hint-alt' : '' }}"></textarea>
                            @else
                                <input type="text" class="form-control" id="media-field-{{ $field }}" name="{{ $field }}" maxlength="255" dir="auto" @readonly(! $mediaCan['edit']) aria-describedby="media-error-{{ $field }}{{ $field === 'alt' ? ' media-hint-alt' : '' }}">
                            @endif
                            @if ($field === 'alt')
                                <p class="admin-media-hint" id="media-hint-alt">{{ $mediaText('Media_Field_Alt_Hint', 'Describes the image for screen readers.') }}</p>
                            @endif
                            <p class="admin-media-field-error" id="media-error-{{ $field }}" hidden></p>
                        </div>
                    @endforeach
                </form>
            </div>

            <div class="admin-media-dialog-footer">
                @if ($mediaCan['delete'])
                    <button type="button" class="btn btn-light-danger" id="media-delete">
                        <i class="ph-duotone ph-trash" aria-hidden="true"></i>
                        <span>{{ $mediaText('Media_Delete', 'Delete') }}</span>
                    </button>
                @endif
                @if ($mediaCan['edit'])
                    <button type="submit" form="media-details-form" class="btn btn-primary" id="media-save">{{ $mediaText('Media_Save', 'Save changes') }}</button>
                @endif
            </div>
        </dialog>

        <script type="application/json" id="media-library-config">@json($mediaConfig)</script>
    </div>

    @push('scripts')
        @vite('resources/js/admin-media-library.js')
    @endpush
</x-dynamic-component>
