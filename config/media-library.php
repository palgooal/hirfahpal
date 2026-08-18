<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route prefix / name
    |--------------------------------------------------------------------------
    |
    | The library page will be served at {prefix} and the JSON API at
    | {prefix}/media/*. Route names are ALWAYS prefixed with "media-library."
    | regardless of this value (e.g. media-library.media.index) — only the
    | URL changes, never the route names, so existing route()/URL helper
    | calls keep working no matter what you set this to.
    |
    | This can be a multi-segment path, which is how you mount the package
    | inside an existing dashboard/admin area without adding any routes
    | file of your own:
    |
    |   'route_prefix' => 'dashboard/media-library',
    |
    | ...registers everything under /dashboard/media-library instead of
    | /media-library. No route file, controller, or namespace inside this
    | package needs to change for this to work — Laravel's Route::prefix()
    | natively supports multi-segment prefixes.
    |
    */
    'route_prefix' => 'dashboard/media-library',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Applied to every route the package registers. Add your own admin/auth
    | guard here (e.g. ['web', 'auth', 'can:access-admin']).
    |
    */
    'middleware' => ['web', 'auth:admin'],

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Disk used to store uploaded files. Must be a disk defined in
    | config/filesystems.php. "public" requires `php artisan storage:link`.
    |
    */
    'disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Upload base directory
    |--------------------------------------------------------------------------
    |
    | Files are stored under {disk}/{directory}/{Year}/{Month}/{file}.
    |
    */
    'directory' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Upload constraints
    |--------------------------------------------------------------------------
    |
    | SVG is intentionally NOT in the default allow-list. An SVG file can
    | contain inline <script>, <foreignObject>, or event-handler attributes
    | (onload, onerror, ...) that execute when the file is opened directly
    | or embedded in the page — a stored XSS vector. This package ships
    | with no SVG sanitizer, so allowing SVG here would let any user with
    | upload access plant script content that runs in the context of
    | whoever later opens the file.
    |
    | Only add 'svg' / 'image/svg+xml' back to these lists if you have
    | verified (and ideally automated, e.g. via a sanitizer library such as
    | enshrined/svg-sanitize run on every upload) that stored SVGs cannot
    | carry executable content. See SECURITY.md for details.
    |
    */
    'max_upload_size_kb' => 10240, // 10MB
    'allowed_mimes'      => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
    'allowed_mimetypes'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Used for the `uploader()` relation on the Media model. Defaults to the
    | app's configured auth provider model.
    |
    */
    'user_model' => App\Models\Admin::class, // matches the 'admin' guard used by the middleware above

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | Class used to authorize viewAny/view/create/update/delete on Media.
    | Override this in your own app (config/media-library.php after
    | publishing, or by binding your own Gate::policy() in a service
    | provider that boots after this package) to plug into your existing
    | roles/permissions system.
    |
    | IMPORTANT: the shipped default policy (Palgoal\MediaLibrary\Policies\
    | MediaPolicy) only requires an authenticated user. It does NOT scope
    | media to its uploader — any authenticated user can view, edit, or
    | delete ANY media item, including files uploaded by other users. This
    | is a deliberate, documented default meant for single-admin / trusted
    | back-office use, not a safe default for multi-tenant or public-user
    | applications. If your app has multiple untrusted users, replace this
    | policy (e.g. restrict update/delete to `$media->uploader_id === $user->id`
    | or your own role system) before going to production.
    |
    */
    'policy' => \Palgoal\MediaLibrary\Policies\MediaPolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Policy auto-registration
    |--------------------------------------------------------------------------
    |
    | The service provider calls Gate::policy(Media::class, ...) using the
    | class above during boot(). Set this to false if your host application
    | registers the Media policy itself (e.g. via its own AuthServiceProvider
    | $policies map) and you want to avoid a double registration / make sure
    | your own registration order/logic wins.
    |
    */
    'register_policy' => true,

    /*
    |--------------------------------------------------------------------------
    | Allowed logical media types
    |--------------------------------------------------------------------------
    |
    | Used to validate the `type` query parameter on the index/listing
    | endpoint (and by detectFileType()). Values other than these are
    | rejected instead of being passed straight into the database query.
    |
    */
    'allowed_types' => ['image', 'video', 'audio', 'document', 'other'],

    /*
    |--------------------------------------------------------------------------
    | Layout component
    |--------------------------------------------------------------------------
    |
    | The full library page (resources/views/media.blade.php) renders its
    | content inside <x-dynamic-component :component="...">, so it can be
    | wrapped by ANY Blade component that accepts a default slot — not just
    | the package's own layout.
    |
    | - null (default): the package's own minimal, self-contained layout
    |   (media-library::layouts.minimal — a bare <html> shell with Tailwind
    |   loaded from the CDN) is used, so the page renders correctly out of
    |   the box with zero configuration in a brand-new project.
    |
    | - Any component name Laravel can resolve via <x-{name}>: point this at
    |   your own dashboard/admin layout to make the page render as part of
    |   your dashboard chrome (sidebar, topbar, etc.) instead of as a
    |   standalone page. Like any <x-...> tag, an unnamespaced name is
    |   resolved as an anonymous component under resources/views/components/
    |   (Laravel's normal convention — this is not a package-specific rule):
    |
    |     'layout' => 'admin-layout',          // resources/views/components/admin-layout.blade.php
    |     'layout' => 'layouts.dashboard',     // resources/views/components/layouts/dashboard.blade.php
    |     'layout' => 'dashboard-layout',      // App\View\Components\DashboardLayout, if that class exists
    |
    |   Your layout component must accept a default slot ({{ $slot }}) —
    |   the same contract as any typical <x-app-layout>-style component.
    |   This package never writes to or depends on any file belonging to
    |   that layout; it only references it by name through config.
    |
    */
    'layout' => null,

    /*
    |--------------------------------------------------------------------------
    | Breadcrumb
    |--------------------------------------------------------------------------
    |
    | Optional breadcrumb trail rendered above the library page's header,
    | so the page can visually sit inside a dashboard's navigation instead
    | of looking like an isolated, unrelated screen. Left as null (default)
    | no breadcrumb is rendered at all.
    |
    | Each entry is an array with a 'label' and an optional 'url' (omit or
    | set to null for the current/last item, which is rendered as plain
    | text instead of a link):
    |
    |   'breadcrumb' => [
    |       ['label' => 'Dashboard', 'url' => '/dashboard'],
    |       ['label' => 'Media Library', 'url' => null],
    |   ],
    |
    */
    'breadcrumb' => [
        ['label' => 'لوحة التحكم', 'url' => '/dashboard'],
        ['label' => 'مكتبة الوسائط', 'url' => null],
    ],

];
