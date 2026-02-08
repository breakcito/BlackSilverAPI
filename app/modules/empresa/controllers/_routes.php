<?php

use App\Modules\Empresa\Presentation\Controllers\AreaController;
use App\Modules\Empresa\Presentation\Controllers\CargoController;
use App\Modules\Empresa\Presentation\Controllers\EmpresaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('jwt.auth')->group(function () {
    // Áreas
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [CargoController::class, 'storeArea'])->name('areas.store');

    // Cargos
    Route::post('/cargos', [CargoController::class, 'storeCargo'])->name('cargos.store');

    // Empresas
    Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas.index');

    // Area-Empresa
    Route::post('/area-empresas', [CargoController::class, 'storeAreaEmpresa'])->name('area-empresas.store');

    // Cargo-Empresa
    Route::post('/cargo-empresas', [CargoController::class, 'storeCargoEmpresa'])->name('cargo-empresas.store');
});
