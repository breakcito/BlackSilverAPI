<?php

use App\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Login
Route::post('/login', [UsuarioController::class, 'login']);

Route::middleware('auth.jwt.custom')->group(function () {
});
