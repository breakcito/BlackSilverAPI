<?php

use App\Views\Roles\RolesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('roles')->controller(RolesController::class)->group(function () {
        
        // Listar roles
        Route::get('/', 'get_roles');
        
        // Obtener la estructura de modulos, submodulos y secciones
        Route::get('/estructura-permisos', 'get_estructura_permisos');

        // Crear un nuevo rol
        Route::post('/', 'crear_rol');

    });
});
