<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->prefix('api')->group(function () {
                require base_path('app/modules/usuarios/presentation/routes.php');
                require base_path('app/modules/sistema/presentation/routes.php');
                require base_path('app/modules/empresa/presentation/routes.php');
                require base_path('app/modules/roles/presentation/routes.php');
                require base_path('app/modules/empleados/presentation/routes.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
