<?php

use App\Modules\OrdenCompra\Controller\AuxController;
use App\Modules\OrdenCompra\Controller\OrdenCompraController;
use App\Modules\OrdenCompra\Controller\RecepcionesOCController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('ordenes-compra')->group(function () {

        Route::controller(OrdenCompraController::class)->group(function () {
            Route::get('/', 'get_listado');
            Route::get('/detalles', 'get_detalles');
            Route::get('/seguimiento', 'get_seguimiento');
        });

        Route::prefix('recepciones')->controller(RecepcionesOCController::class)->group(function () {
            Route::post('/', 'registrar_recepcion');
            Route::get('/{id}', 'get_historial');
        });

        Route::prefix('aux')->controller(AuxController::class)->group(function () {
            Route::get('/almacenes', 'get_almacenes');
            Route::get('/lotes-destino', 'get_lotes_disponibles');
        });
    });
});
