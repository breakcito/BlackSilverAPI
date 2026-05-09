<?php

use App\Modules\SolicitudesReabastecimientoAtencion\Controller\SolicitudesController;
use App\Modules\SolicitudesReabastecimientoAtencion\Controller\AuxController;
use App\Modules\SolicitudesReabastecimientoAtencion\Controller\EntregaController;
use App\Modules\SolicitudesReabastecimientoAtencion\Controller\PrestamosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('solicitudes-atencion')->group(function () {
        Route::controller(SolicitudesController::class)->group(function () {
            Route::get('/', 'get_solicitudes'); // obtener las solicitudes hechas por un almacen
            Route::get('/detalles-by-solicitud', 'get_detalles_solicitud');
            Route::put('/save-decision-detalle', 'update_detalle_estado');
            Route::get('/trazabilidad', 'get_trazabilidad');
        });

        // Entregas
        Route::prefix('entregas')->controller(EntregaController::class)->group(function () {
            Route::get('/', 'get_historial_entregas'); // listar entregas en base a una solicitud
            Route::get('/recepciones', 'get_historial_recepciones'); // listar recepciones en base a una entrega
            Route::post('/', 'crear_entrega'); // registrar una entrega
        });

        Route::prefix('aux')->controller(AuxController::class)->group(function () {
            Route::get('/almacenes-con-stock', 'get_almacenes_con_stock');
            Route::get('/stock-total-almacen', 'get_stock_total_almacen_por_productos');

        });

        // Prestamos
        Route::prefix('prestamos')->controller(PrestamosController::class)->group(function () {
            Route::get('/por-solicitud', 'get_prestamos_por_solicitud');
            Route::post('/nuevo', 'crear_prestamo');
            Route::get('/ver', 'obtener_por_id');
        });
    });
});
