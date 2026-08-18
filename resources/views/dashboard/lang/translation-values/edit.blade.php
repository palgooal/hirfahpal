<x-dashboard-layout>
    <div class="page-header">
        <div class="page-block">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dashboard.translation-values.index') }}">{{ t('dashboard.Translation_Values', 'Translations') }}</a></li>
                <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Edit_translation', 'Edit Translation') }}</li>
            </ul>
            <div class="page-header-title">
                <h2 class="mb-0">{{ t('dashboard.Edit_translation', 'Edit Translation') }} — <code>{{ $key }}</code></h2>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-x-6">
        <div class="col-span-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ t('dashboard.Edit_translation', 'Edit Translation') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('dashboard.translation-values.update', ['key' => $key]) }}" method="POST" class="grid grid-cols-12 gap-x-6">
                        @csrf
                        <div class="col-span-12">
                            <div class="mb-3">
                                <x-form.input
                                    label="{{ t('dashboard.Key', 'Key') }}"
                                    name="key" value="{{ $key }}" readonly />
                            </div>
                        </div>
                        @foreach($languages as $lang)
                            <div class="col-span-12 md:col-span-6">
                                <div class="mb-3">
                                    <x-form.input
                                        label="{{ $lang->native }} ({{ $lang->code }})"
                                        name="values[{{ $lang->code }}]"
                                        type="text"
                                        value="{{ $translations[$lang->code]->value ?? '' }}" />
                                </div>
                            </div>
                        @endforeach
                        <div class="col-span-12 text-right">
                            <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
