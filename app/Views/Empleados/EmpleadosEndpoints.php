<?php

use App\Views\Empleados\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->controller(EmpleadosController::class)->group(function () {

        Route::get('/', 'get_empleados');
        Route::post('/', 'crear_empleado');
        Route::get('/empresas', 'get_empresas');
        Route::get('/areas', 'get_areas');
        Route::get('/cargos/{id_area}', 'get_cargos');
    });
});
