<x-dashboard-layout>
    <div class="page-header">
        <div class="page-block">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dashboard.languages.index') }}">{{ t('dashboard.Languages', 'Languages') }}</a></li>
                <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Translation_Values', 'Translation Values') }}</li>
            </ul>
            <div class="page-header-title">
                <h2 class="mb-0">{{ t('dashboard.Translation_Values', 'Translation Values') }}</h2>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-x-6">
        <div class="col-span-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="sm:flex items-center justify-between">
                        <h5 class="mb-3 sm:mb-0">{{ t('dashboard.Translation_Values', 'Translation Values') }}</h5>
                        <a href="{{ route('dashboard.translation-values.create') }}" class="btn btn-primary">
                            {{ t('dashboard.Add_New_Translation', 'Add New Translation') }}
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success mb-4 mx-5">{{ session('success') }}</div>
                @endif

                {{-- Filters --}}
                <div class="flex items-center justify-between mb-4 px-5 py-4">
                    <form method="GET" action="{{ route('dashboard.translation-values.index') }}" class="flex items-center gap-3 flex-wrap">
                        <select name="locale" onchange="this.form.submit()" class="w-48 border px-2 py-1 rounded">
                            <option value="">-- {{ t('dashboard.All_Languages', 'All Languages') }} --</option>
                            @foreach($languages as $lang)
                                <option value="{{ $lang->code }}" {{ $localeFilter == $lang->code ? 'selected' : '' }}>
                                    {{ $lang->native }} ({{ $lang->code }})
                                </option>
                            @endforeach
                        </select>
                        <select name="type" onchange="this.form.submit()" class="w-48 border px-2 py-1 rounded">
                            <option value="">-- {{ t('dashboard.All_Types', 'All Types') }} --</option>
                            <option value="dashboard" {{ $typeFilter == 'dashboard' ? 'selected' : '' }}>{{ t('dashboard.Dashboard', 'Dashboard') }}</option>
                            <option value="frontend"  {{ $typeFilter == 'frontend'  ? 'selected' : '' }}>{{ t('dashboard.Frontend', 'Frontend') }}</option>
                            <option value="general"   {{ $typeFilter == 'general'   ? 'selected' : '' }}>{{ t('dashboard.General', 'General') }}</option>
                        </select>
                        <input type="text" name="search" value="{{ $search }}"
                            placeholder="{{ t('dashboard.Search_keys...', 'Search keys...') }}"
                            class="border px-2 py-1 rounded w-64">
                        <button type="submit" class="btn btn-primary">{{ t('dashboard.Search', 'Search') }}</button>
                        <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-secondary">{{ t('dashboard.Reset', 'Reset') }}</a>
                    </form>
                </div>

                {{-- Export / Import --}}
                <div class="flex items-center gap-2 mb-4 px-5">
                    <a href="{{ route('dashboard.translation-values.export') }}" class="btn btn-success">
                        {{ t('dashboard.Export_CSV', 'Export CSV') }}
                    </a>
                    <form action="{{ route('dashboard.translation-values.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="csv_file" accept=".csv" required class="border px-2 py-1 rounded">
                        <button type="submit" class="btn btn-primary">{{ t('dashboard.Import_CSV', 'Import CSV') }}</button>
                    </form>
                </div>

                <div class="card-body pt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ t('dashboard.Key', 'Key') }}</th>
                                    <th>{{ t('dashboard.Value', 'Value (first locale)') }}</th>
                                    <th>{{ t('dashboard.Type', 'Type') }}</th>
                                    <th>{{ t('dashboard.Actiont', 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($translations as $key => $items)
                                    @php
                                        $type = Str::startsWith($key, 'dashboard.') ? 'Dashboard'
                                              : (Str::startsWith($key, 'frontend.') ? 'Frontend' : 'General');
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><code class="text-xs">{{ $key }}</code></td>
                                        <td>{{ $items->first()?->value }}</td>
                                        <td><span class="badge bg-secondary">{{ $type }}</span></td>
                                        <td>
                                            <a href="{{ route('dashboard.translation-values.edit', ['key' => $key]) }}" class="btn btn-sm btn-primary">
                                                {{ t('dashboard.edit_translation', 'Edit') }}
                                            </a>
                                            <form action="{{ route('dashboard.translation-values.destroy', ['key' => $key]) }}" method="POST" class="inline-block"
                                                onsubmit="return confirm('{{ t('dashboard.confirm_delete', 'Are you sure?') }}');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">{{ t('dashboard.delete', 'Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
