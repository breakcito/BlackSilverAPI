<?php

namespace App\Views\Concesiones;

use App\Shared\Responses\ApiResponse;
use App\Views\Concesiones\Data\ConcesionesData;
use App\Views\Concesiones\Data\ContratosData;

class ConcesionesService
{
    /**
     * Obtener listado de concesiones asociadas a las empresas del usuario
     */
    public static function get_concesiones(int $id_usuario)
    {
        return ApiResponse::success(ConcesionesData::get_concesiones(id_usuario: $id_usuario));
    }

    /**
     * Obtener empresas asociadas al usuario para nuevos contratos
     */
    public static function get_empresas(int $id_usuario)
    {
        return ApiResponse::success(ContratosData::get_empresas($id_usuario));
    }

    /**
     * Crear una nueva concesión
     */
    public static function crear_concesion(
        string $nombre,
        string $codigo_concesion,
        ?string $codigo_reinfo,
        ?string $ubigeo,
        string $tipo_mineral
    ) {
        if (ConcesionesData::existe_nombre($nombre)) {
            return ApiResponse::error('El nombre de la concesión ya existe.');
        }

        $id = ConcesionesData::crear_concesion(
            $nombre,
            $codigo_concesion,
            $codigo_reinfo,
            $ubigeo,
            $tipo_mineral
        );

        return ApiResponse::success(ConcesionesData::get_concesion_by_id($id), 'Concesión creada con éxito');
    }

    /**
     * Obtener historial de contratos de una concesión
     */
    public static function get_contratos(int $id_concesion)
    {
        return ApiResponse::success(ContratosData::get_contratos($id_concesion));
    }

    /**
     * Crear contrato con empresa
     */
    public static function crear_contrato(
        int $id_concesion,
        int $id_empresa,
        string $fecha_inicio,
        ?string $fecha_fin
    ) {
        if (ContratosData::verificar_contrato_activo($id_concesion, $id_empresa)) {
            return ApiResponse::error('Esta empresa ya tiene un contrato activo en esta concesión.');
        }

        ContratosData::crear_contrato(
            $id_concesion,
            $id_empresa,
            $fecha_inicio,
            $fecha_fin
        );

        return ApiResponse::success(null, 'Contrato registrado correctamente');
    }

    /**
     * Terminar contrato
     */
    public static function terminar_contrato(int $id_contrato)
    {
        ContratosData::terminar_contrato($id_contrato);

        return ApiResponse::success(null, 'Contrato finalizado correctamente');
    }
}
