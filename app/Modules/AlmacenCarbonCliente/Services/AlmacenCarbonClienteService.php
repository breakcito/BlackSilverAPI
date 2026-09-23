<?php

namespace App\Modules\AlmacenCarbonCliente\Services;

use App\Modules\AlmacenCarbonCliente\Data\AlmacenCarbonClienteData;
use App\Shared\Responses\ApiResponse;

class AlmacenCarbonClienteService
{
    /**
     * Lista los almacenes de carbon asociados a un cliente.
     */
    public static function get_por_cliente(int $id_cliente): array
    {
        $data = AlmacenCarbonClienteData::get_por_cliente($id_cliente);
        return ApiResponse::success($data, 'Almacenes de carbon del cliente');
    }

    /**
     * Inserta un nuevo almacen de carbon para el cliente.
     * Direccion obligatoria; los ids de ubigeo son opcionales.
     */
    public static function crear(
        int $id_cliente,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): array {
        $direccion = trim($direccion);
        if ($direccion === '') {
            return ApiResponse::error('La direccion es obligatoria');
        }

        $id = AlmacenCarbonClienteData::insertar(
            $id_cliente,
            $id_departamento,
            $id_provincia,
            $id_distrito,
            $direccion
        );

        $item = AlmacenCarbonClienteData::get_por_id($id);
        return ApiResponse::success($item, 'Almacen de carbon registrado correctamente');
    }

    /**
     * Actualiza un almacen existente del cliente.
     */
    public static function actualizar(
        int $id_almacen,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): array {
        $existe = AlmacenCarbonClienteData::get_por_id($id_almacen);
        if (! $existe) {
            return ApiResponse::error('Almacen de carbon no encontrado');
        }

        $direccion = trim($direccion);
        if ($direccion === '') {
            return ApiResponse::error('La direccion es obligatoria');
        }

        AlmacenCarbonClienteData::actualizar(
            $id_almacen,
            $id_departamento,
            $id_provincia,
            $id_distrito,
            $direccion
        );

        $item = AlmacenCarbonClienteData::get_por_id($id_almacen);
        return ApiResponse::success($item, 'Almacen de carbon actualizado correctamente');
    }

    /**
     * Desactivar (soft delete) un almacen del cliente.
     */
    public static function eliminar(int $id_almacen): array
    {
        $existe = AlmacenCarbonClienteData::get_por_id($id_almacen);
        if (! $existe) {
            return ApiResponse::error('Almacen de carbon no encontrado');
        }

        AlmacenCarbonClienteData::eliminar($id_almacen);
        $item = AlmacenCarbonClienteData::get_por_id($id_almacen);
        return ApiResponse::success($item, 'Almacen de carbon eliminado correctamente');
    }
}