<?php

namespace App\Views\Empresas;

use App\Shared\Responses\ApiResponse;
use App\Views\Empresas\Data\EmpresasData;

class EmpresasService
{
    /**
     * Obtener el listado de empresas
     */
    public static function get_empresas()
    {
        $empresas = EmpresasData::get_empresas();

        return ApiResponse::success($empresas);
    }

    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(array $data)
    {
        if (EmpresasData::verificar_ruc_duplicado($data['ruc'])) {
            return ApiResponse::error('Ya existe una empresa registrada con este RUC.');
        }

        $id_empresa = EmpresasData::crear_empresa($data);
        $nuevaEmpresa = EmpresasData::get_empresa_by_id($id_empresa);

        return ApiResponse::success($nuevaEmpresa, 'Empresa registrada correctamente');
    }
}
