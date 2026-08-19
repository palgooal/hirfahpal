<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Languages', 'Languages') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Locale_Manager', 'Locale Manager') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Languages_List', 'Languages List') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Manage_dashboard_languages', 'Manage active languages, direction, and translation access from one place.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.translation-values.index') }}" class="btn btn-light-secondary">
                        <i class="ti ti-language me-1"></i>
                        {{ t('dashboard.Translation_Values', 'Translation Values') }}
                    </a>
                    <a href="{{ route('dashboard.languages.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        {{ t('dashboard.Add_Languages', 'Add Language') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Total_Languages', 'Total Languages') }}</p>
                    <h4 class="mb-0">{{ $langs->total() }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-primary">
                    <i class="ti ti-world f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Active_Languages', 'Active Languages') }}</p>
                    <h4 class="mb-0">{{ $langs->getCollection()->where('is_active', true)->count() }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-success">
                    <i class="ti ti-circle-check f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.RTL_Languages', 'RTL Languages') }}</p>
                    <h4 class="mb-0">{{ $langs->getCollection()->where('is_rtl', true)->count() }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-warning">
                    <i class="ti ti-text-direction-rtl f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Current_Locale', 'Current Locale') }}</p>
                    <h4 class="mb-0">{{ strtoupper(app()->getLocale()) }}</h4>
                </div>
                <div class="avtar avtar-s bg-light-info">
                    <i class="ti ti-settings f-20"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card table-card">
            <div class="card-header">
                <h5 class="mb-1">{{ t('dashboard.Languages', 'Languages') }}</h5>
                <p class="text-muted mb-0">{{ t('dashboard.Edit_language_rows', 'Edit language records, status, and writing direction.') }}</p>
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
                                <th>{{ t('dashboard.Language', 'Language') }}</th>
                                <th>{{ t('dashboard.Code', 'Code') }}</th>
                                <th>{{ t('dashboard.Direction', 'Direction') }}</th>
                                <th>{{ t('dashboard.Status', 'Status') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($langs as $lang)
                                <tr>
                                    <td>{{ ($langs->currentPage() - 1) * $langs->perPage() + $loop->iteration }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if($lang->flag)
                                                <img src="{{ asset($lang->flag) }}" alt="{{ $lang->native }}" class="w-8 h-8 rounded-full object-cover border">
                                            @else
                                                <span class="avtar avtar-s bg-light-primary text-primary">{{ strtoupper(substr($lang->code, 0, 2)) }}</span>
                                            @endif
                                            <div>
                                                <h6 class="mb-0">{{ $lang->native }}</h6>
                                                <small class="text-muted">{{ $lang->name }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light-secondary text-secondary">{{ strtoupper($lang->code) }}</span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check form-switch switch-lg mb-0">
                                                <input type="checkbox" {{ $lang->is_rtl ? 'checked' : '' }}
                                                    class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                    onclick="toggleRtl({{ $lang->id }}, this.checked)">
                                            </div>
                                            <span class="text-muted">{{ $lang->is_rtl ? 'RTL' : 'LTR' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check form-switch switch-lg mb-0">
                                                <input type="checkbox" {{ $lang->is_active ? 'checked' : '' }}
                                                    class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                    onclick="toggleStatus({{ $lang->id }}, this.checked)">
                                            </div>
                                            <span class="badge {{ $lang->is_active ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}">
                                                {{ $lang->is_active ? t('dashboard.Active', 'Active') : t('dashboard.Inactive', 'Inactive') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <a href="{{ route('dashboard.translation-values.index', ['locale' => $lang->code]) }}" class="btn btn-sm btn-light-info">
                                                <i class="ti ti-language me-1"></i>
                                                {{ t('dashboard.Translations', 'Translations') }}
                                            </a>
                                            <a href="{{ route('dashboard.languages.edit', $lang->id) }}" class="btn btn-sm btn-light-secondary">
                                                <i class="ti ti-edit me-1"></i>
                                                {{ t('dashboard.Edit', 'Edit') }}
                                            </a>
                                            <button type="button" onclick="deleteLanguage({{ $lang->id }})" class="btn btn-sm btn-light-danger">
                                                <i class="ti ti-trash me-1"></i>
                                                {{ t('dashboard.Delete', 'Delete') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="avtar avtar-xl bg-light-primary mx-auto mb-3">
                                            <i class="ti ti-world f-30"></i>
                                        </div>
                                        <h5>{{ t('dashboard.No_languages_found', 'No languages found') }}</h5>
                                        <p class="text-muted mb-3">{{ t('dashboard.Create_first_language', 'Create your first language to start translating the dashboard.') }}</p>
                                        <a href="{{ route('dashboard.languages.create') }}" class="btn btn-primary">{{ t('dashboard.Add_Languages', 'Add Language') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $langs->links() }}
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        function toggleRtl(langId, isChecked) {
            const url = @json(route('dashboard.languages.toggle-rtl', ['language' => '__LANGUAGE__']));
            updateToggle(url.replace('__LANGUAGE__', langId), { is_rtl: isChecked ? 1 : 0 }, 'RTL');
        }

        function toggleStatus(langId, isChecked) {
            const url = @json(route('dashboard.languages.toggle-status', ['language' => '__LANGUAGE__']));
            updateToggle(url.replace('__LANGUAGE__', langId), { is_active: isChecked ? 1 : 0 }, 'Status');
        }

        function updateToggle(url, data, label) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => data.success
                ? Toast.fire({ icon: 'success', title: `${label} updated` })
                : Toast.fire({ icon: 'error', title: `Error updating ${label}` })
            )
            .catch(() => Toast.fire({ icon: 'error', title: 'Connection error' }));
        }

        function deleteLanguage(langId) {
            Swal.fire({
                title: @json(t('dashboard.Delete_language_title', 'Delete this language?')),
                text: @json(t('dashboard.Delete_language_message', 'This will delete the language and all of its translations.')),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: @json(t('dashboard.Delete', 'Delete')),
                cancelButtonText: @json(t('dashboard.Cancel', 'Cancel')),
                reverseButtons: true
            }).then(result => {
                if (!result.isConfirmed) {
                    return;
                }

                const url = @json(route('dashboard.languages.destroy-ajax', ['language' => '__LANGUAGE__']));

                fetch(url.replace('__LANGUAGE__', langId), {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network error');
                    }

                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: @json(t('dashboard.Deleted', 'Deleted')),
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        throw new Error(data.error || 'Delete failed');
                    }
                })
                .catch(error => Swal.fire('Error', error.message, 'error'));
            });
        }
    </script>
</x-dashboard-layout>
