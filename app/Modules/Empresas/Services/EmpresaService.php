<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Empresa;
use App\Shared\Responses\ApiResponse;

class EmpresaService
{
    public function get_empresas()
    {
        $empresas = Empresa::get_empresas();
        return ApiResponse::success($empresas);
    }

    public function get_empresas_by_usuario(int $id_usuario)
    {
        $empresas = Empresa::get_empresas_by_usuario($id_usuario);
        return ApiResponse::success($empresas);
    }
}
