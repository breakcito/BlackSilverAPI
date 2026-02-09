<?php

use App\Modules\Menu\Presentation\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Sistema - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('jwt.auth')->group(function () {
    // Menú de navegación del usuario autenticado
    Route::get('/sistema/menu', [MenuController::class, 'get_menu_navegacion'])->name('sistema.menu');
});
