<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Language Resolver
    |--------------------------------------------------------------------------
    |
    | By default the package reads config('app.locale') as the default language.
    |
    | If your project stores the default language in a database setting
    | (e.g. GeneralSetting model), you can provide a callable here that
    | returns the language code string.
    |
    | Example (in your AppServiceProvider or config):
    |
    |   'default_language_resolver' => function () {
    |       return \App\Models\GeneralSetting::first()?->default_language_code
    |           ?? config('app.locale');
    |   },
    |
    */
    'default_language_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Translation Auto-Create
    |--------------------------------------------------------------------------
    |
    | When enabled, using t('some.key') for a key that does not exist in the
    | database will automatically create an empty row for it. This makes it
    | easy to collect all keys from your views and translate them later in
    | the admin panel.
    |
    | You can also control this via your .env:
    |   TRANSLATION_AUTO_CREATE=true
    |
    */
    'auto_create' => env('TRANSLATION_AUTO_CREATE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long translation values are cached per key/locale pair.
    | Set to 0 to disable caching (not recommended in production).
    |
    */
    'cache_ttl' => (int) env('TRANSLATION_CACHE_TTL', 60),

];
