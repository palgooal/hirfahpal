<?php

use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\CategoryManagementController;
use App\Http\Controllers\Dashboard\CustomerManagementController;
use App\Http\Controllers\Dashboard\DeliveryDriverManagementController;
use App\Http\Controllers\Dashboard\DisputeManagementController;
use App\Http\Controllers\Dashboard\OrderManagementController;
use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Http\Controllers\Dashboard\ReturnRequestManagementController;
use App\Http\Controllers\Dashboard\ReviewManagementController;
use App\Http\Controllers\Dashboard\SettingController;
use App\Http\Controllers\Dashboard\VendorManagementController;
use App\Http\Controllers\Dashboard\VendorOrderManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth:admin', 'setLocale'])
    ->name('dashboard.')
    ->group(function () {

        Route::resource('admins', AdminController::class)->except(['show']);
        Route::resource('categories', CategoryManagementController::class)->except(['show']);
        Route::resource('products', ProductManagementController::class)->except(['show']);

        Route::get('customers', [CustomerManagementController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerManagementController::class, 'show'])->name('customers.show');
        Route::patch('customers/{customer}/status', [CustomerManagementController::class, 'updateStatus'])->name('customers.update-status');

        Route::get('delivery-drivers', [DeliveryDriverManagementController::class, 'index'])->name('delivery-drivers.index');
        Route::get('delivery-drivers/{deliveryDriver}', [DeliveryDriverManagementController::class, 'show'])->name('delivery-drivers.show');
        Route::patch('delivery-drivers/{deliveryDriver}/approve', [DeliveryDriverManagementController::class, 'approve'])->name('delivery-drivers.approve');
        Route::patch('delivery-drivers/{deliveryDriver}/reject', [DeliveryDriverManagementController::class, 'reject'])->name('delivery-drivers.reject');
        Route::patch('delivery-drivers/{deliveryDriver}/availability', [DeliveryDriverManagementController::class, 'updateAvailability'])->name('delivery-drivers.availability');

        Route::get('orders', [OrderManagementController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderManagementController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderManagementController::class, 'updateStatus'])->name('orders.update-status');
        Route::patch('orders/{order}/payment-status', [OrderManagementController::class, 'updatePaymentStatus'])->name('orders.update-payment-status');

        Route::get('reviews', [ReviewManagementController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{review}/status', [ReviewManagementController::class, 'updateStatus'])->name('reviews.update-status');
        Route::delete('reviews/{review}', [ReviewManagementController::class, 'destroy'])->name('reviews.destroy');

        Route::get('disputes', [DisputeManagementController::class, 'index'])->name('disputes.index');
        Route::get('disputes/{dispute}', [DisputeManagementController::class, 'show'])->name('disputes.show');
        Route::patch('disputes/{dispute}/resolve', [DisputeManagementController::class, 'resolve'])->name('disputes.resolve');

        Route::get('return-requests', [ReturnRequestManagementController::class, 'index'])->name('return-requests.index');
        Route::get('return-requests/{returnRequest}', [ReturnRequestManagementController::class, 'show'])->name('return-requests.show');
        Route::patch('return-requests/{returnRequest}/review', [ReturnRequestManagementController::class, 'review'])->name('return-requests.review');

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
