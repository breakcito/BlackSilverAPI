<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\AlmacenesData;

class AlmacenesService
{
    public static function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    public static function crear_almacen(string $nombre, bool $es_principal, ?string $descripcion = null)
    {
        if (AlmacenesData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = AlmacenesData::crear_almacen($nombre, $descripcion, $es_principal);
        $nuevoAlmacen = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }
}
