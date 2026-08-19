<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.languages.index') }}">{{ t('dashboard.Languages', 'Languages') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_languages', 'Add Language') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Locale_Manager', 'Locale Manager') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Add_languages', 'Add Language') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Add_language_hint', 'Create a language record and enable it for dashboard and frontend translations.') }}</p>
                </div>
                <a href="{{ route('dashboard.languages.index') }}" class="btn btn-light-secondary">
                    <i class="ti ti-arrow-left me-1"></i>
                    {{ t('dashboard.Back_to_languages', 'Back to Languages') }}
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">{{ t('dashboard.Language_Details', 'Language Details') }}</h5>
                <p class="text-muted mb-0">{{ t('dashboard.Language_details_hint', 'Use a short locale code such as en, ar, or fr.') }}</p>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.languages.store') }}" method="POST" class="row g-4">
                    @csrf

                    <div class="col-12 col-md-6">
                        <x-form.input
                            label="{{ t('dashboard.Language_Name_English', 'Language Name (English)') }}"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            placeholder="{{ t('dashboard.Language_Name_Placeholder', 'Arabic') }}"
                            re />
                    </div>

                    <div class="col-12 col-md-6">
                        <x-form.input
                            label="{{ t('dashboard.Native_Name', 'Native Name') }}"
                            name="native"
                            type="text"
                            value="{{ old('native') }}"
                            placeholder="{{ t('dashboard.Native_Name_Placeholder', 'العربية') }}"
                            re />
                    </div>

                    <div class="col-12 col-md-6">
                        <x-form.input
                            label="{{ t('dashboard.Language_Code', 'Language Code') }}"
                            name="code"
                            type="text"
                            value="{{ old('code') }}"
                            placeholder="ar"
                            maxlength="10"
                            re />
                        <small class="text-muted">{{ t('dashboard.Language_code_help', 'This code is used in translation keys and locale switching.') }}</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <x-form.input
                            label="{{ t('dashboard.Flag_Image_URL', 'Flag Image URL') }}"
                            name="flag"
                            type="text"
                            value="{{ old('flag') }}"
                            placeholder="flags/ar.png" />
                        <small class="text-muted">{{ t('dashboard.Flag_help', 'Optional path inside public assets or a full image URL.') }}</small>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <h6 class="mb-3">{{ t('dashboard.Language_Settings', 'Language Settings') }}</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check form-switch switch-lg mb-0">
                                            <input
                                                type="checkbox"
                                                id="is_rtl"
                                                name="is_rtl"
                                                value="1"
                                                class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                {{ old('is_rtl') ? 'checked' : '' }}>
                                        </div>
                                        <div>
                                            <label for="is_rtl" class="form-check-label fw-semibold">{{ t('dashboard.RTL_Language', 'RTL Language') }}</label>
                                            <p class="text-muted mb-0">{{ t('dashboard.RTL_help', 'Enable for Arabic, Hebrew, Persian, and other right-to-left languages.') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check form-switch switch-lg mb-0">
                                            <input
                                                type="checkbox"
                                                id="is_active"
                                                name="is_active"
                                                value="1"
                                                class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                {{ old('is_active', true) ? 'checked' : '' }}>
                                        </div>
                                        <div>
                                            <label for="is_active" class="form-check-label fw-semibold">{{ t('dashboard.Active', 'Active') }}</label>
                                            <p class="text-muted mb-0">{{ t('dashboard.Active_language_help', 'Only active languages appear in the language switcher.') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                            <a href="{{ route('dashboard.languages.index') }}" class="btn btn-light-secondary">
                                {{ t('dashboard.Cancel', 'Cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i>
                                {{ t('dashboard.Save_Language', 'Save Language') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Quick_Guide', 'Quick Guide') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 mb-4">
                    <div class="avtar avtar-s bg-light-primary">
                        <i class="ti ti-code f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">{{ t('dashboard.Locale_Code', 'Locale Code') }}</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.Locale_code_hint', 'Keep it lowercase and short, for example ar or en.') }}</p>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-4">
                    <div class="avtar avtar-s bg-light-warning">
                        <i class="ti ti-text-direction-rtl f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">{{ t('dashboard.Direction', 'Direction') }}</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.Direction_hint', 'RTL changes page direction through current_dir().') }}</p>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="avtar avtar-s bg-light-success">
                        <i class="ti ti-circle-check f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">{{ t('dashboard.Visibility', 'Visibility') }}</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.Visibility_hint', 'Inactive languages stay saved but will not appear to users.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
