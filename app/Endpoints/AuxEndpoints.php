<?php

use App\Modules\SolicitudesReabastecimientoAtencion\Controller\AuxController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('aux')->group(function () {
        Route::controller(AuxController::class)->group(function () {
            // listar almacenes autorizados o todos, principales o secundarios
            Route::get('/almacenes', 'get_almacenes');

            // listar y crear personal externo
            Route::get('/personal-externo', 'get_personal_externo');
            Route::post('/personal-externo', 'crear_personal_externo');

            // listar lotes disponibles de un almacen en base a una lista de productos
            Route::get('/lotes', 'get_lotes_disponibles');
        });
    });
});
