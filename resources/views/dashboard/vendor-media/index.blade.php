{{--
    ADMIN-MEDIA-ORGANIZATION-01 P4: the Vendor media section of the media library, a read-only view of the vendor
    libraries (VendorMediaLibraryController::index; media.view and vendors.view). Every vendor is a virtual folder
    (P3 dashboard.vendor-media.folders); a folder lists that vendor's images (dashboard.vendor-media.media); an image
    opens read-only details. Nothing here uploads, edits, deletes or selects. Driven by resources/js/admin-vendor-media.js;
    the open folder is ?vendor=ID. Texts through t() without auto-creating keys (dashboard.Media_*,
    AdminMediaTranslationSeeder; the vendor status labels from AdminVendorTranslationSeeder).
--}}
@php
    $vmText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    // Account and approval states with the tones of the vendors list.
    $vmStatuses = [
        'account' => [
            'active' => ['label' => $vmText('Vendors_Account_Active', 'Active'), 'tone' => 'is-active'],
            'pending' => ['label' => $vmText('Vendors_Account_Pending', 'Pending activation'), 'tone' => 'is-low'],
            'blocked' => ['label' => $vmText('Vendors_Account_Blocked', 'Blocked'), 'tone' => 'is-suspended'],
        ],
        'approval' => [
            'pending' => ['label' => $vmText('Vendors_Approval_Pending', 'Pending review'), 'tone' => 'is-low'],
            'approved' => ['label' => $vmText('Vendors_Approval_Approved', 'Approved'), 'tone' => 'is-active'],
            'rejected' => ['label' => $vmText('Vendors_Approval_Rejected', 'Rejected'), 'tone' => 'is-suspended'],
        ],
        'noProfile' => $vmText('Media_Vendors_No_Profile', 'No store profile'),
    ];

    $vmConfig = [
        'urls' => [
            'folders' => route('dashboard.vendor-media.folders'),
            'media' => route('dashboard.vendor-media.media', ['vendor' => '__ID__']),
        ],
        'locale' => str_replace('_', '-', app()->getLocale()),
        'perPage' => ['folders' => 24, 'media' => 40],
        'statuses' => $vmStatuses,
        'messages' => [
            'accountStatus' => $vmText('Vendors_Account_Status', 'Account status'),
            'approvalStatus' => $vmText('Vendors_Approval_Status', 'Approval status'),
            'count' => $vmText('Media_Count', 'Images: :count'),
            'vendorsCount' => $vmText('Media_Vendors_Count', 'Vendors: :count'),
            'vendorsEmpty' => $vmText('Media_Vendors_Empty', 'No vendors yet.'),
            'vendorsEmptySearch' => $vmText('Media_Vendors_Empty_Search', 'No vendors match your search or filters.'),
            'vendorsLoadError' => $vmText('Media_Vendors_Load_Error', 'The vendors could not be loaded.'),
            'imagesOf' => $vmText('Media_Vendors_Images_Label', 'Images of :name'),
            'folderEmpty' => $vmText('Media_Vendors_Folder_Empty', 'This vendor has no images.'),
            'imagesEmptySearch' => $vmText('Media_Empty_Search', 'No images match your search.'),
            'imagesLoadError' => $vmText('Media_Load_Error', 'The images could not be loaded.'),
            'open' => $vmText('Media_Open_Details', 'Details of :name'),
            'notFound' => $vmText('Media_Vendors_Not_Found', 'This vendor folder could not be found.'),
            'sessionExpired' => $vmText('Media_Vendors_Session_Expired', 'Your session has ended. Sign in again to continue.'),
            'forbidden' => $vmText('Media_Forbidden', 'You do not have permission to do this.'),
            'invalidRequest' => $vmText('Media_Vendors_Invalid_Request', 'The request could not be processed. Change the search and try again.'),
            'network' => $vmText('Media_Picker_Network', 'The connection failed. Check the network and try again.'),
            'notSet' => $vmText('Media_Vendors_Not_Set', 'Not set'),
            'fieldOriginalName' => $vmText('Media_Field_Original_Name', 'File name'),
            'fieldAlt' => $vmText('Media_Field_Alt', 'Alternative text'),
            'fieldTitle' => $vmText('Media_Field_Title', 'Title'),
            'fieldType' => $vmText('Media_Field_Type', 'Type'),
            'fieldSize' => $vmText('Media_Field_Size', 'Size'),
            'fieldDimensions' => $vmText('Media_Field_Dimensions', 'Dimensions'),
            'fieldUploadedAt' => $vmText('Media_Field_Uploaded_At', 'Uploaded on'),
        ],
    ];
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $vmText('Media_Section_Vendors', 'Vendor media') }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-vendor-media" id="admin-vendor-media">

        @include('dashboard.media.partials.sections', ['current' => 'vendors', 'sectionText' => $vmText])

        {{-- Content header: the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $vmText('Media_Vendors_Intro', 'Browse the images each vendor uploaded. This view is read-only.') }}</p>
            <span class="admin-vendor-media-readonly">
                <i class="ph-duotone ph-eye" aria-hidden="true"></i>
                <span>{{ $vmText('Media_Vendors_Read_Only', 'Read only') }}</span>
            </span>
        </div>

        <p class="admin-vendor-media-notice" id="vendor-media-notice" role="status" hidden></p>

        {{-- Vendor folders. --}}
        <section class="card admin-media-panel" id="vendor-media-folders" aria-labelledby="vendor-media-folders-title">
            <div class="admin-vendor-media-panel-head">
                <h2 class="admin-vendor-media-title" id="vendor-media-folders-title">{{ $vmText('Media_Vendors_Folders_Title', 'Vendor folders') }}</h2>
            </div>

            <form class="admin-media-filters admin-vendor-media-filters" id="vendor-media-folder-filters" role="search" aria-label="{{ $vmText('Media_Vendors_Filters_Label', 'Find a vendor') }}">
                <label class="form-label visually-hidden" for="vendor-media-folder-search">{{ $vmText('Media_Vendors_Search_Label', 'Search vendors') }}</label>
                <div class="admin-media-search">
                    <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control" id="vendor-media-folder-search" maxlength="100" autocomplete="off" placeholder="{{ $vmText('Media_Vendors_Search_Placeholder', 'Search by store or vendor name') }}">
                </div>
                <div class="admin-vendor-media-filter">
                    <label class="form-label visually-hidden" for="vendor-media-status">{{ $vmText('Vendors_Account_Status', 'Account status') }}</label>
                    <select class="form-select" id="vendor-media-status">
                        <option value="">{{ $vmText('Media_Vendors_Status_All', 'All account statuses') }}</option>
                        @foreach ($vmStatuses['account'] as $value => $status)
                            <option value="{{ $value }}">{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check admin-vendor-media-check">
                    <input class="form-check-input" type="checkbox" id="vendor-media-has-media">
                    <label class="form-check-label" for="vendor-media-has-media">{{ $vmText('Media_Vendors_Has_Media', 'Vendors with images only') }}</label>
                </div>
                <button type="button" class="btn btn-light-secondary" id="vendor-media-folder-clear">{{ $vmText('Media_Search_Clear', 'Clear search') }}</button>
                <p class="admin-media-count" id="vendor-media-folder-count" aria-live="polite"></p>
            </form>

            <div class="card-body">
                <ul class="admin-vendor-media-folders" id="vendor-media-folder-grid" aria-busy="true" aria-labelledby="vendor-media-folders-title"></ul>

                <div class="admin-media-state" id="vendor-media-folder-loading" role="status">
                    <span class="admin-media-spinner" aria-hidden="true"></span>
                    <span>{{ $vmText('Media_Vendors_Loading', 'Loading vendors…') }}</span>
                </div>
                <div class="admin-media-state" id="vendor-media-folder-empty" hidden>
                    <i class="ph-duotone ph-folder-simple-dashed" aria-hidden="true"></i>
                    <span id="vendor-media-folder-empty-text"></span>
                </div>
                <div class="admin-media-state admin-media-state-error" id="vendor-media-folder-error" role="alert" hidden>
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <span id="vendor-media-folder-error-text"></span>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="vendor-media-folder-retry">
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ $vmText('Media_Retry', 'Try again') }}</span>
                    </button>
                </div>

                <div class="admin-media-more">
                    <button type="button" class="btn btn-light-secondary" id="vendor-media-folder-more" hidden>{{ $vmText('Media_Load_More', 'Load more') }}</button>
                </div>
            </div>
        </section>

        {{-- One vendor's images (?vendor=ID). --}}
        <section class="card admin-media-panel" id="vendor-media-images" aria-labelledby="vendor-media-vendor-title" hidden>
            <div class="admin-vendor-media-panel-head admin-vendor-media-folder-head">
                <button type="button" class="btn btn-sm btn-light-secondary admin-vendor-media-back" id="vendor-media-back">
                    <i class="ph-duotone ph-arrow-left" aria-hidden="true"></i>
                    <span>{{ $vmText('Media_Vendors_Back', 'Back to vendors') }}</span>
                </button>
                <div class="admin-vendor-media-vendor">
                    <h2 class="admin-vendor-media-title" id="vendor-media-vendor-title" tabindex="-1">
                        <i class="ph-duotone ph-folder-open" aria-hidden="true"></i>
                        <bdi id="vendor-media-vendor-name"></bdi>
                    </h2>
                    <bdi class="admin-vendor-media-vendor-secondary" id="vendor-media-vendor-secondary" hidden></bdi>
                    <span class="admin-vendor-media-badges" id="vendor-media-vendor-badges"></span>
                </div>
            </div>

            <form class="admin-media-filters" id="vendor-media-image-filters" role="search" aria-label="{{ $vmText('Media_Vendors_Image_Search_Label', 'Search images in this folder') }}">
                <label class="form-label visually-hidden" for="vendor-media-image-search">{{ $vmText('Media_Vendors_Image_Search_Label', 'Search images in this folder') }}</label>
                <div class="admin-media-search">
                    <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control" id="vendor-media-image-search" maxlength="100" autocomplete="off" placeholder="{{ $vmText('Media_Search_Placeholder', 'Search by name, title or caption') }}">
                </div>
                <button type="button" class="btn btn-light-secondary" id="vendor-media-image-clear">{{ $vmText('Media_Search_Clear', 'Clear search') }}</button>
                <p class="admin-media-count" id="vendor-media-image-count" aria-live="polite"></p>
            </form>

            <div class="card-body">
                <ul class="admin-media-grid" id="vendor-media-image-grid" aria-busy="false"></ul>

                <div class="admin-media-state" id="vendor-media-image-loading" role="status" hidden>
                    <span class="admin-media-spinner" aria-hidden="true"></span>
                    <span>{{ $vmText('Media_Loading', 'Loading images…') }}</span>
                </div>
                <div class="admin-media-state" id="vendor-media-image-empty" hidden>
                    <i class="ph-duotone ph-images" aria-hidden="true"></i>
                    <span id="vendor-media-image-empty-text"></span>
                </div>
                <div class="admin-media-state admin-media-state-error" id="vendor-media-image-error" role="alert" hidden>
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <span id="vendor-media-image-error-text"></span>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="vendor-media-image-retry">
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ $vmText('Media_Retry', 'Try again') }}</span>
                    </button>
                </div>

                <div class="admin-media-more">
                    <button type="button" class="btn btn-light-secondary" id="vendor-media-image-more" hidden>{{ $vmText('Media_Load_More', 'Load more') }}</button>
                </div>
            </div>
        </section>

        {{-- Read-only details: a native modal dialog (focus kept inside, Escape closes, focus returns to the opener). --}}
        <dialog class="admin-media-dialog admin-vendor-media-dialog" id="vendor-media-details" aria-labelledby="vendor-media-details-title">
            <div class="admin-media-dialog-header">
                <h2 class="admin-media-dialog-title" id="vendor-media-details-title">{{ $vmText('Media_Details_Title', 'Image details') }}</h2>
                <button type="button" class="admin-media-icon-button" id="vendor-media-details-close" aria-label="{{ $vmText('Media_Close', 'Close') }}">
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <div class="admin-media-dialog-body">
                <div class="admin-media-dialog-preview">
                    <div class="admin-media-dialog-image"><img id="vendor-media-details-image" src="" alt=""></div>
                </div>
                <dl class="admin-media-facts admin-vendor-media-facts" id="vendor-media-details-facts"></dl>
            </div>

            <div class="admin-media-dialog-footer">
                <a class="btn btn-light-secondary" id="vendor-media-open-original" href="#" target="_blank" rel="noopener noreferrer">
                    <i class="ph-duotone ph-arrow-square-out" aria-hidden="true"></i>
                    <span>{{ $vmText('Media_Vendors_Open_Original', 'Open the original image') }}</span>
                    <span class="visually-hidden">{{ $vmText('Media_Vendors_New_Tab', '(opens in a new tab)') }}</span>
                </a>
                <button type="button" class="btn btn-primary" id="vendor-media-details-done">{{ $vmText('Media_Close', 'Close') }}</button>
            </div>
        </dialog>

        <script type="application/json" id="vendor-media-config">@json($vmConfig)</script>
    </div>

    @push('scripts')
        @vite('resources/js/admin-vendor-media.js')
    @endpush
</x-dashboard-layout>
