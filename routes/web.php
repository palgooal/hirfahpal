<?php


use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminNewPasswordController;
use App\Http\Controllers\Auth\AdminPasswordResetLinkController;
use App\Http\Controllers\Auth\OwnerAuthenticatedSessionController;
use App\Http\Controllers\Auth\OwnerNewPasswordController;
use App\Http\Controllers\Auth\OwnerPasswordResetLinkController;
use App\Http\Controllers\Auth\OwnerRegisteredUserController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\HomeController;

use App\Http\Controllers\Owner\OwnerDashboardController;

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

    if (Auth::guard('owner')->check()) {
        return redirect()->route('owner.dashboard');
    }

    if (Auth::guard('web')->check()) {
        return redirect()->route('home');
    }

    return redirect()->route('login');
})->name('dashboard.home');

Route::prefix('owner')->name('owner.')->group(function () {
    Route::middleware('guest:owner')->group(function () {
        Route::get('/login', [OwnerAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [OwnerAuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('/register', [OwnerRegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [OwnerRegisteredUserController::class, 'store'])->name('register.store');
        Route::get('/forgot-password', [OwnerPasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [OwnerPasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('/reset-password/{token}', [OwnerNewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [OwnerNewPasswordController::class, 'store'])->name('password.update');
    });

    Route::middleware('auth:owner')->group(function () {
        Route::post('/logout', [OwnerAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
    });
});

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
require __DIR__.'/owner.php';

Route::get('dashboard/check-auth', function () {
    return [
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'guard' => config('auth.defaults.guard'),
    ];
})->middleware('dashboard');
