<?php

use App\Views\Categorias\CategoriasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de categorías
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('categorias')->controller(CategoriasController::class)->group(function () {

        // Listar todas las categorías
        Route::get('/', 'get_categorias');

        // Crear una nueva categoría
        Route::post('/', 'crear_categoria');

        // Actualizar destinos de consumo de un insumo
        Route::post('/actualizar-consumidoras', 'actualizar_consumidoras');
    });
});
