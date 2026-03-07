<?php

use App\Controllers\AlmacenController;
use App\Controllers\SolicitudReabastecimientoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Solicitudes de Reabastecimiento - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('solicitudes-reabastecimiento')->controller(SolicitudReabastecimientoController::class)->group(function () {
        Route::get('/', 'get_solicitudes');
        Route::post('/', 'crear_solicitud');
        Route::post('/obtener-por-id', 'obtener_solicitud_por_id');
        Route::post('/obtener-detalles', 'get_detalles_solicitud');
    });

    Route::get('/almacenes/por-responsable', [AlmacenController::class, 'get_almacenes_by_responsable']);
});
