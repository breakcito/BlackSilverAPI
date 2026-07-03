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
            Route::post('/empleados', 'crear_empleado');

            // areas y cargos
            Route::get('/areas', 'get_areas');
            Route::get('/cargos', 'get_cargos');

            // roles
            Route::get('/roles-disponibles', 'get_roles_disponibles');

            // bancos
            Route::get('/bancos', 'get_bancos');
            Route::post('/bancos', 'crear_banco');

            // unidades de medida
            Route::get('/unidades-medida', 'get_unidades_medida');

            // categorias
            Route::get('/categorias', 'get_categorias');
            Route::post('/categorias', 'crear_categoria');

            // proveedores
            Route::get('/proveedores', 'get_proveedores');
            Route::post('/proveedores', 'crear_proveedor');

            // agencias de transporte
            Route::get('/agencias-transporte', 'get_agencias_transporte');
            Route::post('/agencias-transporte', 'crear_agencia_transporte');

            // empresas
            Route::get('/empresas', 'get_empresas');

            // productos
            Route::get('/productos', 'get_productos');
            Route::post('/productos', 'crear_producto');

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

            // lotes de mineral
            Route::get('/lotes-mineral', 'get_lotes_mineral');

            // contratos de empleado (catálogos)
            Route::get('/tipos-contrato', 'get_tipos_contrato');
            Route::get('/periodos-duracion', 'get_periodos_duracion');
        });
    });
});
