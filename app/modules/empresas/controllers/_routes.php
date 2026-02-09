<?php

use App\Modules\Empresas\Controllers\ConcesionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Empresa - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Concesiones
    Route::get('/concesiones', [ConcesionController::class, 'get_concesiones']);
    Route::post('/concesiones', [ConcesionController::class, 'crear_concesion']);
});
