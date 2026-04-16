<?php

namespace App\Modules\MinasLabores\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\MinasLabores\Data\EmpresasData;

class EmpresasService
{


    public static function get_empresas_ejecutoras(int $id_mina): array|object
    {
        $empresas = EmpresasData::get_empresas_ejecutoras($id_mina);

        return ApiResponse::success($empresas);
    }

    public static function get_empresas_disponibles(int $id_concesion, int $id_mina): array|object
    {
        $empresas = EmpresasData::get_empresas_disponibles($id_concesion, $id_mina);

        return ApiResponse::success($empresas);
    }

    public static function asignar_empresa(int $id_mina, int $id_empresa): array|object
    {
        if (EmpresasData::existe_empresa_asignada($id_mina, $id_empresa)) {
            return ApiResponse::error('La empresa ya está asignada como ejecutora de esta mina.');
        }

        $id_empresa_mina = EmpresasData::asignar_empresa($id_mina, $id_empresa);
        $nueva = EmpresasData::get_empresa_ejecutora_by_id($id_empresa_mina);

        return ApiResponse::success($nueva, 'Empresa asignada correctamente');
    }
}
