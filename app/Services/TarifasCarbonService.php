<?php

namespace App\Services;

use App\Data\TarifasCarbonData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class TarifasCarbonService
{
    public static function get_tarifas_carbon(
        ?int $id_tarifa_carbon = null,
        ?int $id_tipo_carbon = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ): array {
        return ApiResponse::success(
            TarifasCarbonData::get_tarifas_carbon(
                id_tarifa_carbon: $id_tarifa_carbon,
                id_tipo_carbon: $id_tipo_carbon,
                estado: $estado,
            ),
            'Tarifas de carbon obtenidas correctamente',
        );
    }

    public static function crear_tarifa_carbon(
        int $id_tipo_carbon,
        float $inicio_porcentaje_ceniza,
        float $fin_porcentaje_ceniza,
        float $precio_unitario,
    ): array {
        if ($id_tipo_carbon <= 0) {
            return ApiResponse::error('Tipo de carbon requerido');
        }
        if ($inicio_porcentaje_ceniza < 0 || $fin_porcentaje_ceniza < 0) {
            return ApiResponse::error('Los porcentajes de ceniza no pueden ser negativos');
        }
        if ($inicio_porcentaje_ceniza >= $fin_porcentaje_ceniza) {
            return ApiResponse::error('El inicio del rango debe ser menor que el fin');
        }
        if ($precio_unitario <= 0) {
            return ApiResponse::error('El precio unitario debe ser mayor a 0');
        }

        if (TarifasCarbonData::existe_tarifa_en_rango(
            id_tipo_carbon: $id_tipo_carbon,
            inicio: $inicio_porcentaje_ceniza,
            fin: $fin_porcentaje_ceniza,
        )) {
            return ApiResponse::error('Ya existe una tarifa que se solapa con ese rango de ceniza para el mismo tipo de carbon');
        }

        $id = TarifasCarbonData::crear_tarifa_carbon(
            id_tipo_carbon: $id_tipo_carbon,
            inicio_porcentaje_ceniza: $inicio_porcentaje_ceniza,
            fin_porcentaje_ceniza: $fin_porcentaje_ceniza,
            precio_unitario: $precio_unitario,
        );

        return ApiResponse::success(
            TarifasCarbonData::get_tarifas_carbon(id_tarifa_carbon: $id),
            'Tarifa de carbon registrada correctamente',
        );
    }
}
