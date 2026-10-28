<?php

use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\CategoryManagementController;
use App\Http\Controllers\Dashboard\ContactMessageManagementController;
use App\Http\Controllers\Dashboard\CustomerManagementController;
use App\Http\Controllers\Dashboard\DeliveryDriverManagementController;
use App\Http\Controllers\Dashboard\DisputeManagementController;
use App\Http\Controllers\Dashboard\OrderManagementController;
use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Http\Controllers\Dashboard\ReturnRequestManagementController;
use App\Http\Controllers\Dashboard\ReviewManagementController;
use App\Http\Controllers\Dashboard\SettingController;
use App\Http\Controllers\Dashboard\SiteAvailabilityController;
use App\Http\Controllers\Dashboard\VendorManagementController;
use App\Http\Controllers\Dashboard\VendorMediaLibraryController;
use App\Http\Controllers\Dashboard\VendorOrderManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth:admin', 'setLocale'])
    ->name('dashboard.')
    ->group(function () {

        Route::resource('admins', AdminController::class)->except(['show']);
        // N-CAT-1: the server-side delete confirmation (GET shows it; only the DELETE route deletes).
        Route::get('categories/{category}/delete', [CategoryManagementController::class, 'confirmDelete'])->name('categories.delete');
        Route::resource('categories', CategoryManagementController::class)->except(['show']);
        // PRODUCT-MEDIA-01 P5-C: read-only images of a vendor's own media library for the product gallery (JSON),
        // products.create for a new product (the chosen vendor), products.edit for a product (its stored vendor).
        // Declared before the resource, so "vendor-media" is never read as a product id.
        Route::get('products/vendor-media', [ProductManagementController::class, 'vendorMediaForCreate'])->name('products.vendor-media.create');
        Route::get('products/{product}/vendor-media', [ProductManagementController::class, 'vendorMediaForProduct'])->whereNumber('product')->name('products.vendor-media.edit');
        Route::resource('products', ProductManagementController::class);
        // AD-UI-PROD-M5: suspension. The id is a plain number (no model binding), so the ability is checked before
        // the product is read; the controller then reads it locked inside its transaction.
        Route::post('products/{product}/suspend', [ProductManagementController::class, 'suspend'])->whereNumber('product')->name('products.suspend');
        Route::post('products/{product}/unsuspend', [ProductManagementController::class, 'unsuspend'])->whereNumber('product')->name('products.unsuspend');
        // AD-UI-PROD-M6: archiving instead of deleting, and restoring, on the same pattern.
        Route::post('products/{product}/archive', [ProductManagementController::class, 'archive'])->whereNumber('product')->name('products.archive');
        Route::post('products/{product}/restore', [ProductManagementController::class, 'restore'])->whereNumber('product')->name('products.restore');

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

        // CONTACT-ADMIN-IMPLEMENTATION-01: read-only list and details of the storefront contact messages.
        Route::get('contact-messages', [ContactMessageManagementController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [ContactMessageManagementController::class, 'show'])->whereNumber('contactMessage')->name('contact-messages.show');
        // CONTACT-ADMIN-DELETE-02: soft delete, super admins only.
        Route::delete('contact-messages/{contactMessage}', [ContactMessageManagementController::class, 'destroy'])->whereNumber('contactMessage')->name('contact-messages.destroy');
        // CONTACT-ADMIN-TRASH-02: the soft-deleted messages, restore and permanent deletion, super admins only.
        Route::get('contact-messages/trash', [ContactMessageManagementController::class, 'trash'])->name('contact-messages.trash');
        Route::get('contact-messages/trash/{contactMessage}', [ContactMessageManagementController::class, 'trashShow'])->whereNumber('contactMessage')->name('contact-messages.trash.show');
        Route::patch('contact-messages/trash/{contactMessage}/restore', [ContactMessageManagementController::class, 'restore'])->whereNumber('contactMessage')->name('contact-messages.restore');
        Route::delete('contact-messages/trash/{contactMessage}', [ContactMessageManagementController::class, 'purge'])->whereNumber('contactMessage')->name('contact-messages.purge');

        Route::get('vendors', [VendorManagementController::class, 'index'])->name('vendors.index');
        Route::get('vendors/create', [VendorManagementController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [VendorManagementController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}', [VendorManagementController::class, 'show'])->name('vendors.show');
        Route::patch('vendors/{vendor}/approve', [VendorManagementController::class, 'approve'])->name('vendors.approve');
        Route::patch('vendors/{vendor}/reject', [VendorManagementController::class, 'reject'])->name('vendors.reject');
        // FR-ADM-009: the per-vendor commission rate (vendor_profiles.commission_rate); no calculation or settlement.
        Route::patch('vendors/{vendor}/commission-rate', [VendorManagementController::class, 'updateCommissionRate'])->name('vendors.commission-rate');

        // ADMIN-MEDIA-ORGANIZATION-01 P3: read-only vendor media folders (JSON). The vendor id is a plain number,
        // resolved after media.view and vendors.view are checked. P4: the page that browses them.
        Route::get('vendor-media', [VendorMediaLibraryController::class, 'index'])->name('vendor-media.index');
        Route::get('vendor-media/folders', [VendorMediaLibraryController::class, 'folders'])->name('vendor-media.folders');
        Route::get('vendor-media/folders/{vendor}/media', [VendorMediaLibraryController::class, 'media'])
            ->where('vendor', '[1-9][0-9]{0,18}')->name('vendor-media.media');

        Route::get('vendor-orders', [VendorOrderManagementController::class, 'index'])->name('vendor-orders.index');
        Route::get('vendor-orders/create', [VendorOrderManagementController::class, 'create'])->name('vendor-orders.create');
        Route::post('vendor-orders', [VendorOrderManagementController::class, 'store'])->name('vendor-orders.store');
        Route::get('vendor-orders/{vendorOrder}', [VendorOrderManagementController::class, 'show'])->name('vendor-orders.show');
        Route::patch('vendor-orders/{vendorOrder}/assign-driver', [VendorOrderManagementController::class, 'assignDriver'])->name('vendor-orders.assign-driver');

        Route::get('settings', [SettingController::class, 'index'])->name('setting.index');
        Route::put('settings', [SettingController::class, 'update'])->name('setting.update');
        Route::get('settings/home-content', [SettingController::class, 'homeContent'])->name('setting.home-content');
        Route::patch('settings/home-content', [SettingController::class, 'updateHomeContent'])->name('setting.home-content.update');
        // SITE-AVAILABILITY Phase 3B: the stored site state, Super Admin only (checked in the controller).
        Route::patch('settings/site-availability', [SiteAvailabilityController::class, 'update'])->name('setting.site-availability.update');

        require __DIR__.'/lang_dashboard.php';

    });
