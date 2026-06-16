<?php

use App\Modules\ProduccionMineral\Controller\ProduccionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('produccion-mineral')->group(function () {
        Route::controller(ProduccionController::class)->group(function () {
            Route::post('/iniciar', 'iniciar_produccion');
            Route::post('/finalizar', 'finalizar_produccion');
            Route::get('/resumen', 'get_resumen');
        });
    });
});
