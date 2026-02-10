<?php

use App\Modules\Inventario\Controllers\CategoriaController;
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
    Route::get('/categorias/{id}', [CategoriaController::class, 'get_categoria_by_id']);
    Route::put('/categorias/{id}', [CategoriaController::class, 'update_categoria']);
    Route::delete('/categorias/{id}', [CategoriaController::class, 'delete_categoria']);
});
