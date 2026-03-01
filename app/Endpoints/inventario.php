<?php

use App\Controllers\CategoriaController;
use App\Controllers\KardexController;
use App\Controllers\LoteController;
use App\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Inventario - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Categorias
    Route::get('/categorias', [CategoriaController::class, 'get_categorias']);
    Route::post('/categorias', [CategoriaController::class, 'crear_categoria']);
    Route::get('/categoria', [CategoriaController::class, 'get_categoria_by_id']);
    Route::put('/categoria', [CategoriaController::class, 'update_categoria']);
    Route::delete('/categoria', [CategoriaController::class, 'delete_categoria']);

    // Productos (Catálogo)
    Route::get('/productos', [ProductoController::class, 'get_productos']);
    Route::post('/productos', [ProductoController::class, 'crear_producto']);

    // Lotes y Stock
    Route::get('/lotes-almacen', [LoteController::class, 'get_lotes_by_almacen']); // ?id_almacen=X
    Route::post('/lotes', [LoteController::class, 'crear_lote']);
    Route::get('/lotes/productos-disponibles', [LoteController::class, 'get_productos_para_lote']); // Para el select de nuevo lote
    Route::get('/unidades-medida', [LoteController::class, 'get_unidades_medida']); // Para el select de unidad

    // Kardex
    Route::get('/kardex', [KardexController::class, 'get_movimientos']); // ?id_lote=X
});
