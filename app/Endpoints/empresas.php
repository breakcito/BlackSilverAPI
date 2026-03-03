<?php

use App\Controllers\AlmacenController;
use App\Controllers\ConcesionController;
use App\Controllers\EmpresaController; // Nuevo
use App\Controllers\LaborController;
use App\Controllers\MinaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Empresas
    Route::prefix('empresas')->controller(EmpresaController::class)->group(function () {
        Route::get('/', 'get_empresas'); // ✅
        Route::post('/', 'crear_empresa'); // ✅
        Route::get('/by-session', 'get_empresas_by_session');
    });

    // Almacenes
    Route::prefix('almacenes')->controller(AlmacenController::class)->group(function () {
        Route::get('/', 'get_almacenes'); // ✅
        Route::post('/', 'crear_almacen'); // ✅
        Route::post('/asignar-responsable', 'asignar_responsable_almacen');
        Route::post('/responsables', 'get_responsables_almacen');
        Route::post('/asignar-mina', 'asignar_mina_almacen');
        Route::post('/minas', 'get_minas_almacen');
        Route::post('/desasignar-mina', 'desasignar_mina_almacen');
    });

    // Concesiones
    Route::prefix('concesiones')->controller(ConcesionController::class)->group(function () {
        Route::get('/', 'get_concesiones');
        Route::get('/tipos-mineral', 'get_tipos_mineral');
        Route::post('/by-empresa', 'get_concesiones_by_empresa');
        Route::get('/by-session', 'get_concesiones_by_session');
        Route::post('/', 'crear_concesion');
        Route::put('/', 'update_concesion');
        Route::delete('/', 'delete_concesion');
        Route::post('/asignaciones', 'get_empresas_historial');
        Route::post('/asignar', 'asignar_empresa');
        Route::post('/desasignar', 'desasignar_empresa');
    });

    // Minas
    Route::prefix('minas')->controller(MinaController::class)->group(function () {
        Route::get('/', 'get_minas');
        Route::post('/', 'crear_mina');
        Route::put('/', 'update_mina');
        Route::delete('/', 'delete_mina');
        Route::post('/asignar-empresa', 'asignar_empresa_mina');
        Route::post('/desasignar-empresa', 'desasignar_empresa_mina');
        Route::get('/empresas', 'get_empresas_mina');
        Route::post('/responsables', 'get_responsables_mina');
        Route::post('/asignar-responsable', 'asignar_responsable_mina');
    });

    // Labores
    Route::prefix('labores')->controller(LaborController::class)->group(function () {
        Route::get('/', 'get_labores');
        Route::get('/tipos', 'get_tipos_labor');
        Route::post('/', 'crear_labor');
        Route::get('/by-id', 'get_labor_by_id');
        Route::put('/', 'update_labor');
        Route::delete('/', 'delete_labor');
    });
});
