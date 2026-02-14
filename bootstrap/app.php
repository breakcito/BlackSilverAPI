<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->prefix('api')->group(function () {
                require base_path('app/modules/usuarios/controllers/_routes.php');
                require base_path('app/modules/empresas/controllers/_routes.php');
                require base_path('app/modules/menu/controllers/_routes.php');
                require base_path('app/modules/inventario/controllers/_routes.php');
                require base_path('app/modules/personal/controllers/_routes.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt.custom' => \App\Http\Middleware\JwtAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
