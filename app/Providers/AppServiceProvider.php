<?php

namespace App\Providers;

use App\Http\Controllers\Dashboard\MediaLibraryController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Vendor;
use App\Support\Storefront\StorefrontContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Palgoal\MediaLibrary\Http\Controllers\MediaController as PackageMediaController;

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

        // AD-UI-CAT-M2-C: the media library routes resolve their controller through the container, so they
        // reach the application subclass that deletes safely (vendor/ is not modified).
        $this->app->bind(PackageMediaController::class, MediaLibraryController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.storefront', 'pages.*', 'storefront.*'], function ($view) {
            $view->with(array_replace(StorefrontContext::forRequest(request()), $view->getData()));
        });
        // Only the stored super_admin flag grants the global bypass; row order never does.
        Gate::before(function ($user) {
            return $user instanceof Admin && $user->isSuperAdmin() ? true : null;
        });

        $this->configureAuthRateLimiting();
        $this->configurePasswordResetLinks();

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

    /**
     * Route-level limiters for the HIRFAH account routes. Names are kept apart
     * from Fortify's ("login", "two-factor", "passkeys") so buckets never mix.
     * The per-account login failure limiter lives in GuardLoginService.
     */
    private function configureAuthRateLimiting(): void
    {
        RateLimiter::for('account-login-ip', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('account-register', function (Request $request) {
            return Limit::perHour(10)->by($this->accountType($request).'|'.$request->ip());
        });

        RateLimiter::for('account-password-email', function (Request $request) {
            $email = is_string($request->input('email')) ? $request->input('email') : '';

            return [
                Limit::perMinute(5)->by('ip|'.$request->ip()),
                Limit::perMinutes(15, 3)->by(
                    'email|'.$this->accountType($request).'|'.hash('sha256', Str::transliterate(Str::lower($email)))
                ),
            ];
        });

        RateLimiter::for('account-password-reset', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }

    /**
     * ARCH-03 / VEN-BE-002 / ADM-BE-001: the default reset email links to Fortify's
     * password.reset, which resets the web "users" broker. Vendor and customer
     * links go to their own reset routes so the token reaches the "vendors" /
     * "customers" broker. Admins and delivery drivers still keep the framework
     * default (ADM-BE-001, open for them).
     */
    private function configurePasswordResetLinks(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, #[\SensitiveParameter] string $token): string {
            $route = match (true) {
                $notifiable instanceof Vendor => 'vendor.password.reset',
                $notifiable instanceof Customer => 'customer.password.reset',
                default => 'password.reset',
            };

            return url(route($route, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }

    private function accountType(Request $request): string
    {
        return $request->route('account_type') ?? 'admin';
    }
}
