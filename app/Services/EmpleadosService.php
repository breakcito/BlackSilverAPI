<?php

namespace App\Services;

use App\Data\EmpleadosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class EmpleadosService
{
    /**
     * Listar almacenes.
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?int $id_mina_excluyente = null,
        ?bool $con_cuenta = null
    ) {
        $empleados = EmpleadosData::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado,
            id_almacen_excluyente: $id_almacen_excluyente,
            id_mina_excluyente: $id_mina_excluyente,
            con_cuenta: $con_cuenta
        );

        return ApiResponse::success($empleados);
    }

    /**
     * Registrar un nuevo empleado
     */
    public static function crear_empleado(
        int $id_cargo,
        string $nombre,
        string $apellido,
        bool $con_contrato = false,
        ?int $id_contrato_vigente = null,
        ?string $genero = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?UploadedFile $foto = null,
        ?bool $return_object = false
    ) {
        if (EmpleadosData::ya_existe(dni: $dni, ruc: $ruc, carnet_extranjeria: $carnet_extranjeria, pasaporte: $pasaporte)) {
            return ApiResponse::error('Ya existe un empleado registrado con uno de los documentos proporcionados.');
        }

        $url_foto_str = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto])[0] ?? null;
            if ($archivo && isset($archivo['url'])) {
                $url_foto_str = $archivo['url'];
            }
        }

        $id = EmpleadosData::crear_empleado(
            id_cargo: $id_cargo,
            nombre: $nombre,
            apellido: $apellido,
            con_contrato: $con_contrato,
            id_contrato_vigente: $id_contrato_vigente,
            genero: $genero,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            url_foto: $url_foto_str
        );

        if ($return_object) {
            $nuevoEmpleado = EmpleadosData::get_empleados(id_empleado: $id);

            return ApiResponse::success(
                $nuevoEmpleado,
                'Empleado registrado correctamente'
            );
        }

        return ApiResponse::success($id, 'Empleado registrado correctamente');
    }

    /**
     * Actualizar la foto del empleado asociado a una cuenta
     */
    public static function actualizar_foto(int $id_empleado, ?UploadedFile $nueva_foto = null)
    {
        $emp = EmpleadosData::get_empleado_dinamico_by_id($id_empleado, ['url_foto']);
        $url_foto_old = ! empty($emp['url_foto']) ? $emp['url_foto'] : null;

        // Caso: eliminar foto (sin nueva)
        if (is_null($nueva_foto)) {
            if ($url_foto_old) {
                ArchivoHelper::eliminarArchivo($url_foto_old);
                EmpleadosData::actualizar_foto($id_empleado, null);

                return ApiResponse::success(null, 'Foto eliminada correctamente.');
            }

            return ApiResponse::success(null, 'No hay foto para eliminar.');
        }

        // Caso: actualizar o agregar foto
        if ($url_foto_old) {
            ArchivoHelper::eliminarArchivo($url_foto_old);
        }

        $resultado = ArchivoHelper::guardarArchivos('perfiles', [$nueva_foto]);
        $url_foto = $resultado[0]['url'] ?? null;

        if (empty($url_foto)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        EmpleadosData::actualizar_foto(id_empleado: $id_empleado, url_foto: $url_foto);

        return ApiResponse::success($url_foto, 'Foto actualizada correctamente.');
    }
}
