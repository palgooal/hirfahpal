<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.translation-values.index') }}">{{ t('dashboard.Translation_Values', 'Translations') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_New_Translation', 'Add New Translation') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Dictionary', 'Dictionary') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Add_New_Translation', 'Add New Translation') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Add_translation_hint', 'Create one translation key and fill its values for every active language.') }}</p>
                </div>
                <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-light-secondary">
                    <i class="ti ti-arrow-left me-1"></i>
                    {{ t('dashboard.Back_to_translations', 'Back to Translations') }}
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">{{ t('dashboard.Translation_Details', 'Translation Details') }}</h5>
                <p class="text-muted mb-0">{{ t('dashboard.Translation_details_hint', 'Use a clear dot-notation key, then add the translated text below.') }}</p>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.translation-values.store') }}" method="POST" class="row g-4">
                    @csrf

                    <div class="col-12">
                        <x-form.input
                            label="{{ t('dashboard.Key', 'Key') }}"
                            name="key"
                            value="{{ old('key') }}"
                            placeholder="dashboard.nav.home"
                            re />
                        <small class="text-muted">{{ t('dashboard.Key_help', 'Recommended examples: dashboard.nav.home, frontend.hero.title, general.save.') }}</small>
                    </div>

                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-1">
                            <h6 class="mb-0">{{ t('dashboard.Values_by_language', 'Values by Language') }}</h6>
                            <span class="badge bg-light-secondary text-secondary">
                                {{ $languages->count() }} {{ t('dashboard.Languages', 'Languages') }}
                            </span>
                        </div>
                        <p class="text-muted mb-0">{{ t('dashboard.Values_by_language_hint', 'Leave a value empty only when you want the fallback language to be used.') }}</p>
                    </div>

                    @forelse($languages as $lang)
                        <div class="col-12 col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    @if($lang->flag)
                                        <img src="{{ asset($lang->flag) }}" alt="{{ $lang->native }}" class="w-8 h-8 rounded-full object-cover border">
                                    @else
                                        <span class="avtar avtar-s bg-light-primary text-primary">{{ strtoupper(substr($lang->code, 0, 2)) }}</span>
                                    @endif
                                    <div>
                                        <h6 class="mb-0">{{ $lang->native }}</h6>
                                        <small class="text-muted">{{ $lang->name }} - {{ strtoupper($lang->code) }}</small>
                                    </div>
                                </div>

                                <label class="form-label" for="values_{{ $lang->code }}">
                                    {{ t('dashboard.Translation_Value', 'Translation Value') }}
                                </label>
                                <textarea
                                    id="values_{{ $lang->code }}"
                                    name="values[{{ $lang->code }}]"
                                    rows="4"
                                    class="form-control @error('values.' . $lang->code) is-invalid @enderror"
                                    dir="{{ $lang->is_rtl ? 'rtl' : 'ltr' }}"
                                    placeholder="{{ t('dashboard.Enter_translation_value', 'Enter translation value') }}"
                                >{{ old('values.' . $lang->code) }}</textarea>
                                @error('values.' . $lang->code)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-center border rounded py-5">
                                <div class="avtar avtar-xl bg-light-primary mx-auto mb-3">
                                    <i class="ti ti-world f-30"></i>
                                </div>
                                <h5>{{ t('dashboard.No_active_languages_found', 'No active languages found') }}</h5>
                                <p class="text-muted mb-3">{{ t('dashboard.Create_active_language_first', 'Create or activate a language before adding translation values.') }}</p>
                                <a href="{{ route('dashboard.languages.create') }}" class="btn btn-primary">
                                    {{ t('dashboard.Add_Languages', 'Add Language') }}
                                </a>
                            </div>
                        </div>
                    @endforelse

                    <div class="col-12">
                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                            <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-light-secondary">
                                {{ t('dashboard.Cancel', 'Cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary" @if($languages->isEmpty()) disabled @endif>
                                <i class="ti ti-device-floppy me-1"></i>
                                {{ t('dashboard.Save_Translation', 'Save Translation') }}
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
                <h5 class="mb-0">{{ t('dashboard.Key_Guide', 'Key Guide') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 mb-4">
                    <div class="avtar avtar-s bg-light-primary">
                        <i class="ti ti-layout-dashboard f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">dashboard.*</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.Dashboard_key_hint', 'Use for admin dashboard labels, menus, buttons, and messages.') }}</p>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-4">
                    <div class="avtar avtar-s bg-light-success">
                        <i class="ti ti-browser f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">frontend.*</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.Frontend_key_hint', 'Use for public website navigation, sections, and visible copy.') }}</p>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="avtar avtar-s bg-light-info">
                        <i class="ti ti-message f-20"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">general.*</h6>
                        <p class="text-muted mb-0">{{ t('dashboard.General_key_hint', 'Use for shared words such as save, cancel, edit, and delete.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <span class="badge bg-light-warning text-warning mb-3">{{ t('dashboard.Tip', 'Tip') }}</span>
                <h5 class="mb-2">{{ t('dashboard.Auto_create_keys', 'Auto-created Keys') }}</h5>
                <p class="text-muted mb-0">{{ t('dashboard.Auto_create_keys_hint', 'When t() is used with a missing key, the package can create it automatically depending on configuration.') }}</p>
            </div>
        </div>
    </div>
</x-dashboard-layout>
