<?php

use App\Modules\TamizajeCarbon\Controller\TamizajeCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('tamizaje-carbon')->controller(TamizajeCarbonController::class)->group(function () {
        Route::get('/', 'get_tamizajes');
        Route::post('/', 'registrar_tamizaje');
        Route::get('stocks', 'get_stocks');
        Route::put('stocks/{id_stock_carbon}', 'actualizar_stock');
        Route::get('cargas-pendientes', 'get_cargas_pendientes');
    });
});
