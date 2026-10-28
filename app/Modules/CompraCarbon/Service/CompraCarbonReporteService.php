<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonReporteData;
use App\Shared\Responses\ApiResponse;

class CompraCarbonReporteService
{
    /**
     * "Registro de Compras" (Control de Compras): todas las cargas de carbón
     * que ingresaron al almacén en el año indicado.
     */
    public static function get_control_compras(int $anio): array
    {
        $cargas = CompraCarbonReporteData::get_cargas_control_compras($anio);

        return ApiResponse::success([
            'anio' => $anio,
            'cargas' => $cargas,
        ]);
    }
}
