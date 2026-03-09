<?php

namespace App\Views\Productos;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Inventario - Catálogo de Productos
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('productos')->controller(ProductosController::class)->group(function () {
        Route::get('/', 'get_productos');
        Route::post('/', 'crear_producto');
        Route::get('/unidades-medida', 'get_unidades_medida');
        Route::get('/categorias', 'get_categorias');
    });
});
