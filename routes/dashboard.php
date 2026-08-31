<?php

use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth:admin', 'setLocale'])
    ->name('dashboard.')
    ->group(function () {

        Route::resource('admins', AdminController::class)->except(['show']);

        Route::get('settings', [SettingController::class, 'index'])
            ->name('setting.index');
        Route::put('settings', [SettingController::class, 'update'])
            ->name('setting.update');

        require __DIR__ . '/lang_dashboard.php';

    });
