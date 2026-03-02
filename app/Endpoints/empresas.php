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
    Route::get('/empresas', [EmpresaController::class, 'get_empresas']);
    Route::post('/empresas', [EmpresaController::class, 'crear_empresa']);
    Route::get('/empresas/by-session', [EmpresaController::class, 'get_empresas_by_session']);
    Route::get('/empresas/usuarios', [EmpresaController::class, 'get_usuarios_por_empresa']);

    // Concesiones
    Route::get('/concesiones', [ConcesionController::class, 'get_concesiones']);
    Route::get('/concesiones/tipos-mineral', [ConcesionController::class, 'get_tipos_mineral']);
    Route::post('/concesiones/by-empresa', [ConcesionController::class, 'get_concesiones_by_empresa']);
    Route::get('/concesiones/by-session', [ConcesionController::class, 'get_concesiones_by_session']);
    Route::post('/concesiones', [ConcesionController::class, 'crear_concesion']);
    Route::put('/concesiones', [ConcesionController::class, 'update_concesion']);
    Route::delete('/concesiones', [ConcesionController::class, 'delete_concesion']);
    Route::post('/concesiones/asignaciones', [ConcesionController::class, 'get_empresas_historial']);
    Route::post('/concesiones/asignar', [ConcesionController::class, 'asignar_empresa']);
    Route::post('/concesiones/desasignar', [ConcesionController::class, 'desasignar_empresa']);

    // Minas
    Route::get('/minas', [MinaController::class, 'get_minas']);
    Route::post('/minas', [MinaController::class, 'crear_mina']);
    Route::put('/minas', [MinaController::class, 'update_mina']);
    Route::delete('/minas', [MinaController::class, 'delete_mina']);
    Route::post('/minas/asignar-empresa', [MinaController::class, 'asignar_empresa_mina']);
    Route::post('/minas/desasignar-empresa', [MinaController::class, 'desasignar_empresa_mina']);
    Route::get('/minas/empresas', [MinaController::class, 'get_empresas_mina']);
    Route::post('/minas/responsables', [MinaController::class, 'get_responsables_mina']);
    Route::post('/minas/asignar-responsable', [MinaController::class, 'asignar_responsable_mina']);

    // Labores
    Route::get('/labores', [LaborController::class, 'get_labores']);
    Route::get('/labores/tipos', [LaborController::class, 'get_tipos_labor']);
    Route::post('/labores', [LaborController::class, 'crear_labor']);
    Route::get('/labores', [LaborController::class, 'get_labor_by_id']);
    Route::put('/labores', [LaborController::class, 'update_labor']);
    Route::delete('/labores', [LaborController::class, 'delete_labor']);

    // Almacenes
    Route::get('/almacenes', [AlmacenController::class, 'get_almacenes']);
    Route::post('/almacenes', [AlmacenController::class, 'crear_almacen']);
    Route::post('/almacenes/asignar-responsable', [AlmacenController::class, 'asignar_responsable_almacen']);
    Route::post('/almacenes/responsables', [AlmacenController::class, 'get_responsables_almacen']);
    Route::post('/almacenes/asignar-mina', [AlmacenController::class, 'asignar_mina_almacen']);
    Route::post('/almacenes/minas', [AlmacenController::class, 'get_minas_almacen']);
    Route::post('/almacenes/desasignar-mina', [AlmacenController::class, 'desasignar_mina_almacen']);
});
