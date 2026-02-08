<?php

use App\Modules\Menu\Presentation\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Sistema - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    // Estructura del sistema (listado completo para administración)
    Route::get('/sistema/modulos', [MenuController::class, 'modulos'])->name('sistema.modulos');

    // Menú de navegación del usuario autenticado
    Route::get('/sistema/menu', [MenuController::class, 'menu'])->name('sistema.menu');
});
