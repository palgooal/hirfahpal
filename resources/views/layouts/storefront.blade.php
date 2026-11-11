<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($currentLanguage?->is_rtl ?? true) ? 'rtl' : 'ltr' }}" class="scroll-smooth motion-reduce:scroll-auto">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'حِرفة - سوق الصنعة الفلسطينية الأصيلة')">
    <title>@yield('title', 'حِرفة | سوق الصنعة الفلسطينية الأصيلة')</title>
    {{-- SETTINGS-MEDIA-01: the site favicon (the media library image, else the one uploaded before it), else the brand
         image; its MIME type when known (public/favicon.ico is an empty placeholder). --}}
    @php($favicon = (($siteSetting ?? null) instanceof \App\Models\Setting ? $siteSetting->faviconLink() : null) ?? ['href' => asset('images/brand/hirfah-logo.png'), 'type' => 'image/png'])
    <link rel="icon" href="{{ $favicon['href'] }}"@if ($favicon['type']) type="{{ $favicon['type'] }}"@endif>
@hasSection('robots')
    <meta name="robots" content="@yield('robots')">
@endif
    {{-- PAGES-05E: page-specific head tags (a content page's canonical, hreflang and Open Graph); empty elsewhere. --}}
    @stack('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,700;1,400&family=Cairo:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    @vite('resources/css/storefront.css')
</head>

<body class="overflow-x-hidden bg-canvas font-cairo text-ink antialiased">
@if ($preview ?? false)
    {{-- PAGES-03A: an admin page preview brings its own bar ($previewBanner); a vendor preview keeps its own. --}}
    @include($previewBanner ?? 'storefront.partials.vendor-preview-banner')
@else
    @include('storefront.partials.site-availability-indicator')

    <header class="relative z-50 bg-surface" data-node-id="223:3408">
        @include('storefront.partials.top-bar')
        @include('storefront.partials.header')
        @include('storefront.partials.mobile-menu')
    </header>

    @include('storefront.partials.search-panel')
@endif

    {{-- A page that shows its own feedback (e.g. the contact form) defines the "inline_feedback" section. --}}
    @sectionMissing('inline_feedback')
        @if (session('success') || $errors->any())
            {{-- PRODUCT-FIX-02: an error is announced at once (alert); a confirmation politely (status). --}}
            <div class="mx-auto max-w-[1216px] px-4 py-3 text-sm text-copper" role="{{ $errors->any() ? 'alert' : 'status' }}">
                {{ session('success') ?: $errors->first() }}
            </div>
        @endif
    @endif

    @yield('content')

@unless ($preview ?? false)
    @include('storefront.partials.footer')
@endunless

    <script src="{{ asset('assets/storefront/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/vendor/lucide.min.js') }}"></script>
    @vite('resources/js/storefront.js')

    @stack('scripts')
</body>

</html>
