<?php

use App\Modules\Empleados\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->group(function () {

        // --- Proceso: Gestión general de empleados ---
        Route::controller(EmpleadosController::class)->group(function () {
            Route::get('/', 'get_empleados');
            Route::post('/', 'crear_empleado');
            // Endpoint orquestador: crear empleado + contrato en una sola transacción
            Route::post('/con-contrato', 'crear_empleado_con_contrato');
            Route::post('/foto/{id_empleado}', 'actualizar_foto');
        });
    });
});
