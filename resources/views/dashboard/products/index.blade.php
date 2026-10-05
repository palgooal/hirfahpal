<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item">{{ t('dashboard.Products', 'Products') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1">{{ t('dashboard.Products', 'Products') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Manage_catalog_products', 'Manage marketplace products, stock and publishing state.') }}</p>
                </div>
                <a href="{{ route('dashboard.products.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ t('dashboard.Add_Product', 'Add Product') }}</a>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-3">
                    <div class="col-lg-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ t('dashboard.Search_products', 'Search products or SKU...') }}"></div>
                    <div class="col-lg-3"><select name="category_id" class="form-select"><option value="">{{ t('dashboard.All_Categories', 'All Categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="col-lg-3"><select name="status" class="form-select"><option value="">{{ t('dashboard.All_Statuses', 'All Statuses') }}</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary">{{ t('dashboard.Filter', 'Filter') }}</button><a href="{{ route('dashboard.products.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Reset', 'Reset') }}</a></div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>{{ t('dashboard.Product', 'Product') }}</th><th>{{ t('dashboard.Vendor', 'Vendor') }}</th><th>{{ t('dashboard.Category', 'Category') }}</th><th>{{ t('dashboard.Stock', 'Stock') }}</th><th>{{ t('dashboard.Status', 'Status') }}</th><th class="text-end">{{ t('dashboard.Price', 'Price') }}</th><th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th></tr></thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td><div class="fw-semibold">{{ $product->name }}</div><small class="text-muted">{{ $product->sku ?? $product->slug }}</small></td>
                                    <td>{{ $product->vendor?->profile?->store_name ?? $product->vendor?->name ?? '-' }}</td>
                                    <td>{{ $product->category?->name ?? '-' }}</td>
                                    <td>{{ $product->stock_quantity }} <small class="text-muted">/{{ t('dashboard.Reserved', 'Reserved') }} {{ $product->reservedStockQuantity() }}</small></td>
                                    <td><span class="badge bg-light-{{ $product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'warning' : 'secondary') }}">{{ $statuses[$product->status] ?? $product->status }}</span></td>
                                    <td class="text-end">{{ number_format((float) $product->price, 2) }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-light-secondary" href="{{ route('dashboard.products.edit', $product) }}">{{ t('dashboard.Edit', 'Edit') }}</a>
                                        <form class="d-inline" method="POST" action="{{ route('dashboard.products.destroy', $product) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger">{{ t('dashboard.Delete', 'Delete') }}</button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $products->links() }}
            </div>
        </div>
    </div>
</x-dashboard-layout>
