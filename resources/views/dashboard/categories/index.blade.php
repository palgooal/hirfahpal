{{--
    AD-UI-CAT-01E-3A: the categories list in the admin shell (AD-UI-01). Main categories are followed by their
    subcategories (CategoryManagementController::index). Actions follow categories.create / edit / delete; the
    server enforces them, and the delete protection (DEC-CAT-Q3) stays on the server whatever is shown here.
    No activation control (DEC-CAT-Q16). Thumbnails come from image_media_id only, never the legacy image path.
    Texts through t() without auto-creating keys (AdminCategoryTranslationSeeder).
--}}
@php
    $catAdmin = auth('admin')->user();
    $catCan = fn (string $ability): bool => (bool) ($catAdmin?->isSuperAdmin() || $catAdmin?->hasAbility($ability));
    $can = ['create' => $catCan('categories.create'), 'edit' => $catCan('categories.edit'), 'delete' => $catCan('categories.delete')];

    $catText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    // Whether a filter applies is decided by the controller (AUD-06: values it ignores do not count).
    $filtered ??= false;

    // A thumbnail only for an allowed image whose file is on its disk: never a broken link.
    $catThumb = function (\App\Models\Category $category): ?string {
        $media = $category->imageMedia;

        if (! $media || $media->file_type !== 'image' || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk($media->disk ?: 'public')->exists($media->file_path) ? $media->url : null;
        } catch (\Throwable) {
            return null;
        }
    };
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-cats" id="admin-categories">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $catText('Categories_Intro', 'Organise the categories shoppers browse in the store. A main category can hold subcategories.') }}</p>
            @if ($can['create'])
                <a href="{{ route('dashboard.categories.create') }}" class="btn btn-primary">
                    <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                    <span>{{ $catText('Category_Add', 'Add category') }}</span>
                </a>
            @endif
        </div>

        {{-- Result messages from the controllers (success, danger), inline until the admin toast exists (AD-UI-01E). --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}" data-cat-alert>
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                    <button type="button" class="admin-media-icon-button" data-cat-alert-close aria-label="{{ $catText('Categories_Dismiss', 'Dismiss') }}">
                        <i class="ph-duotone ph-x" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="categories-list-title">
            <h2 class="visually-hidden" id="categories-list-title">{{ $catText('Categories_List', 'Categories list') }}</h2>

            {{-- Filter bar (5.3): only what the controller supports; Reset clears it. --}}
            <form method="GET" action="{{ route('dashboard.categories.index') }}" class="admin-cats-filters" role="search" aria-label="{{ $catText('Categories_Filters', 'Filter categories') }}">
                <div class="admin-cats-field admin-cats-field-search">
                    <label class="form-label" for="categories-search">{{ $catText('Categories_Search_Label', 'Search categories') }}</label>
                    <div class="admin-media-search">
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="categories-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="{{ $catText('Categories_Search_Placeholder', 'Search by name') }}">
                    </div>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="categories-level">{{ $catText('Categories_Filter_Level', 'Type') }}</label>
                    <select class="form-select" id="categories-level" name="level">
                        <option value="">{{ $catText('Categories_Filter_Level_All', 'All types') }}</option>
                        <option value="root" @selected(request('level') === 'root')>{{ $catText('Category_Level_Root', 'Main category') }}</option>
                        <option value="child" @selected(request('level') === 'child')>{{ $catText('Category_Level_Child', 'Subcategory') }}</option>
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="categories-status">{{ $catText('Categories_Filter_Status', 'Status') }}</label>
                    <select class="form-select" id="categories-status" name="is_active">
                        <option value="">{{ $catText('Categories_Filter_Status_All', 'All statuses') }}</option>
                        <option value="1" @selected(request('is_active') === '1')>{{ $catText('Category_Status_Active', 'Active') }}</option>
                        <option value="0" @selected(request('is_active') === '0')>{{ $catText('Category_Status_Inactive', 'Inactive') }}</option>
                    </select>
                </div>
                <div class="admin-cats-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-duotone ph-funnel" aria-hidden="true"></i>
                        <span>{{ $catText('Categories_Apply', 'Apply') }}</span>
                    </button>
                    @if ($filtered)
                        <a href="{{ route('dashboard.categories.index') }}" class="btn btn-light-secondary">{{ $catText('Categories_Reset', 'Reset') }}</a>
                    @endif
                </div>
            </form>

            <div class="card-body">
                @if ($categories->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-squares-four" aria-hidden="true"></i>
                        @if ($categories->total() > 0)
                            {{-- AUD-03: a page past the last one (the results exist). The link keeps the search and filters. --}}
                            <span>{{ $catText('Categories_Page_Out_Of_Range', 'There are no categories on this page.') }}</span>
                            <a href="{{ $categories->url($categories->lastPage()) }}" class="btn btn-light-secondary">{{ $catText('Categories_Go_Last_Page', 'Go to the last page') }}</a>
                        @elseif ($filtered)
                            <span>{{ $catText('Categories_Empty_Filtered', 'No categories match these filters.') }}</span>
                        @else
                            <span>{{ $catText('Categories_Empty', 'No categories yet.') }}</span>
                            @if ($can['create'])
                                <span class="admin-media-hint">{{ $catText('Categories_Empty_Hint', 'Add the first category to start organising the store.') }}</span>
                            @endif
                        @endif
                    </div>
                @else
                    <ul class="admin-cats-list" aria-labelledby="categories-list-title">
                        @foreach ($categories as $category)
                            @php
                                $isChild = $category->parent_id !== null;
                                $thumb = $catThumb($category);
                                $blocked = $category->products_count > 0 ? 'products' : ($category->children_count > 0 ? 'children' : null);
                            @endphp
                            <li class="admin-cats-row {{ $isChild ? 'is-child' : 'is-root' }}" data-category-id="{{ $category->id }}">
                                <div class="admin-cats-thumb">
                                    @if ($thumb)
                                        <img src="{{ $thumb }}" alt="" loading="lazy" data-category-thumb>
                                        <i class="ph-duotone ph-image" aria-hidden="true" data-category-thumb-fallback hidden></i>
                                    @else
                                        <i class="ph-duotone ph-image" aria-hidden="true"></i>
                                    @endif
                                </div>

                                <div class="admin-cats-main">
                                    <p class="admin-cats-name">
                                        @if ($isChild)
                                            <i class="ph-duotone ph-arrow-elbow-down-left admin-cats-branch" aria-hidden="true"></i>
                                        @endif
                                        <bdi>{{ $category->name }}</bdi>
                                    </p>
                                    <p class="admin-cats-tags">
                                        <span class="admin-cats-badge {{ $isChild ? 'is-child' : 'is-root' }}">{{ $isChild ? $catText('Category_Level_Child', 'Subcategory') : $catText('Category_Level_Root', 'Main category') }}</span>
                                        @if ($isChild && $category->parent)
                                            <span class="admin-cats-parent">{{ $catText('Category_Under', 'Under') }} <bdi>{{ $category->parent->name }}</bdi></span>
                                        @endif
                                        @unless ($category->is_active)
                                            <span class="admin-cats-badge is-inactive">{{ $catText('Category_Status_Inactive', 'Inactive') }}</span>
                                        @endunless
                                        <span class="admin-cats-slug" dir="ltr">{{ $category->slug }}</span>
                                    </p>
                                </div>

                                <dl class="admin-cats-stats">
                                    <div>
                                        <dt>{{ $catText('Category_Products_Direct', 'Direct products') }}</dt>
                                        <dd>{{ (int) $category->products_count }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ $catText('Category_Children_Count', 'Subcategories') }}</dt>
                                        <dd>{{ (int) $category->children_count }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ $catText('Category_Sort_Order', 'Order') }}</dt>
                                        <dd>{{ (int) $category->sort_order }}</dd>
                                    </div>
                                </dl>

                                @if ($can['edit'] || $can['delete'])
                                    <div class="admin-cats-actions">
                                        @if ($can['edit'])
                                            <a href="{{ route('dashboard.categories.edit', $category) }}" class="btn btn-sm btn-light-secondary" aria-label="{{ str_replace(':name', $category->name, $catText('Category_Edit_Named', 'Edit :name')) }}">
                                                <i class="ph-duotone ph-pencil-simple" aria-hidden="true"></i>
                                                <span>{{ $catText('Category_Edit', 'Edit') }}</span>
                                            </a>
                                        @endif
                                        @if ($can['delete'])
                                            @if ($blocked)
                                                {{-- Not offered; the server refuses it anyway (DEC-CAT-Q3). --}}
                                                <p class="admin-cats-blocked">
                                                    <i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>
                                                    <span>{{ $blocked === 'products' ? $catText('Category_Delete_Blocked_Products', 'Cannot be deleted: it has products.') : $catText('Category_Delete_Blocked_Children', 'Cannot be deleted: it has subcategories.') }}</span>
                                                </p>
                                            @else
                                                {{-- N-CAT-1: without JavaScript this opens the confirmation page (GET, never deletes); the
                                                     script opens the dialog instead and sends the DELETE with this row's confirmation. --}}
                                                <form method="GET" action="{{ route('dashboard.categories.delete', $category) }}" data-category-delete data-name="{{ $category->name }}" data-destroy="{{ route('dashboard.categories.destroy', $category) }}" data-confirmation="{{ ($deleteConfirmations ?? [])[$category->id] ?? '' }}">
                                                    <button type="submit" class="btn btn-sm btn-light-danger" aria-label="{{ str_replace(':name', $category->name, $catText('Category_Delete_Named', 'Delete :name')) }}">
                                                        <i class="ph-duotone ph-trash" aria-hidden="true"></i>
                                                        <span>{{ $catText('Category_Delete', 'Delete') }}</span>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    {{ $categories->links('dashboard.partials.pagination') }}
                @endif
            </div>
        </section>

        @if ($can['delete'])
            {{-- Confirm dialog (5.11): Cancel is the default; Escape closes; focus returns to the delete button. --}}
            <dialog class="admin-media-dialog admin-cats-confirm" id="category-delete-dialog" aria-labelledby="category-delete-title" aria-describedby="category-delete-text">
                <div class="admin-media-dialog-header">
                    <h2 class="admin-media-dialog-title" id="category-delete-title">{{ $catText('Category_Delete_Confirm_Title', 'Delete this category?') }}</h2>
                </div>
                <div class="admin-cats-confirm-body">
                    <p id="category-delete-text" data-template="{{ $catText('Category_Delete_Confirm_Text', ':name will be deleted. This cannot be undone.') }}"></p>
                </div>
                {{-- The script sets the action and the confirmation of the chosen row; the server checks both. --}}
                <form method="POST" action="" class="admin-media-dialog-footer" data-category-delete-form>
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirmation" value="" data-category-delete-confirmation>
                    <button type="button" class="btn btn-light-secondary" data-category-delete-cancel autofocus>{{ $catText('Category_Delete_Cancel', 'Cancel') }}</button>
                    <button type="submit" class="btn btn-danger" data-category-delete-confirm>{{ $catText('Category_Delete_Confirm', 'Delete category') }}</button>
                </form>
            </dialog>
        @endif
    </div>

    @push('scripts')
        @vite('resources/js/admin-categories.js')
    @endpush
</x-dashboard-layout>
