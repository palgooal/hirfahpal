<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.products.index') }}">{{ t('dashboard.Products', 'Products') }}</a></li>
        <li class="breadcrumb-item">{{ $product->exists ? t('dashboard.Edit', 'Edit') : t('dashboard.Create', 'Create') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header"><h4 class="mb-0">{{ $product->exists ? t('dashboard.Edit_Product', 'Edit Product') : t('dashboard.Create_Product', 'Create Product') }}</h4></div>
            <div class="card-body">
                <form method="POST" action="{{ $product->exists ? route('dashboard.products.update', $product) : route('dashboard.products.store') }}" class="row g-3">
                    @csrf
                    @if($product->exists) @method('PUT') @endif
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Name', 'Name') }}</label><input name="name" class="form-control" value="{{ old('name', $product->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Slug', 'Slug') }}</label><input name="slug" class="form-control" value="{{ old('slug', $product->slug) }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Vendor', 'Vendor') }}</label><select name="vendor_id" class="form-select" required>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected(old('vendor_id', $product->vendor_id) == $vendor->id)>{{ $vendor->profile?->store_name ?? $vendor->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Category', 'Category') }}</label><select name="category_id" class="form-select"><option value="">{{ t('dashboard.None', 'None') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Price', 'Price') }}</label><input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price) }}" required></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Compare_Price', 'Compare Price') }}</label><input type="number" step="0.01" min="0" name="compare_at_price" class="form-control" value="{{ old('compare_at_price', $product->compare_at_price) }}"></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.SKU', 'SKU') }}</label><input name="sku" class="form-control" value="{{ old('sku', $product->sku) }}"></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Status', 'Status') }}</label><select name="status" class="form-select">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(old('status', $product->status) === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Stock', 'Stock') }}</label><input type="number" min="0" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}"></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Low_Stock_Threshold', 'Low Stock Threshold') }}</label><input type="number" min="0" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 1) }}"></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Weight', 'Weight') }}</label><input type="number" step="0.01" min="0" name="weight" class="form-control" value="{{ old('weight', $product->weight) }}"></div>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_featured" value="0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))><label class="form-check-label">{{ t('dashboard.Featured', 'Featured') }}</label></div></div>
                    <div class="col-12"><label class="form-label">{{ t('dashboard.Short_Description', 'Short Description') }}</label><textarea name="short_description" rows="2" class="form-control">{{ old('short_description', $product->short_description) }}</textarea></div>
                    <div class="col-12"><label class="form-label">{{ t('dashboard.Description', 'Description') }}</label><textarea name="description" rows="5" class="form-control">{{ old('description', $product->description) }}</textarea></div>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button><a href="{{ route('dashboard.products.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a></div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
