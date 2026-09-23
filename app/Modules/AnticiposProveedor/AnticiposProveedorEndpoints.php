<?php

use App\Modules\AnticiposProveedor\Controllers\AnticiposProveedorController;
use Illuminate\Support\Facades\Route;

/**
 * CRUD de anticipos otorgados a un proveedor (modulo carbon).
 *
 * - GET    /api/proveedores/{id_proveedor}/anticipos
 * - POST   /api/proveedores/{id_proveedor}/anticipos
 * - POST   /api/proveedores/{id_proveedor}/anticipos/{id_anticipo}/anular
 *
 * Sin DELETE ni PUT: el anticipo es inmutable (kardex), solo se anula.
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('proveedores')->controller(AnticiposProveedorController::class)->group(function () {
        Route::get('{id_proveedor}/anticipos', 'listar_por_proveedor');
        Route::post('{id_proveedor}/anticipos', 'registrar');
        Route::post('{id_proveedor}/anticipos/{id_anticipo}/anular', 'anular');
    });
});
