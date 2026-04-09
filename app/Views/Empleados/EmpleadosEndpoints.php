<?php

use App\Views\Empleados\AsignacionLaborEmpleadoController;
use App\Views\Empleados\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->group(function () {

        // --- Proceso: Gestión general de empleados ---
        Route::controller(EmpleadosController::class)->group(function () {
            Route::get('/', 'get_empleados');
            Route::post('/', 'crear_empleado');
            Route::get('/minas', 'get_minas');
            Route::get('/areas', 'get_areas');
            Route::get('/cargos/{id_area}', 'get_cargos');
            Route::post('/foto/{id_empleado}', 'actualizar_foto');
        });

        // --- Proceso: Asignación de labores al empleado ---
        Route::controller(AsignacionLaborEmpleadoController::class)->group(function () {
            Route::get('/labores-mina/{id_mina}', 'get_labores_disponibles');
            Route::get('/{id_empleado}/labores', 'get_labores_empleado');
            Route::post('/{id_empleado}/labores', 'asignar_labores');
        });
    });
});
