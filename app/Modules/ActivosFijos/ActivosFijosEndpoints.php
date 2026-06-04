<?php

use App\Modules\ActivosFijos\Controller\ActivosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Activos Fijos - Rutas
|--------------------------------------------------------------------------
|*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('activos-fijos')->controller(ActivosController::class)->group(function () {
        Route::get('/', 'get_activos');
        Route::post('/', 'crear_activo');
        Route::post('/ubicacion', 'actualizar_ubicacion');
        Route::post('/configurar-alertas', 'configurar_alertas');
        Route::post('/mantenimiento', 'registrar_mantenimiento');
    });
});
