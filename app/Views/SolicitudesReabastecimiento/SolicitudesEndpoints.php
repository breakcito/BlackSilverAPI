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
    });

    Route::controller(AuxController::class)->prefix('catalogos')->group(function () {
        // Obtener la lista de almacenes donde el empleado es responsable,
        // productos y unidades de medida
        Route::get('/', 'get_catalogos');
    });
});
