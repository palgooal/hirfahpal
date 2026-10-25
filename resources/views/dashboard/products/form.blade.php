{{--
    AD-UI-PROD-M7-FIX-01: the admin product create / edit form in the admin shell (AD-UI-01), in four sections, on the
    pieces of the category form (admin-cat-form-*). The fields, their names and what the form submits are unchanged,
    including the M0-FIX stock basis (stock_quantity_original, stock_version). Each field has a linked label, its hint
    and its error (aria-invalid, aria-describedby); the summary links each error to its field. Suspension and archiving
    are only shown here (read only); they are managed on the product page. F-2: an inactive current category is kept
    and marked. Texts through t() without auto-creating keys (AdminProductTranslationSeeder); the field names come
    from the controller, which uses them in the validation messages too.
--}}
@php
    $prodText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $editing = $product->exists;
    $formTitle = $editing ? $prodText('Products_Form_Edit_Title', 'Edit product') : $prodText('Products_Form_Create_Title', 'Add product');

    $fieldIds = [
        'name' => 'product-name', 'slug' => 'product-slug', 'vendor_id' => 'product-vendor', 'category_id' => 'product-category',
        'sku' => 'product-sku', 'price' => 'product-price', 'compare_at_price' => 'product-compare-price', 'stock_quantity' => 'product-stock',
        'low_stock_threshold' => 'product-low-stock', 'weight' => 'product-weight', 'weight_unit' => 'product-weight-unit', 'short_description' => 'product-short-description',
        'description' => 'product-description', 'images' => 'product-images', 'status' => 'product-status', 'is_featured' => 'product-featured',
    ];
    // aria-describedby: the hint, then the error when there is one.
    $describedBy = fn (string $field, ?string $hint = null) => trim(($hint ? $hint.' ' : '').($errors->has($field) ? $fieldIds[$field].'-error' : '')) ?: null;

    // Cancel and back: the product page when editing, the list when creating; the dashboard home for an admin who
    // may not open that page (as in the category form, AUD-04).
    $prodAdmin = auth('admin')->user();
    $canViewProducts = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.view'));
    $backUrl = ! $canViewProducts ? route('dashboard.home') : ($editing ? route('dashboard.products.show', $product) : route('dashboard.products.index'));
    $backLabel = $editing ? $prodText('Products_Form_Back', 'Back to the product') : $prodText('Products_Show_Back', 'Back to products');

    $requiredMark = $prodText('Products_Form_Required', 'Required');
    $optionalMark = $prodText('Products_Form_Optional', 'Optional');
    $selectedCategory = (string) old('category_id', $product->category_id);
    $selectedStatus = (string) old('status', $product->status);
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $formTitle }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-cat-form admin-product-form" id="admin-product-form">
        <div class="admin-media-head admin-product-form-head">
            @if ($canViewProducts)
                <a href="{{ $backUrl }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $backLabel }}</span>
                </a>
            @endif
            @if ($editing)
                <h2 class="admin-product-title"><bdi>{{ $product->name }}</bdi></h2>
            @endif
            <p class="admin-media-intro">
                {{ $editing
                    ? $prodText('Products_Form_Edit_Intro', 'Update the product details. Suspension and archiving are managed from the product page.')
                    : $prodText('Products_Form_Create_Intro', 'Add a product to a vendor\'s store. It is on sale only when its status is Active.') }}
            </p>
        </div>

        @if ($editing && ($product->isSuspended() || $product->isArchived()))
            {{-- Read only: saving this form never changes the suspension or the archive state. --}}
            <div class="admin-cat-form-note admin-product-form-note" role="note">
                <i class="ph-duotone ph-info" aria-hidden="true"></i>
                <div>
                    @if ($product->isSuspended())
                        <p>{{ $prodText('Products_Form_Suspended_Note', 'This product is suspended by the administration and cannot be sold. Saving this form does not lift the suspension.') }}</p>
                    @endif
                    @if ($product->isArchived())
                        <p>{{ $prodText('Products_Form_Archived_Note', 'This product is archived and cannot be sold. Saving this form does not restore it.') }}</p>
                    @endif
                    @if ($canViewProducts)
                        <p><a href="{{ route('dashboard.products.show', $product) }}#product-panel-moderation">{{ $prodText('Products_Form_Moderation_Link', 'Open the moderation tab') }}</a></p>
                    @endif
                </div>
            </div>
        @endif

        @if ($errors->any())
            {{-- Error summary: translated field names, each linked to its field. --}}
            <div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="product-form-errors">
                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                <div class="admin-media-alert-content">
                    <p>{{ $prodText('Products_Form_Errors', 'Please correct the following:') }}</p>
                    <ul>
                        @foreach ($errors->getMessages() as $field => $messages)
                            <li><a href="#{{ $fieldIds[$field] ?? 'product-name' }}">{{ $fieldLabels[$field] ?? $field }}</a>: {{ $messages[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('dashboard.products.update', $product) : route('dashboard.products.store') }}" enctype="multipart/form-data" class="card admin-media-panel admin-cat-form-card" novalidate aria-label="{{ $formTitle }}">
            @csrf
            @if ($editing)
                @method('PUT')
                {{-- M0-FIX: the stock this form displays and its version; the stock is written only when the admin changed it, and only if neither moved since. --}}
                <input type="hidden" name="stock_quantity_original" value="{{ old('stock_quantity_original', $product->stock_quantity) }}">
                <input type="hidden" name="stock_version" value="{{ old('stock_version', \App\Http\Controllers\Dashboard\ProductManagementController::stockVersion($product)) }}">
            @endif

            {{-- 1. Basic details --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $prodText('Products_Form_Section_Basics', 'Basic details') }}</legend>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="product-name">{{ $fieldLabels['name'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                    <input type="text" @class(['form-control', 'is-invalid' => $errors->has('name')]) id="product-name" name="name" value="{{ old('name', $product->name) }}" maxlength="255" required aria-required="true" dir="auto" autocomplete="off"
                        @error('name') aria-invalid="true" @enderror @if ($d = $describedBy('name')) aria-describedby="{{ $d }}" @endif>
                    @error('name') <p class="admin-cat-form-error" id="product-name-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-vendor">{{ $fieldLabels['vendor_id'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('vendor_id')]) id="product-vendor" name="vendor_id" required aria-required="true"
                            @error('vendor_id') aria-invalid="true" @enderror @if ($d = $describedBy('vendor_id')) aria-describedby="{{ $d }}" @endif>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected((string) old('vendor_id', $product->vendor_id) === (string) $vendor->id)>{{ $vendor->profile?->store_name ?? $vendor->name }}</option>
                            @endforeach
                        </select>
                        @error('vendor_id') <p class="admin-cat-form-error" id="product-vendor-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-category">{{ $fieldLabels['category_id'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('category_id')]) id="product-category" name="category_id"
                            @error('category_id') aria-invalid="true" @enderror @if ($d = $describedBy('category_id', $inactiveCategoryId ? 'product-category-hint' : null)) aria-describedby="{{ $d }}" @endif>
                            <option value="">{{ $prodText('Products_Form_Category_None', 'No category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($selectedCategory === (string) $category->id)>{{ $category->id === $inactiveCategoryId ? str_replace(':name', $category->name, $prodText('Products_Form_Category_Inactive', ':name (inactive)')) : $category->name }}</option>
                            @endforeach
                        </select>
                        @if ($inactiveCategoryId)
                            <p class="admin-media-hint" id="product-category-hint">{{ $prodText('Products_Form_Category_Inactive_Hint', 'The current category is inactive. It is kept when you save; choose another one to move the product.') }}</p>
                        @endif
                        @error('category_id') <p class="admin-cat-form-error" id="product-category-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-slug">{{ $fieldLabels['slug'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="text" @class(['form-control', 'is-invalid' => $errors->has('slug')]) id="product-slug" name="slug" value="{{ old('slug', $product->slug) }}" maxlength="255" dir="ltr" autocomplete="off" spellcheck="false"
                            @error('slug') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('slug', 'product-slug-hint') }}">
                        <p class="admin-media-hint" id="product-slug-hint">{{ $prodText('Products_Form_Slug_Hint', 'Used in the store link. Leave it empty to create it from the name. Spaces and these characters are not allowed: / ? # %') }}</p>
                        @error('slug') <p class="admin-cat-form-error" id="product-slug-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-sku">{{ $fieldLabels['sku'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="text" @class(['form-control', 'is-invalid' => $errors->has('sku')]) id="product-sku" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="255" dir="ltr" autocomplete="off" spellcheck="false"
                            @error('sku') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('sku', 'product-sku-hint') }}">
                        <p class="admin-media-hint" id="product-sku-hint">{{ $prodText('Products_Form_Sku_Hint', 'The product code. It must not be used by another product.') }}</p>
                        @error('sku') <p class="admin-cat-form-error" id="product-sku-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            {{-- 2. Price and stock --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $prodText('Products_Form_Section_Pricing', 'Price and stock') }}</legend>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-price">{{ $fieldLabels['price'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('price')]) id="product-price" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" inputmode="decimal" dir="ltr" required aria-required="true"
                            @error('price') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('price', 'product-price-hint') }}">
                        <p class="admin-media-hint" id="product-price-hint">{{ $prodText('Products_Form_Price_Hint', 'Regular price, in the store currency, with up to two decimal places.') }}</p>
                        @error('price') <p class="admin-cat-form-error" id="product-price-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-compare-price">{{ $fieldLabels['compare_at_price'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('compare_at_price')]) id="product-compare-price" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" step="0.01" min="0" inputmode="decimal" dir="ltr"
                            @error('compare_at_price') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('compare_at_price', 'product-compare-price-hint') }}">
                        <p class="admin-media-hint" id="product-compare-price-hint">{{ $prodText('Products_Form_Compare_Price_Hint', 'Optional sale price. It must be lower than the regular price; the regular price is shown crossed out in the store.') }}</p>
                        @error('compare_at_price') <p class="admin-cat-form-error" id="product-compare-price-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-stock">{{ $fieldLabels['stock_quantity'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('stock_quantity')]) id="product-stock" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" step="1" min="0" inputmode="numeric" dir="ltr" required aria-required="true"
                            @error('stock_quantity') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('stock_quantity', 'product-stock-hint') }}">
                        <p class="admin-media-hint" id="product-stock-hint">{{ $prodText('Products_Form_Stock_Hint', 'The units in stock, including those reserved for open orders. It cannot be lower than the reserved units.') }}</p>
                        @error('stock_quantity') <p class="admin-cat-form-error" id="product-stock-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="admin-cat-form-field">
                        <label class="form-label" for="product-low-stock">{{ $fieldLabels['low_stock_threshold'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('low_stock_threshold')]) id="product-low-stock" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 1) }}" step="1" min="0" inputmode="numeric" dir="ltr"
                            @error('low_stock_threshold') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('low_stock_threshold', 'product-low-stock-hint') }}">
                        <p class="admin-media-hint" id="product-low-stock-hint">{{ $prodText('Products_Form_Low_Stock_Hint', 'The product is marked low in stock when its available units reach this number. 1 by default.') }}</p>
                        @error('low_stock_threshold') <p class="admin-cat-form-error" id="product-low-stock-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            {{-- 3. Description and details --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $prodText('Products_Form_Section_Details', 'Description and details') }}</legend>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="product-short-description">{{ $fieldLabels['short_description'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <textarea @class(['form-control', 'is-invalid' => $errors->has('short_description')]) id="product-short-description" name="short_description" rows="2" maxlength="1000" dir="auto"
                        @error('short_description') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('short_description', 'product-short-description-hint') }}">{{ old('short_description', $product->short_description) }}</textarea>
                    <p class="admin-media-hint" id="product-short-description-hint">{{ $prodText('Products_Form_Short_Description_Hint', 'Up to 1000 characters.') }}</p>
                    @error('short_description') <p class="admin-cat-form-error" id="product-short-description-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="product-description">{{ $fieldLabels['description'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <textarea @class(['form-control', 'is-invalid' => $errors->has('description')]) id="product-description" name="description" rows="6" dir="auto"
                        @error('description') aria-invalid="true" @enderror @if ($d = $describedBy('description')) aria-describedby="{{ $d }}" @endif>{{ old('description', $product->description) }}</textarea>
                    @error('description') <p class="admin-cat-form-error" id="product-description-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field admin-cat-form-field-narrow admin-product-form-narrow">
                    <label class="form-label" for="product-weight">{{ $fieldLabels['weight'] }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <div class="input-group">
                        <input type="number" @class(['form-control', 'is-invalid' => $errors->has('weight')]) id="product-weight" name="weight" value="{{ old('weight', $product->weight) }}" step="0.01" min="0" inputmode="decimal" dir="ltr"
                            @error('weight') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('weight', 'product-weight-hint') }}">
                        <label class="visually-hidden" for="product-weight-unit">{{ $prodText('Products_Form_Weight_Unit', 'Weight unit') }}</label>
                        <select @class(['form-select', 'is-invalid' => $errors->has('weight_unit')]) id="product-weight-unit" name="weight_unit" style="max-width: 8rem" dir="ltr">
                            <option value="" @selected(old('weight_unit', $product->weight_unit) === null)>{{ $prodText('Products_Form_Weight_Unit_Select', 'Unit') }}</option>
                            @foreach (['kg' => ['Products_Form_Weight_Unit_KG', 'kg'], 'g' => ['Products_Form_Weight_Unit_G', 'g'], 't' => ['Products_Form_Weight_Unit_T', 'ton']] as $value => [$unitKey, $label])
                                <option value="{{ $value }}" @selected(old('weight_unit', $product->weight_unit) === $value)>{{ $prodText($unitKey, $label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="admin-media-hint" id="product-weight-hint">{{ $prodText('Products_Form_Weight_Hint', 'For shipping, enter the weight and choose kg, g or ton.') }}</p>
                    @error('weight') <p class="admin-cat-form-error" id="product-weight-error">{{ $message }}</p> @enderror
                    @error('weight_unit') <p class="admin-cat-form-error" id="product-weight-unit-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-cat-form-field">
                    <label class="form-label" for="product-images">{{ $prodText('Products_Form_Images', 'Product images') }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></label>
                    <input type="file" @class(['form-control', 'is-invalid' => $errors->has('images') || $errors->has('images.*')]) id="product-images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple
                        @error('images') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('images', 'product-images-hint') }}">
                    <p class="admin-media-hint" id="product-images-hint">{{ $prodText('Products_Form_Images_Hint', 'Choose up to 6 JPG, PNG, GIF or WebP images (5 MB each). The first uploaded image becomes the main image. You can review all images from the product page.') }}</p>
                    @error('images') <p class="admin-cat-form-error" id="product-images-error">{{ $message }}</p> @enderror
                    @error('images.*') <p class="admin-cat-form-error" id="product-images-error">{{ $message }}</p> @enderror
                    @if ($editing && $product->images()->exists() && $canViewProducts)
                        <p class="admin-media-hint"><a href="{{ route('dashboard.products.show', $product) }}#product-panel-images">{{ $prodText('Products_Form_Manage_Images', 'View current images') }}</a></p>
                    @endif
                </div>
            </fieldset>

            {{-- 4. Status and settings --}}
            <fieldset class="admin-cat-form-section">
                <legend class="admin-cat-form-legend">{{ $prodText('Products_Form_Section_Status', 'Status and settings') }}</legend>

                <div class="admin-cat-form-field admin-product-form-narrow">
                    <label class="form-label" for="product-status">{{ $fieldLabels['status'] }} <span class="admin-cat-form-mark">({{ $requiredMark }})</span></label>
                    <select @class(['form-select', 'is-invalid' => $errors->has('status')]) id="product-status" name="status" required aria-required="true"
                        @error('status') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('status', 'product-status-hint') }}">
                        @foreach ($statusKeys as $key => $label)
                            <option value="{{ $key }}" @selected($selectedStatus === $key)>{{ $prodText(...$label) }}</option>
                        @endforeach
                    </select>
                    <p class="admin-media-hint" id="product-status-hint">{{ $prodText('Products_Form_Status_Hint', 'Only an active product can be on sale. A suspended or archived product stays off sale whatever its status.') }}</p>
                    @error('status') <p class="admin-cat-form-error" id="product-status-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-product-form-check">
                    <input type="hidden" name="is_featured" value="0">
                    <input type="checkbox" @class(['form-check-input', 'is-invalid' => $errors->has('is_featured')]) id="product-featured" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))
                        @error('is_featured') aria-invalid="true" @enderror aria-describedby="{{ $describedBy('is_featured', 'product-featured-hint') }}">
                    <div>
                        <label class="form-label" for="product-featured">{{ $fieldLabels['is_featured'] }}</label>
                        <p class="admin-media-hint" id="product-featured-hint">{{ $prodText('Products_Form_Featured_Hint', 'Featured products can be highlighted in the store.') }}</p>
                        @error('is_featured') <p class="admin-cat-form-error" id="product-featured-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <div class="admin-cat-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                    <span>{{ $prodText('Products_Form_Save', 'Save product') }}</span>
                </button>
                <a href="{{ $backUrl }}" class="btn btn-light-secondary">{{ $prodText('Products_Form_Cancel', 'Cancel') }}</a>
            </div>
        </form>
    </div>
</x-dashboard-layout>
