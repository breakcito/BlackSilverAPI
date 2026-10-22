<?php

namespace App\Services;

use App\Data\VehiculoCarbonData;
use App\Shared\Responses\ApiResponse;

class VehiculoCarbonService
{
    /**
     * Catalogo de vehiculos de carbon.
     */
    public static function get_vehiculos(?int $id_vehiculo_carbon = null, ?string $filtros = null)
    {
        return ApiResponse::success(VehiculoCarbonData::get_vehiculos(
            id_vehiculo_carbon: $id_vehiculo_carbon,
            filtros: $filtros,
        ));
    }

    /**
     * Registra un vehiculo en el catalogo. Pensado para el boton "+" del
     * formulario de despacho de una venta de carbon.
     */
    public static function crear_vehiculo(int $id_marca, string $tipo_vehiculo, string $placa)
    {
        if (trim($placa) === '') {
            return ApiResponse::error('La placa del vehiculo es obligatoria');
        }

        if (VehiculoCarbonData::existe_vehiculo($id_marca, $tipo_vehiculo, $placa)) {
            return ApiResponse::error('Ya existe un vehiculo con esa placa, marca y tipo');
        }

        $idVehiculo = VehiculoCarbonData::crear_vehiculo($id_marca, $tipo_vehiculo, $placa);

        $vehiculos = VehiculoCarbonData::get_vehiculos(id_vehiculo_carbon: $idVehiculo);

        return ApiResponse::success($vehiculos[0] ?? null, 'Vehiculo registrado correctamente');
    }
}
