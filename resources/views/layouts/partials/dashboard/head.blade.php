@php
    // Direction comes from the current Language row (is_rtl), as on the admin login
    // and vendor dashboard. If the locale has no Language row, fall back to ltr.
    $pageDirection = $currentLanguage?->is_rtl ? 'rtl' : 'ltr';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="preset-1 admin-shell"data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="{{ $pageDirection }}" dir="{{ $pageDirection }}" data-pc-theme_contrast="" data-pc-theme="light">
<!-- [Head] start -->

<head>
    {{-- "{page title} | {brand}", resolved in dashboard-layout.blade.php (AD-UI-01D-6). --}}
    <title>{{ $adminDocumentTitle }}</title>
    <!-- [Meta] -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <meta name="author" content="" />

    <link href="{{asset('assets-dashboard/css/plugins/bootstrap.min.css')}}" rel="stylesheet" />
    <!-- [Favicon] icon -->
    <link rel="icon" href="{{asset('assets-dashboard/images/favicon.svg')}}" type="image/x-icon" />
    <!-- [Font] Family -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/inter/inter.css')}}" id="main-font-link" />
    {{-- Cairo is the HIRFAH font (DEC-AD-UI-04.b), loaded as on the vendor dashboard; applied under html.admin-shell. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- [phosphor Icons] https://phosphoricons.com/ -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/phosphor/duotone/style.css')}}" />
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/tabler-icons.min.css')}}" />
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/feather.css')}}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/fontawesome.css')}}" />
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/fonts/material.css')}}" />
    <!-- [Template CSS Files] -->
    <link rel="stylesheet" href="{{asset('assets-dashboard/css/style.css')}}" id="" />
    @vite(['resources/css/admin-dashboard.css', 'resources/js/admin-dashboard.js'])

    <style>
        .offcanvas.offcanvas-end {
            width: 35%;
        }
    </style>
    @stack('styles')


</head>
<!-- [Head] end -->
<!-- [Body] Start -->

<body>
    {{-- No template pre-loader: it was removed by Able Pro script.js, which the admin no longer loads (AD-UI-01C-4B). --}}
    {{--
    @auth
    <script>
        var JSvar = "{{ Auth::user()->id }}";
        Pusher.logToConsole = true;
        var pusher = new Pusher('4e7b4215841c9ad639ad', { cluster: 'mt1' });
        var channel = pusher.subscribe('contact');
        channel.bind('notify', function(data) {
            if (data.user_id == JSvar) {
                $("#notifications_count").load(window.location.href + " #notifications_count");
                $.get(window.location.href, function(response) {
                    $("#unread").html($(response).find('#unread').html());
                });
            }
        });
    </script>
    @endauth
    --}}
