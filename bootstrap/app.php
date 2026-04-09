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
                require base_path('app/Views/Login/LoginEndpoints.php');
                require base_path('app/Endpoints/MenuNavEndpoints.php');
                require base_path('app/Endpoints/ArchivoEndpoints.php');
                require base_path('app/Views/Almacenes/AlmacenesEndpoints.php');
                require base_path('app/Views/Categorias/CategoriasEndpoints.php');
                require base_path('app/Views/Empresas/EmpresasEndpoints.php');
                require base_path('app/Views/Organigrama/OrganigramaEndpoints.php');
                require base_path('app/Views/Concesiones/ConcesionesEndpoints.php');
                require base_path('app/Views/Empleados/EmpleadosEndpoints.php');
                require base_path('app/Views/Productos/ProductosEndpoints.php');
                require base_path('app/Views/MinasLabores/MinasLaboresEndpoints.php');
                require base_path('app/Views/LotesProductos/LotesEndpoints.php');
                require base_path('app/Views/RequerimientosAlmacen/RequerimientosEndpoints.php');
                require base_path('app/Views/RequerimientosAlmacenAtencion/RequerimientosAtencionEndpoints.php');
                require base_path('app/Views/KardexProductos/KardexEndpoints.php');
                require base_path('app/Views/Roles/RolesEndpoints.php');
                require base_path('app/Views/Cuentas/CuentasEndpoints.php');
                require base_path('app/Views/Perfil/PerfilEndpoints.php');
                require base_path('app/Views/SolicitudesReabastecimiento/SolicitudesEndpoints.php');
                require base_path('app/Views/SolicitudesReabastecimientoAtencion/SolicitudesAtencionEndpoints.php');
                require base_path('app/Views/PrestamosAlmacenAtencion/PrestamosAtencionEndpoints.php');
                require base_path('app/Views/PrestamosAlmacen/PrestamosAlmacenEndpoints.php');
                require base_path('app/Views/Proveedores/ProveedoresEndpoints.php');
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
