<?php

use App\Modules\CompraCarbon\Controller\CompraCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('compras-carbon')->controller(CompraCarbonController::class)->group(function () {
        Route::get('/', 'get_compras');
        Route::post('/', 'crear_compra');
        Route::get('{id_compra_carbon}', 'get_compra_con_detalles');
        Route::post('{id_compra_carbon}/aprobar', 'aprobar_compra');
        Route::post('{id_compra_carbon}/anular', 'anular_compra');
        Route::post('{id_compra_carbon}/evidencias', 'set_evidencias_aprobacion');
    });
});