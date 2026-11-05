{{--
    My Store (VUI-03B). Server-rendered labels, empty disabled fields and loading
    placeholders only. resources/js/vendor-my-store.js reads the existing profile
    endpoint (vendor.dashboard.profile.show), fills the approved fields, keeps the
    values the replacement-style update needs in memory, and saves through the
    existing update endpoint (vendor.dashboard.profile.update). MYSTORE-UI-01C: the
    logo is chosen from the vendor's media library (shared picker) by media id
    (vendor.dashboard.profile.logo.update / .destroy).
--}}
@php
    $vendor = auth('vendor')->user();
    $storeName = filled($vendor->profile?->store_name) ? $vendor->profile->store_name : $vendor->name;
    $initial = mb_strtoupper(mb_substr(trim($storeName), 0, 1));

    $i18n = [
        'saving' => t('vendor.My_Store_Saving', 'Saving…'),
        'saved' => t('vendor.My_Store_Saved', 'Changes saved successfully.'),
        'notSpecified' => t('vendor.My_Store_Not_Specified', 'Not specified'),
        'fixErrors' => t('vendor.My_Store_Fix_Errors', 'Please review the highlighted fields.'),
        'saveError' => t('vendor.My_Store_Save_Error', 'Your changes could not be saved. Please try again.'),
        'sessionError' => t('vendor.My_Store_Session_Error', 'Your session has ended. Sign in again, then try again.'),
        'accessError' => t('vendor.My_Store_Access_Error', 'You cannot edit the store details right now.'),
        'required' => t('vendor.My_Store_Error_Required', 'This field is required.'),
        'tooLong' => t('vendor.My_Store_Error_Too_Long', 'This text is longer than allowed.'),
        'invalid' => t('vendor.My_Store_Error_Invalid', 'Please check this value.'),
        // MYSTORE-UI-01C: the logo section's messages ("Uploading…" and "Saving…" are the product gallery's).
        'logoUploading' => t('vendor.Product_Gallery_Uploading', 'Uploading…'),
        'logoSaving' => t('vendor.Product_Gallery_Saving', 'Saving…'),
        'logoUploaded' => t('vendor.My_Store_Logo_Uploaded', 'Logo updated.'),
        'logoUploadFailed' => t('vendor.My_Store_Logo_Upload_Failed', 'This image could not be set as the logo. Choose another image from your media library.'),
        'logoConfirmDelete' => t('vendor.My_Store_Logo_Confirm_Delete', 'Remove the logo from your store? The image stays in your media library.'),
        'logoDeleted' => t('vendor.My_Store_Logo_Deleted', 'Logo removed. The image stays in your media library.'),
        'logoDeleteFailed' => t('vendor.My_Store_Logo_Delete_Failed', 'The logo could not be removed. Try again.'),
    ];

    // MYSTORE-UI-01C: the shared media picker (partials/media-picker.blade.php) with the product gallery's texts, read
    // without creating translation rows, as the product editor reads them; the current logo has its own marker.
    $galleryText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('vendor.Product_Gallery_'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $pickerMessages = [
        'title' => $galleryText('Panel_Title', 'Choose an image from media'),
        'libraryCount' => $galleryText('Library_Count', 'Images: :count'),
        'pageStatus' => $galleryText('Page_Status', 'Page :page of :last'),
        'choose' => $galleryText('Choose', 'Choose :name'),
        'mediaAlt' => $galleryText('Media_Alt', 'Media library image'),
        'attached' => $galleryText('Attached', 'Already in this product'),
        'current' => t('vendor.My_Store_Logo_Current', 'Current logo'),
        'libraryError' => $galleryText('Library_Error', 'Your media library could not be loaded.'),
        'uploadInvalid' => $galleryText('Upload_Invalid', 'The file must be a JPEG, PNG, GIF or WebP image of up to 10 MB.'),
        'uploadMax' => $galleryText('Upload_Max', 'You can upload up to 10 images at a time.'),
        'session' => $galleryText('Session', 'Your session has ended. Sign in again to continue.'),
        'forbidden' => $galleryText('Forbidden', 'You do not have permission to do this.'),
        'invalid' => $galleryText('Invalid', 'The request could not be processed. Reload the page and try again.'),
        'network' => $galleryText('Network', 'The connection failed. Check the network and try again.'),
        'failed' => $galleryText('Failed', 'Something went wrong. Try again.'),
    ];
@endphp

<x-vendor-dashboard-layout :title="t('vendor.Nav_My_Store', 'My Store')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('vendor.Nav_My_Store', 'My Store') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <section class="card vendor-welcome-card vendor-store-identity" aria-labelledby="vendor-store-identity-title">
            <div class="card-body">
                <span class="vendor-monogram vendor-monogram-lg" aria-hidden="true">{{ $initial }}</span>
                <div class="vendor-store-identity-text">
                    <p id="vendor-store-identity-title" class="vendor-welcome-eyebrow">{{ t('vendor.My_Store_Identity', 'Store identity') }}</p>
                    <p class="vendor-welcome-name"><bdi>{{ $storeName }}</bdi></p>
                </div>
            </div>
        </section>
    </div>

    <div
        class="col-span-12 vendor-store"
        data-vendor-my-store
        data-state="loading"
        data-profile-url="{{ route('vendor.dashboard.profile.show') }}"
        data-update-url="{{ route('vendor.dashboard.profile.update') }}"
        data-logo-url="{{ route('vendor.dashboard.profile.logo.update') }}"
        data-media-library-url="{{ route('vendor.dashboard.products.media.library') }}"
        data-media-store-url="{{ route('vendor.dashboard.products.media.store') }}"
    >
        <div class="card vendor-home-error" data-store-load-error hidden>
            <div class="card-body">
                <span class="vendor-kpi-icon" aria-hidden="true"><i class="ph-duotone ph-cloud-slash"></i></span>
                <p class="vendor-home-error-text" role="alert">{{ t('vendor.My_Store_Load_Error', 'Store details could not be loaded.') }}</p>
                <button type="button" class="vendor-retry-button" data-store-retry>
                    <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                    <span>{{ t('vendor.Dashboard_Retry', 'Retry') }}</span>
                </button>
            </div>
        </div>

        <form class="vendor-store-form" data-store-form novalidate aria-busy="true">
            @csrf

            <section class="card vendor-form-card" aria-labelledby="vendor-store-info-title">
                <div class="card-body">
                    <h2 id="vendor-store-info-title" class="vendor-section-title">{{ t('vendor.My_Store_Info', 'Store information') }}</h2>

                    <div class="vendor-field">
                        <label class="vendor-label" for="vendor-field-store-name">{{ t('vendor.Store_Name', 'Store name') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                        <input class="vendor-input" id="vendor-field-store-name" name="store_name" type="text" maxlength="255" required dir="auto" autocomplete="organization" disabled aria-describedby="vendor-error-store_name">
                        <p class="vendor-field-error" id="vendor-error-store_name" data-error-for="store_name" hidden></p>
                    </div>

                    <div class="vendor-field">
                        <div class="vendor-label-row">
                            <label class="vendor-label" for="vendor-field-short-description">{{ t('vendor.My_Store_Short_Description', 'Short description') }}</label>
                            <span class="vendor-counter" data-counter-for="short_description" aria-hidden="true"><bdi>0 / 255</bdi></span>
                        </div>
                        {{-- maxlength 255 guards the column length (VEN-BE-016); the backend stays authoritative. --}}
                        <input class="vendor-input" id="vendor-field-short-description" name="short_description" type="text" maxlength="255" dir="auto" disabled aria-describedby="vendor-error-short_description">
                        <p class="vendor-field-error" id="vendor-error-short_description" data-error-for="short_description" hidden></p>
                    </div>

                    <div class="vendor-field">
                        <label class="vendor-label" for="vendor-field-description">{{ t('vendor.My_Store_Description', 'Description') }}</label>
                        <textarea class="vendor-input vendor-textarea" id="vendor-field-description" name="description" rows="5" dir="auto" disabled aria-describedby="vendor-error-description"></textarea>
                        <p class="vendor-field-error" id="vendor-error-description" data-error-for="description" hidden></p>
                    </div>
                </div>
            </section>

            <section class="card vendor-form-card" aria-labelledby="vendor-store-logo-title">
                <div class="card-body">
                    <h2 id="vendor-store-logo-title" class="vendor-section-title">{{ t('vendor.My_Store_Logo', 'Store logo') }}</h2>
                    <p class="vendor-field-hint" id="vendor-store-logo-hint">{{ t('vendor.My_Store_Logo_Hint', 'Choose an image from your media library to appear next to your store name in the vendor dashboard. Removing the logo does not delete the image from the library.') }}</p>
                    {{-- MYSTORE-UI-01C: the logo is chosen (or uploaded) in the shared media library picker below the form. --}}
                    <div class="vendor-store-logo-row">
                        <img class="vendor-store-logo-preview" data-store-logo hidden alt="" width="72" height="72">
                        <button class="btn vendor-store-logo-button" type="button" data-store-logo-pick aria-describedby="vendor-store-logo-hint" aria-haspopup="dialog" disabled>
                            <i class="ph-duotone ph-images" aria-hidden="true"></i>
                            <span>{{ t('vendor.My_Store_Logo_Choose', 'Choose from media library') }}</span>
                        </button>
                        <button class="btn vendor-store-logo-button vendor-store-logo-delete" type="button" data-store-logo-delete hidden>
                            <i class="ph-duotone ph-x-circle" aria-hidden="true"></i>
                            <span>{{ t('vendor.My_Store_Logo_Remove', 'Remove logo') }}</span>
                        </button>
                    </div>
                    <p class="vendor-form-status" data-store-logo-status role="status" aria-live="polite"></p>
                </div>
            </section>

            <section class="card vendor-form-card" aria-labelledby="vendor-store-location-title">
                <div class="card-body">
                    <h2 id="vendor-store-location-title" class="vendor-section-title">{{ t('vendor.My_Store_Location', 'Location') }}</h2>

                    {{-- Display only (VEN-BE-024). --}}
                    <dl class="vendor-readonly">
                        <div class="vendor-readonly-row">
                            <dt>{{ t('vendor.My_Store_Governorate', 'Governorate') }}</dt>
                            <dd data-readonly="governorate"><span class="vendor-skeleton vendor-skeleton-meta"></span></dd>
                        </div>
                        <div class="vendor-readonly-row">
                            <dt>{{ t('vendor.My_Store_City', 'City') }}</dt>
                            <dd data-readonly="city"><span class="vendor-skeleton vendor-skeleton-meta"></span></dd>
                        </div>
                    </dl>

                    <div class="vendor-field">
                        <label class="vendor-label" for="vendor-field-address-line">{{ t('vendor.My_Store_Address', 'Address') }}</label>
                        <input class="vendor-input" id="vendor-field-address-line" name="address_line" type="text" maxlength="255" dir="auto" autocomplete="street-address" disabled aria-describedby="vendor-error-address_line">
                        <p class="vendor-field-error" id="vendor-error-address_line" data-error-for="address_line" hidden></p>
                    </div>
                    <div class="vendor-field">
                        <label class="vendor-label" for="vendor-field-preparation-days">{{ t('vendor.My_Store_Preparation_Days', 'Preparation days') }}</label>
                        <p class="vendor-field-hint" id="vendor-hint-preparation_days">{{ t('vendor.My_Store_Preparation_Days_Hint', 'Your usual time to prepare an order before shipping, in days (0 to 365).') }}</p>
                        <input class="vendor-input" id="vendor-field-preparation-days" name="preparation_days" type="number" min="0" max="365" inputmode="numeric" disabled aria-describedby="vendor-hint-preparation_days vendor-error-preparation_days">
                        <p class="vendor-field-error" id="vendor-error-preparation_days" data-error-for="preparation_days" hidden></p>
                    </div>
                </div>
            </section>

            <section class="card vendor-form-card" aria-labelledby="vendor-store-account-title">
                <div class="card-body">
                    <h2 id="vendor-store-account-title" class="vendor-section-title">{{ t('vendor.My_Store_Account', 'Account information') }}</h2>

                    <div class="vendor-field">
                        <label class="vendor-label" for="vendor-field-name">{{ t('vendor.My_Store_Full_Name', 'Full name') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                        <input class="vendor-input" id="vendor-field-name" name="name" type="text" maxlength="255" required dir="auto" autocomplete="name" disabled aria-describedby="vendor-error-name">
                        <p class="vendor-field-error" id="vendor-error-name" data-error-for="name" hidden></p>
                    </div>

                    {{-- Display only (VEN-BE-016). --}}
                    <dl class="vendor-readonly">
                        <div class="vendor-readonly-row">
                            <dt>{{ t('vendor.My_Store_Email', 'Email') }}</dt>
                            <dd data-readonly="email"><span class="vendor-skeleton vendor-skeleton-meta"></span></dd>
                        </div>
                        <div class="vendor-readonly-row">
                            <dt>{{ t('vendor.My_Store_Phone', 'Phone') }}</dt>
                            <dd data-readonly="phone"><span class="vendor-skeleton vendor-skeleton-meta"></span></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div class="vendor-form-actions">
                <p class="vendor-form-status" data-store-status role="status" aria-live="polite"></p>
                <button type="submit" class="vendor-save-button" data-store-save disabled>
                    <span data-save-label>{{ t('vendor.My_Store_Save', 'Save changes') }}</span>
                </button>
            </div>
        </form>

        {{-- Outside the form: the picker has its own search form. One image at a time; it becomes the logo. --}}
        @include('vendor-dashboard.partials.media-picker', [
            'uploadHint' => $galleryText('Upload_Invalid', 'The file must be a JPEG, PNG, GIF or WebP image of up to 10 MB.'),
            'multiple' => false,
        ])
        <script type="application/json" data-store-picker-i18n>@json($pickerMessages)</script>

        <noscript>
            <p class="vendor-empty">{{ t('vendor.My_Store_Load_Error', 'Store details could not be loaded.') }}</p>
        </noscript>

        {{-- Interface strings only (no user or business data). --}}
        <script type="application/json" data-store-i18n>@json($i18n)</script>
    </div>

    @push('scripts')
        @vite('resources/js/vendor-my-store.js')
    @endpush
</x-vendor-dashboard-layout>
