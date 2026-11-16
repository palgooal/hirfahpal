{{--
    LOC-UI-7B, H2-C: the governorates and cities page (Governorate → City: a city belongs to one governorate through
    cities.governorate_id). A header with the add actions, two counts, the governorates with their cities, the cities
    linked to no governorate (legacy rows, shown read-only), and an add dialog for each form (settings.edit only).
    Without JavaScript every add action is a link: the server renders its dialog open (?add=governorate, ?add=city,
    &governorate= preselects a governorate) and the form posts as before; after a failed save the failed form's dialog is
    open with its errors and input. The script (7B-2) opens the same dialogs as modals instead and runs the search.
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
        'intro' => $locationText('Location_Hierarchy_Intro', 'Manage the governorates and the cities that belong to them.'),
        'governorates' => $locationText('Location_Governorates', 'Governorates'),
        'governoratesHint' => $locationText('Location_Governorates_Hint_Parent', 'Add a governorate before adding its cities.'),
        'governorate' => $locationText('Location_Governorate', 'Governorate'),
        'addGovernorate' => $locationText('Location_Add_Governorate', 'Add governorate'),
        'cities' => $locationText('Location_Cities', 'Cities'),
        'citiesHint' => $locationText('Location_Cities_Hint_Child', 'Choose the governorate these cities belong to.'),
        'selectGovernorate' => $locationText('Location_Select_Governorate', 'Choose a governorate'),
        'addCity' => $locationText('Location_Add_City', 'Add city'),
        'addCityTo' => $locationText('Location_Add_City_To', 'Add city to :name'),
        'existing' => $locationText('Location_Existing', 'Configured locations'),
        'noGovernorates' => $locationText('Location_No_Governorates', 'No governorates added yet.'),
        'noCities' => $locationText('Location_No_Cities', 'No cities added yet.'),
        'emptyHint' => $locationText('Location_Empty_Hint_Governorate', 'Add the first governorate to start.'),
        'cityCount' => $locationText('Location_Governorate_Cities_Count', 'Cities: :count'),
        'inactive' => $locationText('Location_Inactive', 'Inactive'),
        'unassignedTitle' => $locationText('Location_Unassigned_Cities_Title', 'Cities without a governorate'),
        'unassignedHint' => $locationText('Location_Unassigned_Cities_Hint', 'These cities are not linked to a governorate yet, so they are not offered for store and delivery addresses. They are shown as they are.'),
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

    // Cities can be added only under an active governorate (the controller checks it too).
    $activeGovernorates = $governorates->where('status', 'active')->values();
    // Counts from the loaded collections: a city has a governorate or none, never both.
    $unassignedCount = $unassignedCities->count();
    $cityCount = $governorates->sum(fn ($governorate) => $governorate->cities->count()) + $unassignedCount;

    // The failed form: its hidden location_form, only the two known values; without it the errors decide.
    $postedForm = old('location_form');
    $failedForm = $canEdit && $errors->any()
        ? (in_array($postedForm, ['city', 'governorate'], true) ? $postedForm : (($errors->has('governorate_id') || $errors->has('cities') || $errors->has('cities.*') || old('governorate_id') !== null) ? 'city' : 'governorate'))
        : null;
    // An add link (no JavaScript): the cities form only once an active governorate exists.
    $requestedForm = $canEdit && ! $failedForm && in_array(request()->query('add'), ['city', 'governorate'], true) ? request()->query('add') : null;
    if ($requestedForm === 'city' && $activeGovernorates->isEmpty()) {
        $requestedForm = null;
    }
    $openForm = $failedForm ?? $requestedForm;

    $scalar = fn ($value): string => is_scalar($value) ? (string) $value : '';
    $governorateName = $failedForm === 'governorate' ? $scalar(old('name')) : '';
    // The controller drops blank rows before validating, so the old rows line up with their cities.N errors.
    $cityNames = $failedForm === 'city' && is_array(old('cities'))
        ? array_values(array_map($scalar, old('cities')))
        : ($failedForm === 'city' && old('name') !== null ? [$scalar(old('name'))] : []);
    $cityNames = array_pad($cityNames, 3, '');
    $selectedGovernorateId = match (true) {
        $failedForm === 'city' => $scalar(old('governorate_id')),
        $requestedForm === 'city' => $scalar(request()->query('governorate')),
        default => '',
    };
    $governorateErrors = $failedForm === 'governorate' ? ['name' => $errors->first('name')] : ['name' => ''];
    $cityErrors = $failedForm === 'city'
        ? ['governorate_id' => $errors->first('governorate_id'), 'names' => $errors->get('cities.*'), 'list' => $errors->first('cities')]
        : ['governorate_id' => '', 'names' => [], 'list' => ''];
    // The field that takes focus when the page opens with a dialog: the first error, else the first field.
    $focusField = match ($openForm) {
        'governorate' => 'location-governorate-name',
        'city' => $cityErrors['governorate_id'] !== '' || ($cityErrors['names'] === [] && $selectedGovernorateId === '') ? 'location-city-governorate' : 'location-city-name',
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
                    <a class="btn btn-primary" href="{{ $addUrl(['add' => 'governorate'], 'location-governorate-dialog') }}" data-location-open="governorate" aria-controls="location-governorate-dialog">
                        <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                        <span>{{ $text['addGovernorate'] }}</span>
                    </a>
                    @if ($activeGovernorates->isEmpty())
                        <button type="button" class="btn btn-light-secondary" aria-disabled="true" aria-describedby="location-needs-governorate">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $text['addCity'] }}</span>
                        </button>
                        <p class="admin-media-hint admin-locations-needs-city" id="location-needs-governorate">{{ $text['governoratesHint'] }}</p>
                    @else
                        <a class="btn btn-light-secondary" href="{{ $addUrl(['add' => 'city'], 'location-city-dialog') }}" data-location-open="city" aria-controls="location-city-dialog">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $text['addCity'] }}</span>
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
                            <label class="form-label" for="location-governorate-name">{{ $text['governorate'] }}</label>
                            <input id="location-governorate-name" class="form-control @if ($governorateErrors['name'] !== '') is-invalid @endif" name="name" value="{{ $governorateName }}" required maxlength="255" autocomplete="off"
                                @if ($governorateErrors['name'] !== '') aria-invalid="true" aria-describedby="location-governorate-name-error" @endif
                                @if ($focusField === 'location-governorate-name') autofocus @endif>
                            @if ($governorateErrors['name'] !== '')
                                <p class="admin-cat-form-error" id="location-governorate-name-error">{{ $governorateErrors['name'] }}</p>
                            @endif
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

            @if ($activeGovernorates->isNotEmpty())
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
                                <label class="form-label" for="location-city-governorate">{{ $text['governorate'] }}</label>
                                <select id="location-city-governorate" class="form-select @if ($cityErrors['governorate_id'] !== '') is-invalid @endif" name="governorate_id" required
                                    @if ($cityErrors['governorate_id'] !== '') aria-invalid="true" aria-describedby="location-city-governorate-error" @endif
                                    @if ($focusField === 'location-city-governorate') autofocus @endif>
                                    <option value="">{{ $text['selectGovernorate'] }}</option>
                                    @foreach ($activeGovernorates as $governorate)
                                        <option value="{{ $governorate->id }}" @selected((string) $governorate->id === $selectedGovernorateId)>{{ $governorate->name }}</option>
                                    @endforeach
                                </select>
                                @if ($cityErrors['governorate_id'] !== '')
                                    <p class="admin-cat-form-error" id="location-city-governorate-error">{{ $cityErrors['governorate_id'] }}</p>
                                @endif
                            </div>
                            <div class="admin-cat-form-field">
                                <label class="form-label">{{ $text['cities'] }}</label>
                                {{-- One row per city; blank rows are ignored. The row classes keep the shared row styles. --}}
                                <div class="admin-locations-governorate-inputs" data-city-inputs>
                                    @foreach ($cityNames as $index => $cityName)
                                        @php($inputId = $index === 0 ? 'location-city-name' : 'location-city-name-'.$index)
                                        @php($error = $errors->first('cities.'.$index) ?: ($index === 0 ? $cityErrors['list'] : ''))
                                        <div class="admin-locations-governorate-row" data-city-row>
                                            <input id="{{ $inputId }}" class="form-control @if ($error !== '') is-invalid @endif" name="cities[]" value="{{ $cityName }}" maxlength="255" autocomplete="off" aria-label="{{ $cityLabel }}"
                                                @if ($index === 0) required @endif
                                                @if ($error !== '') aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                                @if ($focusField === 'location-city-name' && $index === 0) autofocus @endif>
                                            @if ($error !== '') <p class="admin-cat-form-error" id="{{ $inputId }}-error">{{ $error }}</p> @endif
                                        </div>
                                    @endforeach
                                    <button type="button" class="btn btn-light-secondary admin-locations-add-governorate-row" data-add-city-row aria-label="{{ $text['addCity'] }}">
                                        <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <template data-city-row-template>
                                    <div class="admin-locations-governorate-row" data-city-row>
                                        <input class="form-control" name="cities[]" maxlength="255" autocomplete="off" aria-label="{{ $cityLabel }}">
                                    </div>
                                </template>
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
            @endif
        @endif

        <dl class="admin-locations-summary">
            <div class="card admin-media-panel admin-locations-stat">
                <dt>{{ $text['governorates'] }}</dt>
                <dd data-location-count="governorates">{{ $governorates->count() }}</dd>
            </div>
            <div class="card admin-media-panel admin-locations-stat">
                <dt>{{ $text['cities'] }}</dt>
                <dd data-location-count="cities">{{ $cityCount }}</dd>
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
                @if ($governorates->isEmpty() && $unassignedCities->isEmpty())
                    <div class="admin-media-state">
                        <i class="ph-duotone ph-map-pin" aria-hidden="true"></i>
                        <span>{{ $text['noGovernorates'] }}</span>
                        @if ($canEdit)
                            <span class="admin-media-hint">{{ $text['emptyHint'] }}</span>
                        @endif
                    </div>
                @else
                    @if ($governorates->isEmpty())
                        <p class="admin-locations-empty-note">{{ $text['noGovernorates'] }}</p>
                    @else
                        <ul class="admin-locations-list">
                            @foreach ($governorates as $governorate)
                                <li class="admin-locations-group" data-location-group data-location-names="{{ $governorate->cities->pluck('name')->prepend($governorate->name)->implode("\n") }}">
                                    <div class="admin-locations-group-head">
                                        <h3 class="admin-locations-group-name"><bdi>{{ $governorate->name }}</bdi></h3>
                                        <span class="admin-locations-count">{{ str_replace(':count', $governorate->cities->count(), $text['cityCount']) }}</span>
                                        @if ($governorate->status !== 'active')
                                            <span class="admin-locations-count" data-location-inactive>{{ $text['inactive'] }}</span>
                                        @elseif ($canEdit)
                                            @php($shortcutLabel = str_replace(':name', $governorate->name, $text['addCityTo']))
                                            <a class="btn btn-sm btn-light-secondary admin-locations-shortcut" href="{{ $addUrl(['add' => 'city', 'governorate' => $governorate->id], 'location-city-dialog') }}"
                                                data-location-open="city" data-governorate-id="{{ $governorate->id }}" aria-controls="location-city-dialog" aria-label="{{ $shortcutLabel }}" title="{{ $shortcutLabel }}">
                                                <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if ($governorate->cities->isNotEmpty())
                                        <ul class="admin-locations-chips">
                                            @foreach ($governorate->cities as $city)
                                                <li><bdi>{{ $city->name }}</bdi></li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="admin-locations-empty-note">{{ $text['noCities'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($unassignedCities->isNotEmpty())
                        <section class="admin-locations-group admin-locations-unassigned" aria-labelledby="locations-unassigned-title" data-location-group data-location-names="{{ $unassignedCities->pluck('name')->implode("\n") }}">
                            <div class="admin-locations-group-head">
                                <i class="ph-duotone ph-warning-circle" aria-hidden="true"></i>
                                <h3 class="admin-locations-group-name" id="locations-unassigned-title">{{ $text['unassignedTitle'] }}</h3>
                                <span class="admin-locations-count">{{ str_replace(':count', $unassignedCount, $text['unassignedCount']) }}</span>
                            </div>
                            <p class="admin-locations-empty-note">{{ $text['unassignedHint'] }}</p>
                            <ul class="admin-locations-chips">
                                @foreach ($unassignedCities as $city)
                                    <li><bdi>{{ $city->name }}</bdi></li>
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
