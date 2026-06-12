<?php

use App\Modules\LoteMineral\Controller\LoteMineralController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Lote Mineral - Rutas
|--------------------------------------------------------------------------
|*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('lote-mineral')->controller(LoteMineralController::class)->group(function () {
        Route::get('/', 'get_lotes');
        Route::post('/', 'registrar_lote');
    });
});
