<?php

namespace App\Modules\Empleados;

use App\Shared\Responses\ApiResponse;
use App\Modules\Empleados\Data\EmpleadosData;
use Illuminate\Http\UploadedFile;
use App\Services\EmpleadosService as EmpleadosServiceGlobal;

class EmpleadosService
{
    /**
     * Listar empleados
     */
    public static function get_empleados(?int $id_empresa = null)
    {
        $empleados = EmpleadosData::get_empleados(id_empresa: $id_empresa);
        return ApiResponse::success($empleados);
    }

    /**
     * Registrar un nuevo empleado
     */
    public static function crear_empleado(
        int $id_cargo,
        string $nombre,
        string $apellido,
        ?int $id_empresa = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?UploadedFile $foto = null
    ) {
        $response = EmpleadosServiceGlobal::crear_empleado(
            id_empresa: $id_empresa,
            id_cargo: $id_cargo,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            foto: $foto
        );

        if ($response['success']) {
            $id = $response['data'];
            $new_empleado = EmpleadosData::get_empleados(id_empleado: $id);
            return ApiResponse::success(
                $new_empleado,
                'Empleado registrado correctamente'
            );
        }

        return $response;
    }
}
