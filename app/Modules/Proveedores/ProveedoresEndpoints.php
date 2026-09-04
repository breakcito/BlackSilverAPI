<?php

use App\Modules\Proveedores\Controllers\CuentasBancariasController;
use App\Modules\Proveedores\Controllers\ProveedoresController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('proveedores')->group(function () {
        Route::controller(ProveedoresController::class)->group(function () {
            Route::get('/', 'get_proveedores');
            Route::post('/', 'crear_proveedor');
        });

        Route::prefix('cuentas-bancarias')->controller(CuentasBancariasController::class)->group(function () {
            Route::get('/{id_proveedor}', 'get_cuentas_bancarias');
            Route::post('/', 'crear_cuenta_bancaria');
            Route::put('/{id_cuenta_bancaria}', 'actualizar_cuenta_bancaria');
        });

        // Comodin {id_proveedor} al final: Laravel resuelve primero los
        // segmentos literales (cuentas-bancarias, tipos-carbon,
        // lugares-extraccion), pero se declara aqui para dejarlo explicito.
        Route::controller(ProveedoresController::class)->group(function () {
            Route::put('/{id_proveedor}', 'actualizar_proveedor');
            Route::delete('/{id_proveedor}', 'eliminar_proveedor');
        });
    });
});