<?php

use App\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Login (sin autenticación)
Route::post('/login', [UsuarioController::class, 'login']);

// Requieren autenticación JWT
Route::middleware('auth.jwt.custom')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
});
