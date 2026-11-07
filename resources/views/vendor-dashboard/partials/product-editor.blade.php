{{--
    VPM-UX-01A: the vendor product editor, moved from the shared vendor-dashboard.resources view when the products page
    got its own view (vendor-dashboard.products). Expects $endpoint (the products JSON endpoint).

    VPM-UX-01E: the approved prototype's layout (ask/vpm-ux-01e/product-editor.html) in My Store's card and field
    language (vendor-form-card, vendor-field, vendor-input, the sticky vendor-form-actions bar). The form's two direct
    fieldsets are its columns: main (basic information, pricing and inventory, shipping, craft) and side (images,
    publication status); the script disables both for an archived product (read-only), as it did the former sections.
    One column below a content width of 880px (main first, as My Store), two from it. Element ids, field names and the
    script's data hooks are unchanged.
--}}
<div class="col-span-12 admin-media admin-cat-form admin-product-form vendor-product-form" data-product-editor data-endpoint="{{ $endpoint }}" data-media-library-url="{{ route('vendor.dashboard.products.media.library') }}" data-media-store-url="{{ route('vendor.dashboard.products.media.store') }}" data-library-prefix="{{ \App\Support\Media\VendorMediaPath::prefix((int) auth('vendor')->id()) }}" hidden>
    {{-- VPM-UX-01E-POLISH-01: while the editor is open its title is the page heading (the script hides the layout's
         "Products" h1; the breadcrumb keeps it). Back sits on the title's row; the Edit subtitle (stored name, status,
         moderation, filled by the script) runs below both. --}}
    <div class="vendor-product-editor-head">
        <h1 class="vendor-product-editor-title" data-product-editor-title>{{ t('vendor.Product_Heading_New', 'Add Product') }}</h1>
        {{-- The arrow points back along the reading direction (as the order details page chooses it). --}}
        <button type="button" class="vendor-product-back" data-product-editor-close><i class="ph-duotone {{ ($currentLanguage ?? null)?->is_rtl ? 'ph-arrow-right' : 'ph-arrow-left' }}" aria-hidden="true"></i><span>{{ t('vendor.Product_Back_To_List', 'Back to products') }}</span></button>
        <p class="vendor-product-editor-subtitle" data-product-editor-subtitle hidden><bdi data-product-editor-name></bdi><span class="vendor-status-badge" data-product-editor-status></span><span class="vendor-moderation-badge" data-product-editor-moderation hidden></span></p>
    </div>

    <form class="vendor-product-editor-form" data-product-form novalidate>
        @csrf
        {{-- VENDOR-PRODUCT-PUBLISH-UI-01D (DETAIL-02, DETAIL-03): the administration's state, from the product's
             moderation flags (shown by the script; archived wins). data-refused is the text announced when a
             change is refused because the product was archived while it was open. --}}
        <div class="vendor-product-notice is-archived" role="note" tabindex="-1" data-product-notice="archived" data-refused="{{ t('vendor.Product_Archived_Refused', 'This product was archived and can no longer be edited.') }}" hidden><span class="vendor-product-notice-icon" aria-hidden="true"><i class="ph-duotone ph-archive" aria-hidden="true"></i></span><div><p class="vendor-product-notice-title">{{ t('vendor.Product_Notice_Archived_Title', 'Archived product') }}</p><p class="vendor-product-notice-text">{{ t('vendor.Product_Notice_Archived', 'This product is archived by the administration and cannot be edited. Contact the administration to restore it.') }}</p></div></div>
        <div class="vendor-product-notice is-suspended" role="note" data-product-notice="suspended" hidden><span class="vendor-product-notice-icon" aria-hidden="true"><i class="ph-duotone ph-pause-circle" aria-hidden="true"></i></span><div><p class="vendor-product-notice-title">{{ t('vendor.Product_Notice_Suspended_Title', 'Suspended by the administration') }}</p><p class="vendor-product-notice-text">{{ t('vendor.Product_Notice_Suspended', 'This product is suspended by the administration. It is not shown in the store or available for sale until the suspension is lifted, whatever its publication status. You can still edit it.') }}</p></div></div>

        <p class="vendor-required-legend" data-product-required-legend>{{ t('vendor.My_Store_Required_Legend', 'Fields marked * are required.') }}</p>

        <fieldset class="vendor-product-column is-main">
            <section class="card vendor-form-card" data-product-section="basic" aria-labelledby="vendor-product-basic-title">
                <div class="card-body">
                    <h2 class="vendor-section-title" id="vendor-product-basic-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-tag"></i></span><span>{{ t('vendor.Product_Section_Basic', 'Basic information') }}</span></h2>
                    <div class="vendor-product-fields">
                        <div class="vendor-field is-wide">
                            <label class="vendor-label" for="vendor-product-name">{{ t('vendor.Product_Name', 'Name') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                            <input id="vendor-product-name" class="vendor-input" name="name" required maxlength="255" dir="auto" aria-describedby="vendor-product-name-error">
                            <p class="vendor-field-error" id="vendor-product-name-error" data-error-for="name" hidden></p>
                        </div>
                        <div class="vendor-field">
                            {{-- VPM-CATEGORY-REQUIRED-01: required; the prompt is never a choice (disabled), so a product stored
                                 without a category shows it and must be given one before it can be saved.
                                 VPM-CATEGORY-RELIABILITY-01: disabled until the script has loaded the categories; a failed
                                 load shows its message and Retry (data-product-category-failed). --}}
                            <label class="vendor-label" for="vendor-product-category">{{ t('vendor.Product_Category', 'Category') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                            <select id="vendor-product-category" class="vendor-input" name="category_id" required disabled aria-busy="true" aria-describedby="vendor-product-category-error"><option value="" disabled selected>{{ t('vendor.Product_Category_Loading', 'Loading categories…') }}</option></select>
                            <div class="vendor-product-category-failed" data-product-category-failed hidden>
                                <p class="vendor-field-error" role="alert">{{ t('vendor.Product_Category_Load_Failed', 'The categories could not be loaded.') }}</p>
                                <button type="button" class="vendor-product-category-retry" data-product-category-retry><i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i><span>{{ t('vendor.Dashboard_Retry', 'Retry') }}</span></button>
                            </div>
                            <p class="vendor-field-error" id="vendor-product-category-error" data-error-for="category_id" hidden></p>
                        </div>
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-sku">{{ t('vendor.Product_Sku', 'SKU') }}</label>
                            <input id="vendor-product-sku" class="vendor-input" name="sku" maxlength="255" dir="ltr" autocomplete="off" aria-describedby="vendor-product-sku-error">
                            <p class="vendor-field-error" id="vendor-product-sku-error" data-error-for="sku" hidden></p>
                        </div>
                        <div class="vendor-field is-wide">
                            <div class="vendor-label-row">
                                <label class="vendor-label" for="vendor-product-short-description">{{ t('vendor.Product_Short_Description', 'Short description') }}</label>
                                <span class="vendor-counter" data-product-counter="short_description" aria-hidden="true"><bdi>0 / 1000</bdi></span>
                            </div>
                            <textarea id="vendor-product-short-description" class="vendor-input vendor-product-textarea-sm" name="short_description" rows="2" maxlength="1000" dir="auto" aria-describedby="vendor-product-short-description-error"></textarea>
                            <p class="vendor-field-error" id="vendor-product-short-description-error" data-error-for="short_description" hidden></p>
                        </div>
                        <div class="vendor-field is-wide">
                            <label class="vendor-label" for="vendor-product-description">{{ t('vendor.Product_Description', 'Description') }}</label>
                            <textarea id="vendor-product-description" class="vendor-input vendor-textarea" name="description" rows="5" dir="auto" aria-describedby="vendor-product-description-error"></textarea>
                            <p class="vendor-field-error" id="vendor-product-description-error" data-error-for="description" hidden></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card vendor-form-card" data-product-section="pricing" aria-labelledby="vendor-product-pricing-title">
                <div class="card-body">
                    <h2 class="vendor-section-title" id="vendor-product-pricing-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-coins"></i></span><span>{{ t('vendor.Product_Section_Inventory', 'Pricing and inventory') }}</span></h2>
                    <div class="vendor-product-fields">
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-price">{{ t('vendor.Product_Price', 'Regular price') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                            <input id="vendor-product-price" class="vendor-input" name="price" type="number" min="0.01" step="0.01" inputmode="decimal" dir="ltr" required aria-describedby="vendor-product-price-error">
                            <p class="vendor-field-error" id="vendor-product-price-error" data-error-for="price" hidden></p>
                        </div>
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-compare-price">{{ t('vendor.Product_Compare_Price', 'Sale price (optional)') }}</label>
                            <input id="vendor-product-compare-price" class="vendor-input" name="compare_at_price" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" aria-describedby="vendor-product-compare-price-hint vendor-product-compare-price-error">
                            <p class="vendor-field-hint" id="vendor-product-compare-price-hint">{{ t('vendor.Product_Compare_Price_Hint', 'Must be lower than the regular price.') }}</p>
                            <p class="vendor-field-error" id="vendor-product-compare-price-error" data-error-for="compare_at_price" hidden></p>
                        </div>
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-stock">{{ t('vendor.Product_Stock', 'Stock') }} <span class="vendor-required" aria-hidden="true">*</span></label>
                            <input id="vendor-product-stock" class="vendor-input" name="stock_quantity" type="number" min="0" step="1" inputmode="numeric" dir="ltr" required aria-describedby="vendor-product-stock-error">
                            <p class="vendor-field-error" id="vendor-product-stock-error" data-error-for="stock_quantity" hidden></p>
                        </div>
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-low-stock">{{ t('vendor.Product_Low_Stock', 'Low stock threshold') }}</label>
                            <input id="vendor-product-low-stock" class="vendor-input" name="low_stock_threshold" type="number" min="0" step="1" inputmode="numeric" dir="ltr" value="1" aria-describedby="vendor-product-low-stock-error">
                            <p class="vendor-field-error" id="vendor-product-low-stock-error" data-error-for="low_stock_threshold" hidden></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card vendor-form-card" data-product-section="shipping" aria-labelledby="vendor-product-shipping-title">
                <div class="card-body">
                    <h2 class="vendor-section-title" id="vendor-product-shipping-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-ruler"></i></span><span>{{ t('vendor.Product_Section_Shipping', 'Shipping and dimensions') }}</span></h2>
                    <div class="vendor-product-fields">
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-weight">{{ t('vendor.Product_Weight', 'Weight') }}</label>
                            {{-- The stored unit values (kg, g, t) are unchanged; only their labels follow the page language. --}}
                            <div class="vendor-product-input-group">
                                <input id="vendor-product-weight" class="vendor-input" name="weight" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" aria-describedby="vendor-product-weight-error">
                                <select class="vendor-input" name="weight_unit" aria-label="{{ t('vendor.Product_Weight_Unit', 'Weight unit') }}" aria-describedby="vendor-product-weight-unit-error"><option value="">{{ t('vendor.Product_Weight_Unit_Select', 'Unit') }}</option><option value="kg">{{ t('vendor.Product_Weight_Unit_Kg', 'kg') }}</option><option value="g">{{ t('vendor.Product_Weight_Unit_G', 'g') }}</option><option value="t">{{ t('vendor.Product_Weight_Unit_T', 'ton') }}</option></select>
                            </div>
                            <p class="vendor-field-error" id="vendor-product-weight-error" data-error-for="weight" hidden></p>
                            <p class="vendor-field-error" id="vendor-product-weight-unit-error" data-error-for="weight_unit" hidden></p>
                        </div>
                        <fieldset class="vendor-product-subgroup is-wide">
                            <legend class="vendor-label">{{ t('vendor.Product_Dimensions', 'Dimensions (cm)') }}</legend>
                            <div class="vendor-product-dimensions">
                                @foreach (['length' => ['vendor.Product_Length', 'Length'], 'width' => ['vendor.Product_Width', 'Width'], 'height' => ['vendor.Product_Height', 'Height']] as $dimension => [$key, $default])
                                    <div class="vendor-field">
                                        <label class="vendor-product-dimension-label" for="vendor-product-{{ $dimension }}">{{ t($key, $default) }}</label>
                                        <div class="vendor-product-suffix">
                                            <input id="vendor-product-{{ $dimension }}" class="vendor-input" name="dimensions[{{ $dimension }}]" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" aria-describedby="vendor-product-{{ $dimension }}-error">
                                            <span aria-hidden="true">{{ t('vendor.Product_Unit_Cm', 'cm') }}</span>
                                        </div>
                                        <p class="vendor-field-error" id="vendor-product-{{ $dimension }}-error" data-error-for="dimensions[{{ $dimension }}]" hidden></p>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </div>
            </section>

            <section class="card vendor-form-card" data-product-section="craft" aria-labelledby="vendor-product-craft-title">
                <div class="card-body">
                    <h2 class="vendor-section-title" id="vendor-product-craft-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-hand-heart"></i></span><span>{{ t('vendor.Product_Section_Craft', 'Craft, care and delivery') }}</span></h2>
                    <div class="vendor-product-fields">
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-material">{{ t('vendor.Product_Material', 'Main material') }}</label>
                            <input id="vendor-product-material" class="vendor-input" name="material" maxlength="255" dir="auto" aria-describedby="vendor-product-material-error">
                            <p class="vendor-field-error" id="vendor-product-material-error" data-error-for="material" hidden></p>
                        </div>
                        <div class="vendor-field">
                            <label class="vendor-label" for="vendor-product-place">{{ t('vendor.Product_Place_Of_Manufacture', 'Place of manufacture') }}</label>
                            <input id="vendor-product-place" class="vendor-input" name="place_of_manufacture" maxlength="255" dir="auto" aria-describedby="vendor-product-place-error">
                            <p class="vendor-field-error" id="vendor-product-place-error" data-error-for="place_of_manufacture" hidden></p>
                        </div>
                        <div class="vendor-field vendor-field-narrow is-wide">
                            <label class="vendor-label" for="vendor-product-preparation-days">{{ t('vendor.Product_Preparation_Days', 'Preparation time (days)') }}</label>
                            <input id="vendor-product-preparation-days" class="vendor-input" name="preparation_days" type="number" min="0" max="3650" step="1" inputmode="numeric" dir="ltr" aria-describedby="vendor-product-preparation-days-hint vendor-product-preparation-days-error">
                            {{-- The product page shows the product's own preparation time, else the store's. --}}
                            <p class="vendor-field-hint" id="vendor-product-preparation-days-hint">{{ t('vendor.Product_Preparation_Days_Hint', 'Leave empty to use your store’s preparation time.') }}</p>
                            <p class="vendor-field-error" id="vendor-product-preparation-days-error" data-error-for="preparation_days" hidden></p>
                        </div>
                        <div class="vendor-field is-wide">
                            <label class="vendor-label" for="vendor-product-care">{{ t('vendor.Product_Care_Instructions', 'Care and storage instructions') }}</label>
                            <textarea id="vendor-product-care" class="vendor-input vendor-product-textarea-sm" name="care_instructions" rows="3" maxlength="5000" dir="auto" aria-describedby="vendor-product-care-error"></textarea>
                            <p class="vendor-field-error" id="vendor-product-care-error" data-error-for="care_instructions" hidden></p>
                        </div>
                        <div class="vendor-field is-wide">
                            <label class="vendor-label" for="vendor-product-shipping">{{ t('vendor.Product_Shipping_Details', 'Packaging and shipping details') }}</label>
                            <textarea id="vendor-product-shipping" class="vendor-input vendor-product-textarea-sm" name="shipping_details" rows="3" maxlength="5000" dir="auto" aria-describedby="vendor-product-shipping-error"></textarea>
                            <p class="vendor-field-error" id="vendor-product-shipping-error" data-error-for="shipping_details" hidden></p>
                        </div>
                    </div>
                </div>
            </section>
        </fieldset>

        @php
            // G7-C2-5: the gallery's texts, read without creating translation rows
            // (VendorDashboardTranslationSeeder holds them in Arabic and English).
            $galleryText = function (string $key, string $default): string {
                $autoCreate = config('palgoals-locale.auto_create');
                config(['palgoals-locale.auto_create' => false]);

                try {
                    return t('vendor.Product_Gallery_'.$key, $default);
                } finally {
                    config(['palgoals-locale.auto_create' => $autoCreate]);
                }
            };
            $galleryMessages = [
                'change' => $galleryText('Change', 'Change'),
                'setMain' => $galleryText('Set_Main', 'Set as main'),
                'remove' => $galleryText('Remove', 'Remove'),
                'confirmRemove' => $galleryText('Confirm_Remove', 'Remove this image from the product? The file stays in your media library.'),
                'noMain' => $galleryText('No_Main', 'No main image selected.'),
                'noGallery' => $galleryText('No_Gallery', 'No gallery images selected.'),
                'count' => $galleryText('Count', ':count of :max images'),
                'titleMain' => $galleryText('Title_Main', 'Choose the main product image'),
                'titleReplace' => $galleryText('Title_Replace', 'Choose a replacement image'),
                'titleGallery' => $galleryText('Title_Gallery', 'Choose a gallery image'),
                'loading' => $galleryText('Loading', 'Loading images…'),
                'libraryEmpty' => $galleryText('Library_Empty', 'There are no images in your media library yet. Upload one from this window.'),
                'libraryError' => $galleryText('Library_Error', 'Your media library could not be loaded.'),
                'saving' => $galleryText('Saving', 'Saving…'),
                'uploading' => $galleryText('Uploading', 'Uploading…'),
                'added' => $galleryText('Added', 'Image added.'),
                'replaced' => $galleryText('Replaced', 'Image replaced.'),
                'removed' => $galleryText('Removed', 'Image removed. The file stays in your media library.'),
                'mainChanged' => $galleryText('Main_Changed', 'Main image changed.'),
                'limit' => $galleryText('Limit', 'A product can have up to 6 images. Remove or replace an image to add another.'),
                'library' => $galleryText('Not_In_Library', 'This image is not in your media library, or it was removed. Choose another image.'),
                'gone' => $galleryText('Gone', 'This image or product is no longer available. Reload the page and try again.'),
                'session' => $galleryText('Session', 'Your session has ended. Sign in again to continue.'),
                'forbidden' => $galleryText('Forbidden', 'You do not have permission to do this.'),
                'invalid' => $galleryText('Invalid', 'The request could not be processed. Reload the page and try again.'),
                'network' => $galleryText('Network', 'The connection failed. Check the network and try again.'),
                'failed' => $galleryText('Failed', 'Something went wrong. Try again.'),
                'uploadInvalid' => $galleryText('Upload_Invalid', 'The file must be a JPEG, PNG, GIF or WebP image of up to 10 MB.'),
                'imageAlt' => $galleryText('Image_Alt', 'Product image'),
                'mediaAlt' => $galleryText('Media_Alt', 'Media library image'),
                // VPM-01A: the admin gallery card's position line and main badge.
                'position' => $galleryText('Position', 'Image :position'),
                'mainBadge' => $galleryText('Main_Badge', 'Main image'),
                // VPM-01B: the picker dialog.
                'libraryCount' => $galleryText('Library_Count', 'Images: :count'),
                'pageStatus' => $galleryText('Page_Status', 'Page :page of :last'),
                'choose' => $galleryText('Choose', 'Choose :name'),
                'attached' => $galleryText('Attached', 'Already in this product'),
                'uploaded' => $galleryText('Uploaded', 'Images uploaded: :count. The first one was added to the product; the others are in your media library.'),
                'uploadMax' => $galleryText('Upload_Max', 'You can upload up to 10 images at a time.'),
                // VPM-01C: duplicates.
                'duplicate' => $galleryText('Duplicate', 'This image is already in this product. Choose another image.'),
                'current' => $galleryText('Current', 'The image being replaced'),
                // VPM-01D: moving images (the first image is the main one).
                'moveUp' => $galleryText('Move_Up', 'Move up'),
                'moveDown' => $galleryText('Move_Down', 'Move down'),
                'moved' => $galleryText('Moved', ':name is now image :position.'),
                // VPM-01E: images from before the media library, missing files, and the six-image limit.
                'legacy' => $galleryText('Legacy', 'Not in your media library'),
                'missing' => $galleryText('Missing', 'Image file not found'),
                'full' => $galleryText('Full', 'This product has the maximum of :max images. Remove or replace one to add another.'),
                'overMax' => $galleryText('Over_Max', 'This product has :count images, more than the current maximum of :max. Nothing can be added. You can still remove, replace, reorder and set the main image; no image is removed automatically.'),
            ];
            // VPM-01A: the editor's own texts (title, list button, product load, save and delete).
            // VPM-UX-01E: the save bar, the leave warning, the field-error summary and the images card's save hint.
            $editorMessages = [
                'add' => t('vendor.Product_New', 'Add product'),
                'edit' => t('vendor.Product_Edit', 'Edit product'),
                // VPM-UX-01E-POLISH-01: the page heading while the editor is open (the list keeps "Add product" for its button).
                'headingAdd' => t('vendor.Product_Heading_New', 'Add Product'),
                'headingEdit' => t('vendor.Product_Heading_Edit', 'Edit Product'),
                'noCategory' => t('vendor.Product_Category_Choose', 'Choose a category'),
                // VPM-CATEGORY-RELIABILITY-01: the category field while loading, and a product's own inactive category.
                'categoriesLoading' => t('vendor.Product_Category_Loading', 'Loading categories…'),
                'categoryInactive' => t('vendor.Product_Category_Inactive', ':name (inactive)'),
                'confirmDelete' => t('vendor.Product_Confirm_Delete', 'Delete this product? This cannot be undone.'),
                'deleteFailed' => t('vendor.Product_Delete_Failed', 'The product could not be deleted.'),
                'loadFailed' => t('vendor.Product_Load_Failed', 'The product could not be loaded.'),
                'saveFailed' => t('vendor.Product_Save_Failed', 'The product could not be saved.'),
                'saveInvalid' => t('vendor.Product_Save_Invalid', 'The product could not be saved. Check these details:'),
                'save' => t('vendor.Product_Save', 'Save product'),
                'saving' => t('vendor.My_Store_Saving', 'Saving…'),
                'unsaved' => t('vendor.My_Store_Unsaved', 'You have unsaved changes'),
                'fixErrors' => t('vendor.My_Store_Fix_Errors', 'Please review the highlighted fields.'),
                'discard' => t('vendor.Product_Discard_Confirm', 'Leave without saving? Your unsaved changes will be lost.'),
                'cancel' => t('vendor.Cancel', 'Cancel'),
                'back' => t('vendor.Product_Back_To_List', 'Back to products'),
                'mediaAdd' => t('vendor.Product_Media_Hint_Add', 'Images are saved with the product when you select “Save product”.'),
                'mediaEdit' => t('vendor.Product_Media_Hint_Edit', 'Image changes are saved right away; you do not need to select “Save product”.'),
            ];
        @endphp
        <fieldset class="vendor-product-column is-side">
            {{--
                VPM-01A: the image section (main image box, gallery box, the picker below); VPM-UX-01E: in the side
                column as the prototype's images card: the count in the card head, the main image large, the gallery
                images as tiles with icon actions (their names stay as the buttons' text for assistive tech).
            --}}
            <section class="card vendor-form-card vendor-product-gallery" data-product-section="images" data-product-gallery aria-busy="false" aria-labelledby="vendor-product-images-title">
                <div class="card-body">
                    <div class="vendor-product-card-head">
                        <h2 class="vendor-section-title" id="vendor-product-images-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-images"></i></span><span>{{ $galleryText('Section', 'Product images') }}</span></h2>
                        <p class="vendor-product-gallery-count" data-product-gallery-count></p>
                    </div>
                    <p class="vendor-product-media-hint" id="vendor-product-gallery-hint"><i class="ph-duotone ph-info" aria-hidden="true"></i><span><span>{{ $galleryText('Hint', 'Choose up to 6 images from your media library. The main image is shown first in the store.') }}</span> <span data-product-image-saving></span></span></p>
                    {{-- VPM-01E: one note at or above the six-image limit (a gallery from before it keeps every image). --}}
                    <p class="vendor-product-gallery-note" id="vendor-product-gallery-limit" role="status" aria-live="polite" data-product-gallery-limit hidden></p>

                    <section class="vendor-product-gallery-group is-main" aria-labelledby="vendor-product-main-label">
                        <div class="vendor-product-gallery-group-head">
                            <h3 class="vendor-product-gallery-label" id="vendor-product-main-label">{{ $galleryText('Main_Label', 'Main product image') }}</h3>
                            <p class="vendor-product-hint">{{ $galleryText('Main_Help', 'Shown first on the store.') }}</p>
                        </div>
                        <div class="vendor-product-gallery-box" data-product-primary-image></div>
                        {{-- Shown while there is no main image (the main image's own Change replaces it otherwise). --}}
                        <button class="btn btn-light-secondary vendor-product-gallery-add" type="button" data-product-primary-open>
                            <i class="ph-duotone ph-image" aria-hidden="true"></i>
                            <span>{{ $galleryText('Choose_Main', 'Choose from media') }}</span>
                        </button>
                    </section>

                    <section class="vendor-product-gallery-group" aria-labelledby="vendor-product-gallery-label">
                        <div class="vendor-product-gallery-group-head">
                            <h3 class="vendor-product-gallery-label" id="vendor-product-gallery-label">{{ $galleryText('Gallery_Label', 'Product gallery') }}</h3>
                            <p class="vendor-product-hint">{{ $galleryText('Gallery_Help', 'Choose additional images from your media library.') }}</p>
                        </div>
                        <div class="vendor-product-gallery-box" data-product-gallery-images></div>
                        <button class="btn btn-light-secondary vendor-product-gallery-add" type="button" data-product-gallery-open data-limited="true" aria-describedby="vendor-product-gallery-hint vendor-product-gallery-limit">
                            <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                            <span>{{ $galleryText('Add', 'Add gallery image') }}</span>
                        </button>
                    </section>

                    <p class="vendor-product-gallery-status" role="status" aria-live="polite" data-product-gallery-status></p>
                    <p class="vendor-product-error" role="alert" data-product-gallery-error hidden></p>
                    <script type="application/json" data-product-gallery-messages>@json($galleryMessages)</script>
                </div>
            </section>

            <section class="card vendor-form-card" data-product-section="status" aria-labelledby="vendor-product-status-title">
                <div class="card-body">
                    <h2 class="vendor-section-title" id="vendor-product-status-title"><span class="vendor-product-chip" aria-hidden="true"><i class="ph-duotone ph-eye"></i></span><span>{{ t('vendor.Product_Status', 'Publication status') }}</span></h2>
                    {{-- VENDOR-PRODUCT-PUBLISH-UI-01B (OPEN-PROD-19, 20; DETAIL-01): the vendor's three statuses. A product
                         stored as out_of_stock gets a "keep" option (value "", added by the script from data-keep-label),
                         so its status is left out of the update; out_of_stock itself is never an option. --}}
                    <div class="vendor-field">
                        <label class="sr-only" for="vendor-product-status">{{ t('vendor.Product_Status', 'Publication status') }}</label>
                        <select id="vendor-product-status" class="vendor-input" name="status" aria-describedby="vendor-product-status-hint" data-keep-label="{{ t('vendor.Product_Status_Keep_Out_Of_Stock', 'Out of stock (keep current status)') }}"><option value="draft" selected>{{ t('vendor.Product_Status_Draft', 'Draft') }}</option><option value="active">{{ t('vendor.Product_Status_Active', 'Active') }}</option><option value="inactive">{{ t('vendor.Product_Status_Inactive', 'Inactive') }}</option></select>
                        <p class="vendor-product-hint" id="vendor-product-status-hint">{{ t('vendor.Product_Status_Hint', 'Active products are shown in the store only when they meet its existing conditions. Draft and inactive products are not shown.') }}</p>
                    </div>
                </div>
            </section>
        </fieldset>

        <script type="application/json" data-product-editor-messages>@json($editorMessages)</script>
        {{-- MYSTORE-UX-01D's sticky save bar: Save (enabled while there are unsaved changes), Cancel, and one line for
             the unsaved changes, the save outcome (data-product-error) or, for an archived product, the read-only hint. --}}
        <div class="vendor-form-actions vendor-product-save-bar">
            <button class="vendor-save-button" type="submit" data-product-save><i class="ph-duotone ph-circle-notch vendor-product-spinner" aria-hidden="true"></i><span data-product-save-label>{{ t('vendor.Product_Save', 'Save product') }}</span></button>
            <button class="vendor-product-cancel" type="button" data-product-editor-close data-product-cancel>{{ t('vendor.Cancel', 'Cancel') }}</button>
            <p class="vendor-unsaved" role="status" aria-live="polite" data-product-unsaved></p>
            <p class="vendor-product-error" role="alert" data-product-error hidden></p>
            <p class="vendor-readonly-hint" data-product-readonly hidden><i class="ph-duotone ph-lock-simple" aria-hidden="true"></i><span>{{ t('vendor.My_Store_Readonly_Hint', 'These details cannot be edited on this page.') }}</span></p>
        </div>
    </form>

    @include('vendor-dashboard.partials.media-picker', [
        'uploadHint' => $galleryText('Upload_Hint', 'JPEG, PNG, GIF or WebP, up to 10 MB each and up to 10 at a time. The first uploaded image is added to the product; the others stay in your media library.'),
        'multiple' => true,
    ])
</div>
