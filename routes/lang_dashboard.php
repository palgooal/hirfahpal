<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TranslationValueController;

/*
|--------------------------------------------------------------------------
| Admin Language & Translation Routes
|--------------------------------------------------------------------------
|
| Add these routes inside your admin/dashboard route group.
| Example in routes/dashboard.php (inside auth + admin middleware):
|
|   require __DIR__ . '/lang_dashboard.php';
|
| All route names are prefixed with "dashboard." by the parent group.
| If your prefix is different, update the ->name() calls accordingly.
|
*/

// ── Languages CRUD ─────────────────────────────────────────────────────────
Route::resource('languages', LanguageController::class)
    ->except(['show'])
    ->names('languages');

// AJAX toggle endpoints
Route::post('languages/{language}/toggle-rtl',    [LanguageController::class, 'toggleRtl'])
    ->name('languages.toggle-rtl');

Route::post('languages/{language}/toggle-status', [LanguageController::class, 'toggleStatus'])
    ->name('languages.toggle-status');

Route::delete('languages/{language}/delete',      [LanguageController::class, 'destroy'])
    ->name('languages.destroy-ajax');

// ── Translation Values (dictionary) ───────────────────────────────────────
Route::resource('translation-values', TranslationValueController::class)
    ->except(['show', 'edit', 'update', 'destroy']);

Route::get('translation-values/{key}/edit',      [TranslationValueController::class, 'edit'])
    ->name('translation-values.edit');

Route::post('translation-values/{key}/update',   [TranslationValueController::class, 'update'])
    ->name('translation-values.update');

Route::delete('translation-values/{key}/delete', [TranslationValueController::class, 'destroy'])
    ->name('translation-values.destroy');

// Export / Import
Route::get('translation-values/export',          [TranslationValueController::class, 'export'])
    ->name('translation-values.export');

Route::post('translation-values/import',         [TranslationValueController::class, 'import'])
    ->name('translation-values.import');
