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
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\ProductCatalogController;
use App\Http\Controllers\Store\ReviewController;
use App\Support\Auth\AccountGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['setLocale'])->group(function () {
    require __DIR__ . '/lang.php';
});


Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('products', [ProductCatalogController::class, 'index'])->name('products.index');
    Route::get('products/{product:slug}', [ProductCatalogController::class, 'show'])->name('products.show');
    Route::get('categories', [ProductCatalogController::class, 'categories'])->name('categories.index');
    Route::get('vendors', [ProductCatalogController::class, 'vendors'])->name('vendors.index');
});


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

Route::prefix('customer')
    ->middleware(['auth:customer', 'setLocale'])
    ->name('customer.')
    ->group(function () {
        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/reviews', [ReviewController::class, 'store'])->name('orders.reviews.store');
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

Route::get('dashboard/check-auth', function () {
    return [
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'guard' => config('auth.defaults.guard'),
    ];
})->middleware('dashboard');
