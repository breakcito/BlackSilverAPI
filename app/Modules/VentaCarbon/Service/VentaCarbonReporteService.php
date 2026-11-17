<?php

namespace App\Modules\VentaCarbon\Service;

use App\Modules\VentaCarbon\Data\VentaCarbonReporteData;
use App\Shared\Responses\ApiResponse;

class VentaCarbonReporteService
{
    /**
     * "Registro de Ventas": las cargas de carbon que ingresaron al almacen de
     * destino en el anio indicado.
     *
     * Si se informa `$mes`, el corte se limita a ese mes del anio.
     */
    public static function get_control_ventas(int $anio, ?int $mes = null): array
    {
        $cargas = VentaCarbonReporteData::get_cargas_control_ventas($anio, $mes);

        return ApiResponse::success([
            'anio' => $anio,
            'mes' => $mes,
            'cargas' => $cargas,
        ]);
    }
}
