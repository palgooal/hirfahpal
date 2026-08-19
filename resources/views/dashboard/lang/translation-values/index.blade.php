<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.languages.index') }}">{{ t('dashboard.Languages', 'Languages') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Translation_Values', 'Translation Values') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Dictionary', 'Dictionary') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Translation_Values', 'Translation Values') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Manage_translation_keys', 'Search, filter, import, export, and edit translation keys for all active languages.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.languages.index') }}" class="btn btn-light-secondary">
                        <i class="ti ti-world me-1"></i>
                        {{ t('dashboard.Languages', 'Languages') }}
                    </a>
                    <a href="{{ route('dashboard.translation-values.export') }}" class="btn btn-light-success">
                        <i class="ti ti-download me-1"></i>
                        {{ t('dashboard.Export_CSV', 'Export CSV') }}
                    </a>
                    <a href="{{ route('dashboard.translation-values.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        {{ t('dashboard.Add_New_Translation', 'Add New Translation') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 md:col-span-4">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Keys', 'Keys') }}</p>
                    <h4 class="mb-0">{{ $translations->count() }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-primary">
                    <i class="ti ti-key f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 md:col-span-4">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Active_Languages', 'Active Languages') }}</p>
                    <h4 class="mb-0">{{ $languages->count() }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-success">
                    <i class="ti ti-language f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 md:col-span-4">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Filter', 'Filter') }}</p>
                    <h4 class="mb-0">{{ $localeFilter ? strtoupper($localeFilter) : t('dashboard.All', 'All') }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-info">
                    <i class="ti ti-filter f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">{{ t('dashboard.Filters', 'Filters') }}</h5>
                <p class="text-muted mb-0">{{ t('dashboard.Narrow_translation_results', 'Narrow the list by language, type, or key name.') }}</p>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.translation-values.index') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label">{{ t('dashboard.Language', 'Language') }}</label>
                        <select name="locale" class="form-select">
                            <option value="">{{ t('dashboard.All_Languages', 'All Languages') }}</option>
                            @foreach($languages as $lang)
                                <option value="{{ $lang->code }}" {{ $localeFilter == $lang->code ? 'selected' : '' }}>
                                    {{ $lang->native }} ({{ strtoupper($lang->code) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label">{{ t('dashboard.Type', 'Type') }}</label>
                        <select name="type" class="form-select">
                            <option value="">{{ t('dashboard.All_Types', 'All Types') }}</option>
                            <option value="dashboard" {{ $typeFilter == 'dashboard' ? 'selected' : '' }}>{{ t('dashboard.Dashboard', 'Dashboard') }}</option>
                            <option value="frontend" {{ $typeFilter == 'frontend' ? 'selected' : '' }}>{{ t('dashboard.Frontend', 'Frontend') }}</option>
                            <option value="general" {{ $typeFilter == 'general' ? 'selected' : '' }}>{{ t('dashboard.General', 'General') }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-xl-4">
                        <label class="form-label">{{ t('dashboard.Search', 'Search') }}</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ t('dashboard.Search_keys...', 'Search keys...') }}">
                    </div>
                    <div class="col-12 col-xl-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ti ti-search me-1"></i>
                            {{ t('dashboard.Search', 'Search') }}
                        </button>
                        <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-light-secondary">
                            <i class="ti ti-refresh"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card table-card">
            <div class="card-header">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">{{ t('dashboard.Translations', 'Translations') }}</h5>
                        <p class="text-muted mb-0">{{ t('dashboard.Translation_table_hint', 'Each row represents one key with values across locales.') }}</p>
                    </div>
                    <form action="{{ route('dashboard.translation-values.import') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                        @csrf
                        <input type="file" name="csv_file" accept=".csv" required class="form-control">
                        <button type="submit" class="btn btn-light-primary text-nowrap">
                            <i class="ti ti-upload me-1"></i>
                            {{ t('dashboard.Import_CSV', 'Import CSV') }}
                        </button>
                    </form>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success mx-4 mt-4 mb-0">{{ session('success') }}</div>
            @endif

            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ t('dashboard.Key', 'Key') }}</th>
                                <th>{{ t('dashboard.Value', 'Value') }}</th>
                                <th>{{ t('dashboard.Type', 'Type') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($translations as $key => $items)
                                @php
                                    $type = Str::startsWith($key, 'dashboard.') ? 'Dashboard'
                                        : (Str::startsWith($key, 'frontend.') ? 'Frontend' : 'General');
                                    $badgeClass = $type === 'Dashboard' ? 'bg-light-primary text-primary'
                                        : ($type === 'Frontend' ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary');
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><code class="text-xs d-inline-block bg-light-secondary px-2 py-1 rounded">{{ $key }}</code></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 520px;">{{ $items->first()?->value ?: '-' }}</div>
                                        <small class="text-muted">{{ $items->count() }} {{ t('dashboard.Locales', 'locales') }}</small>
                                    </td>
                                    <td><span class="badge {{ $badgeClass }}">{{ $type }}</span></td>
                                    <td>
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <a href="{{ route('dashboard.translation-values.edit', ['key' => $key]) }}" class="btn btn-sm btn-light-primary">
                                                <i class="ti ti-edit me-1"></i>
                                                {{ t('dashboard.Edit', 'Edit') }}
                                            </a>
                                            <form action="{{ route('dashboard.translation-values.destroy', ['key' => $key]) }}" method="POST" onsubmit="return confirm('{{ t('dashboard.confirm_delete', 'Are you sure?') }}');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light-danger">
                                                    <i class="ti ti-trash me-1"></i>
                                                    {{ t('dashboard.Delete', 'Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="avtar avtar-xl bg-light-primary mx-auto mb-3">
                                            <i class="ti ti-language f-30"></i>
                                        </div>
                                        <h5>{{ t('dashboard.No_translations_found', 'No translations found') }}</h5>
                                        <p class="text-muted mb-3">{{ t('dashboard.Create_first_translation', 'Create your first translation key or adjust the filters.') }}</p>
                                        <a href="{{ route('dashboard.translation-values.create') }}" class="btn btn-primary">{{ t('dashboard.Add_New_Translation', 'Add New Translation') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
