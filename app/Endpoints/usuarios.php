<?php

use App\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Login
Route::post('/login', [UsuarioController::class, 'login']);

Route::middleware('auth.jwt.custom')->group(function () {
    // Usuarios
    Route::prefix('usuarios')->controller(UsuarioController::class)->group(function () {
        Route::get('/by-empresa', 'get_usuarios_por_empresa');
    });
});
