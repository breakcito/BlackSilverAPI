<?php

use App\Modules\Empresas\Controllers\ConcesionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Empresas
    Route::get('/empresas', [\App\Modules\Empresas\Controllers\EmpresaController::class, 'index']);

    // Concesiones
    Route::get('/concesiones', [ConcesionController::class, 'get_concesiones']);
    Route::post('/concesiones', [ConcesionController::class, 'crear_concesion']);
    Route::put('/concesiones/{id}', [ConcesionController::class, 'update_concesion']);
    Route::delete('/concesiones/{id}', [ConcesionController::class, 'delete_concesion']);

    // Labores
    Route::get('/labores', [\App\Modules\Empresas\Controllers\LaborController::class, 'index']);
    Route::post('/labores', [\App\Modules\Empresas\Controllers\LaborController::class, 'store']);
    Route::get('/labores/{id}', [\App\Modules\Empresas\Controllers\LaborController::class, 'show']);
    Route::put('/labores/{id}', [\App\Modules\Empresas\Controllers\LaborController::class, 'update']);
    Route::delete('/labores/{id}', [\App\Modules\Empresas\Controllers\LaborController::class, 'destroy']);
});
