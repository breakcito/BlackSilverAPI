<?php

namespace App\Modules\ActivosFijos\Service;

use App\Modules\ActivosFijos\Data\ActivosData;
use App\Services\ActivosFijosService as GlobalActivosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Responses\ApiResponse;

class ActivosService
{
    /**
     * Listar todos los activos fijos con su información detallada.
     */
    public static function get_activos()
    {
        $activos = ActivosData::get_activos();
        return ApiResponse::success($activos);
    }

    /**
     * Crear un nuevo activo fijo consumiendo el servicio global.
     */
    public static function crear_activo(
        int $id_producto,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_marca = null,
        //
        ?string $codigo = null,
        ?string $numero_serie = null,
        ?string $modelo = null,
        ?int $yearcito_modelo = null,
        ?string $descripcion = null,
        ?string $serie_placa = null,
        ?string $numero_placa = null,
        ?array $especificaciones = null,
        ?string $fecha_hora_ingreso = null,
        ?EstadoActivoFijo $estado = EstadoActivoFijo::EnUso
    ) {
        $res = GlobalActivosService::crear_activo(
            id_producto: $id_producto,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            id_marca: $id_marca,
            codigo: $codigo,
            numero_serie: $numero_serie,
            modelo: $modelo,
            yearcito_modelo: $yearcito_modelo,
            descripcion: $descripcion,
            serie_placa: $serie_placa,
            numero_placa: $numero_placa,
            especificaciones: $especificaciones,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            estado: $estado,
            return_objecto: false
        );

        $id_activo = $res['data'];

        return ApiResponse::success(ActivosData::get_activos((int) $id_activo));
    }

    /**
     * Actualizar la ubicación de un activo fijo consumiendo el servicio global.
     */
    public static function actualizar_ubicacion(
        int $id_activo,
        MovimientoActivoFijo $tipo_movimiento,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion = null,
        ?string $fecha_hora_movimiento = null
    ) {
        $id_log = GlobalActivosService::new_ubicacion(
            id_activo: $id_activo,
            tipo_movimiento: $tipo_movimiento,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion: $descripcion,
            fecha_hora_movimiento: $fecha_hora_movimiento
        );

        return ApiResponse::success($id_log, 'Ubicación actualizada correctamente');
    }
}
