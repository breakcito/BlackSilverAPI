<?php

namespace App\Modules\AlmacenCarbonProveedor\Services;

use App\Modules\AlmacenCarbonProveedor\Data\AlmacenCarbonProveedorData;
use App\Shared\Responses\ApiResponse;

class AlmacenCarbonProveedorService
{
    /**
     * Lista los almacenes de carbon asociados a un proveedor.
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $data = AlmacenCarbonProveedorData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Almacenes de carbon del proveedor');
    }

    /**
     * Inserta un nuevo almacen de carbon para el proveedor.
     * Direccion obligatoria; los ids de ubigeo son opcionales.
     */
    public static function crear(
        int $id_proveedor,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): array {
        $direccion = trim($direccion);
        if ($direccion === '') {
            return ApiResponse::error('La direccion es obligatoria');
        }

        $id = AlmacenCarbonProveedorData::insertar(
            $id_proveedor,
            $id_departamento,
            $id_provincia,
            $id_distrito,
            $direccion
        );

        $item = AlmacenCarbonProveedorData::get_por_id($id);
        return ApiResponse::success($item, 'Almacen de carbon registrado correctamente');
    }

    /**
     * Actualiza un almacen existente del proveedor.
     */
    public static function actualizar(
        int $id_almacen,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): array {
        $existe = AlmacenCarbonProveedorData::get_por_id($id_almacen);
        if (! $existe) {
            return ApiResponse::error('Almacen de carbon no encontrado');
        }

        $direccion = trim($direccion);
        if ($direccion === '') {
            return ApiResponse::error('La direccion es obligatoria');
        }

        AlmacenCarbonProveedorData::actualizar(
            $id_almacen,
            $id_departamento,
            $id_provincia,
            $id_distrito,
            $direccion
        );

        $item = AlmacenCarbonProveedorData::get_por_id($id_almacen);
        return ApiResponse::success($item, 'Almacen de carbon actualizado correctamente');
    }

    /**
     * Desactivar (soft delete) un almacen del proveedor.
     */
    public static function eliminar(int $id_almacen): array
    {
        $existe = AlmacenCarbonProveedorData::get_por_id($id_almacen);
        if (! $existe) {
            return ApiResponse::error('Almacen de carbon no encontrado');
        }

        AlmacenCarbonProveedorData::eliminar($id_almacen);
        $item = AlmacenCarbonProveedorData::get_por_id($id_almacen);
        return ApiResponse::success($item, 'Almacen de carbon eliminado correctamente');
    }
}
