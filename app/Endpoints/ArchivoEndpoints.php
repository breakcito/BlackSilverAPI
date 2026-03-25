<?php

use App\Controllers\ArchivoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::get('/download-archivo', [ArchivoController::class, 'download_archivo']);
});
