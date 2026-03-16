<?php

namespace App\Views\Empleados;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\Empleados\Data\EmpleadosData;

class EmpleadosService
{
    /**
     * Listar empleados de las empresas del usuario
     */
    public static function get_empleados(int $id_usuario, ?int $id_empresa = null)
    {
        $empleados = EmpleadosData::get_empleados($id_usuario, $id_empresa);

        // Convertir path_foto a URL completa
        foreach ($empleados as $empleado) {
            if ($empleado->path_foto) {
                $empleado->path_foto = asset('storage/' . $empleado->path_foto);
            }
        }

        return ApiResponse::success($empleados);
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
    public static function crear_empleado(int $id_usuario, array $data)
    {
        $dni = $data['dni'] ?? null;
        if ($dni && EmpleadosData::existe_dni($dni)) {
            return ApiResponse::error('El DNI ingresado ya se encuentra registrado.');
        }

        // Si viene un archivo en path_foto, lo procesamos
        $request = request();
        if ($request->hasFile('path_foto')) {
            $archivos = ArchivoHelper::guardarArchivos('fotos-empleados', [$request->file('path_foto')]);
            if (!empty($archivos)) {
                $data['path_foto'] = $archivos[0]['relative_path'];
            }
        }

        $id = EmpleadosData::crear_empleado(
            (int) $data['id_empresa'],
            (int) $data['id_cargo'],
            (string) $data['nombre'],
            (string) $data['apellido'],
            $data['dni'] ?? null,
            $data['ruc'] ?? null,
            $data['carnet_extranjeria'] ?? null,
            $data['pasaporte'] ?? null,
            $data['fecha_nacimiento'] ?? null,
            $data['path_foto'] ?? null
        );

        $nuevoEmpleado = EmpleadosData::get_empleado_by_id($id_usuario, $id);

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
    public static function actualizar_foto(int $id_usuario, int $id_empleado, $file)
    {
        $archivos = ArchivoHelper::guardarArchivos('fotos-empleados', [$file]);
        if (empty($archivos)) {
            return ApiResponse::error('No se pudo procesar la imagen.');
        }

        $path_foto = $archivos[0]['relative_path'];
        EmpleadosData::actualizar_foto($id_empleado, $path_foto);

        $empleado = EmpleadosData::get_empleado_by_id($id_usuario, $id_empleado);
        if ($empleado && $empleado->path_foto) {
            $empleado->path_foto = asset('storage/' . $empleado->path_foto);
        }

        return ApiResponse::success($empleado, 'Foto de perfil actualizada correctamente');
    }
}
