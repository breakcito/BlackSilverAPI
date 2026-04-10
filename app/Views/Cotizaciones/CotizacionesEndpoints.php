<?php

use App\Views\Cotizaciones\CotizacionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('cotizaciones')->group(function () {
        
        Route::controller(CotizacionesController::class)->group(function () {
            Route::get('/', 'get_listado');
            Route::post('/registrar', 'registrar_comparativo');
            
            // Endpoints maestros para independencia del módulo
            Route::get('/unidades-medida', 'get_unidades_medida');
            Route::get('/productos', 'get_productos');
            Route::get('/proveedores', 'get_proveedores');
        });

    });
});
