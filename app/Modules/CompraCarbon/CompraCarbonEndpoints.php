<?php

use App\Modules\CompraCarbon\Controller\CompraCarbonComprobantesController;
use App\Modules\CompraCarbon\Controller\CompraCarbonController;
use App\Modules\CompraCarbon\Controller\CompraCarbonPagosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('compras-carbon')->controller(CompraCarbonController::class)->group(function () {
        Route::get('/', 'get_compras');
        Route::post('/', 'crear_compra');
        Route::get('{id_compra_carbon}', 'get_compra_con_detalles');
        Route::post('{id_compra_carbon}/cargas', 'registrar_cargas');
        Route::post('{id_compra_carbon}/cerrar', 'cerrar_compra');
        Route::post('{id_compra_carbon}/anular', 'anular_compra');
    });

    Route::middleware('auth.jwt.custom')
        ->prefix('compras-carbon/{id_compra_carbon}')
        ->group(function () {
            // Comprobantes
            Route::controller(CompraCarbonComprobantesController::class)->group(function () {
                Route::post('comprobantes-proveedor', 'registrar_comprobante_proveedor');
                Route::post('comprobantes-transporte', 'registrar_comprobante_transporte');
            });

            // Pagos
            Route::controller(CompraCarbonPagosController::class)->group(function () {
                Route::post('pagos-proveedor', 'registrar_pago_proveedor');
                Route::post('pagos-transporte', 'registrar_pago_transporte');
            });
        });
});