<?php

use App\Modules\Empresas\Controller\EmpresasController;
use App\Modules\Empresas\Controller\OficinasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de empresas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empresas')->group(function () {
        Route::controller(EmpresasController::class)->group(function () {

            // Listar todas las empresas
            Route::get('/', 'get_empresas');

            // Crear una nueva empresa
            Route::post('/', 'crear_empresa');

            // Actualizar logo de empresa
            Route::post('{id}/logo', 'actualizar_logo');
        });

        Route::prefix('/oficinas')->controller(OficinasController::class)->group(function () {

            // Crear una nueva oficina
            Route::post('/', 'crear_oficina');
        });
    });
});
