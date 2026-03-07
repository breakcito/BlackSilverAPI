<?php


use App\Views\SolicitudesReabastecimiento\SolicitudesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de solicitudes de reabastecimiento
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('solicitudes-reabastecimiento')->controller(SolicitudesController::class)->group(function () {
        // Obtener todas la lista de solicitudes en base al almacen solicitante
        // dentro de un periodo de tiempo (mes y año)
        Route::get('/', 'get_solicitudes');
        // Registrar una solicitud y sus detalles
        Route::post('/', 'crear_solicitud');
        // Obtener los detalles de una solicitud
        Route::get('/detalles-solicitud', 'get_detalles_solicitud');
        // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
        Route::get('/productos', 'get_productos');
        // Obtener la lista de almacenes en las que el empleado
        // solicitante es reesponsable
        Route::get('/almacenes', 'get_almacenes');
        // Listar unidades de medida.
        Route::get('/unidades-medida', 'get_unidades_medida');
    });
});
