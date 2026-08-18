<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    View::composer([
        'layouts.app',
        'components.navbar',
        'components.footer',
    ], function ($view) {
        $setting = Schema::hasTable('settings')
            ? Setting::query()->first()
            : null;

        $view->with('siteSetting', $setting);
    });

    view()->composer('*', function ($view) {
        $view->with([
            'currentLocale'   => app()->getLocale(),
            'currentLanguage' => \App\Models\Language::where('code', app()->getLocale())->first(),
            'languages'       => \App\Models\Language::where('is_active', true)->get(),
        ]);
    });
}
}
