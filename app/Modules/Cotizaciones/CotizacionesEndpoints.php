<?php


use App\Modules\Cotizaciones\Controller\CotizacionesController;
use App\Modules\Cotizaciones\Controller\OrdenesCompraController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('cotizaciones')->group(function () {

        Route::controller(CotizacionesController::class)->group(function () {
            Route::get('/', 'get_listado');
            Route::post('/registrar', 'registrar_comparativo');
            Route::put('/{id}', 'actualizar_cotizacion');
            Route::post('/{id}/aprobar', 'aprobar_cotizacion_parcial');
        });


        Route::prefix('ordenes-compra')->controller(OrdenesCompraController::class)->group(function () {
            Route::get('/{id_orden_compra}', 'get_orden_compra');
        });
    });
});
