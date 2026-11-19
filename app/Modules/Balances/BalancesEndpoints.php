<?php

use App\Modules\Balances\Controller\BalancesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Balances - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('balances')->controller(BalancesController::class)->group(function () {
        Route::get('/', 'get_balance');
    });
});
