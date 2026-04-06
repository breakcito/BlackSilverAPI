<?php

use App\Views\PrestamosAlmacenAtencion\Controller\AtencionController;
use App\Views\PrestamosAlmacenAtencion\Controller\EntregaController;
use App\Views\PrestamosAlmacenAtencion\Controller\RecepcionesController;
use App\Views\PrestamosAlmacenAtencion\Controller\AuxController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('prestamos-atencion')->group(function () {

        // Auxiliares
        Route::controller(AuxController::class)->group(function () {
            Route::get('/almacenes-autorizados', 'get_almacenes_autorizados');
            Route::get('/empleados',             'get_empleados');
            Route::get('/lotes',                 'get_lotes_disponibles');
            Route::get('/catalogos/unidades',    'get_unidades_medida');
            Route::get('/catalogos/lotes-destino', 'get_lotes_destino');
        });

        // Listado y Detalle (Atención)
        Route::controller(AtencionController::class)->group(function () {
            Route::get('/prestamos',    'get_prestamos');
            Route::get('/ver',          'get_detalles_prestamo');
            Route::get('/historial-entregas', 'get_historial_entregas');
            Route::get('/trazabilidad', 'get_trazabilidad_detalle');
            Route::post('/cambiar-estado', 'cambiar_estado_detalle');
        });

        // Despacho y Gestión (Entrega)
        Route::controller(EntregaController::class)->group(function () {
            Route::post('/despacho', 'registrar_despacho');
            Route::get('/lotes-batch', 'obtener_lotes_batch');
            Route::get('/entregas-solicitud', 'get_entregas_por_solicitud');
        });

        // Recepciones
        Route::controller(RecepcionesController::class)->prefix('recepciones')->group(function () {
            Route::get('/', 'get_historial_recepciones_entrega');
        });
    });
});
