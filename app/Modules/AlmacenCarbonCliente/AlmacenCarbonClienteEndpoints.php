<?php

use App\Modules\AlmacenCarbonCliente\Controllers\AlmacenCarbonClienteController;
use Illuminate\Support\Facades\Route;

/**
 * CRUD de almacenes de carbon asignados a un cliente (modulo carbon).
 *
 * Cada almacen pertenece a un solo cliente (1:N). `id_cliente` viaja en
 * la ruta para que el listado y la validacion vivan en el mismo contexto.
 *
 * - GET    /api/clientes/{id_cliente}/almacenes-carbon   -> listar
 * - POST   /api/clientes/{id_cliente}/almacenes-carbon   -> crear
 * - PUT    /api/clientes/{id_cliente}/almacenes-carbon/{id_almacen} -> actualizar
 * - DELETE /api/clientes/{id_cliente}/almacenes-carbon/{id_almacen} -> soft delete
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('clientes')->controller(AlmacenCarbonClienteController::class)->group(function () {
        Route::get('almacenes-carbon', 'get_todos_almacenes');
        Route::get('{id_cliente}/almacenes-carbon', 'get_almacenes_por_cliente');
        Route::post('{id_cliente}/almacenes-carbon', 'crear_almacen');
        Route::put('{id_cliente}/almacenes-carbon/{id_almacen}', 'actualizar_almacen');
        Route::delete('{id_cliente}/almacenes-carbon/{id_almacen}', 'eliminar_almacen');
    });
});