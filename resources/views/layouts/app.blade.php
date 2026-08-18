<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="@yield('meta_description', $siteSetting?->description ?? config('app.name'))" />
  <title>@yield('title', $siteSetting?->title ?? config('app.name'))</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="min-h-screen bg-[#F8FAFC] pb-28 text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif] lg:pb-0">

  {{-- Navbar --}}
  <x-navbar :active="$active ?? ''" />

  {{-- Main Content --}}
  <main>
    @yield('content')
  </main>

  {{-- Mobile Bottom Nav --}}
  <x-mobile-nav :active="$active ?? ''" />

  {{-- Footer --}}
  <x-footer />

  @stack('scripts')
</body>
</html>
