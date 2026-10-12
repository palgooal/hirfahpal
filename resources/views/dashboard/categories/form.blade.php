{{--
    AD-UI-CAT-01E-3B: the category create / edit form in the admin shell (AD-UI-01). The page title is its own
    ("Add category" / "Edit category") and lives in the header only. The server keeps every rule:
    two levels (DEC-CAT-Q13), no activation control (DEC-CAT-Q16: new categories are active, edits keep the
    state), the image from the media library (M4: image_media_id / remove_image through the media picker),
    categories.image is never written. Texts through t() without auto-creating keys (AdminCategoryTranslationSeeder).
--}}
@php
    $catText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $editing = $category->exists;
    $formTitle = $editing ? $catText('Edit_Category', 'Edit Category') : $catText('Category_Add', 'Add category');
    // A main category with subcategories stays a main category (DEC-CAT-Q13); the controller offers it no parent.
    $parentLocked = $editing && $category->children()->exists();
    $selectedParent = old('parent_id', $category->parent_id);

    // Field => label, for the fields and for the error summary.
    $fieldLabels = [
        'name' => $catText('Category_Field_Name', 'Name'),
        'slug' => $catText('Category_Field_Slug', 'Slug'),
        'parent_id' => $catText('Category_Field_Parent', 'Parent category'),
        'sort_order' => $catText('Category_Field_Order', 'Display order'),
        'description' => $catText('Category_Field_Description', 'Description'),
        'image_media_id' => $catText('Category_Image', 'Image'),
        'remove_image' => $catText('Category_Image', 'Image'),
    ];
    $fieldIds = ['name' => 'category-name', 'slug' => 'category-slug', 'parent_id' => 'category-parent', 'sort_order' => 'category-order', 'description' => 'category-description', 'image_media_id' => 'media-picker-image-media-id', 'remove_image' => 'media-picker-image-media-id'];

    // aria-describedby: the hint, then the error when there is one.
    $describedBy = fn (string $field, ?string $hint = null) => trim(($hint ? $hint.' ' : '').($errors->has($field) ? $fieldIds[$field].'-error' : '')) ?: null;
    // AUD-04 (option A: independent abilities): Cancel leads to the list only for admins who may open it;
    // otherwise to the dashboard home, which any signed-in admin may open (routes/web.php, dashboard.home).
    $catAdmin = auth('admin')->user();
    $canViewList = (bool) ($catAdmin?->isSuperAdmin() || $catAdmin?->hasAbility('categories.view'));
    $cancelUrl = $canViewList ? route('dashboard.categories.index') : route('dashboard.home');

    $requiredMark = $catText('Category_Required', 'Required');
    $optionalMark = $catText('Category_Optional', 'Optional');
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $formTitle }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-cat-form" id="admin-category-form">
        <div class="admin-media-head">
            <p class="admin-media-intro">
                {{ $editing
                    ? $catText('Category_Form_Edit_Intro', 'Update the category details. Its active state stays as it is.')
                    : $catText('Category_Form_Create_Intro', 'Add a category shoppers can browse in the store. New categories are active.') }}
            </p>
        </div>

        @if (session('success'))
            {{-- AUD-04: after a save, admins without categories.view come back to a form; the result is shown here. --}}
            <div class="admin-media-alert is-success" role="status" data-cat-alert>
                <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                <div class="admin-media-alert-content"><p>{{ session('success') }}</p></div>
                <button type="button" class="admin-media-icon-button" data-cat-alert-close aria-label="{{ $catText('Categories_Dismiss', 'Dismiss') }}">
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($editing && ! $category->is_active)
            <p class="admin-cat-form-note" role="note">
                <i class="ph-duotone ph-info" aria-hidden="true"></i>
                <span>{{ $catText('Category_Form_Inactive_Note', 'This category is inactive. Saving does not change that.') }}</span>
            </p>
        @endif

        @if ($errors->any())
            {{-- Error summary: translated field names, each linked to its field (DEC-AD-UI-12). --}}
            <div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="category-form-errors">
                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                <div class="admin-media-alert-content">
                    <p>{{ $catText('Category_Form_Errors', 'Please correct the following:') }}</p>
                    <ul>
                        @foreach ($errors->getMessages() as $field => $messages)
                            <li><a href="#{{ $fieldIds[$field] ?? 'category-name' }}">{{ $fieldLabels[$field] ?? $field }}</a>: {{ $messages[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('dashboard.categories.update', $category) : route('dashboard.categories.store') }}" class="card admin-media-panel admin-cat-form-card" novalidate data-category-form aria-label="{{ $formTitle }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            {{-- Basic details --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $catText('Category_Form_Section_Basics', 'Basic details') }}</legend>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="category-name">
                        {{ $fieldLabels['name'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span>
                    </label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('name')]) id="category-name" name="name" value="{{ old('name', $category->name) }}" maxlength="255" required aria-required="true" dir="auto" autocomplete="off"
                        @error('name') aria-invalid="true" @enderror @if ($d = $describedBy('name')) aria-describedby="{{ $d }}" @endif>
                    @error('name') <p class="admin-cat-form-error" id="category-name-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="category-slug">
                        {{ $fieldLabels['slug'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span>
                    </label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('slug')]) id="category-slug" name="slug" value="{{ old('slug', $category->slug) }}" maxlength="255" dir="ltr" autocomplete="off" spellcheck="false"
                        @error('slug') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('slug', 'category-slug-hint') }}">
                    <p class="admin-media-hint" id="category-slug-hint">{{ $catText('Category_Field_Slug_Hint', 'Used in the store link. Leave it empty to create it from the name.') }}</p>
                    @error('slug') <p class="admin-cat-form-error" id="category-slug-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="category-description">
                        {{ $fieldLabels['description'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span>
                    </label>
                    <textarea @class(['form-control', 'is-invalid' => $errors->has('description')]) id="category-description" name="description" rows="4" dir="auto"
                        @error('description') aria-invalid="true" @enderror @if ($d = $describedBy('description')) aria-describedby="{{ $d }}" @endif>{{ old('description', $category->description) }}</textarea>
                    @error('description') <p class="admin-cat-form-error" id="category-description-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            {{-- Placement --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $catText('Category_Form_Section_Placement', 'Placement') }}</legend>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="category-parent">
                            {{ $fieldLabels['parent_id'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span>
                        </label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('parent_id')]) id="category-parent" name="parent_id"
                            @error('parent_id') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('parent_id', 'category-parent-hint') }}">
                            <option value="">{{ $catText('Category_Field_Parent_None', 'None (main category)') }}</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected((string) $selectedParent === (string) $parent->id)>{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p class="admin-media-hint" id="category-parent-hint">
                            {{ $parentLocked
                                ? $catText('Category_Field_Parent_Locked', 'This category has subcategories, so it stays a main category.')
                                : $catText('Category_Field_Parent_Hint', 'Choose a main category to make this a subcategory. Only main categories can be parents.') }}
                        </p>
                        @error('parent_id') <p class="admin-cat-form-error" id="category-parent-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field admin-cat-form-field-narrow">
                        <label class="form-label" for="category-order">
                            {{ $fieldLabels['sort_order'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span>
                        </label>
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('sort_order')]) id="category-order" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" step="1" inputmode="numeric" dir="ltr"
                            @error('sort_order') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('sort_order', 'category-order-hint') }}">
                        <p class="admin-media-hint" id="category-order-hint">{{ $catText('Category_Field_Order_Hint', 'Lower numbers appear first. 0 by default.') }}</p>
                        @error('sort_order') <p class="admin-cat-form-error" id="category-order-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            {{-- Image: the media picker from M4, unchanged. --}}
            <div class="admin-cat-form-section">
                <x-admin.media-picker :value="$category->image_media_id" :can-remove="$editing" />
            </div>

            {{-- DEC-CAT-Q16: no activation control; new categories are active, edits keep the state. --}}

            <div class="admin-cat-form-actions">
                <button type="submit" class="btn btn-primary" data-category-submit data-saving="{{ $catText('Category_Saving', 'Saving…') }}">
                    <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                    <span>{{ $catText('Category_Save', 'Save category') }}</span>
                </button>
                <a href="{{ $cancelUrl }}" class="btn btn-light-secondary">{{ $catText('Category_Cancel', 'Cancel') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        @vite('resources/js/admin-categories.js')
    @endpush
</x-dashboard-layout>
