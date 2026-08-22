<?php

use App\Http\Controllers\Dashboard\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth:admin', 'setLocale'])
    ->name('dashboard.')
    ->group(function () {

        Route::resource('admins', AdminController::class)->except(['show']);

        require __DIR__ . '/lang_dashboard.php';

    });
