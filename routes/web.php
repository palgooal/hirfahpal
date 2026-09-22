<?php


use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\Auth\AccountAuthenticatedSessionController;
use App\Http\Controllers\Auth\AccountNewPasswordController;
use App\Http\Controllers\Auth\AccountPasswordResetLinkController;
use App\Http\Controllers\Auth\AccountRegisteredUserController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminNewPasswordController;
use App\Http\Controllers\Auth\AdminPasswordResetLinkController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\HomeController;
use App\Support\Auth\AccountGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['setLocale'])->group(function () {
    require __DIR__ . '/lang.php';
});


Route::get('/', [HomeController::class, 'index'])->name('home');


Route::middleware('auth:web')->group(function () {
   
});

Route::get('/dashboard', function () {
    if (Auth::guard('admin')->check()) {
        return app(AdminDashboardController::class)->index();
    }

    foreach (AccountGuard::all() as $account) {
        if (Auth::guard($account['guard'])->check()) {
            return redirect()->route($account['dashboard_route']);
        }
    }

    if (Auth::guard('web')->check()) {
        return redirect()->route('home');
    }

    return redirect()->route('admin.login');
})->middleware('setLocale')->name('dashboard.home');

foreach (AccountGuard::all() as $accountType => $account) {
    Route::prefix($account['prefix'])
        ->name($account['route'].'.')
        ->group(function () use ($accountType, $account): void {
            Route::middleware('guest:'.$account['guard'])->group(function () use ($accountType): void {
                Route::get('/login', [AccountAuthenticatedSessionController::class, 'create'])->name('login')->defaults('account_type', $accountType);
                Route::post('/login', [AccountAuthenticatedSessionController::class, 'store'])->name('login.store')->defaults('account_type', $accountType);
                Route::get('/register', [AccountRegisteredUserController::class, 'create'])->name('register')->defaults('account_type', $accountType);
                Route::post('/register', [AccountRegisteredUserController::class, 'store'])->name('register.store')->defaults('account_type', $accountType);
                Route::get('/forgot-password', [AccountPasswordResetLinkController::class, 'create'])->name('password.request')->defaults('account_type', $accountType);
                Route::post('/forgot-password', [AccountPasswordResetLinkController::class, 'store'])->name('password.email')->defaults('account_type', $accountType);
                Route::get('/reset-password/{token}', [AccountNewPasswordController::class, 'create'])->name('password.reset')->defaults('account_type', $accountType);
                Route::post('/reset-password', [AccountNewPasswordController::class, 'store'])->name('password.update')->defaults('account_type', $accountType);
            });

            Route::middleware('auth:'.$account['guard'])->group(function () use ($accountType): void {
                Route::post('/logout', [AccountAuthenticatedSessionController::class, 'destroy'])->name('logout')->defaults('account_type', $accountType);
                Route::get('/dashboard', AccountDashboardController::class)->name('dashboard')->defaults('account_type', $accountType);
            });
    });
}

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])->name('password.update');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});

require __DIR__.'/dashboard.php';

Route::get('dashboard/check-auth', function () {
    return [
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'guard' => config('auth.defaults.guard'),
    ];
})->middleware('dashboard');
