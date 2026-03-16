

<?php

use App\Views\RequerimientosAlmacenAtencion\Controller\AtencionController;
use App\Views\RequerimientosAlmacenAtencion\Controller\EntregaController;
use App\Views\RequerimientosAlmacenAtencion\Controller\SolicitudesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('requerimientos-atencion')->group(function () {

        Route::controller(AtencionController::class)->group(function () {
            Route::get('/almacenes-autorizados', 'get_almacenes_autorizados');
            Route::get('/requerimientos', 'get_requerimientos');
            Route::get('/detalles-by-requerimiento', 'get_detalles_requerimiento');
            Route::put('/save-decision-detalle', 'update_estado_detalle_requerimiento');
            Route::get('/trazabilidad', 'get_trazabilidad');
        });

        // Entregas (Despacho, Stock, Lotes)
        Route::controller(EntregaController::class)->group(function () {
            Route::get('/lotes', 'get_lotes_disponibles');
            Route::get('/empleados', 'get_empleados');
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
