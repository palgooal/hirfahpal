<x-dashboard-layout>
    <div class="page-header">
        <div class="page-block">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dashboard.languages.index') }}">{{ t('dashboard.Languages', 'Languages') }}</a></li>
                <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_languages', 'Add Language') }}</li>
            </ul>
            <div class="page-header-title">
                <h2 class="mb-0">{{ t('dashboard.Add_languages', 'Add Language') }}</h2>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-x-6">
        <div class="col-span-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ t('dashboard.Add_languages', 'Add Language') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('dashboard.languages.store') }}" method="POST" class="grid grid-cols-12 gap-x-6">
                        @csrf
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3">
                                <x-form.input
                                    label="{{ t('dashboard.Language_Name_(English):', 'Language Name (English)') }}"
                                    name="name" type="text"
                                    placeholder="{{ t('dashboard.Language_Name', 'e.g. Arabic') }}" />
                                @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3">
                                <x-form.input
                                    label="{{ t('dashboard.Native_Name:', 'Native Name') }}"
                                    name="native" type="text"
                                    placeholder="{{ t('dashboard.Native_Name', 'e.g. العربية') }}" />
                                @error('native') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3">
                                <x-form.input
                                    label="{{ t('dashboard.Language_Code', 'Language Code (e.g. en, ar, fr)') }}"
                                    name="code" type="text"
                                    placeholder="ar" />
                                @error('code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3">
                                <x-form.input
                                    label="{{ t('dashboard.Flag_Image_URL:', 'Flag Image URL (optional)') }}"
                                    name="flag" type="text"
                                    placeholder="flags/ar.png" />
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3 form-check form-switch switch-lg">
                                <input type="checkbox" name="is_rtl" value="1"
                                    class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg">
                                <label class="form-check-label">{{ t('dashboard.RTL:Yes_(language written from right to left):', 'RTL (right-to-left language)') }}</label>
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="mb-3 form-check form-switch switch-lg">
                                <input type="checkbox" name="is_active" value="1"
                                    class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg">
                                <label class="form-check-label">{{ t('dashboard.Status:Active', 'Active') }}</label>
                            </div>
                        </div>
                        <div class="col-span-12 text-right">
                            <a href="{{ route('dashboard.languages.index') }}" class="btn btn-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ t('dashboard.Add', 'Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
