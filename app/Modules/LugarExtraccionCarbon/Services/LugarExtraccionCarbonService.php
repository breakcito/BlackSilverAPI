<?php

namespace App\Modules\LugarExtraccionCarbon\Services;

use App\Modules\LugarExtraccionCarbon\Data\LugarExtraccionCarbonData;
use App\Shared\Responses\ApiResponse;

class LugarExtraccionCarbonService
{
    /**
     * Lista los lugares de extraccion de un proveedor.
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $data = LugarExtraccionCarbonData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Lugares de extraccion del proveedor');
    }

    /**
     * Reemplaza el set de lugares asociados al proveedor.
     * @param int[] $lugares Lista de {id_departamento, id_provincia, id_distrito, direccion}
     */
    public static function set_para_proveedor(int $id_proveedor, array $lugares): array
    {
        LugarExtraccionCarbonData::set_para_proveedor($id_proveedor, $lugares);
        $data = LugarExtraccionCarbonData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Lugares de extraccion actualizados');
    }

    /**
     * Inserta un nuevo lugar de extraccion para un proveedor sin tocar los
     * existentes. Devuelve el registro recien creado (incluye su id).
     */
    public static function insertar(
        int $id_proveedor,
        int $id_departamento,
        int $id_provincia,
        int $id_distrito,
        string $direccion,
    ): array {
        $direccion = trim($direccion);
        if ($id_proveedor <= 0) {
            return ApiResponse::error('Proveedor requerido');
        }
        if ($id_departamento <= 0 || $id_provincia <= 0 || $id_distrito <= 0) {
            return ApiResponse::error('Departamento, provincia y distrito son requeridos');
        }
        if ($direccion === '') {
            return ApiResponse::error('La direccion es obligatoria');
        }

        $id = LugarExtraccionCarbonData::insertar(
            $id_proveedor,
            $id_departamento,
            $id_provincia,
            $id_distrito,
            $direccion,
        );

        $lugar = LugarExtraccionCarbonData::get_por_id($id);
        return ApiResponse::success($lugar, 'Lugar de extraccion registrado correctamente');
    }
}