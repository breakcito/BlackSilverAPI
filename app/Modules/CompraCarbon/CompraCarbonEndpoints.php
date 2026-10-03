<?php

use App\Modules\CompraCarbon\Controller\CompraCarbonComprobantesController;
use App\Modules\CompraCarbon\Controller\CompraCarbonController;
use App\Modules\CompraCarbon\Controller\CompraCarbonPagosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('compras-carbon')->controller(CompraCarbonController::class)->group(function () {
        Route::get('/', 'get_compras');
        Route::post('/', 'crear_compra');
        Route::post('verificar-documentos', 'verificar_documentos_duplicados');
        Route::get('{id_compra_carbon}', 'get_compra_con_detalles');
        // Confirmar y actualizar son POST (no PUT): viajan como multipart/form-data
        // para adjuntar las evidencias de la carga. PHP no puebla $_FILES ni $_POST
        // en un PUT multipart, asi que con PUT los adjuntos llegarian vacios.
        Route::post('{id_compra_carbon}/confirmar', 'confirmar_compra');
        Route::post('{id_compra_carbon}', 'actualizar_compra');
        Route::post('{id_compra_carbon}/aprobar-liquidacion', 'aprobar_liquidacion');
        Route::post('{id_compra_carbon}/aprobar', 'confirmar_compra'); // alias backward-compat
        Route::post('{id_compra_carbon}/anular', 'anular_compra');
        Route::post('{id_compra_carbon}/evidencias', 'set_evidencias');
    });

    // Comprobantes del proveedor y de flete. Se registran al aprobar la
    // liquidacion y se adjuntan evidencias, asi que las escrituras son POST
    // multipart por la misma razon que confirmar/actualizar.
    Route::middleware('auth.jwt.custom')
        ->prefix('compras-carbon/{id_compra_carbon}')
        ->controller(CompraCarbonComprobantesController::class)
        ->group(function () {
            Route::get('comprobante-proveedor', 'get_comprobante_proveedor');
            Route::post('comprobante-proveedor', 'registrar_comprobante_proveedor');

            Route::get('grupos-flete', 'get_grupos_flete');
            Route::get('comprobantes-transporte/{id_comprobante}', 'get_comprobante_transporte');
            Route::post('comprobantes-transporte', 'registrar_comprobante_transporte');
        });

    // Pagos al proveedor (directos o contra su comprobante) y al transportista.
    Route::middleware('auth.jwt.custom')
        ->prefix('compras-carbon/{id_compra_carbon}')
        ->controller(CompraCarbonPagosController::class)
        ->group(function () {
            Route::get('pagos', 'get_pagos');
            Route::post('pagos-proveedor', 'registrar_pago_proveedor');
            Route::post('pagos-transporte', 'registrar_pago_transporte');
        });
});