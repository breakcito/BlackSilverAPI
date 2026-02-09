<?php

use App\Modules\Usuarios\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Login (sin autenticación)
Route::post('/login', [UsuarioController::class, 'login']);

// Requieren autenticación JWT
Route::middleware('jwt.auth')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
});
