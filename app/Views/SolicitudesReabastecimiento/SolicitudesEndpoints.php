<?php

use App\Views\SolicitudesReabastecimiento\Controller\SolicitudesController;
use App\Views\SolicitudesReabastecimiento\Controller\AuxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Solicitudes de Reabastecimiento Endpoints
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->prefix('solicitudes-reabastecimiento')->group(function () {
    Route::controller(SolicitudesController::class)->group(function () {
        // Obtener todas la lista de solicitudes hechas por el usuario
        Route::get('/', 'get_solicitudes');
        // Registrar una solicitud y sus detalles
        Route::post('/', 'crear_solicitud');
        // Obtener los detalles de una solicitud
        Route::get('/detalles-solicitud', 'get_detalles_solicitud');
        // Obtener la trazabilidad de un detalle de solicitud
        Route::get('/trazabilidad-detalle', 'get_trazabilidad_by_detalle');
        // Obtener historial de entregas de una solicitud
        Route::get('/historial-entregas', 'get_historial_entregas');
        // Recibir un item de una entrega
        Route::post('/recibir-entrega-item', 'recibir_entrega_item');
        // Recibir múltiples entregas a la vez (Global)
        Route::post('/recibir-entrega-bulk', 'recibir_entrega_bulk');
    });

    Route::controller(AuxController::class)->prefix('catalogos')->group(function () {
        // Obtener la lista de almacenes donde el empleado es responsable,
        // productos y unidades de medida
        Route::get('/', 'get_catalogos');
        // Obtener los lotes disponibles del almacén solicitante para la recepcion
        Route::get('/lotes-destino', 'get_lotes_destino');
    });
});
