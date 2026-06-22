<?php

use App\Modules\Contratistas\ContratistasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('contratistas')->controller(ContratistasController::class)->group(function () {
        Route::get('/', 'get_contratistas');
        Route::post('/', 'crear_contratista');
        Route::post('{id}/foto', 'actualizar_foto');
        Route::post('{id}/labores', 'asignar_labores');
    });
});
