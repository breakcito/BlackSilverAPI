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

    // Vistas especializadas (Sin parámetros en URL / Body POST)
    Route::post('/requerimientos/obtener-por-id', [RequerimientoController::class, 'obtener_requerimiento_por_id']);
    Route::post('/requerimientos/detalle/trazabilidad', [RequerimientoController::class, 'obtener_trazabilidad_detalle']);
});
