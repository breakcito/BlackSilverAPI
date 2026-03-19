<?php

namespace App\Views\Empleados;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\Empleados\Data\EmpleadosData;
use Illuminate\Http\UploadedFile;

class EmpleadosService
{
    /**
     * Listar empleados de las empresas del usuario
     */
    public static function get_empleados(?int $id_empresa = null)
    {
        $empleados = EmpleadosData::get_empleados(id_empresa: $id_empresa);

        // Convertir path_foto a URL completa
        foreach ($empleados as $empleado) {
            if ($empleado->path_foto) {
                $empleado->path_foto = asset('storage/' . $empleado->path_foto);
            }
        }

        return ApiResponse::success($empleados);
    }

    /**
     * Obtener empresas
     */
    public static function get_empresas()
    {
        return ApiResponse::success(EmpleadosData::get_empresas());
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
        int $id_empresa,
        int $id_cargo,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?UploadedFile $foto = null
    ) {
        if ($dni && EmpleadosData::existe_dni($dni)) {
            return ApiResponse::error('El DNI ingresado ya se encuentra registrado.');
        }

        $path_foto = null;
        if ($foto && $foto->isValid()) {
            $archivos = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto]);
            if (!empty($archivos)) {
                $path_foto = $archivos[0]['relative_path'];
            }
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

        $nuevoEmpleado = EmpleadosData::get_empleado_by_id($id);

        if ($nuevoEmpleado && $nuevoEmpleado->path_foto) {
            $nuevoEmpleado->path_foto = asset('storage/' . $nuevoEmpleado->path_foto);
        }

        return ApiResponse::success(
            $nuevoEmpleado,
            'Empleado registrado correctamente'
        );
    }
    /**
     * Actualizar la foto de un empleado
     */
    public static function actualizar_foto(int $id_empleado, ?UploadedFile $file)
    {
        $archivos = ArchivoHelper::guardarArchivos('fotos-empleados', [$file]);
        if (empty($archivos)) {
            return ApiResponse::error('No se pudo procesar la imagen.');
        }

        $path_foto = $archivos[0]['relative_path'];
        EmpleadosData::actualizar_foto($id_empleado, $path_foto);

        $empleado = EmpleadosData::get_empleado_by_id($id_empleado);
        if ($empleado && $empleado->path_foto) {
            $empleado->path_foto = asset('storage/' . $empleado->path_foto);
        }

        return ApiResponse::success($empleado, 'Foto de perfil actualizada correctamente');
    }
}
