<?php
namespace App\Services;

use App\Data\EmpresasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Helpers\LogoEmbedder;
use App\Shared\Responses\ApiResponse;

class EmpresasService
{
    /**
     * Listar empresas.
     */
    public static function get_empresas(
        ?int $id_empresa = null,
        ?EstadoBase $estado = null
    ) {
        $empresas = EmpresasData::get_empresas(
            id_empresa: $id_empresa,
            estado: $estado,
        );

        // Lista: iterar.
        if (is_array($empresas)) {
            foreach ($empresas as $row) {
                if (isset($row->url_logo)) {
                    $row->url_logo = LogoEmbedder::embed($row->url_logo);
                }
            }
        }
        // Single row (caso `get_empresa_dinamica_by_id` o cuando llega solo uno):
        elseif (is_object($empresas) && isset($empresas->url_logo)) {
            $empresas->url_logo = LogoEmbedder::embed($empresas->url_logo);
        }

        return ApiResponse::success($empresas);
    }
}