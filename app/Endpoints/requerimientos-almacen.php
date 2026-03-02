<?php

use App\Controllers\RequerimientoAlmacenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Requermientos de Almacen - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Requerimientos / Solicitud a almacen
    Route::get('/requerimientos', [RequerimientoAlmacenController::class, 'get_requerimientos']);
    Route::post('/requerimientos', [RequerimientoAlmacenController::class, 'crear_requerimiento']);
    Route::get('/requerimientos/almacenes', [RequerimientoAlmacenController::class, 'get_almacenes_por_mina']);

    // Vistas especializadas (Sin parámetros en URL / Body POST)
    Route::post('/requerimientos/obtener-por-id', [RequerimientoAlmacenController::class, 'obtener_requerimiento_por_id']);
    Route::post('/requerimientos/detalle/trazabilidad', [RequerimientoAlmacenController::class, 'obtener_trazabilidad_detalle']);

    // Atención de Requerimientos (Despacho)
    Route::post('/requerimientos/atencion/obtener-pendientes', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'obtener_requerimientos_atencion']);
    Route::post('/requerimientos/atencion/cambiar-estado-detalle', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'cambiar_estado_detalle']);
    Route::post('/requerimientos/atencion/obtener-lotes-disponibles', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'obtener_lotes_disponibles']);
    Route::post('/requerimientos/atencion/registrar-entrega', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'registrar_entrega']);
    Route::post('/requerimientos/atencion/obtener-historial-entregas', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'obtener_historial_entregas_por_item']);
    // Route::post('/requerimientos/atencion/finalizar', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'finalizar_requerimiento']);
    // Route::post('/requerimientos/anular', [\App\Controllers\RequerimientoAlmacenAtencionController::class, 'anular_requerimiento']);
});
