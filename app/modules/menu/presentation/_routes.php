<?php

use App\Modules\Sistema\Presentation\Controllers\SistemaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Sistema - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    // Estructura del sistema (listado completo para administración)
    Route::get('/sistema/modulos', [SistemaController::class, 'modulos'])->name('sistema.modulos');

    // Menú de navegación del usuario autenticado
    Route::get('/sistema/menu', [SistemaController::class, 'menu'])->name('sistema.menu');
});
