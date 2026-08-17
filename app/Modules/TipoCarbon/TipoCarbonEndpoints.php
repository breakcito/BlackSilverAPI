<?php

use App\Modules\TipoCarbon\Controllers\TipoCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('tipo-carbon')->controller(TipoCarbonController::class)->group(function () {
        Route::get('/', 'get_tipos');
        Route::post('/', 'crear_tipo');
        Route::get('{id_tipo_carbon}', 'get_tipo_by_id_route');
        Route::put('{id_tipo_carbon}', 'actualizar_tipo');
        Route::delete('{id_tipo_carbon}', 'eliminar_tipo');

        Route::get('{id_tipo_carbon}/variantes', 'get_variantes');
        Route::get('{id_tipo_carbon}/variantes-opciones', 'get_variantes_opciones');
        Route::put('{id_tipo_carbon}/variantes', 'set_variantes');
    });
});
