<?php

use App\Controllers\AuxController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('aux')->group(function () {
        Route::controller(AuxController::class)->group(function () {
            // almacenes
            Route::get('/almacenes', 'get_almacenes');

            // personal externo
            Route::get('/personal-externo', 'get_personal_externo');
            Route::post('/personal-externo', 'crear_personal_externo');

            // lotes disponibles de un almacen
            Route::get('/lotes', 'get_lotes_disponibles');

            // empleados
            Route::get('/empleados', 'get_empleados');

            // unidades de medida
            Route::get('/unidades-medida', 'get_unidades_medida');

            // proveedores
            Route::get('/proveedores', 'get_proveedores');
            Route::post('/proveedores', 'crear_proveedor');

            // empresas
            Route::get('/empresas', 'get_empresas');

            // productos
            Route::get('/productos', 'get_productos');

            // minas
            Route::get('/minas', 'get_minas');

            // marcas
            Route::get('/marcas', 'get_marcas');
            Route::post('/marcas', 'crear_marca');

            // activos fijos disponibles
            Route::get('/activos-disponibles', 'get_activos_disponibles');

            // labores
            Route::get('/labores', 'get_labores');

            // contratistas
            Route::get('/contratistas', 'get_contratistas');
        });
    });
});
