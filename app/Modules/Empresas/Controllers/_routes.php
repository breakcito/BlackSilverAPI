<?php

use App\Modules\Empresas\Controllers\ConcesionController;
use App\Modules\Empresas\Controllers\LaborController;
use App\Modules\Empresas\Controllers\MinaController; // Nuevo
use Illuminate\Support\Facades\Route;

use App\Modules\Empresas\Controllers\EmpresaController;
use App\Modules\Empresas\Controllers\AlmacenController;

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
    Route::post('/concesiones/by-empresa', [ConcesionController::class, 'get_concesiones_by_empresa']);
    Route::get('/concesiones/by-session', [ConcesionController::class, 'get_concesiones_by_session']);
    Route::post('/concesiones', [ConcesionController::class, 'crear_concesion']);
    Route::put('/concesion', [ConcesionController::class, 'update_concesion']);
    Route::delete('/concesion', [ConcesionController::class, 'delete_concesion']);
    
    // Asignacion de empresas a concesiones
    Route::post('/concesiones/asignaciones', [ConcesionController::class, 'get_empresas_historial']);
    Route::post('/concesiones/asignar', [ConcesionController::class, 'asignar_empresa']);
    Route::post('/concesiones/desasignar', [ConcesionController::class, 'desasignar_empresa']);

    // Minas (NUEVO)
    Route::get('/minas', [MinaController::class, 'get_minas']);
    Route::post('/minas', [MinaController::class, 'crear_mina']);
    Route::put('/mina', [MinaController::class, 'update_mina']);
    Route::delete('/mina', [MinaController::class, 'delete_mina']);
    Route::post('/minas/asignar-empresa', [MinaController::class, 'asignar_empresa_mina']);
    Route::post('/minas/desasignar-empresa', [MinaController::class, 'desasignar_empresa_mina']);
    Route::get('/minas/empresas', [MinaController::class, 'get_empresas_mina']); 

    // Labores
    Route::get('/labores', [LaborController::class, 'get_labores']); 
    Route::get('/labores/tipos', [LaborController::class, 'get_tipos_labor']); 

    Route::post('/labores', [LaborController::class, 'crear_labor']);
    Route::get('/labor', [LaborController::class, 'get_labor_by_id']);
    Route::put('/labor', [LaborController::class, 'update_labor']);
    Route::delete('/labor', [LaborController::class, 'delete_labor']);
    Route::post('/labor/responsables', [LaborController::class, 'get_responsables_labor']); 
    Route::post('/labor/asignar-responsable', [LaborController::class, 'asignar_responsable_labor']);

    // Almacenes
    Route::get('/almacenes', [AlmacenController::class, 'get_almacenes']);
    Route::post('/almacenes', [AlmacenController::class, 'crear_almacen']);
    
    // Almacenes - Responsables
    Route::post('/almacenes/asignar-responsable', [AlmacenController::class, 'asignar_responsable_almacen']);
    Route::get('/almacenes/responsables', [AlmacenController::class, 'get_responsables_almacen']); 
    
    // Almacenes - Labores
    Route::post('/almacenes/asignar-labor', [AlmacenController::class, 'asignar_labor_almacen']);
    Route::get('/almacenes/labores', [AlmacenController::class, 'get_labores_almacen']);
});
