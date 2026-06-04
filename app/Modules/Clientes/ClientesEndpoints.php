<?php

use App\Modules\Clientes\Controllers\ClientesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('clientes')->controller(ClientesController::class)->group(function () {
        Route::get('/', 'get_clientes');
        Route::post('/', 'crear_cliente');
    });
});
