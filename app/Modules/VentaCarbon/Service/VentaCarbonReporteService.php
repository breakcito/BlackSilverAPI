<?php

namespace App\Modules\VentaCarbon\Service;

use App\Modules\VentaCarbon\Data\VentaCarbonReporteData;
use App\Shared\Responses\ApiResponse;

class VentaCarbonReporteService
{
    /**
     * "Registro de Ventas": todas las cargas de carbon que ingresaron al
     * almacen de destino en el anio indicado.
     */
    public static function get_control_ventas(int $anio): array
    {
        $cargas = VentaCarbonReporteData::get_cargas_control_ventas($anio);

        return ApiResponse::success([
            'anio' => $anio,
            'cargas' => $cargas,
        ]);
    }
}
