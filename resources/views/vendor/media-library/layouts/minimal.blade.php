<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Media Library') }} — Media Library</title>

    {{--
        Standalone fallback layout so the media library page renders correctly
        even in a brand-new project with no admin theme installed yet.
        Publish the views (`php artisan vendor:publish --tag=media-library-views`)
        and edit this file (or media.blade.php) to use your own layout instead.
    --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="bg-gray-50 dark:bg-gray-950 antialiased">
    {{ $slot }}
</body>
</html>
