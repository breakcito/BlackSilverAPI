<?php

use App\Modules\Roles\Presentation\Controllers\RolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Roles - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    // Roles
    Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
});
