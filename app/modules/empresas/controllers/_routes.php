<?php

use App\Modules\Empresas\Controllers\AreaController;
use App\Modules\Empresas\Controllers\EmpresaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Áreas
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');

    // Empresas
    Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas.index');
});
