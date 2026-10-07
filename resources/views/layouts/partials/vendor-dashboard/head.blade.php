@php
    // Direction comes from the current Language row (is_rtl), as on the vendor auth
    // pages, and is rendered by the server: no LTR-then-RTL switch in JavaScript.
    $pageDirection = $currentLanguage?->is_rtl ? 'rtl' : 'ltr';
@endphp
<!doctype html>
{{-- Light, vertical layout only: set here, never read from the admin's localStorage preferences. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $pageDirection }}" class="vendor-shell" data-pc-direction="{{ $pageDirection }}" data-pc-layout="vertical" data-pc-theme="light" data-pc-sidebar-caption="true">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }} | {{ t('vendor.Shell_Title_Suffix', 'Hirfah vendor dashboard') }}</title>
    <link rel="icon" href="{{ asset('images/brand/hirfah-logo.png') }}" type="image/png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet" />
    {{-- Able Pro presentation assets shared with the admin dashboard: layout CSS and icon fonts only. --}}
    <link rel="stylesheet" href="{{ asset('assets-dashboard/fonts/phosphor/duotone/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets-dashboard/fonts/tabler-icons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets-dashboard/css/style.css') }}" />
    @vite(['resources/css/vendor-dashboard.css', 'resources/js/vendor-dashboard.js'])
</head>
<body>
