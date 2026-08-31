<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('abilities', function () {
            return include base_path('data/abilities.php');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            if ($user instanceof Admin) {
                if ($user->super_admin) {
                    return true;
                }

                if (Schema::hasTable('admins') && $user->getKey() === Admin::query()->min('id')) {
                    return true;
                }
            }

            return null;
        });

        $settingResolved = false;
        $setting = null;

        View::composer([
            'layouts.app',
            'components.navbar',
            'components.footer',
        ], function ($view) use (&$settingResolved, &$setting) {
            if (! $settingResolved) {
                $setting = Schema::hasTable('settings')
                    ? Setting::query()->first()
                    : null;
                $settingResolved = true;
            }

            $view->with('siteSetting', $setting);
        });

        view()->composer('*', function ($view) {
            $view->with([
                'currentLocale' => app()->getLocale(),
                'currentLanguage' => Language::where('code', app()->getLocale())->first(),
                'languages' => Language::where('is_active', true)->get(),
            ]);
        });
    }
}
