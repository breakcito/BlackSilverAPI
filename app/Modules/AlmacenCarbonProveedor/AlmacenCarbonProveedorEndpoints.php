<?php

use App\Modules\AlmacenCarbonProveedor\Controllers\AlmacenCarbonProveedorController;
use Illuminate\Support\Facades\Route;

/**
 * CRUD de almacenes de carbon asignados a un proveedor (modulo carbon).
 *
 * Cada almacen pertenece a un solo proveedor (1:N). `id_proveedor` viaja en
 * la ruta para que el listado y la validacion vivan en el mismo contexto.
 *
 * - GET    /api/proveedores/{id_proveedor}/almacenes-carbon   -> listar
 * - POST   /api/proveedores/{id_proveedor}/almacenes-carbon   -> crear
 * - PUT    /api/proveedores/{id_proveedor}/almacenes-carbon/{id_almacen} -> actualizar
 * - DELETE /api/proveedores/{id_proveedor}/almacenes-carbon/{id_almacen} -> soft delete
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('proveedores')->controller(AlmacenCarbonProveedorController::class)->group(function () {
        Route::get('{id_proveedor}/almacenes-carbon', 'get_almacenes_por_proveedor');
        Route::post('{id_proveedor}/almacenes-carbon', 'crear_almacen');
        Route::put('{id_proveedor}/almacenes-carbon/{id_almacen}', 'actualizar_almacen');
        Route::delete('{id_proveedor}/almacenes-carbon/{id_almacen}', 'eliminar_almacen');
    });
});
