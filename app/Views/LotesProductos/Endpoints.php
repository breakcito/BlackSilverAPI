
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
    Route::prefix('categorias')->controller(CategoriaController::class)->group(function () {
        Route::get('/', 'get_categorias');
        Route::post('/', 'crear_categoria');
        Route::get('/by-id', 'get_categoria_by_id');
        Route::put('/', 'update_categoria');
        Route::delete('/', 'delete_categoria');
    });

    // Productos (Catálogo)
    Route::prefix('productos')->controller(ProductoController::class)->group(function () {
        Route::get('/', 'get_productos');
        Route::post('/', 'crear_producto');
        Route::get('/unidades-base', 'get_unidades_medida_base');
    });

    // Lotes y Stock
    Route::prefix('lotes')->controller(LoteController::class)->group(function () {
        Route::get('/by-almacen', 'get_lotes_by_almacen');
        Route::post('/', 'crear_lote');
        Route::get('/productos-disponibles', 'get_productos_para_lote');
        Route::get('/unidades-medida', 'get_unidades_medida');
        Route::post('/ajustar-stock', 'ajustar_stock');
    });

    // Kardex
    Route::prefix('kardex')->controller(KardexController::class)->group(function () {
        Route::get('/', 'get_movimientos');
    });
});
