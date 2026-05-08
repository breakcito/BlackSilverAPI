<?php

namespace App\Modules\Empleados;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\Empleados\Data\EmpleadosData;
use Illuminate\Http\UploadedFile;

class EmpleadosService
{
    /**
     * Listar empleados con su mina y labores asignadas
     */
    public static function get_empleados(?int $id_mina = null)
    {
        $empleados = EmpleadosData::get_empleados(id_mina: $id_mina);

        // Convertir path_foto a URL completa (compatible con registros viejos y nuevos)
        foreach ($empleados as $empleado) {
            if ($empleado->path_foto && !str_starts_with($empleado->path_foto, 'http')) {
                $empleado->path_foto = asset('storage/' . $empleado->path_foto);
            }
        }

        return ApiResponse::success($empleados);
    }

    /**
     * Obtener minas activas
     */
    public static function get_minas()
    {
        return ApiResponse::success(EmpleadosData::get_minas());
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
        int $id_cargo,
        string $nombre,
        string $apellido,
        ?int $id_mina = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?UploadedFile $foto = null,
        array $ids_labor = []
    ) {
        if ($dni && EmpleadosData::existe_dni($dni)) {
            return ApiResponse::error('El DNI ingresado ya se encuentra registrado.');
        }

        $path_foto = null;
        if ($foto && $foto->isValid()) {
            $archivos = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto]);
            if (!empty($archivos)) {
                // Guardar URL completa en BD (no solo el path relativo)
                $path_foto = asset('storage/' . $archivos[0]['path_relativo']);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use (
            $id_mina,
            $id_cargo,
            $nombre,
            $apellido,
            $dni,
            $ruc,
            $carnet_extranjeria,
            $pasaporte,
            $fecha_nacimiento,
            $path_foto,
            $ids_labor
        ) {
            $id = EmpleadosData::crear_empleado(
                $id_mina,
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

            // Asignar labores iniciales si las hay y hay una mina seleccionada
            if ($id_mina) {
                foreach ($ids_labor as $id_labor) {
                    // Validar que la labor sea de la mina seleccionada
                    if (EmpleadosData::labor_pertenece_a_mina((int)$id_labor, $id_mina)) {
                        EmpleadosData::asignar_labor($id, (int)$id_labor);
                    }
                }
            }

            $nuevoEmpleado = EmpleadosData::get_empleado_by_id($id);

            return ApiResponse::success(
                $nuevoEmpleado,
                'Empleado registrado correctamente'
            );
        });
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

        // Guardar URL completa en BD (no solo el path relativo)
        $path_foto = asset('storage/' . $archivos[0]['path_relativo']);
        EmpleadosData::actualizar_foto($id_empleado, $path_foto);

        $empleado = EmpleadosData::get_empleado_by_id($id_empleado);

        // path_foto ya viene como URL completa desde la BD
        return ApiResponse::success($empleado, 'Foto de perfil actualizada correctamente');
    }
}
