{{--
    PRODUCT-MEDIA-01 P5-C: the product images field, an ordered gallery of up to six images from the product vendor's
    own media library (resources/js/admin-product-gallery.js, the chooser in gallery-dialog.blade.php). The first
    image is the main one. The script writes the P5-B contract (gallery_sync=1, gallery[] in order, and
    gallery_fingerprint when editing) only once the list is changed, so saving other fields leaves the images alone.
    Without the script the current images are listed here and kept. Removing an image unlinks it; its file stays.
    P5-HARDEN-01: every current image is listed, even above six (a product given more elsewhere); a warning then
    says nothing can be added and the list must be brought down to six before the images can be saved.
    $gallery comes from ProductManagementController::galleryFormData(); $prodText reads t() without auto-create.
--}}
@php
    $galleryErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $field) => preg_match('/^(gallery|gallery_fingerprint|gallery_sync|media_image_ids|images)(\.|$)/', $field) === 1)
        ->flatten()->unique()->values();

    $galleryConfig = [
        'editing' => $gallery['editing'],
        'max' => $gallery['max'],
        'items' => $gallery['items'],
        'restored' => $gallery['restored'],
        'mediaUrl' => $gallery['mediaUrl'],
        'perPage' => 24,
        'locale' => str_replace('_', '-', app()->getLocale()),
        'messages' => [
            'count' => $prodText('Products_Gallery_Count', ':count of :max images'),
            'main' => $prodText('Products_Gallery_Main', 'Main image'),
            'overMax' => $prodText('Products_Gallery_Over_Max', 'This product has :count images, more than the maximum of :max. Nothing can be added. Remove images until :max or fewer remain to save changes to the images. No image is removed automatically.'),
            'legacy' => $prodText('Products_Gallery_Legacy', 'Not in the vendor library'),
            'position' => $prodText('Products_Gallery_Position', 'Image :position'),
            'moveUp' => $prodText('Products_Gallery_Move_Up', 'Move up'),
            'moveDown' => $prodText('Products_Gallery_Move_Down', 'Move down'),
            'replace' => $prodText('Products_Gallery_Replace', 'Replace'),
            'remove' => $prodText('Products_Gallery_Remove', 'Remove'),
            'added' => $prodText('Products_Gallery_Added', ':name was added.'),
            'removed' => $prodText('Products_Gallery_Removed', ':name was removed from the product. The file stays in the media library.'),
            'moved' => $prodText('Products_Gallery_Moved', ':name is now image :position.'),
            'replaced' => $prodText('Products_Gallery_Replaced', ':name was replaced.'),
            'cleared' => $prodText('Products_Gallery_Cleared', 'The chosen images were cleared because the vendor changed.'),
            'dialogTitle' => $prodText('Products_Gallery_Dialog_Title', 'Choose an image'),
            'dialogReplaceTitle' => $prodText('Products_Gallery_Dialog_Replace_Title', 'Choose a replacement image'),
            'choose' => $prodText('Products_Gallery_Choose', 'Choose :name'),
            'inGallery' => $prodText('Products_Gallery_In_Gallery', 'Already added'),
            'libraryEmpty' => $prodText('Products_Gallery_Library_Empty', 'This vendor has no images in their media library yet.'),
            'libraryEmptySearch' => $prodText('Products_Gallery_Library_Empty_Search', 'No images match your search.'),
            'loadError' => $prodText('Products_Gallery_Load_Error', 'The images could not be loaded.'),
            'forbidden' => $prodText('Products_Gallery_Forbidden', 'You do not have permission to do this.'),
            'network' => $prodText('Products_Gallery_Network', 'The connection failed. Check the network and try again.'),
            'session' => $prodText('Products_Gallery_Session', 'Your session has ended. Sign in again to continue.'),
            'invalid' => $prodText('Products_Gallery_Invalid_Request', 'The request could not be processed. Change the search and try again.'),
        ],
    ];
@endphp

<div class="admin-cat-form-field admin-product-gallery" id="product-images" data-product-gallery tabindex="-1" aria-labelledby="product-gallery-label">
    <span class="form-label" id="product-gallery-label">{{ $prodText('Products_Form_Images', 'Product images') }} <span class="admin-cat-form-mark">({{ $optionalMark }})</span></span>
    <p class="admin-media-hint" id="product-images-hint">{{ $prodText('Products_Gallery_Hint', 'Choose up to 6 images from the vendor\'s media library. The first image is the main image; use the move buttons to change the order.') }}</p>

    <div class="admin-product-gallery-bar">
        <p class="admin-product-gallery-count" id="product-gallery-count">{{ str_replace([':count', ':max'], [count($gallery['items']), $gallery['max']], $prodText('Products_Gallery_Count', ':count of :max images')) }}</p>
        <button type="button" class="btn btn-light-secondary admin-product-gallery-add" id="product-gallery-add" aria-describedby="product-images-hint" hidden>
            <i class="ph-duotone ph-image" aria-hidden="true"></i>
            <span>{{ $prodText('Products_Gallery_Add', 'Add image') }}</span>
        </button>
    </div>

    <p class="admin-product-gallery-note" id="product-gallery-vendor-first" hidden>{{ $prodText('Products_Gallery_Vendor_First', 'Choose the vendor first to add its images.') }}</p>
    <p class="admin-product-gallery-note" id="product-gallery-over" @if (count($gallery['items']) <= $gallery['max']) hidden @endif>{{ count($gallery['items']) > $gallery['max'] ? str_replace([':count', ':max'], [count($gallery['items']), $gallery['max']], $galleryConfig['messages']['overMax']) : '' }}</p>
    <p class="admin-product-gallery-note" id="product-gallery-full" hidden>{{ str_replace(':max', $gallery['max'], $prodText('Products_Gallery_Full', 'The product has the maximum of :max images. Remove one to add another.')) }}</p>

    <ol class="admin-product-gallery-list" id="product-gallery-list" aria-labelledby="product-gallery-label">
        @foreach ($gallery['items'] as $index => $item)
            <li class="admin-product-gallery-item" data-ref="{{ $item['ref'] }}">
                <div class="admin-product-gallery-thumb">
                    @if ($item['url'])
                        <img src="{{ $item['url'] }}" alt="{{ $item['alt'] !== '' ? $item['alt'] : $item['name'] }}" loading="lazy">
                    @else
                        <i class="ph-duotone ph-image-broken" aria-hidden="true"></i>
                    @endif
                </div>
                <div class="admin-product-gallery-meta">
                    <bdi class="admin-product-gallery-name">{{ $item['name'] }}</bdi>
                    @if ($index === 0)
                        <span class="admin-product-gallery-badge is-main">{{ $prodText('Products_Gallery_Main', 'Main image') }}</span>
                    @endif
                    @if ($item['legacy'])
                        <span class="admin-product-gallery-badge is-legacy">{{ $prodText('Products_Gallery_Legacy', 'Not in the vendor library') }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
    <p class="admin-product-gallery-empty" id="product-gallery-empty" @if ($gallery['items'] !== []) hidden @endif>{{ $prodText('Products_Gallery_Empty', 'No images yet.') }}</p>

    <noscript><p class="admin-media-hint">{{ $prodText('Products_Gallery_No_Script', 'Turn on JavaScript to change the product images. The current images are kept when you save.') }}</p></noscript>

    @foreach ($galleryErrors as $message)
        <p class="admin-cat-form-error"@if ($loop->first) id="product-images-error"@endif>{{ $message }}</p>
    @endforeach

    {{-- The P5-B fields: written by the script once the list changes. --}}
    <div id="product-gallery-inputs" hidden></div>
    @if ($gallery['editing'])
        <input type="hidden" name="gallery_fingerprint" value="{{ $gallery['fingerprint'] }}">
    @endif

    <p class="visually-hidden" id="product-gallery-status" role="status" aria-live="polite"></p>
    <script type="application/json" id="product-gallery-config">@json($galleryConfig)</script>
</div>
