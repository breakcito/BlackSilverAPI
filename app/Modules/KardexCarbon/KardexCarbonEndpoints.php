<?php

use App\Modules\KardexCarbon\Controller\KardexCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('kardex-carbon')->controller(KardexCarbonController::class)->group(function () {
        Route::get('/', 'get_movimientos');
    });
});
