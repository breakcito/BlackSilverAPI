<?php

use App\Modules\Empleados\Presentation\Controllers\CargoController;
use App\Modules\Empleados\Presentation\Controllers\EmpleadoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empleados - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    // Cargos
    Route::get('/cargos', [CargoController::class, 'index'])->name('cargos.index');
    Route::post('/cargos', [CargoController::class, 'store'])->name('cargos.store');

    // Empleados
    Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
    Route::post('/empleados', [EmpleadoController::class, 'store'])->name('empleados.store');
});
