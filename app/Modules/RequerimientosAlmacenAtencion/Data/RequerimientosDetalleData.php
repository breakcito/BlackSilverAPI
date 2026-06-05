<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use Illuminate\Support\Facades\DB;

class RequerimientosDetalleData
{

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles_by_requerimiento(
        int $id_requerimiento
    ) {
        return RequerimientoAlmacenDetalle::get_detalles(
            id_requerimiento: $id_requerimiento
        );
    }

    public static function get_cantidades_of_detalle_by_id(int $id_detalle)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)->first([
            'cantidad_solicitada_base',
            'cantidad_entregada_base',
        ]);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return RequerimientoAlmacenDetalleLog::get_logs(
            id_requerimiento_detalle: $id_detalle
        );
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(
        int $id_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoRequerimientoDetalleLog $estado
    ) {
        return RequerimientoAlmacenDetalleLog::crear_log(
            id_requerimiento_detalle: $id_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }


    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_req, float $cantidad_base)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_req,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }

    public static function get_id_requerimiento_by_detalle(int $id_detalle)
    {
        return DB::selectOne('
            SELECT
                rad.id_requerimiento_almacen
            FROM
                requerimiento_almacen_detalle rad
            WHERE
                rad.id = :id_detalle
        ', ["id_detalle" => $id_detalle]);
    }


    /**
     * Crear el detalle de un requerimiento de almacén.
     */
    public static function crear_detalle(
        int $id_requerimiento,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad,
        float $contenido,
        float $cantidad_base,
        ?string $comentario = null,
    ) {
        return RequerimientoAlmacenDetalle::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad,
            'contenido_por_presentacion' => $contenido,
            'cantidad_solicitada_base' => $cantidad_base,
            'cantidad_entregada' => 0,
            'cantidad_entregada_base' => 0,
            'comentario' => $comentario,
            'estado' => EstadoRequerimientoDetalle::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Registra en la trazbilidad del detalle
     */
    public static function registrar_trazabilidad(
        int $id_detalle,
        int $id_empleado_registro
    ) {
        return RequerimientoAlmacenDetalleLog::crear_log(
            id_requerimiento_detalle: $id_detalle,
            id_empleado: $id_empleado_registro,
            descripcion: EstadoRequerimientoDetalle::EsperandoAprobacion->getGlosa(),
            estado: EstadoRequerimientoDetalleLog::EsperandoAprobacion
        );
    }
}
