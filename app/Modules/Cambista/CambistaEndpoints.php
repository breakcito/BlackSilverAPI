<?php

use App\Modules\Cambista\CambistaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints del catalogo simple de cambistas
|--------------------------------------------------------------------------
| - GET  /api/cambistas         -> listar todos (ordenados por nombre)
| - POST /api/cambistas         -> crear (solo pide nombre)
|
| La tabla `cambista` solo tiene `id` y `nombre`. Sin soft-delete por
| diseno: es un catalogo chico de referencia.
*/
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('cambistas')->controller(CambistaController::class)->group(function () {
        Route::get('/', 'listar');
        Route::post('/', 'crear');
    });
});
