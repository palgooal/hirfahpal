<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocaleController;

/*
|--------------------------------------------------------------------------
| Language / Locale Routes
|--------------------------------------------------------------------------
|
| Include this file in your main web.php:
|
|   require __DIR__ . '/lang.php';
|
| Or wrap it in a middleware group if needed.
|
*/

// Switch active language (saves to session + smart redirect)
Route::get('change-locale/{locale}', [LocaleController::class, 'change'])
    ->name('change_locale');

// Return all translations as flat JSON — useful for JS / SPA
Route::get('translate-json/{locale}', [LocaleController::class, 'translateJson'])
    ->name('translate_json');
