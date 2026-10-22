<?php

namespace App\Modules\TipoCarbon\Service;

use App\Modules\TipoCarbon\Data\CalidadCarbonData;
use App\Shared\Responses\ApiResponse;

class CalidadCarbonService
{
    /**
     * Lista las calidades de carbón configuradas.
     */
    public static function get_calidades(): array
    {
        return ApiResponse::success(CalidadCarbonData::get_calidades());
    }

    /**
     * Actualiza los rangos de ceniza de una calidad.
     */
    public static function actualizar_calidad(
        int $id_calidad_carbon,
        float $porcentaje_inicio_ceniza,
        float $porcentaje_fin_ceniza
    ): array {
        $calidad = CalidadCarbonData::get_calidad_by_id($id_calidad_carbon);
        if ($calidad === null) {
            return ApiResponse::error('La calidad indicada no existe');
        }

        CalidadCarbonData::actualizar_calidad(
            id_calidad_carbon: $id_calidad_carbon,
            porcentaje_inicio_ceniza: $porcentaje_inicio_ceniza,
            porcentaje_fin_ceniza: $porcentaje_fin_ceniza
        );

        return ApiResponse::success(
            CalidadCarbonData::get_calidad_by_id($id_calidad_carbon),
            'Rango de ceniza de la calidad actualizado'
        );
    }
}
