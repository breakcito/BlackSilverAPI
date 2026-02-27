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

    // Atención de Requerimientos (Despacho)
    Route::post('/requerimientos/atencion/obtener-pendientes', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'obtener_requerimientos_atencion']);
    Route::post('/requerimientos/atencion/cambiar-estado-detalle', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'cambiar_estado_detalle']);
    Route::post('/requerimientos/atencion/obtener-lotes-disponibles', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'obtener_lotes_disponibles']);
    Route::post('/requerimientos/atencion/registrar-entrega', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'registrar_entrega']);
    Route::post('/requerimientos/atencion/obtener-historial-entregas', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'obtener_historial_entregas_por_item']);
    Route::post('/requerimientos/atencion/finalizar', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'finalizar_requerimiento']);
    Route::post('/requerimientos/anular', [\App\Modules\RequerimientosAlmacen\Controllers\AtencionController::class, 'anular_requerimiento']);
});
