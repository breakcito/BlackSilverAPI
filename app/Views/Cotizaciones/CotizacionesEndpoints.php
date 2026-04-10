<?php

use App\Views\Cotizaciones\CotizacionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('cotizaciones')->group(function () {
        
        Route::controller(CotizacionesController::class)->group(function () {
            Route::get('/', 'get_listado');
            Route::post('/registrar', 'registrar_comparativo');
        });

    });
});
