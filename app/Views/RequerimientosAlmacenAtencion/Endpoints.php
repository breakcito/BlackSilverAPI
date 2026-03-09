

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
    Route::prefix('requerimientos')->controller(RequerimientoAlmacenController::class)->group(function () {
        Route::get('/', 'get_requerimientos');
        Route::post('/', 'crear_requerimiento');
        Route::get('/almacenes', 'get_almacenes_por_mina');
        Route::post('/obtener-por-id', 'obtener_requerimiento_por_id');
        Route::post('/detalle/trazabilidad', 'obtener_trazabilidad_detalle');
    });

    // Atención de Requerimientos (Despacho)
    Route::prefix('requerimientos/atencion')->controller(\App\Controllers\RequerimientoAlmacenAtencionController::class)->group(function () {
        Route::post('/obtener-pendientes', 'obtener_requerimientos_atencion');
        Route::post('/obtener-detalles', 'obtener_detalles_atencion');
        Route::post('/cambiar-estado-detalle', 'cambiar_estado_detalle');
        Route::post('/obtener-lotes-disponibles', 'obtener_lotes_disponibles');
        Route::post('/registrar-entrega', 'registrar_entrega');
        Route::post('/obtener-historial-entregas', 'obtener_historial_entregas_por_item');
    });
});
