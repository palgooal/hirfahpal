@props([
    'name' => 'image_media_id',
    'removeName' => 'remove_image',
    'value' => null,
    'labelKey' => 'Category_Image',
    'labelDefault' => 'Image',
    'canRemove' => true,
])

{{--
    HIRFAH admin media picker (AD-UI-CAT-M4-B, decision M4-D1 option C). Chooses one image from the media
    library through its JSON API (type=image), can upload into it, previews, replaces and unlinks. Unlinking only
    clears the reference: the media item and its file stay in the library.

    Posts two fields: {name} (the chosen media id, or the current one; empty means "no change") and {removeName}
    (1 only when the admin asked to remove the image). The server decides what changes; see
    CategoryManagementController::imageChange(). Buttons follow media.view / media.create; the server checks
    them again. Driven by resources/js/admin-media-picker.js; texts through t() without auto-creating keys.
--}}
@php
    $pickerAdmin = auth('admin')->user();
    $pickerGate = \Illuminate\Support\Facades\Gate::forUser($pickerAdmin);
    $pickerCan = [
        'browse' => $pickerGate->allows('viewAny', \Palgoal\MediaLibrary\Models\Media::class),
        'upload' => $pickerGate->allows('create', \Palgoal\MediaLibrary\Models\Media::class),
    ];

    $pickerText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $pickerValue = old($name, $value);
    $pickerValue = is_numeric($pickerValue) && (int) $pickerValue > 0 ? (int) $pickerValue : null;
    $pickerRemoved = (bool) old($removeName, false);
    // The preview is resolved on the server (no media.view needed to see the current image).
    $pickerMedia = $pickerValue ? \Palgoal\MediaLibrary\Models\Media::query()->images()->find($pickerValue) : null;
    $pickerId = 'media-picker-'.\Illuminate\Support\Str::slug($name);
    $pickerError = $errors->first($name) ?: $errors->first($removeName);

    $pickerConfig = [
        'urls' => [
            'index' => route('media-library.media.index'),
            'store' => route('media-library.media.store'),
        ],
        'csrf' => csrf_token(),
        'locale' => str_replace('_', '-', app()->getLocale()),
        'perPage' => 24,
        'can' => $pickerCan,
        'messages' => [
            'loading' => $pickerText('Media_Loading', 'Loading images…'),
            'empty' => $pickerText('Media_Empty', 'No images yet.'),
            'emptySearch' => $pickerText('Media_Empty_Search', 'No images match your search.'),
            'loadError' => $pickerText('Media_Load_Error', 'The images could not be loaded.'),
            'forbidden' => $pickerText('Media_Forbidden', 'You do not have permission to do this.'),
            'network' => $pickerText('Media_Picker_Network', 'The connection failed. Check the network and try again.'),
            'select' => $pickerText('Media_Select_Item', 'Select :name'),
            'selected' => $pickerText('Media_Picker_Selected', 'Selected image: :name'),
            'uploading' => $pickerText('Media_Uploading', 'Uploading…'),
            'uploaded' => $pickerText('Media_Uploaded_Count', 'Images uploaded: :count.'),
            'uploadFailed' => $pickerText('Media_Upload_Failed', 'The images could not be uploaded.'),
            'dismiss' => $pickerText('Media_Dismiss', 'Dismiss'),
            'none' => $pickerText('Media_Picker_None', 'No image selected.'),
        ],
    ];
@endphp

<div {{ $attributes->class(['admin-media', 'admin-media-picker']) }} id="{{ $pickerId }}" data-admin-media-picker>
    <span class="form-label admin-media-picker-label" id="{{ $pickerId }}-label">{{ $pickerText($labelKey, $labelDefault) }}</span>

    <input type="hidden" name="{{ $name }}" value="{{ $pickerValue }}" data-picker-value>
    <input type="hidden" name="{{ $removeName }}" value="{{ $pickerRemoved ? 1 : 0 }}" data-picker-remove>

    <div class="admin-media-picker-field @if ($pickerError) is-invalid @endif" role="group" aria-labelledby="{{ $pickerId }}-label" @if ($pickerError) aria-describedby="{{ $pickerId }}-error" @endif>
        <div class="admin-media-picker-preview" data-picker-preview>
            <img src="{{ $pickerMedia?->url }}" alt="{{ $pickerMedia?->alt ?: '' }}" data-picker-image @if (! $pickerMedia || $pickerRemoved) hidden @endif>
            <i class="ph-duotone ph-image" aria-hidden="true" data-picker-placeholder @if ($pickerMedia && ! $pickerRemoved) hidden @endif></i>
        </div>

        <div class="admin-media-picker-body">
            <p class="admin-media-picker-name" data-picker-name data-none="{{ $pickerText('Media_Picker_None', 'No image selected.') }}" aria-live="polite">
                @if ($pickerMedia && ! $pickerRemoved)
                    <bdi>{{ $pickerMedia->file_original_name ?: $pickerMedia->file_name }}</bdi>
                @else
                    {{ $pickerText('Media_Picker_None', 'No image selected.') }}
                @endif
            </p>
            <p class="admin-media-picker-note" data-picker-removed-note @unless ($pickerRemoved) hidden @endunless>
                {{ $pickerText('Media_Picker_Removed_Note', 'The image will be removed from this category when you save. The file stays in the media library.') }}
            </p>
            <p class="admin-media-hint">{{ $pickerText('Media_Picker_Hint', 'Optional. A JPG, PNG, GIF or WebP image from the media library.') }}</p>

            <div class="admin-media-picker-actions">
                @if ($pickerCan['browse'])
                    <button type="button" class="btn btn-sm btn-light-secondary" data-picker-open aria-haspopup="dialog" aria-controls="{{ $pickerId }}-dialog">
                        <i class="ph-duotone ph-images" aria-hidden="true"></i>
                        <span data-picker-open-label data-choose="{{ $pickerText('Media_Picker_Choose', 'Choose image') }}" data-replace="{{ $pickerText('Media_Picker_Replace', 'Replace image') }}">{{ $pickerMedia && ! $pickerRemoved ? $pickerText('Media_Picker_Replace', 'Replace image') : $pickerText('Media_Picker_Choose', 'Choose image') }}</span>
                    </button>
                @endif
                @if ($canRemove)
                    <button type="button" class="btn btn-sm btn-light-danger" data-picker-remove-button @if (! $pickerMedia || $pickerRemoved) hidden @endif>
                        <i class="ph-duotone ph-x" aria-hidden="true"></i>
                        <span>{{ $pickerText('Media_Picker_Remove', 'Remove image') }}</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-light-secondary" data-picker-undo-remove @unless ($pickerRemoved) hidden @endunless>
                        {{ $pickerText('Media_Picker_Undo_Remove', 'Keep image') }}
                    </button>
                @endif
            </div>

            @unless ($pickerCan['browse'])
                <p class="admin-media-hint">{{ $pickerText('Media_Picker_No_Access', 'You need permission to view the media library to choose or replace the image.') }}</p>
            @endunless
        </div>
    </div>

    @if ($pickerError)
        <p class="admin-media-field-error" id="{{ $pickerId }}-error">{{ $pickerError }}</p>
    @endif

    @if ($pickerCan['browse'])
        <dialog class="admin-media-dialog admin-media-picker-dialog" id="{{ $pickerId }}-dialog" aria-labelledby="{{ $pickerId }}-title" data-picker-dialog>
            <div class="admin-media-dialog-header">
                <h2 class="admin-media-dialog-title" id="{{ $pickerId }}-title">{{ $pickerText('Media_Picker_Title', 'Choose an image') }}</h2>
                <button type="button" class="admin-media-icon-button" data-picker-close aria-label="{{ $pickerText('Media_Close', 'Close') }}">
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <div class="admin-media-picker-toolbar">
                <label class="visually-hidden" for="{{ $pickerId }}-search">{{ $pickerText('Media_Search_Label', 'Search images') }}</label>
                <div class="admin-media-search">
                    <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control" id="{{ $pickerId }}-search" autocomplete="off" data-picker-search placeholder="{{ $pickerText('Media_Search_Placeholder', 'Search by name, title or caption') }}">
                </div>
                <button type="button" class="btn btn-sm btn-light-secondary" data-picker-search-clear>{{ $pickerText('Media_Search_Clear', 'Clear search') }}</button>
                @if ($pickerCan['upload'])
                    <button type="button" class="btn btn-sm btn-primary" data-picker-upload>
                        <i class="ph-duotone ph-upload-simple" aria-hidden="true"></i>
                        <span>{{ $pickerText('Media_Upload', 'Upload images') }}</span>
                    </button>
                    <label class="visually-hidden" for="{{ $pickerId }}-file">{{ $pickerText('Media_Upload', 'Upload images') }}</label>
                    <input type="file" class="visually-hidden" id="{{ $pickerId }}-file" tabindex="-1" aria-hidden="true" accept="image/jpeg,image/png,image/gif,image/webp" data-picker-file>
                @endif
            </div>

            <div class="admin-media-dialog-scroll">
                <div class="admin-media-alerts" data-picker-alerts></div>

                <ul class="admin-media-grid admin-media-picker-grid" data-picker-grid aria-busy="true" aria-label="{{ $pickerText('Media_Grid_Label', 'Images') }}"></ul>

                <div class="admin-media-state" data-picker-loading role="status">
                    <span class="admin-media-spinner" aria-hidden="true"></span>
                    <span>{{ $pickerText('Media_Loading', 'Loading images…') }}</span>
                </div>
                <div class="admin-media-state" data-picker-empty hidden>
                    <i class="ph-duotone ph-images" aria-hidden="true"></i>
                    <span data-picker-empty-text></span>
                </div>
                <div class="admin-media-state admin-media-state-error" data-picker-error role="alert" hidden>
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <span data-picker-error-text></span>
                    <button type="button" class="btn btn-sm btn-light-secondary" data-picker-retry>
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ $pickerText('Media_Retry', 'Try again') }}</span>
                    </button>
                </div>
                <div class="admin-media-more">
                    <button type="button" class="btn btn-light-secondary" data-picker-more hidden>{{ $pickerText('Media_Load_More', 'Load more') }}</button>
                </div>
            </div>

            <div class="admin-media-dialog-footer">
                <p class="admin-media-picker-selection" data-picker-selection aria-live="polite"></p>
                <div class="admin-media-picker-footer-actions">
                    <button type="button" class="btn btn-light-secondary" data-picker-cancel>{{ $pickerText('Media_Picker_Cancel', 'Cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-picker-confirm disabled>{{ $pickerText('Media_Picker_Use', 'Use this image') }}</button>
                </div>
            </div>
        </dialog>

        <script type="application/json" data-picker-config>@json($pickerConfig)</script>
    @endif
</div>

@if ($pickerCan['browse'] || $canRemove)
    @once
        @push('scripts')
            @vite('resources/js/admin-media-picker.js')
        @endpush
    @endonce
@endif
