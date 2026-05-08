<?php

namespace App\Modules\Productos;

use App\Modules\Productos\Controller\AuxController;
use App\Modules\Productos\Controller\ProductosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Inventario - Catálogo de Productos
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('productos')->group(function () {

        Route::controller(ProductosController::class)->group(function () {
            Route::get('/', 'get_productos');
            Route::post('/', 'crear_producto');
        });

        Route::prefix('aux')->controller(AuxController::class)->group(function () {
            Route::get('/unidades-medida', 'get_unidades_medida');
            Route::get('/categorias', 'get_categorias');
        });
    });
});
