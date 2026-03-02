<?php

use App\Middlewares\JwtAuthMiddleware;
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
                require base_path('app/Endpoints/menu-navegacion.php');
                // Configuracion
                require base_path('app/Endpoints/empresas.php');
                require base_path('app/Endpoints/personal.php');
                require base_path('app/Endpoints/usuarios.php');
                // Logistica
                require base_path('app/Endpoints/inventario.php');
                require base_path('app/Endpoints/requerimientos-almacen.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt.custom' => JwtAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
