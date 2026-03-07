

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
        // Obtener historial de responsables de un almacen
        Route::get('/responsables', 'get_historial_responsables');
        // Asignar un nuevo responsable de almacen
        Route::post('/nuevo-responsable', 'nuevo_responsable');
        // Listar las minas que abstece un almacen
        Route::get('/minas', 'get_minas_abastecidas');
        // Asignar nueva mina por abastecer
        Route::post('/abastecer-mina', 'nueva_mina_por_abastecer');
        // Dejar de abastecer a una mina
        Route::post('/eliminar-abastecimiento-mina', 'eliminar_abastecimiento_mina');
    });
});
