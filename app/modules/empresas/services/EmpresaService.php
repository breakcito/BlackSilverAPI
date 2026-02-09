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
}
