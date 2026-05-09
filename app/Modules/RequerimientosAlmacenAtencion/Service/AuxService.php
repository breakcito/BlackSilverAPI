<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;

class AuxService
{


    /**
     * Obtiene la lista de minas que son abastecidas por un almacen
     */
    public static function get_minas_by_almacen(int $id_almacen)
    {
        $minas = RequerimientosData::get_minas_by_almacen($id_almacen);
        return ApiResponse::success($minas);
    }

    /**
     * Obtiene la lista de responsables y labores de una mina
     */
    public static function get_data_by_mina(int $id_mina)
    {
        $responsables = RequerimientosData::get_responsables_by_mina($id_mina);
        $labores = RequerimientosData::get_labores($id_mina);

        return ApiResponse::success([
            'responsables' => $responsables,
            'labores' => $labores,
        ]);
    }

}
