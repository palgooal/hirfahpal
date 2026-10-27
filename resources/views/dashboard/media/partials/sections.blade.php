{{--
    ADMIN-MEDIA-ORGANIZATION-01 P4: the two sections of the media library, Admin media (the package page) and Vendor
    media (dashboard.vendor-media.index, read-only). Plain links on the pattern of the contact messages switcher; no
    JavaScript tabs. $current is 'admin' or 'vendors'; $sectionText is the parent's t() reader without auto-create.
    The parent includes it only for an admin who may open both sections (media.view and vendors.view).
--}}
<nav class="admin-vendor-media-sections" aria-label="{{ $sectionText('Media_Sections_Label', 'Media library sections') }}" data-media-sections>
    <a href="{{ route('media-library.page') }}" class="btn btn-sm {{ $current === 'admin' ? 'btn-primary' : 'btn-light-secondary' }}" @if ($current === 'admin') aria-current="page" @endif>
        <i class="ph-duotone ph-images" aria-hidden="true"></i>
        <span>{{ $sectionText('Media_Section_Admin', 'Admin media') }}</span>
    </a>
    <a href="{{ route('dashboard.vendor-media.index') }}" class="btn btn-sm {{ $current === 'vendors' ? 'btn-primary' : 'btn-light-secondary' }}" @if ($current === 'vendors') aria-current="page" @endif>
        <i class="ph-duotone ph-storefront" aria-hidden="true"></i>
        <span>{{ $sectionText('Media_Section_Vendors', 'Vendor media') }}</span>
    </a>
</nav>
