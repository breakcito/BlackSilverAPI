<?php
namespace App\Services;

use App\Data\ProveedoresData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;

class ProveedoresService
{
    /**
     * Listar almacenes.
     */
    public static function get_proveedores(
        ?int $id_proveedor = null,
        ?EstadoBase $estado = null,
        ?TipoEntidad $tipoEntidad = null,
        ?bool $paraMantenimiento = null
    ) {
        $empleados = ProveedoresData::get_proveedores(
            id_proveedor: $id_proveedor,
            estado: $estado,
            tipoEntidad: $tipoEntidad,
            paraMantenimiento: $paraMantenimiento
        );

        return ApiResponse::success($empleados);
    }

    /**
     * Registrar proveedor
     */
    public static function crear_proveedor(
        TipoEntidad $tipoEntidad,
        string $razonSocial,
        bool $paraMantenimiento,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $correo = null,
        ?bool $return_object = false
    ): array {
        // verificamos que no exista
        $ya_existe = ProveedoresData::ya_existe(dni: $dni, ruc: $ruc, razonSocial: $razonSocial);
        if ($ya_existe) {
            return ApiResponse::error("El proveedor ya existe");
        }

        $id = ProveedoresData::crear_proveedor(
            tipoEntidad: $tipoEntidad,
            razonSocial: $razonSocial,
            paraMantenimiento: $paraMantenimiento,
            dni: $dni,
            ruc: $ruc,
            direccion: $direccion,
            telefono: $telefono,
            correo: $correo
        );

        if ($return_object) {
            $new_proveedor = ProveedoresData::get_proveedores(id_proveedor: $id);
            return ApiResponse::success($new_proveedor, "Proveedor registrado correctamente");
        }

        return ApiResponse::success($id, "Proveedor registrado correctamente");
    }
}