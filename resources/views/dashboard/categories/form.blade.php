<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.categories.index') }}">{{ t('dashboard.Categories', 'Categories') }}</a></li>
        <li class="breadcrumb-item">{{ $category->exists ? t('dashboard.Edit', 'Edit') : t('dashboard.Create', 'Create') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header"><h4 class="mb-0">{{ $category->exists ? t('dashboard.Edit_Category', 'Edit Category') : t('dashboard.Create_Category', 'Create Category') }}</h4></div>
            <div class="card-body">
                <form method="POST" action="{{ $category->exists ? route('dashboard.categories.update', $category) : route('dashboard.categories.store') }}" class="row g-3">
                    @csrf
                    @if($category->exists) @method('PUT') @endif
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Name', 'Name') }}</label><input name="name" class="form-control" value="{{ old('name', $category->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Slug', 'Slug') }}</label><input name="slug" class="form-control" value="{{ old('slug', $category->slug) }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ t('dashboard.Parent', 'Parent') }}</label><select name="parent_id" class="form-select"><option value="">{{ t('dashboard.None', 'None') }}</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">{{ t('dashboard.Sort_Order', 'Sort Order') }}</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order ?? 0) }}"></div>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))><label class="form-check-label">{{ t('dashboard.Active', 'Active') }}</label></div></div>
                    <div class="col-12"><label class="form-label">{{ t('dashboard.Image_Path', 'Image Path') }}</label><input name="image" class="form-control" value="{{ old('image', $category->image) }}"></div>
                    <div class="col-12"><label class="form-label">{{ t('dashboard.Description', 'Description') }}</label><textarea name="description" rows="4" class="form-control">{{ old('description', $category->description) }}</textarea></div>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button><a href="{{ route('dashboard.categories.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a></div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
