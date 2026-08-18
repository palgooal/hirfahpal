<x-dashboard-layout>
    <div class="page-header">
        <div class="page-block">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dashboard.languages.index') }}">{{ t('dashboard.Languages', 'Languages') }}</a></li>
                <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Languages_List', 'Languages List') }}</li>
            </ul>
            <div class="page-header-title">
                <h2 class="mb-0">{{ t('dashboard.Languages_List', 'Languages List') }}</h2>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-x-6">
        <div class="col-span-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="sm:flex items-center justify-between">
                        <h5 class="mb-3 sm:mb-0">{{ t('dashboard.Languages_List', 'Languages List') }}</h5>
                        <a href="{{ route('dashboard.languages.create') }}" class="btn btn-primary">
                            {{ t('dashboard.Add_Languages', 'Add Language') }}
                        </a>
                    </div>
                </div>
                <div class="card-body pt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ t('dashboard.Name', 'Name') }}</th>
                                    <th>{{ t('dashboard.Native_Name', 'Native Name') }}</th>
                                    <th>{{ t('dashboard.Code', 'Code') }}</th>
                                    <th>{{ t('dashboard.Flag', 'Flag') }}</th>
                                    <th>{{ t('dashboard.RTL', 'RTL') }}</th>
                                    <th>{{ t('dashboard.Status', 'Status') }}</th>
                                    <th>{{ t('dashboard.Actiont', 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($langs as $lang)
                                <tr>
                                    <td>{{ ($langs->currentPage() - 1) * $langs->perPage() + $loop->iteration }}</td>
                                    <td>{{ $lang->name }}</td>
                                    <td>{{ $lang->native }}</td>
                                    <td>{{ strtoupper($lang->code) }}</td>
                                    <td>
                                        @if($lang->flag)
                                            <img src="{{ asset($lang->flag) }}" alt="flag" class="w-6 h-4 inline-block">
                                        @endif
                                    </td>
                                    <td>
                                        <div class="form-check form-switch switch-lg">
                                            <input type="checkbox" {{ $lang->is_rtl ? 'checked' : '' }}
                                                class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                onclick="toggleRtl({{ $lang->id }}, this.checked)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch switch-lg">
                                            <input type="checkbox" {{ $lang->is_active ? 'checked' : '' }}
                                                class="form-check-input checked:!bg-success-500 checked:!border-success-500 text-lg"
                                                onclick="toggleStatus({{ $lang->id }}, this.checked)">
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('dashboard.translation-values.index', ['locale' => $lang->code]) }}" class="btn btn-sm btn-info">
                                            {{ t('dashboard.languages_edit', 'Translations') }}
                                        </a>
                                        <a href="{{ route('dashboard.languages.edit', $lang->id) }}"
                                            class="w-8 h-8 rounded-xl inline-flex items-center justify-center btn-link-secondary">
                                            <i class="ti ti-edit text-xl leading-none"></i>
                                        </a>
                                        <a href="#" onclick="deleteLanguage({{ $lang->id }})"
                                            class="w-8 h-8 rounded-xl inline-flex items-center justify-center btn-link-secondary">
                                            <i class="ti ti-trash text-xl leading-none"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-4">{{ $langs->links() }}</div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const Toast = Swal.mixin({
    toast: true, position: 'top-end',
    showConfirmButton: false, timer: 2000, timerProgressBar: true
});

function toggleRtl(langId, isChecked) {
    updateToggle(`/admin/languages/${langId}/toggle-rtl`, { is_rtl: isChecked ? 1 : 0 }, 'RTL');
}
function toggleStatus(langId, isChecked) {
    updateToggle(`/admin/languages/${langId}/toggle-status`, { is_active: isChecked ? 1 : 0 }, 'Status');
}
function updateToggle(url, data, label) {
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => d.success
        ? Toast.fire({ icon: 'success', title: `✅ ${label} updated` })
        : Toast.fire({ icon: 'error',   title: `❌ Error updating ${label}` })
    )
    .catch(() => Toast.fire({ icon: 'error', title: '❌ Connection error' }));
}

function deleteLanguage(langId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف اللغة مع كافة ترجماتها نهائياً!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف الآن', cancelButtonText: 'تراجع', reverseButtons: true
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'جاري الحذف...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch(`/admin/languages/${langId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        })
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'تم الحذف!', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else throw new Error(d.error || 'حدث خطأ ما');
        })
        .catch(e => Swal.fire('خطأ!', e.message, 'error'));
    });
}
</script>
</x-dashboard-layout>
