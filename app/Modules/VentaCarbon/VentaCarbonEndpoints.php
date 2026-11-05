<?php

use App\Modules\VentaCarbon\Controller\VentaCarbonController;
use App\Modules\VentaCarbon\Controller\VentaCarbonPagosController;
use App\Modules\VentaCarbon\Controller\VentaCarbonReporteController;
use Illuminate\Support\Facades\Route;

/**
 * Modulo de Venta de Carbon.
 *
 * - GET  /api/ventas-carbon                       -> listar ventas con el arbol completo
 * - POST /api/ventas-carbon                       -> registrar venta (cargas opcionales)
 * - GET  /api/ventas-carbon/reporte               -> "Registro de Ventas" por anio
 * - GET  /api/ventas-carbon/blendings             -> blendings despachables del almacen
 * - GET  /api/ventas-carbon/{id_venta_carbon}      -> detalle de la venta
 * - POST /api/ventas-carbon/{id}/cargas           -> despachar cargas de una venta
 * - POST /api/ventas-carbon/{id}/cargas/{id_carga}/confirmar-llegada
 * - POST /api/ventas-carbon/{id}/liquidaciones    -> registrar liquidacion
 * - PUT  /api/ventas-carbon/{id}/liquidaciones/{id_liquidacion}
 * - POST /api/ventas-carbon/{id}/liquidaciones/{id_liquidacion}/confirmar
 * - POST /api/ventas-carbon/{id}/liquidaciones/{id_liquidacion}/comprobantes
 * - POST /api/ventas-carbon/{id}/comprobantes/{id_comprobante}/pagos
 */
Route::middleware('auth.jwt.custom')->group(function () {
    // Reporte de "Registro de Ventas" por anio.
    // Se declara ANTES del grupo con `{id_venta_carbon}` para que "reporte"
    // no se interprete como un id de venta.
    Route::prefix('ventas-carbon')->controller(VentaCarbonReporteController::class)->group(function () {
        Route::get('reporte', 'get_reporte');
    });

    Route::prefix('ventas-carbon')->controller(VentaCarbonController::class)->group(function () {
        Route::get('/', 'get_ventas');
        Route::post('/', 'registrar_venta');
        // Rutas estaticas antes que las dinamicas: si no, "blendings" se
        // interpretaria como un id de venta.
        Route::get('blendings', 'get_blendings');
        Route::get('{id_venta_carbon}', 'get_venta');
        Route::post('{id_venta_carbon}/cargas', 'registrar_cargas');
        Route::post(
            '{id_venta_carbon}/cargas/{id_carga_venta_carbon}/confirmar-llegada',
            'confirmar_llegada'
        );
    });

    Route::prefix('ventas-carbon/{id_venta_carbon}')
        ->controller(VentaCarbonPagosController::class)
        ->group(function () {
            // Liquidaciones
            Route::post('liquidaciones', 'registrar_liquidacion');
            Route::put('liquidaciones/{id_liquidacion_venta_carbon}', 'actualizar_liquidacion');
            Route::post(
                'liquidaciones/{id_liquidacion_venta_carbon}/confirmar',
                'confirmar_liquidacion'
            );
            Route::post(
                'liquidaciones/{id_liquidacion_venta_carbon}/comprobantes',
                'registrar_comprobante'
            );

            // Pagos
            Route::post('comprobantes/{id_comprobante_venta_carbon}/pagos', 'registrar_pago');
        });
});
