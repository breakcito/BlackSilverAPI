<?php

use App\Modules\OrdenCompra\OrdenCompraController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('ordenes-compra')->group(function () {

        Route::controller(OrdenCompraController::class)->group(function () {
            Route::get('/', 'get_listado');
            Route::get('/detalles', 'get_detalles');
            Route::get('/seguimiento', 'get_seguimiento');
        });
    });
});
