<?php

use App\Modules\Clientes\Controllers\ClientesController;
use App\Modules\Clientes\Controllers\CuentasBancariasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('clientes')->group(function () {
        Route::controller(ClientesController::class)->group(function () {
            Route::get('/', 'get_clientes');
            Route::post('/', 'crear_cliente');
        });

        Route::prefix('cuentas-bancarias')->controller(CuentasBancariasController::class)->group(function () {
            Route::get('/{id_cliente}', 'get_cuentas_bancarias');
            Route::post('/', 'crear_cuenta_bancaria');
        });
    });
});
