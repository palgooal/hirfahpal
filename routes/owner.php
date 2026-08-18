<?php

// use Illuminate\Support\Facades\Auth;
// use App\Http\Controllers\Dashboard\AreaController;

use Illuminate\Support\Facades\Route;

Route::prefix('owner')->middleware('auth:owner')->name('owner.')->group(function () {
  
       Route::resources([
           
        ]);
    });

    
