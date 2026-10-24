{{--
    AD-UI-VENDORS-02: the create vendor form in the admin shell (AD-UI-01), on the pieces of the category and product
    forms (admin-cat-form-*: sections, fields, rows, marks, error summary, actions; admin-product-form: back link).
    The page title is its own ("Add vendor") and lives in the header only. Fields, names, order of options and defaults
    are unchanged (account status active, approval status approved); VendorManagementController::store keeps every rule
    and the server validation is the reference (DEC-AD-UI-12). Texts through t() without auto-creating keys
    (AdminVendorTranslationSeeder).
--}}
@php
    $vendText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $formTitle = $vendText('Vendors_Create_Title', 'Add vendor');

    // Field => label, for the fields and for the error summary; field => id of its control.
    $fieldLabels = [
        'name' => $vendText('Vendors_Form_Owner_Name', 'Owner name'),
        'email' => $vendText('Vendors_Show_Email', 'Email'),
        'phone' => $vendText('Vendors_Show_Phone', 'Phone'),
        'password' => $vendText('Vendors_Form_Password', 'Password'),
        'password_confirmation' => $vendText('Vendors_Form_Password_Confirmation', 'Confirm password'),
        'status' => $vendText('Vendors_Account_Status', 'Account status'),
        'store_name' => $vendText('Vendors_Show_Store_Name', 'Store name'),
        'slug' => $vendText('Vendors_Form_Slug', 'Slug'),
        'short_description' => $vendText('Vendors_Show_Short_Description', 'Short description'),
        'description' => $vendText('Vendors_Show_Description', 'Description'),
        'governorate_id' => $vendText('Vendors_Show_Governorate', 'Governorate'),
        'city_id' => $vendText('Vendors_Show_City', 'City'),
        'address_line' => $vendText('Vendors_Show_Address', 'Address'),
        'commission_rate' => $vendText('Vendors_Commission_Title', 'Commission rate'),
        'approval_status' => $vendText('Vendors_Approval_Status', 'Approval status'),
        'rejection_reason' => $vendText('Vendors_Rejection_Reason', 'Rejection reason'),
    ];
    $fieldIds = array_combine(array_keys($fieldLabels), array_map(fn ($field) => 'vendor-'.str_replace('_', '-', $field), array_keys($fieldLabels)));

    // aria-describedby: the hint, then the error when there is one.
    $describedBy = fn (string $field, ?string $hint = null) => trim(($hint ? $hint.' ' : '').($errors->has($field) ? $fieldIds[$field].'-error' : '')) ?: null;

    // The values the controller accepts, in the same order as before, with the labels of the vendor details page.
    $accountKeys = [
        'active' => ['Vendors_Account_Active', 'Active'],
        'pending' => ['Vendors_Account_Pending', 'Pending activation'],
        'blocked' => ['Vendors_Account_Blocked', 'Blocked'],
    ];
    $approvalKeys = [
        'approved' => ['Vendors_Approval_Approved', 'Approved'],
        'pending' => ['Vendors_Approval_Pending', 'Pending review'],
        'rejected' => ['Vendors_Approval_Rejected', 'Rejected'],
    ];

    // Back and Cancel lead to the list only for admins who may open it; otherwise to the dashboard home.
    $vendAdmin = auth('admin')->user();
    $canViewList = (bool) ($vendAdmin?->isSuperAdmin() || $vendAdmin?->hasAbility('vendors.view'));
    $cancelUrl = $canViewList ? route('dashboard.vendors.index') : route('dashboard.home');

    $requiredMark = $vendText('Vendors_Form_Required', 'Required');
    $optionalMark = $vendText('Vendors_Form_Optional', 'Optional');
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $formTitle }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-cat-form admin-product-form admin-vendor-form" id="admin-vendor-form">
        <div class="admin-media-head admin-product-form-head">
            @if ($canViewList)
                <a href="{{ route('dashboard.vendors.index') }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $vendText('Vendors_Show_Back', 'Back to vendors') }}</span>
                </a>
            @endif
            <p class="admin-media-intro">{{ $vendText('Vendors_Create_Intro', 'Create a vendor account and its store profile. The account status and the approval decision are set separately.') }}</p>
        </div>

        @if ($errors->any())
            {{-- Error summary: translated field names, each linked to its field (DEC-AD-UI-12). --}}
            <div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="vendor-form-errors">
                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                <div class="admin-media-alert-content">
                    <p>{{ $vendText('Vendors_Form_Errors', 'Please correct the following:') }}</p>
                    <ul>
                        @foreach ($errors->getMessages() as $field => $messages)
                            <li><a href="#{{ $fieldIds[$field] ?? 'vendor-name' }}">{{ $fieldLabels[$field] ?? $field }}</a>: {{ $messages[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.vendors.store') }}" class="card admin-media-panel admin-cat-form-card" novalidate aria-label="{{ $formTitle }}">
            @csrf

            {{-- Account --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $vendText('Vendors_Form_Section_Account', 'Account') }}</legend>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="vendor-name">{{ $fieldLabels['name'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('name')]) id="vendor-name" name="name" value="{{ old('name') }}" maxlength="255" required aria-required="true" dir="auto" autocomplete="off"
                        @error('name') aria-invalid="true" @enderror @if ($d = $describedBy('name')) aria-describedby="{{ $d }}" @endif>
                    @error('name') <p class="admin-cat-form-error" id="vendor-name-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-email">{{ $fieldLabels['email'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="email" @class(['form-control', 'is-invalid' => $errors->has('email')]) id="vendor-email" name="email" value="{{ old('email') }}" maxlength="255" dir="ltr" autocomplete="off" spellcheck="false"
                            @error('email') aria-invalid="true" @enderror @if ($d = $describedBy('email')) aria-describedby="{{ $d }}" @endif>
                        @error('email') <p class="admin-cat-form-error" id="vendor-email-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-phone">{{ $fieldLabels['phone'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="text" @class(['form-control', 'is-invalid' => $errors->has('phone')]) id="vendor-phone" name="phone" value="{{ old('phone') }}" maxlength="30" required aria-required="true" dir="ltr" inputmode="tel" autocomplete="off"
                            @error('phone') aria-invalid="true" @enderror @if ($d = $describedBy('phone')) aria-describedby="{{ $d }}" @endif>
                        @error('phone') <p class="admin-cat-form-error" id="vendor-phone-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-password">{{ $fieldLabels['password'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) id="vendor-password" name="password" required aria-required="true" dir="ltr" autocomplete="new-password"
                            @error('password') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('password', 'vendor-password-hint') }}">
                        <p class="admin-media-hint" id="vendor-password-hint">{{ $vendText('Vendors_Form_Password_Hint', 'At least 8 characters.') }}</p>
                        @error('password') <p class="admin-cat-form-error" id="vendor-password-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-password-confirmation">{{ $fieldLabels['password_confirmation'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="password" class="form-control" id="vendor-password-confirmation" name="password_confirmation" required aria-required="true" dir="ltr" autocomplete="new-password">
                    </div>
                </div>

                <div class="admin-cat-form-field admin-product-form-narrow">
                    <label class="form-label" for="vendor-status">{{ $fieldLabels['status'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                    <select @class(['form-select', 'is-invalid' => $errors->has('status')]) id="vendor-status" name="status" required aria-required="true"
                        @error('status') aria-invalid="true" @enderror @if ($d = $describedBy('status')) aria-describedby="{{ $d }}" @endif>
                        @foreach ($accountKeys as $value => [$key, $default])
                            <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $vendText($key, $default) }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="admin-cat-form-error" id="vendor-status-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            {{-- Store profile --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $vendText('Vendors_Form_Section_Store', 'Store profile') }}</legend>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-store-name">{{ $fieldLabels['store_name'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="text" @class(['form-control', 'is-invalid' => $errors->has('store_name')]) id="vendor-store-name" name="store_name" value="{{ old('store_name') }}" maxlength="255" required aria-required="true" dir="auto" autocomplete="off"
                            @error('store_name') aria-invalid="true" @enderror @if ($d = $describedBy('store_name')) aria-describedby="{{ $d }}" @endif>
                        @error('store_name') <p class="admin-cat-form-error" id="vendor-store-name-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-slug">{{ $fieldLabels['slug'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="text" @class(['form-control', 'is-invalid' => $errors->has('slug')]) id="vendor-slug" name="slug" value="{{ old('slug') }}" maxlength="255" dir="ltr" autocomplete="off" spellcheck="false"
                            @error('slug') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('slug', 'vendor-slug-hint') }}">
                        <p class="admin-media-hint" id="vendor-slug-hint">{{ $vendText('Vendors_Form_Slug_Hint', 'Used in the store link. Leave it empty to create it from the store name.') }}</p>
                        @error('slug') <p class="admin-cat-form-error" id="vendor-slug-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="vendor-short-description">{{ $fieldLabels['short_description'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('short_description')]) id="vendor-short-description" name="short_description" value="{{ old('short_description') }}" maxlength="255" dir="auto"
                        @error('short_description') aria-invalid="true" @enderror @if ($d = $describedBy('short_description')) aria-describedby="{{ $d }}" @endif>
                    @error('short_description') <p class="admin-cat-form-error" id="vendor-short-description-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="vendor-description">{{ $fieldLabels['description'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <textarea @class(['form-control', 'is-invalid' => $errors->has('description')]) id="vendor-description" name="description" rows="4" dir="auto"
                        @error('description') aria-invalid="true" @enderror @if ($d = $describedBy('description')) aria-describedby="{{ $d }}" @endif>{{ old('description') }}</textarea>
                    @error('description') <p class="admin-cat-form-error" id="vendor-description-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field admin-product-form-narrow">
                    <label class="form-label" for="vendor-commission-rate">{{ $fieldLabels['commission_rate'] }} (%) <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <input type="number" @class(['form-control', 'is-invalid' => $errors->has('commission_rate')]) id="vendor-commission-rate" name="commission_rate" value="{{ old('commission_rate') }}" min="0" max="100" step="0.01" dir="ltr" inputmode="decimal"
                        @error('commission_rate') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('commission_rate', 'vendor-commission-rate-hint') }}">
                    <p class="admin-media-hint" id="vendor-commission-rate-hint">{{ $vendText('Vendors_Form_Commission_Hint', 'Between 0 and 100, with up to two decimal places.') }}</p>
                    @error('commission_rate') <p class="admin-cat-form-error" id="vendor-commission-rate-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            {{-- Location --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $vendText('Vendors_Show_Location', 'Location') }}</legend>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-governorate-id">{{ $fieldLabels['governorate_id'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('governorate_id')]) id="vendor-governorate-id" name="governorate_id"
                            @error('governorate_id') aria-invalid="true" @enderror @if ($d = $describedBy('governorate_id')) aria-describedby="{{ $d }}" @endif>
                            <option value="">{{ $vendText('Vendors_Form_Select_Governorate', 'Select governorate') }}</option>
                            @foreach ($governorates as $governorate)
                                <option value="{{ $governorate->id }}" @selected(old('governorate_id') == $governorate->id)>{{ $governorate->name }}</option>
                            @endforeach
                        </select>
                        @error('governorate_id') <p class="admin-cat-form-error" id="vendor-governorate-id-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="vendor-city-id">{{ $fieldLabels['city_id'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('city_id')]) id="vendor-city-id" name="city_id"
                            @error('city_id') aria-invalid="true" @enderror @if ($d = $describedBy('city_id')) aria-describedby="{{ $d }}" @endif>
                            <option value="">{{ $vendText('Vendors_Form_Select_City', 'Select city') }}</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </select>
                        @error('city_id') <p class="admin-cat-form-error" id="vendor-city-id-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="vendor-address-line">{{ $fieldLabels['address_line'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('address_line')]) id="vendor-address-line" name="address_line" value="{{ old('address_line') }}" maxlength="255" dir="auto"
                        @error('address_line') aria-invalid="true" @enderror @if ($d = $describedBy('address_line')) aria-describedby="{{ $d }}" @endif>
                    @error('address_line') <p class="admin-cat-form-error" id="vendor-address-line-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            {{-- Approval: the default stays "approved" (ADM-BE-002 is out of scope). --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $vendText('Vendors_Form_Section_Approval', 'Approval') }}</legend>

                <div class="admin-cat-form-field admin-product-form-narrow">
                    <label class="form-label" for="vendor-approval-status">{{ $fieldLabels['approval_status'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                    <select @class(['form-select', 'is-invalid' => $errors->has('approval_status')]) id="vendor-approval-status" name="approval_status" required aria-required="true"
                        @error('approval_status') aria-invalid="true" @enderror @if ($d = $describedBy('approval_status')) aria-describedby="{{ $d }}" @endif>
                        @foreach ($approvalKeys as $value => [$key, $default])
                            <option value="{{ $value }}" @selected(old('approval_status', 'approved') === $value)>{{ $vendText($key, $default) }}</option>
                        @endforeach
                    </select>
                    @error('approval_status') <p class="admin-cat-form-error" id="vendor-approval-status-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="vendor-rejection-reason">{{ $fieldLabels['rejection_reason'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <textarea @class(['form-control', 'is-invalid' => $errors->has('rejection_reason')]) id="vendor-rejection-reason" name="rejection_reason" rows="3" maxlength="1000" dir="auto"
                        @error('rejection_reason') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('rejection_reason', 'vendor-rejection-reason-hint') }}">{{ old('rejection_reason') }}</textarea>
                    <p class="admin-media-hint" id="vendor-rejection-reason-hint">{{ $vendText('Vendors_Form_Rejection_Hint', 'Required when the approval status is Rejected.') }}</p>
                    @error('rejection_reason') <p class="admin-cat-form-error" id="vendor-rejection-reason-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <div class="admin-cat-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                    <span>{{ $vendText('Vendors_Form_Submit', 'Create vendor') }}</span>
                </button>
                <a href="{{ $cancelUrl }}" class="btn btn-light-secondary">{{ $vendText('Vendors_Form_Cancel', 'Cancel') }}</a>
            </div>
        </form>
    </div>
</x-dashboard-layout>
