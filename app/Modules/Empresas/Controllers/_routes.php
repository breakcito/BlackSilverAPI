<?php

use App\Modules\Empresas\Controllers\ConcesionController;
use App\Modules\Empresas\Controllers\LaborController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Empresas
    Route::get('/empresas/by-session', [\App\Modules\Empresas\Controllers\EmpresaController::class, 'get_empresas_by_session']);

    // Concesiones
    Route::get('/concesiones', [ConcesionController::class, 'get_concesiones']);
    Route::post('/concesiones', [ConcesionController::class, 'crear_concesion']);
    Route::put('/concesion', [ConcesionController::class, 'update_concesion']);
    Route::delete('/concesion', [ConcesionController::class, 'delete_concesion']);

    // Labores
    Route::get('/labores', [LaborController::class, 'get_labores']);
    Route::post('/labores', [LaborController::class, 'crear_labor']);
    Route::get('/labor', [LaborController::class, 'get_labor_by_id']);
    Route::put('/labor', [LaborController::class, 'update_labor']);
    Route::delete('/labor', [LaborController::class, 'delete_labor']);
});
