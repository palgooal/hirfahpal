<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item">{{ t('dashboard.Categories', 'Categories') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1">{{ t('dashboard.Categories', 'Categories') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Manage_storefront_categories', 'Manage storefront browsing categories.') }}</p>
                </div>
                <a href="{{ route('dashboard.categories.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ t('dashboard.Add', 'Add') }}</a>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ t('dashboard.Search_categories', 'Search categories...') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="is_active" class="form-select">
                            <option value="">{{ t('dashboard.All', 'All') }}</option>
                            <option value="1" @selected(request('is_active') === '1')>{{ t('dashboard.Active', 'Active') }}</option>
                            <option value="0" @selected(request('is_active') === '0')>{{ t('dashboard.Inactive', 'Inactive') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary">{{ t('dashboard.Filter', 'Filter') }}</button>
                        <a href="{{ route('dashboard.categories.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Reset', 'Reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>{{ t('dashboard.Name', 'Name') }}</th><th>{{ t('dashboard.Parent', 'Parent') }}</th><th>{{ t('dashboard.Products', 'Products') }}</th><th>{{ t('dashboard.Status', 'Status') }}</th><th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th></tr></thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr>
                                    <td><div class="fw-semibold">{{ $category->name }}</div><small class="text-muted">{{ $category->slug }}</small></td>
                                    <td>{{ $category->parent?->name ?? '-' }}</td>
                                    <td>{{ $category->products_count }}</td>
                                    <td><span class="badge bg-light-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? t('dashboard.Active', 'Active') : t('dashboard.Inactive', 'Inactive') }}</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-light-secondary" href="{{ route('dashboard.categories.edit', $category) }}">{{ t('dashboard.Edit', 'Edit') }}</a>
                                        <form class="d-inline" method="POST" action="{{ route('dashboard.categories.destroy', $category) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger">{{ t('dashboard.Delete', 'Delete') }}</button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $categories->links() }}
            </div>
        </div>
    </div>
</x-dashboard-layout>
