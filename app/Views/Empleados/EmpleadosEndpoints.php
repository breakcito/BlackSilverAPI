


<?php

use App\Controllers\EmpresaController; // Nuevo
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->controller(EmpresaController::class)->group(function () {});
});
Route::middleware('auth.jwt.custom')->group(function () {
    // Empleados
    Route::get('/empleados', [EmpleadoController::class, 'get_empleados']);
    Route::post('/empleados', [EmpleadoController::class, 'crear_empleado']);

    // Cargos
    Route::get('/cargos', [CargoController::class, 'get_cargos']);
});
