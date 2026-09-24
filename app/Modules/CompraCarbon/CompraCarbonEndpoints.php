<?php

use App\Modules\CompraCarbon\Controller\CompraCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('compras-carbon')->controller(CompraCarbonController::class)->group(function () {
        Route::get('/', 'get_compras');
        Route::post('/', 'crear_compra');
        Route::post('verificar-documentos', 'verificar_documentos_duplicados');
        Route::get('{id_compra_carbon}', 'get_compra_con_detalles');
        Route::put('{id_compra_carbon}/confirmar', 'confirmar_compra');
        Route::put('{id_compra_carbon}', 'actualizar_compra');
        Route::post('{id_compra_carbon}/aprobar-liquidacion', 'aprobar_liquidacion');
        Route::post('{id_compra_carbon}/aprobar', 'confirmar_compra'); // alias backward-compat
        Route::post('{id_compra_carbon}/anular', 'anular_compra');
        Route::post('{id_compra_carbon}/evidencias', 'set_evidencias');
    });
});