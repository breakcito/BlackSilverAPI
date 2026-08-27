<?php
namespace App\Services;

use App\Data\AlmacenesData;
use App\Shared\Responses\ApiResponse;
class AlmacenesService
{
    /**
     * Listar almacenes.
     *
     * Por defecto NO se incluyen los almacenes de carbon (`incluir_carbon=false`):
     * el front debe pedir explicitamente `incluir_carbon=true` para listarlos.
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_empleado_responsable = null,
        ?int $es_principal = null,
        bool $incluir_carbon = false
    ) {
        $almacenes = AlmacenesData::get_almacenes(
            id_almacen: $id_almacen,
            id_empleado_responsable: $id_empleado_responsable,
            es_principal: $es_principal,
            incluir_carbon: $incluir_carbon
        );

        return ApiResponse::success($almacenes);
    }
}