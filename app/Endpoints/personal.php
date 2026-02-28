<?php

use App\Modules\Personal\Controllers\CargoController;
use App\Modules\Personal\Controllers\EmpleadoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Personal - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Empleados
    Route::get('/empleados', [EmpleadoController::class, 'get_empleados']);
    Route::post('/empleados', [EmpleadoController::class, 'crear_empleado']);

    // Cargos
    Route::get('/cargos', [CargoController::class, 'get_cargos']);
});
