{{--
    LOC-UI-7B: the cities and governorates page. A header with the add actions, two counts, the cities with their
    governorates, the governorates linked to no city, and an add dialog for each form (settings.edit only).
    Without JavaScript every add action is a link: the server renders its dialog open (?add=city, ?add=governorate,
    &city= preselects a city) and the form posts as before; after a failed save the failed form's dialog is open with
    its errors and input. The script (7B-2) opens the same dialogs as modals instead and runs the search.
    Texts through t() without auto-creating keys (AdminSettingsTranslationSeeder): each key read once in $text.
--}}
@php
    $locationText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $canEdit = auth('admin')->user()->can('edit', App\Models\Setting::class);

    $text = [
        'intro' => $locationText('Location_Intro', 'Manage the cities and governorates available for product locations.'),
        'cities' => $locationText('Location_Cities', 'Cities'),
        'citiesHint' => $locationText('Location_Cities_Hint', 'Add a city before adding its governorates.'),
        'addCity' => $locationText('Location_Add_City', 'Add city'),
        'governorates' => $locationText('Location_Governorates', 'Governorates'),
        'governoratesHint' => $locationText('Location_Governorates_Hint', 'Choose the city this governorate belongs to.'),
        'selectCity' => $locationText('Location_Select_City', 'Choose a city'),
        'governorate' => $locationText('Location_Governorate', 'Governorate'),
        'addGovernorate' => $locationText('Location_Add_Governorate', 'Add governorate'),
        'addGovernorateTo' => $locationText('Location_Add_Governorate_To', 'Add governorate to :name'),
        'existing' => $locationText('Location_Existing', 'Configured locations'),
        'noGovernorates' => $locationText('Location_No_Governorates', 'No governorates added yet.'),
        'noCities' => $locationText('Location_No_Cities', 'No cities added yet.'),
        'emptyHint' => $locationText('Location_Empty_Hint', 'Add the first city to start.'),
        'cityCount' => $locationText('Location_City_Governorates_Count', 'Governorates: :count'),
        'unassignedTitle' => $locationText('Location_Unassigned_Title', 'Unassigned governorates'),
        'unassignedHint' => $locationText('Location_Unassigned_Hint', 'These governorates are not linked to a city, so they cannot be chosen for products.'),
        'unassignedCount' => $locationText('Location_Unassigned_Count', 'Unassigned: :count'),
        'searchLabel' => $locationText('Location_Search_Label', 'Search locations'),
        'searchPlaceholder' => $locationText('Location_Search_Placeholder', 'Search by city or governorate'),
        'searchEmpty' => $locationText('Location_Search_Empty', 'No locations match your search.'),
        'searchResults' => $locationText('Location_Search_Results', 'Results: :count'),
        'cancel' => $locationText('Location_Cancel', 'Cancel'),
        'close' => $locationText('Location_Close', 'Close'),
        'saving' => $locationText('Location_Saving', 'Saving…'),
        'dismiss' => $locationText('Location_Dismiss', 'Dismiss'),
    ];
    $cityLabel = t('dashboard.City', 'City');

    // Counts from the loaded collections: a governorate has a city or none (nullOnDelete), never both.
    $assignedCount = $cities->sum(fn ($city) => $city->governorates->count());
    $unassignedCount = $unassignedGovernorates->count();
    $governorateCount = $assignedCount + $unassignedCount;

    // The failed form: its hidden location_form, only the two known values; without it the errors decide.
    $postedForm = old('location_form');
    $failedForm = $canEdit && $errors->any()
        ? (in_array($postedForm, ['city', 'governorate'], true) ? $postedForm : (($errors->has('city_id') || old('city_id') !== null) ? 'governorate' : 'city'))
        : null;
    // An add link (no JavaScript): the governorate form only once a city exists.
    $requestedForm = $canEdit && ! $failedForm && in_array(request()->query('add'), ['city', 'governorate'], true) ? request()->query('add') : null;
    if ($requestedForm === 'governorate' && $cities->isEmpty()) {
        $requestedForm = null;
    }
    $openForm = $failedForm ?? $requestedForm;

    $scalar = fn ($value): string => is_scalar($value) ? (string) $value : '';
    $cityName = $failedForm === 'city' ? $scalar(old('name')) : '';
    $governorateNames = $failedForm === 'governorate' && is_array(old('governorates')) && old('governorates') !== []
        ? array_map($scalar, old('governorates'))
        : ($failedForm === 'governorate' && old('name') !== null ? [$scalar(old('name'))] : []);
    $governorateNames = array_values(array_filter($governorateNames, fn ($name) => $name !== ''));
    $governorateNames = array_pad($governorateNames, 3, '');
    $selectedCityId = match (true) {
        $failedForm === 'governorate' => $scalar(old('city_id')),
        $requestedForm === 'governorate' => $scalar(request()->query('city')),
        default => '',
    };
    $cityErrors = $failedForm === 'city' ? ['name' => $errors->first('name')] : ['name' => ''];
    $governorateErrors = $failedForm === 'governorate'
        ? ['city_id' => $errors->first('city_id'), 'names' => $errors->get('governorates.*'), 'list' => $errors->first('governorates')]
        : ['city_id' => '', 'names' => [], 'list' => ''];
    // The field that takes focus when the page opens with a dialog: the first error, else the first field.
    $focusField = match ($openForm) {
        'city' => 'location-city-name',
        'governorate' => $governorateErrors['city_id'] !== '' || ($governorateErrors['names'] === [] && $selectedCityId === '') ? 'location-governorate-city' : 'location-governorate-name',
        default => null,
    };

    $locationsUrl = route('dashboard.setting.locations.index');
    $addUrl = fn (array $query, string $dialog): string => route('dashboard.setting.locations.index', $query).'#'.$dialog;
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ t('dashboard.Location_Management', 'Manage cities and governorates') }}</x-slot:pageTitle>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.setting.index') }}">{{ t('dashboard.Settings', 'Settings') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Location_Management', 'Manage cities and governorates') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12 admin-media admin-locations" id="admin-locations">

        {{-- Content header: the page title is the admin header's <h1>. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $text['intro'] }}</p>
            @if ($canEdit)
                <div class="admin-locations-actions">
                    <a class="btn btn-primary" href="{{ $addUrl(['add' => 'city'], 'location-city-dialog') }}" data-location-open="city" aria-controls="location-city-dialog">
                        <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                        <span>{{ $text['addCity'] }}</span>
                    </a>
                    @if ($cities->isEmpty())
                        <button type="button" class="btn btn-light-secondary" aria-disabled="true" aria-describedby="location-needs-city">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $text['addGovernorate'] }}</span>
                        </button>
                        <p class="admin-media-hint admin-locations-needs-city" id="location-needs-city">{{ $text['citiesHint'] }}</p>
                    @else
                        <a class="btn btn-light-secondary" href="{{ $addUrl(['add' => 'governorate'], 'location-governorate-dialog') }}" data-location-open="governorate" aria-controls="location-governorate-dialog">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $text['addGovernorate'] }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if (session('success'))
            <div class="admin-media-alert is-success" role="status" data-location-alert>
                <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                <div class="admin-media-alert-content"><p>{{ session('success') }}</p></div>
                {{-- Revealed by the script (7B-2). --}}
                <button type="button" class="admin-media-icon-button" data-location-alert-close aria-label="{{ $text['dismiss'] }}" hidden>
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($canEdit)
            {{-- Add dialogs. Open from the server after a failed save or an add link (shown in the page, not modal);
                 Cancel and Close are links back to the page. The script (7B-2) opens and closes them as modals. --}}
            <dialog class="admin-media-dialog admin-locations-dialog" id="location-city-dialog" aria-labelledby="location-city-title" aria-describedby="location-city-hint" @if ($openForm === 'city') open @endif>
                <form method="POST" action="{{ route('dashboard.setting.locations.cities.store') }}" class="admin-cat-form admin-locations-form" data-location-form="city">
                    @csrf
                    <input type="hidden" name="location_form" value="city">
                    <div class="admin-media-dialog-header">
                        <h2 class="admin-media-dialog-title" id="location-city-title">{{ $text['addCity'] }}</h2>
                        <a class="admin-media-icon-button" href="{{ $locationsUrl }}" data-location-close aria-label="{{ $text['close'] }}"><i class="ph-duotone ph-x" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-media-dialog-form">
                        <p class="admin-locations-dialog-hint" id="location-city-hint">{{ $text['citiesHint'] }}</p>
                        <div class="admin-cat-form-field">
                            <label class="form-label" for="location-city-name">{{ $cityLabel }}</label>
                            <input id="location-city-name" class="form-control @if ($cityErrors['name'] !== '') is-invalid @endif" name="name" value="{{ $cityName }}" required maxlength="255" autocomplete="off"
                                @if ($cityErrors['name'] !== '') aria-invalid="true" aria-describedby="location-city-name-error" @endif
                                @if ($focusField === 'location-city-name') autofocus @endif>
                            @if ($cityErrors['name'] !== '')
                                <p class="admin-cat-form-error" id="location-city-name-error">{{ $cityErrors['name'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="admin-media-dialog-footer">
                        <a class="btn btn-light-secondary" href="{{ $locationsUrl }}" data-location-close>{{ $text['cancel'] }}</a>
                        <button type="submit" class="btn btn-primary" data-saving="{{ $text['saving'] }}">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $text['addCity'] }}</span>
                        </button>
                    </div>
                </form>
            </dialog>

            @if ($cities->isNotEmpty())
                <dialog class="admin-media-dialog admin-locations-dialog" id="location-governorate-dialog" aria-labelledby="location-governorate-title" aria-describedby="location-governorate-hint" @if ($openForm === 'governorate') open @endif>
                    <form method="POST" action="{{ route('dashboard.setting.locations.governorates.store') }}" class="admin-cat-form admin-locations-form" data-location-form="governorate">
                        @csrf
                        <input type="hidden" name="location_form" value="governorate">
                        <div class="admin-media-dialog-header">
                            <h2 class="admin-media-dialog-title" id="location-governorate-title">{{ $text['addGovernorate'] }}</h2>
                            <a class="admin-media-icon-button" href="{{ $locationsUrl }}" data-location-close aria-label="{{ $text['close'] }}"><i class="ph-duotone ph-x" aria-hidden="true"></i></a>
                        </div>
                        <div class="admin-media-dialog-form">
                            <p class="admin-locations-dialog-hint" id="location-governorate-hint">{{ $text['governoratesHint'] }}</p>
                            <div class="admin-cat-form-field">
                                <label class="form-label" for="location-governorate-city">{{ $cityLabel }}</label>
                                <select id="location-governorate-city" class="form-select @if ($governorateErrors['city_id'] !== '') is-invalid @endif" name="city_id" required
                                    @if ($governorateErrors['city_id'] !== '') aria-invalid="true" aria-describedby="location-governorate-city-error" @endif
                                    @if ($focusField === 'location-governorate-city') autofocus @endif>
                                    <option value="">{{ $text['selectCity'] }}</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected((string) $city->id === $selectedCityId)>{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                @if ($governorateErrors['city_id'] !== '')
                                    <p class="admin-cat-form-error" id="location-governorate-city-error">{{ $governorateErrors['city_id'] }}</p>
                                @endif
                            </div>
                            <div class="admin-cat-form-field">
                                <label class="form-label">{{ $text['governorates'] }}</label>
                                <div class="admin-locations-governorate-inputs" data-governorate-inputs>
                                    @foreach ($governorateNames as $index => $governorateName)
                                        @php($inputId = $index === 0 ? 'location-governorate-name' : 'location-governorate-name-'.$index)
                                        @php($error = $errors->first('governorates.'.$index) ?: ($index === 0 ? $governorateErrors['list'] : ''))
                                        <div class="admin-locations-governorate-row" data-governorate-row>
                                            <input id="{{ $inputId }}" class="form-control @if ($error !== '') is-invalid @endif" name="governorates[]" value="{{ $governorateName }}" maxlength="255" autocomplete="off" aria-label="{{ $text['governorate'] }}"
                                                @if ($error !== '') aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                                @if ($focusField === 'location-governorate-name' && $index === 0) autofocus @endif>
                                            @if ($error !== '') <p class="admin-cat-form-error" id="{{ $inputId }}-error">{{ $error }}</p> @endif
                                        </div>
                                    @endforeach
                                    <button type="button" class="btn btn-light-secondary admin-locations-add-governorate-row" data-add-governorate-row aria-label="{{ $text['addGovernorate'] }}">
                                        <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <template data-governorate-row-template>
                                    <div class="admin-locations-governorate-row" data-governorate-row>
                                        <input class="form-control" name="governorates[]" maxlength="255" autocomplete="off" aria-label="{{ $text['governorate'] }}">
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="admin-media-dialog-footer">
                            <a class="btn btn-light-secondary" href="{{ $locationsUrl }}" data-location-close>{{ $text['cancel'] }}</a>
                            <button type="submit" class="btn btn-primary" data-saving="{{ $text['saving'] }}">
                                <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                <span>{{ $text['addGovernorate'] }}</span>
                            </button>
                        </div>
                    </form>
                </dialog>
            @endif
        @endif

        <dl class="admin-locations-summary">
            <div class="card admin-media-panel admin-locations-stat">
                <dt>{{ $text['cities'] }}</dt>
                <dd data-location-count="cities">{{ $cities->count() }}</dd>
            </div>
            <div class="card admin-media-panel admin-locations-stat">
                <dt>{{ $text['governorates'] }}</dt>
                <dd data-location-count="governorates">{{ $governorateCount }}</dd>
                @if ($unassignedCount > 0)
                    <dd class="admin-locations-stat-note">{{ str_replace(':count', $unassignedCount, $text['unassignedCount']) }}</dd>
                @endif
            </div>
        </dl>

        <section class="card admin-media-panel" aria-labelledby="locations-list-title">
            <div class="admin-locations-panel-head">
                <h2 class="admin-locations-title" id="locations-list-title">{{ $text['existing'] }}</h2>
                {{-- Revealed and run by the script (7B-2); without it every location is listed. --}}
                <div class="admin-locations-search" role="search" aria-label="{{ $text['searchLabel'] }}" data-location-search hidden>
                    {{-- Named for assistive technology; the icon and placeholder show what the field is (as the media searches). --}}
                    <label class="form-label visually-hidden" for="locations-search">{{ $text['searchLabel'] }}</label>
                    <div class="admin-media-search">
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="locations-search" autocomplete="off" placeholder="{{ $text['searchPlaceholder'] }}" aria-describedby="locations-search-results">
                    </div>
                    <p class="admin-locations-results" id="locations-search-results" role="status" aria-live="polite" data-template="{{ $text['searchResults'] }}"></p>
                </div>
            </div>

            <div class="admin-locations-body">
                @if ($cities->isEmpty() && $unassignedGovernorates->isEmpty())
                    <div class="admin-media-state">
                        <i class="ph-duotone ph-map-pin" aria-hidden="true"></i>
                        <span>{{ $text['noCities'] }}</span>
                        @if ($canEdit)
                            <span class="admin-media-hint">{{ $text['emptyHint'] }}</span>
                        @endif
                    </div>
                @else
                    @if ($cities->isEmpty())
                        <p class="admin-locations-empty-note">{{ $text['noCities'] }}</p>
                    @else
                        <ul class="admin-locations-list">
                            @foreach ($cities as $city)
                                <li class="admin-locations-group" data-location-group data-location-names="{{ $city->governorates->pluck('name')->prepend($city->name)->implode("\n") }}">
                                    <div class="admin-locations-group-head">
                                        <h3 class="admin-locations-group-name"><bdi>{{ $city->name }}</bdi></h3>
                                        <span class="admin-locations-count">{{ str_replace(':count', $city->governorates->count(), $text['cityCount']) }}</span>
                                        @if ($canEdit)
                                            @php($shortcutLabel = str_replace(':name', $city->name, $text['addGovernorateTo']))
                                            <a class="btn btn-sm btn-light-secondary admin-locations-shortcut" href="{{ $addUrl(['add' => 'governorate', 'city' => $city->id], 'location-governorate-dialog') }}"
                                                data-location-open="governorate" data-city-id="{{ $city->id }}" aria-controls="location-governorate-dialog" aria-label="{{ $shortcutLabel }}" title="{{ $shortcutLabel }}">
                                                <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if ($city->governorates->isNotEmpty())
                                        <ul class="admin-locations-chips">
                                            @foreach ($city->governorates as $governorate)
                                                <li><bdi>{{ $governorate->name }}</bdi></li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="admin-locations-empty-note">{{ $text['noGovernorates'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($unassignedGovernorates->isNotEmpty())
                        <section class="admin-locations-group admin-locations-unassigned" aria-labelledby="locations-unassigned-title" data-location-group data-location-names="{{ $unassignedGovernorates->pluck('name')->implode("\n") }}">
                            <div class="admin-locations-group-head">
                                <i class="ph-duotone ph-warning-circle" aria-hidden="true"></i>
                                <h3 class="admin-locations-group-name" id="locations-unassigned-title">{{ $text['unassignedTitle'] }}</h3>
                                <span class="admin-locations-count">{{ str_replace(':count', $unassignedCount, $text['unassignedCount']) }}</span>
                            </div>
                            <p class="admin-locations-empty-note">{{ $text['unassignedHint'] }}</p>
                            <ul class="admin-locations-chips">
                                @foreach ($unassignedGovernorates as $governorate)
                                    <li><bdi>{{ $governorate->name }}</bdi></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <div class="admin-media-state" data-location-no-results hidden>
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <span>{{ $text['searchEmpty'] }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    @push('scripts')
        @vite('resources/js/admin-locations.js')
    @endpush
</x-dashboard-layout>
