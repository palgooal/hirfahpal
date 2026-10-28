<?php

use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\Auth\AccountAuthenticatedSessionController;
use App\Http\Controllers\Auth\AccountNewPasswordController;
use App\Http\Controllers\Auth\AccountPasswordResetLinkController;
use App\Http\Controllers\Auth\AccountRegisteredUserController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminNewPasswordController;
use App\Http\Controllers\Auth\AdminPasswordResetLinkController;
use App\Http\Controllers\Customer\AccountDetailsController as CustomerAccountDetailsController;
use App\Http\Controllers\Customer\AddressController as CustomerAddressController;
use App\Http\Controllers\Customer\FavoriteController as CustomerFavoriteController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\DeliveryDriverDashboard\AssignmentController as DeliveryDriverAssignmentController;
use App\Http\Controllers\DeliveryDriverDashboard\OverviewController as DeliveryDriverOverviewController;
use App\Http\Controllers\DeliveryDriverDashboard\ProfileController as DeliveryDriverProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\ContactMessageController;
use App\Http\Controllers\Store\NewsletterSubscriptionController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\ProductCatalogController;
use App\Http\Controllers\Store\ReviewController;
use App\Http\Controllers\Store\StorefrontContentPageController;
use App\Http\Controllers\Store\StorefrontPageController;
use App\Http\Controllers\Store\VendorDirectoryController;
use App\Http\Controllers\VendorDashboard\ApprovalStatusController as VendorApprovalStatusController;
use App\Http\Controllers\VendorDashboard\CommissionController as VendorCommissionController;
use App\Http\Controllers\VendorDashboard\DisputeController as VendorDisputeController;
use App\Http\Controllers\VendorDashboard\MediaLibraryController as VendorDashboardMediaLibraryController;
use App\Http\Controllers\VendorDashboard\OrderController as VendorDashboardOrderController;
use App\Http\Controllers\VendorDashboard\OverviewController as VendorDashboardOverviewController;
use App\Http\Controllers\VendorDashboard\ProductController as VendorDashboardProductController;
use App\Http\Controllers\VendorDashboard\ProductImageController as VendorDashboardProductImageController;
use App\Http\Controllers\VendorDashboard\ProfileController as VendorDashboardProfileController;
use App\Http\Controllers\VendorDashboard\ProfileMediaController as VendorDashboardProfileMediaController;
use App\Http\Controllers\VendorDashboard\ReturnRequestController as VendorReturnRequestController;
use App\Http\Controllers\VendorDashboard\ReviewController as VendorDashboardReviewController;
use App\Http\Middleware\EnsureSafeVendorMediaUpload;
use App\Support\Auth\AccountGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['setLocale'])->group(function () {
    require __DIR__.'/lang.php';
});

Route::get('/', [HomeController::class, 'index'])->middleware('setLocale')->name('home');
Route::post('/newsletter', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:5,1')->name('newsletter.store');

Route::get('/contact', [ContactMessageController::class, 'create'])->middleware('setLocale')->name('contact.create');
Route::post('/contact', [ContactMessageController::class, 'store'])->middleware(['setLocale', 'throttle:5,1'])->name('contact.store');
Route::get('/pages/{slug}', [StorefrontContentPageController::class, 'show'])->middleware('setLocale')->name('content.show');

Route::get('/categories', [StorefrontPageController::class, 'categories'])->middleware('setLocale')->name('categories');

Route::get('/browse', [StorefrontPageController::class, 'browse'])->middleware('setLocale')->name('browse');

Route::get('/product', [StorefrontPageController::class, 'product'])->middleware('setLocale')->name('product');

Route::get('/vendors', [VendorDirectoryController::class, 'index'])->middleware('setLocale')->name('vendors');

Route::get('/vendors/dar-al-karma', [VendorDirectoryController::class, 'show'])
    ->defaults('storeSlug', 'dar-al-karma')->middleware('setLocale')->name('vendors.show');
Route::get('/vendors/{storeSlug}', [VendorDirectoryController::class, 'show'])
    ->middleware('setLocale')->name('vendors.profile');

Route::get('/cart', [StorefrontPageController::class, 'cart'])->middleware('setLocale')->name('cart');

/*
|--------------------------------------------------------------------------
| Shop Routes
|--------------------------------------------------------------------------
*/

Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('products', [ProductCatalogController::class, 'index'])
        ->name('products.index');

    Route::get('products/{product:slug}', [ProductCatalogController::class, 'show'])
        ->name('products.show');

    Route::get('categories', [ProductCatalogController::class, 'categories'])
        ->name('categories.index');

    Route::get('vendors', [ProductCatalogController::class, 'vendors'])
        ->name('vendors.index');
});

/*
|--------------------------------------------------------------------------
| Web Auth Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')->group(function () {
    //
});

/*
|--------------------------------------------------------------------------
| Dashboard Redirect
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Account Routes
|--------------------------------------------------------------------------
*/

foreach (AccountGuard::all() as $accountType => $account) {
    Route::prefix($account['prefix'])
        ->name($account['route'].'.')
        ->group(function () use ($accountType, $account): void {
            $guestMiddleware = ['guest:'.$account['guard']];

            // Vendor auth pages use seeded translations only,
            // so t() must not insert keys here.
            if ($accountType === 'vendor') {
                $guestMiddleware = ['setLocale', ...$guestMiddleware, 'disableTranslationAutoCreate'];
            }

            Route::middleware($guestMiddleware)
                ->group(function () use ($accountType): void {
                    Route::get('/login', [AccountAuthenticatedSessionController::class, 'create'])
                        ->name('login')
                        ->defaults('account_type', $accountType);

                    Route::post('/login', [AccountAuthenticatedSessionController::class, 'store'])
                        ->name('login.store')
                        ->defaults('account_type', $accountType)
                        ->middleware('throttle:account-login-ip');

                    Route::get('/register', [AccountRegisteredUserController::class, 'create'])
                        ->name('register')
                        ->defaults('account_type', $accountType);

                    Route::post('/register', [AccountRegisteredUserController::class, 'store'])
                        ->name('register.store')
                        ->defaults('account_type', $accountType)
                        ->middleware('throttle:account-register');

                    Route::get('/forgot-password', [AccountPasswordResetLinkController::class, 'create'])
                        ->name('password.request')
                        ->defaults('account_type', $accountType);

                    Route::post('/forgot-password', [AccountPasswordResetLinkController::class, 'store'])
                        ->name('password.email')
                        ->defaults('account_type', $accountType)
                        ->middleware('throttle:account-password-email');

                    Route::get('/reset-password/{token}', [AccountNewPasswordController::class, 'create'])
                        ->name('password.reset')
                        ->defaults('account_type', $accountType);

                    Route::post('/reset-password', [AccountNewPasswordController::class, 'store'])
                        ->name('password.update')
                        ->defaults('account_type', $accountType)
                        ->middleware('throttle:account-password-reset');
                });

            Route::middleware('auth:'.$account['guard'])
                ->group(function () use ($accountType): void {
                    Route::post('/logout', [AccountAuthenticatedSessionController::class, 'destroy'])
                        ->name('logout')
                        ->defaults('account_type', $accountType);

                    if ($accountType === 'vendor') {
                        // Outside the approval gate: pending/rejected vendors land here (VEN-BE-001).
                        // Its page uses seeded translations only, so t() must not insert keys here.
                        Route::get('approval-status', VendorApprovalStatusController::class)
                            ->middleware(['setLocale', 'disableTranslationAutoCreate'])
                            ->name('approval-status');

                        // Every operational vendor route requires an approved store.
                        Route::prefix('dashboard')->middleware('vendor.approved')->group(function (): void {
                            // Canonical browser dashboard (VEN-BE-010): the vendor dashboard shell. Its
                            // page uses seeded translations only, so t() must not insert keys here.
                            Route::view('/', 'vendor-dashboard.home')
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])
                                ->name('dashboard');
                            // Overview data endpoint (JSON), moved from GET vendor/dashboard unchanged.
                            Route::get('overview', VendorDashboardOverviewController::class)->name('dashboard.overview');
                            // My Store browser page (VUI-03B); its data comes from the profile JSON endpoints below.
                            Route::view('my-store', 'vendor-dashboard.my-store')
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])
                                ->name('dashboard.my-store');

                            // Browser pages consume the existing JSON endpoints below.  Keeping their
                            // URLs separate means API clients retain a stable JSON contract.
                            Route::view('products/manage', 'vendor-dashboard.resources', ['resource' => 'products'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.products.page');
                            Route::view('orders/manage', 'vendor-dashboard.resources', ['resource' => 'orders'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.orders.page');
                            Route::view('reviews/manage', 'vendor-dashboard.resources', ['resource' => 'reviews'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.reviews.page');
                            Route::view('returns/manage', 'vendor-dashboard.resources', ['resource' => 'returns'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.returns.page');
                            Route::view('disputes/manage', 'vendor-dashboard.resources', ['resource' => 'disputes'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.disputes.page');
                            Route::view('finance/manage', 'vendor-dashboard.resources', ['resource' => 'commissions'])
                                ->middleware(['setLocale', 'disableTranslationAutoCreate'])->name('dashboard.commissions.page');

                            Route::get('profile', [VendorDashboardProfileController::class, 'show'])->name('dashboard.profile.show');
                            Route::put('profile', [VendorDashboardProfileController::class, 'update'])->name('dashboard.profile.update');
                            Route::post('profile/media/{type}', [VendorDashboardProfileMediaController::class, 'store'])
                                ->whereIn('type', ['logo', 'cover'])
                                ->name('dashboard.profile.media.store');
                            Route::delete('profile/media/{type}', [VendorDashboardProfileMediaController::class, 'destroy'])
                                ->whereIn('type', ['logo', 'cover'])
                                ->name('dashboard.profile.media.destroy');

                            Route::get('products/media/library', [VendorDashboardMediaLibraryController::class, 'index'])->name('dashboard.products.media.library');
                            // VENDOR-MEDIA-02A: the admin library's content checks on every uploaded file.
                            Route::post('products/media/library', [VendorDashboardMediaLibraryController::class, 'store'])
                                ->middleware(EnsureSafeVendorMediaUpload::class)->name('dashboard.products.media.store');
                            Route::delete('products/media/library/{media}', [VendorDashboardMediaLibraryController::class, 'destroy'])->name('dashboard.products.media.destroy');
                            Route::apiResource('products', VendorDashboardProductController::class)
                                ->names('dashboard.products');
                            Route::post('products/{product}/images', [VendorDashboardProductImageController::class, 'store'])->name('dashboard.products.images.store');
                            Route::patch('products/{product}/images/{productImage}', [VendorDashboardProductImageController::class, 'update'])->name('dashboard.products.images.update');
                            Route::delete('products/{product}/images/{productImage}', [VendorDashboardProductImageController::class, 'destroy'])->name('dashboard.products.images.destroy');

                            Route::get('orders', [VendorDashboardOrderController::class, 'index'])->name('dashboard.orders.index');
                            Route::get('orders/{vendorOrder}', [VendorDashboardOrderController::class, 'show'])->name('dashboard.orders.show');
                            Route::patch('orders/{vendorOrder}/accept', [VendorDashboardOrderController::class, 'accept'])->name('dashboard.orders.accept');
                            Route::patch('orders/{vendorOrder}/reject', [VendorDashboardOrderController::class, 'reject'])->name('dashboard.orders.reject');
                            Route::patch('orders/{vendorOrder}/preparing', [VendorDashboardOrderController::class, 'markPreparing'])->name('dashboard.orders.preparing');
                            Route::patch('orders/{vendorOrder}/ready', [VendorDashboardOrderController::class, 'markReady'])->name('dashboard.orders.ready');

                            Route::get('reviews', [VendorDashboardReviewController::class, 'index'])->name('dashboard.reviews.index');
                            Route::get('returns', [VendorReturnRequestController::class, 'index'])->name('dashboard.returns.index');
                            Route::get('returns/{returnRequest}', [VendorReturnRequestController::class, 'show'])->name('dashboard.returns.show');
                            Route::get('disputes', [VendorDisputeController::class, 'index'])->name('dashboard.disputes.index');
                            Route::get('disputes/{dispute}', [VendorDisputeController::class, 'show'])->name('dashboard.disputes.show');
                            Route::get('commissions', [VendorCommissionController::class, 'index'])->name('dashboard.commissions.index');
                        });

                        return;
                    }

                    Route::get('/dashboard', AccountDashboardController::class)
                        ->name('dashboard')
                        ->defaults('account_type', $accountType);

                    if ($accountType === 'delivery_driver') {
                        Route::prefix('dashboard')->middleware('delivery-driver.approved')->group(function (): void {
                            Route::get('overview', DeliveryDriverOverviewController::class)
                                ->name('dashboard.overview');
                            Route::get('profile', [DeliveryDriverProfileController::class, 'show'])
                                ->name('dashboard.profile.show');
                            Route::put('profile', [DeliveryDriverProfileController::class, 'update'])
                                ->name('dashboard.profile.update');
                            Route::get('assignments', [DeliveryDriverAssignmentController::class, 'index'])
                                ->name('dashboard.assignments.index');
                            Route::get('assignments/{assignment}', [DeliveryDriverAssignmentController::class, 'show'])
                                ->name('dashboard.assignments.show');
                            Route::patch('assignments/{assignment}/accept', [DeliveryDriverAssignmentController::class, 'accept'])
                                ->name('dashboard.assignments.accept');
                            Route::patch('assignments/{assignment}/pick-up', [DeliveryDriverAssignmentController::class, 'pickUp'])
                                ->name('dashboard.assignments.pick-up');
                            Route::patch('assignments/{assignment}/delivered', [DeliveryDriverAssignmentController::class, 'markDelivered'])
                                ->name('dashboard.assignments.delivered');
                            Route::patch('assignments/{assignment}/failed', [DeliveryDriverAssignmentController::class, 'markFailed'])
                                ->name('dashboard.assignments.failed');
                        });
                    }
                });
        });
}

// Vendor entry point: the dashboard's own gates decide where the visitor lands
// (guest → login, unapproved → approval status, inactive → signed out).
Route::redirect('/vendor', '/vendor/dashboard')->name('vendor.entry');

/*
|--------------------------------------------------------------------------
| Customer Store Routes
|--------------------------------------------------------------------------
*/

Route::prefix('customer')
    ->middleware(['setLocale'])
    ->name('customer.')
    ->group(function () {
        Route::get('cart', [CartController::class, 'show'])
            ->name('cart.show');

        Route::post('cart/items', [CartController::class, 'store'])
            ->name('cart.items.store');

        Route::patch('cart/items/{cartItem}', [CartController::class, 'update'])
            ->name('cart.items.update');

        Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy'])
            ->name('cart.items.destroy');

        Route::middleware('auth:customer')->group(function () {
            Route::get('account-details', [CustomerAccountDetailsController::class, 'edit'])
                ->name('account-details');
            Route::patch('account-details', [CustomerAccountDetailsController::class, 'update'])
                ->name('account-details.update');
            Route::patch('account-details/password', [CustomerAccountDetailsController::class, 'updatePassword'])
                ->name('account-details.password.update');

            Route::get('addresses', [CustomerAddressController::class, 'index'])
                ->name('addresses');
            Route::post('addresses', [CustomerAddressController::class, 'store'])
                ->name('addresses.store');
            Route::patch('addresses/{address}', [CustomerAddressController::class, 'update'])
                ->name('addresses.update');
            Route::delete('addresses/{address}', [CustomerAddressController::class, 'destroy'])
                ->name('addresses.destroy');

            Route::get('favorites', [CustomerFavoriteController::class, 'index'])
                ->name('favorites');
            Route::post('favorites', [CustomerFavoriteController::class, 'store'])
                ->name('favorites.store');
            Route::delete('favorites/{favorite}', [CustomerFavoriteController::class, 'destroy'])
                ->name('favorites.destroy');

            Route::get('checkout', [StorefrontPageController::class, 'checkout'])
                ->name('checkout.show');

            Route::post('checkout', [CheckoutController::class, 'store'])
                ->name('checkout.store');

            Route::get('orders', [OrderController::class, 'index'])
                ->name('orders.index');

            Route::get('orders/{order}', [OrderController::class, 'show'])
                ->name('orders.show');

            Route::patch('orders/{order}/vendor-orders/{vendorOrder}/confirm-receipt', [OrderController::class, 'confirmReceipt'])
                ->name('orders.vendor-orders.confirm-receipt');

            Route::post('orders/{order}/reviews', [ReviewController::class, 'store'])
                ->name('orders.reviews.store');
        });
    });

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware('setLocale')
    ->group(function () {
        // Guest auth pages use seeded translations only,
        // so t() must not insert keys here.
        Route::middleware([
            'guest:admin',
            'disableTranslationAutoCreate',
        ])->group(function () {
            Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])
                ->name('login.store')
                ->middleware('throttle:account-login-ip');

            Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])
                ->name('password.request');

            Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])
                ->name('password.email')
                ->middleware('throttle:account-password-email');

            Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])
                ->name('password.reset');

            Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])
                ->name('password.update')
                ->middleware('throttle:account-password-reset');
        });

        Route::middleware('auth:admin')->group(function () {
            Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
        });
    });

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/dashboard.php';

/*
|--------------------------------------------------------------------------
| Dashboard Auth Check
|--------------------------------------------------------------------------
*/

Route::get('dashboard/check-auth', function () {
    return [
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'guard' => config('auth.defaults.guard'),
    ];
})->middleware('dashboard');
