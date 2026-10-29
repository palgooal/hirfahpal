{{--
    Media Library (VENDOR-MEDIA-04A). Server-rendered and informational only: the
    upload, browse, search and delete tools come in a later phase. It reads no
    media and loads no page script; the media library endpoints are unchanged.
--}}
<x-vendor-dashboard-layout :title="t('vendor.Nav_Media_Library', 'Media Library')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('vendor.Nav_Media_Library', 'Media Library') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12" data-vendor-media-page>
        {{-- The dashboard's neutral centered panel (vendor-home-error), used here as an informational state. --}}
        <section class="card vendor-home-error" aria-labelledby="vendor-media-next-title">
            <div class="card-body">
                <span class="vendor-kpi-icon" aria-hidden="true"><i class="ph-duotone ph-images"></i></span>
                <p class="vendor-home-error-text">{{ t('vendor.Media_Library_Description', 'Keep your store images in one place, ready to add to your products.') }}</p>
                <h2 id="vendor-media-next-title" class="vendor-section-title">{{ t('vendor.Media_Library_Next_Title', 'Media tools are on the way') }}</h2>
                <p class="vendor-empty">{{ t('vendor.Media_Library_Next_Text', 'Uploading, browsing and managing your images here will be added in the next phase. Until then, add images from the product editor.') }}</p>
            </div>
        </section>
    </div>
</x-vendor-dashboard-layout>
