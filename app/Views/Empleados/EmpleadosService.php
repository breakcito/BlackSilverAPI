<?php

namespace App\Views\Empleados;

use App\Shared\Responses\ApiResponse;
use App\Views\Empleados\Data\EmpleadosData;

class EmpleadosService
{
    /**
     * Listar empleados de las empresas del usuario
     */
    public static function get_empleados(int $id_usuario, ?int $id_empresa = null)
    {
        return ApiResponse::success(EmpleadosData::get_empleados($id_usuario, $id_empresa));
    }

    /**
     * Obtener empresas asociadas al usuario
     */
    public static function get_empresas(int $id_usuario)
    {
        return ApiResponse::success(EmpleadosData::get_empresas($id_usuario));
    }

    /**
     * Obtener todas las áreas activas
     */
    public static function get_areas()
    {
        return ApiResponse::success(EmpleadosData::get_areas());
    }

    /**
     * Obtener cargos por área
     */
    public static function get_cargos(int $id_area)
    {
        return ApiResponse::success(EmpleadosData::get_cargos_by_area($id_area));
    }

    /**
     * Registrar un nuevo empleado
     */
    public static function crear_empleado(
        int $id_usuario,
        int $id_empresa,
        int $id_cargo,
        string $nombre,
        string $apellido,
        ?string $dni,
        ?string $ruc,
        ?string $carnet_extranjeria,
        ?string $pasaporte,
        ?string $fecha_nacimiento,
        ?string $path_foto
    ) {
        if ($dni && EmpleadosData::existe_dni($dni)) {
            return ApiResponse::error('El DNI ingresado ya se encuentra registrado.');
        }

        $id = EmpleadosData::crear_empleado(
            $id_empresa,
            $id_cargo,
            $nombre,
            $apellido,
            $dni,
            $ruc,
            $carnet_extranjeria,
            $pasaporte,
            $fecha_nacimiento,
            $path_foto
        );

        return ApiResponse::success(
            EmpleadosData::get_empleado_by_id($id_usuario, $id),
            'Empleado registrado correctamente'
        );
    }
}
