<?php

use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\SettingController;
use App\Http\Controllers\Dashboard\VendorManagementController;
use App\Http\Controllers\Dashboard\VendorOrderManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth:admin', 'setLocale'])
    ->name('dashboard.')
    ->group(function () {

        Route::resource('admins', AdminController::class)->except(['show']);

        Route::get('vendors', [VendorManagementController::class, 'index'])->name('vendors.index');
        Route::get('vendors/create', [VendorManagementController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [VendorManagementController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}', [VendorManagementController::class, 'show'])->name('vendors.show');
        Route::patch('vendors/{vendor}/approve', [VendorManagementController::class, 'approve'])->name('vendors.approve');
        Route::patch('vendors/{vendor}/reject', [VendorManagementController::class, 'reject'])->name('vendors.reject');

        Route::get('vendor-orders', [VendorOrderManagementController::class, 'index'])->name('vendor-orders.index');
        Route::get('vendor-orders/create', [VendorOrderManagementController::class, 'create'])->name('vendor-orders.create');
        Route::post('vendor-orders', [VendorOrderManagementController::class, 'store'])->name('vendor-orders.store');
        Route::get('vendor-orders/{vendorOrder}', [VendorOrderManagementController::class, 'show'])->name('vendor-orders.show');
        Route::patch('vendor-orders/{vendorOrder}/assign-driver', [VendorOrderManagementController::class, 'assignDriver'])->name('vendor-orders.assign-driver');

        Route::get('settings', [SettingController::class, 'index'])->name('setting.index');
        Route::put('settings', [SettingController::class, 'update'])->name('setting.update');

        require __DIR__.'/lang_dashboard.php';

    });
