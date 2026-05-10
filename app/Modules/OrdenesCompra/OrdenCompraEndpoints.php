<?php

use App\Modules\OrdenesCompra\Controller\OrdenCompraController;
use App\Modules\OrdenesCompra\Controller\RecepcionesOCController;
use App\Modules\OrdenesCompra\Controller\TransferenciaController;
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

        Route::prefix('transferencias')->controller(TransferenciaController::class)->group(function () {
            Route::post('/', 'registrar_transferencia');
        });
    });
});
