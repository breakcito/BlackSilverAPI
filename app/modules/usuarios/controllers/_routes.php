<?php

use App\Modules\Usuarios\Presentation\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Usuarios - Rutas
|--------------------------------------------------------------------------
*/

// Login (sin autenticación)
Route::post('/login', [UsuarioController::class, 'login'])->name('usuarios.login');

/*
|--------------------------------------------------------------------------
| Usuarios CRUD Routes (requieren autenticación JWT)
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
});
