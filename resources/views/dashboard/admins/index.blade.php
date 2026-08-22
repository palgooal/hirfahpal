<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Admins', 'Admins') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Permissions', 'Permissions') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Admins', 'Admins') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Manage_admin_permissions', 'Manage dashboard admins and their permissions.') }}</p>
                </div>
                @can('create', App\Models\Admin::class)
                    <a href="{{ route('dashboard.admins.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        {{ t('dashboard.Add_Admin', 'Add Admin') }}
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form action="{{ route('dashboard.admins.index') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-8">
                        <label class="form-label">{{ t('dashboard.Search', 'Search') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ t('dashboard.Search_admins', 'Search admins...') }}">
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">{{ t('dashboard.Search', 'Search') }}</button>
                        <a href="{{ route('dashboard.admins.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Reset', 'Reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Name', 'Name') }}</th>
                                <th>{{ t('dashboard.Email', 'Email') }}</th>
                                <th>{{ t('dashboard.Phone', 'Phone') }}</th>
                                <th>{{ t('dashboard.Status', 'Status') }}</th>
                                <th>{{ t('dashboard.Type', 'Type') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($admins as $admin)
                                <tr>
                                    <td class="fw-semibold">{{ $admin->name }}</td>
                                    <td>{{ $admin->email ?: '-' }}</td>
                                    <td>{{ $admin->phone }}</td>
                                    <td>
                                        <span class="badge bg-light-{{ $admin->status === 'active' ? 'success' : ($admin->status === 'blocked' ? 'danger' : 'warning') }}">
                                            {{ t('dashboard.' . ucfirst($admin->status), ucfirst($admin->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($admin->super_admin)
                                            <span class="badge bg-light-primary text-primary">{{ t('dashboard.Super_Admin', 'Super Admin') }}</span>
                                        @else
                                            <span class="badge bg-light-secondary text-secondary">{{ t('dashboard.Admin', 'Admin') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            @can('edit', App\Models\Admin::class)
                                                <a href="{{ route('dashboard.admins.edit', $admin) }}" class="btn btn-sm btn-light-secondary">
                                                    {{ t('dashboard.Edit', 'Edit') }}
                                                </a>
                                            @endcan

                                            @can('delete', App\Models\Admin::class)
                                                <form action="{{ route('dashboard.admins.destroy', $admin) }}" method="POST" onsubmit="return confirm('{{ t('dashboard.confirm_delete', 'Are you sure?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light-danger">
                                                        {{ t('dashboard.Delete', 'Delete') }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        {{ t('dashboard.No_admins_found', 'No admins found') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $admins->links() }}
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
