<?php

use App\Modules\Menu\Presentation\MenuController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth.jwt.custom')->group(function () {
    Route::get('/menu_navegacion', [MenuController::class, 'get_menu_navegacion_by_rol']);
});
