<?php

namespace App\Views\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Data\AlmacenesData;

class AlmacenesService
{
    public function __construct(
        private AlmacenesData $data,
    ) {}

    /**
     * Listar un resumen de todos los almacenes
     */
    public function get_almacenes()
    {
        $almacenes = $this->data->get_almacenes();
        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén.
     * @param string $nombre
     * @param mixed $descripcion
     * @param bool $es_principal
     */
    public function crear_almacen(string $nombre, ?string $descripcion = null, bool $es_principal)
    {
        if ($this->data->verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = $this->data->crear_almacen($nombre, $descripcion, $es_principal);
        $nuevoAlmacen = $this->data->get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }
}
