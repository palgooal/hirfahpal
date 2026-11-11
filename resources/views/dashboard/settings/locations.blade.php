<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.setting.index') }}">{{ t('dashboard.Settings', 'Settings') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Location_Management', 'Manage cities and governorates') }}</li>
    </x-slot:breadcrumbs>

    {{-- The page's own texts (AdminSettingsTranslationSeeder), read without creating translation rows; the breadcrumb and
         dashboard.City are seeded with the pages that share them. --}}
    @php($locationText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    })
    @php($canEdit = auth('admin')->user()->can('edit', App\Models\Setting::class))
    <div class="col-span-12 admin-media admin-cat-form">
        @if (session('success'))
            <div class="admin-media-alert is-success mb-3" role="status"><i class="ph-duotone ph-check-circle" aria-hidden="true"></i><div class="admin-media-alert-content"><p>{{ session('success') }}</p></div></div>
        @endif
        <div class="row g-4">
            <div class="col-12 col-lg-5">
                <form method="POST" action="{{ route('dashboard.setting.locations.cities.store') }}" class="card admin-media-panel admin-cat-form-card">
                    @csrf
                    <div class="card-body">
                        <h1 class="admin-settings-panel-title">{{ $locationText('Location_Cities', 'Cities') }}</h1>
                        <p class="admin-media-hint">{{ $locationText('Location_Cities_Hint', 'Add a city before adding its governorates.') }}</p>
                        <fieldset @disabled(! $canEdit)>
                            <div class="admin-cat-form-field"><label class="form-label" for="location-city-name">{{ t('dashboard.City', 'City') }}</label><input id="location-city-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required maxlength="255"></div>
                            @error('name')<p class="admin-cat-form-error">{{ $message }}</p>@enderror
                            <button class="btn btn-primary mt-3" type="submit"><i class="ph-duotone ph-plus" aria-hidden="true"></i> {{ $locationText('Location_Add_City', 'Add city') }}</button>
                        </fieldset>
                    </div>
                </form>
            </div>
            <div class="col-12 col-lg-7">
                <form method="POST" action="{{ route('dashboard.setting.locations.governorates.store') }}" class="card admin-media-panel admin-cat-form-card">
                    @csrf
                    <div class="card-body">
                        <h2 class="admin-settings-panel-title">{{ $locationText('Location_Governorates', 'Governorates') }}</h2>
                        <p class="admin-media-hint">{{ $locationText('Location_Governorates_Hint', 'Choose the city this governorate belongs to.') }}</p>
                        <fieldset @disabled(! $canEdit)>
                            <div class="admin-cat-form-row">
                                <div class="admin-cat-form-field"><label class="form-label" for="location-governorate-city">{{ t('dashboard.City', 'City') }}</label><select id="location-governorate-city" class="form-select @error('city_id') is-invalid @enderror" name="city_id" required><option value="">{{ $locationText('Location_Select_City', 'Choose a city') }}</option>@foreach ($cities as $city)<option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}</option>@endforeach</select></div>
                                <div class="admin-cat-form-field"><label class="form-label" for="location-governorate-name">{{ $locationText('Location_Governorate', 'Governorate') }}</label><input id="location-governorate-name" class="form-control @error('name') is-invalid @enderror" name="name" required maxlength="255"></div>
                            </div>
                            @error('city_id')<p class="admin-cat-form-error">{{ $message }}</p>@enderror
                            <button class="btn btn-primary mt-3" type="submit"><i class="ph-duotone ph-plus" aria-hidden="true"></i> {{ $locationText('Location_Add_Governorate', 'Add governorate') }}</button>
                        </fieldset>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <section class="card admin-media-panel"><div class="card-body"><h2 class="admin-settings-panel-title">{{ $locationText('Location_Existing', 'Configured locations') }}</h2><div class="row g-3">@forelse ($cities as $city)<div class="col-12 col-md-6"><strong>{{ $city->name }}</strong><p class="mb-0 text-muted">{{ $city->governorates->pluck('name')->implode(app()->getLocale() === 'ar' ? '، ' : ', ') ?: $locationText('Location_No_Governorates', 'No governorates added yet.') }}</p></div>@empty<p class="mb-0 text-muted">{{ $locationText('Location_No_Cities', 'No cities added yet.') }}</p>@endforelse</div></div></section>
            </div>
        </div>
    </div>
</x-dashboard-layout>
