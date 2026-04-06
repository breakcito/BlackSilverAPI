<?php

use App\Views\SolicitudesReabastecimiento\Controller\SolicitudesController;
use App\Views\SolicitudesReabastecimiento\Controller\AuxController;
use App\Views\SolicitudesReabastecimiento\Controller\EntregasController;
use App\Views\SolicitudesReabastecimiento\Controller\RecepcionesController;
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
    });

    Route::controller(EntregasController::class)->prefix('entregas')->group(function () {
        // Obtener historial de entregas de una solicitud
        Route::get('/', 'get_historial_entregas');
    });

    Route::controller(RecepcionesController::class)->prefix('recepciones')->group(function () {
        // Registrar una recepcion de stock para una entrega de logistica
        Route::post('/registrar-recepcion-logistica', 'registrar_recepcion_logistica');
        // Registrar una recepcion de stock para una entrega de prestamo
        Route::post('/registrar-recepcion-prestamo', 'registrar_recepcion_prestamo');
        // Obtener el historial de recepciones de una entrega
        Route::get('/historial', 'get_historial_recepciones_entrega');
    });

    Route::controller(AuxController::class)->prefix('catalogos')->group(function () {
        // Obtener la lista de almacenes donde el empleado es responsable,
        // productos y unidades de medida
        Route::get('/', 'get_catalogos');
        // Obtener los lotes disponibles del almacén solicitante para la recepcion
        Route::get('/lotes-destino', 'get_lotes_destino');
    });
});
