<?php

use App\Modules\VentaCarbon\Controller\VentaCarbonController;
use Illuminate\Support\Facades\Route;

/**
 * Modulo de Venta de Carbon.
 *
 * - GET  /api/ventas-carbon                       -> listar ventas con sus cargas
 * - POST /api/ventas-carbon                       -> registrar venta (cargas opcionales)
 * - GET  /api/ventas-carbon/{id}                  -> detalle de la venta
 * - POST /api/ventas-carbon/{id}/cargas           -> despachar cargas de una venta
 * - POST /api/ventas-carbon/{id}/cargas/{id_carga}/confirmar-llegada
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('ventas-carbon')->controller(VentaCarbonController::class)->group(function () {
        Route::get('/', 'get_ventas');
        Route::post('/', 'registrar_venta');
        Route::get('{id_venta_carbon}', 'get_venta');
        Route::post('{id_venta_carbon}/cargas', 'registrar_cargas');
        Route::post(
            '{id_venta_carbon}/cargas/{id_carga_venta_carbon}/confirmar-llegada',
            'confirmar_llegada'
        );
    });
});
