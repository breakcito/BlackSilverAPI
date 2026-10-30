<?php

use App\Modules\AnticiposCliente\Controllers\AnticiposClienteController;
use Illuminate\Support\Facades\Route;

/**
 * CRUD de anticipos RECIBIDOS de un cliente de carbon.
 *
 * - GET    /api/clientes/{id_cliente}/anticipos
 * - POST   /api/clientes/{id_cliente}/anticipos
 * - POST   /api/clientes/{id_cliente}/anticipos/{id_anticipo}/anular
 *
 * Sin DELETE ni PUT: el anticipo es inmutable (kardex), solo se anula.
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('clientes')->controller(AnticiposClienteController::class)->group(function () {
        Route::get('{id_cliente}/anticipos', 'listar_por_cliente');
        Route::post('{id_cliente}/anticipos', 'registrar');
        Route::post('{id_cliente}/anticipos/{id_anticipo}/anular', 'anular');
    });
});
