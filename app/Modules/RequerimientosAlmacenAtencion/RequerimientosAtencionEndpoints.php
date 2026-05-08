<?php

use App\Modules\RequerimientosAlmacenAtencion\Controller\AtencionController;
use App\Modules\RequerimientosAlmacenAtencion\Controller\AuxController;
use App\Modules\RequerimientosAlmacenAtencion\Controller\EntregaController;
use App\Modules\RequerimientosAlmacenAtencion\Controller\SolicitudesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('requerimientos-atencion')->group(function () {

        // Entregas (Despacho, Stock, Lotes)
        Route::controller(AuxController::class)->group(function () {
            Route::get('/lotes', 'get_lotes_disponibles');
            Route::get('/almacenes-autorizados', 'get_almacenes_autorizados');
            Route::get('/data-to-registro', 'get_data_to_registro');
            Route::get('/minas-by-almacen', 'get_minas_by_almacen');
            Route::get('/data-by-mina', 'get_data_by_mina');
            Route::get('/empleados', 'get_empleados');
        });

        Route::controller(AtencionController::class)->group(function () {
            // Registro de requerimientos desde atención
            Route::post('/', 'crear_requerimiento');
            Route::get('/requerimientos', 'get_requerimientos');
            Route::get('/detalles-by-requerimiento', 'get_detalles_requerimiento');
            Route::put('/save-decision-detalle', 'update_estado_detalle_requerimiento');
            Route::get('/trazabilidad', 'get_trazabilidad');
        });

        // Entregas (Despacho, Stock, Lotes)
        Route::controller(EntregaController::class)->group(function () {
            Route::post('/save-entrega', 'crear_entrega');
            Route::get('/entregas', 'get_historial_entregas');
        });

        // Solicitudes (Logística)
        Route::controller(SolicitudesController::class)->group(function () {
            Route::post('/save-solicitud-logistica', 'registrar_solicitud');
            Route::get('/solicitudes-logistica', 'get_historial_solicitudes');
        });
    });
});
