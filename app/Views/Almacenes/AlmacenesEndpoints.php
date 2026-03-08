

<?php

use App\Views\Almacenes\AlmacenesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de almacenes
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('almacenes')->controller(AlmacenesController::class)->group(function () {

        // Listar un resumen de todos los almacenes
        Route::get('/', 'get_almacenes');

        // Crear un nuevo almacén.
        Route::post('/', 'crear_almacen');


        // Responsables
        Route::prefix('responsables')->group(function () {
            // Obtener historial de responsables de un almacen
            Route::get('/{id_almacen}', 'get_historial_responsables');

            // Asignar un nuevo responsable de almacen
            Route::post('/', 'nuevo_responsable');

            // Listar empleados disponibles para asignar como responsable de almacen
            Route::post('/empleados/{id_almacen}', 'get_empleados');
        });


        // Abastecimiento de minas
        Route::prefix('abastecimiento-minas')->group(function () {
            // Listar las minas que abstece un almacen
            Route::get('/{id_almacen}', 'get_minas_abastecidas');

            // Asignar nueva mina por abastecer
            Route::post('/', 'nueva_mina_por_abastecer');

            // Dejar de abastecer a una mina
            Route::delete('/{id_almacen_mina}', 'eliminar_abastecimiento_mina');

            // Listar las minas disponibles para abastecer
            Route::get('/minas/{id_almacen}', 'get_minas');
        });
    });
});
