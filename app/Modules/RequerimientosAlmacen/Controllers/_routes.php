<?php

use App\Modules\RequerimientosAlmacen\Controllers\RequerimientoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Requermientos de Almacen - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Requerimientos / Solicitud a almacen
    Route::get('/requerimientos', [RequerimientoController::class, 'get_requerimientos']);
    Route::post('/requerimientos', [RequerimientoController::class, 'crear_requerimiento']);
    Route::get('/requerimientos/almacenes', [RequerimientoController::class, 'get_almacenes_por_mina']);
});
