<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenReposicionRecepcion;
use App\Models\PrestamoAlmacenReposicionRecepcionDetalle;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicionDetalle;
use Illuminate\Support\Facades\DB;

class RecepcionesReposicionData
{
    /**
     * Crear cabecera de recepción de reposición.
     */
    public static function crear_recepcion(
        int $id_reposicion,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias = null,
        bool $con_incidencia = false,
        EstadoPrestamoReposicionDetalle $estado = EstadoPrestamoReposicionDetalle::RecepcionCompleta
    ): int {
        return PrestamoAlmacenReposicionRecepcion::crear_recepcion(
            $id_reposicion,
            $id_empleado,
            $fecha_hora_recepcion,
            $observacion,
            $evidencias,
            $con_incidencia,
            $estado
        );
    }

    /**
     * Crear detalle de recepción de reposición.
     * Para activos fijos: id_lote_producto puede ser 0, id_activo_fijo debe ser provisto.
     * Para productos comunes: id_lote_producto requerido, id_activo_fijo = null.
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_reposicion_detalle,
        int $id_lote_producto,
        bool $es_ajuste_stock,
        float $cantidad_recep_base,
        EstadoPrestamoReposicionDetalle $estado = EstadoPrestamoReposicionDetalle::RecepcionCompleta,
        ?int $id_activo_fijo = null
    ): int {
        return (int) PrestamoAlmacenReposicionRecepcionDetalle::insertGetId([
            'id_prestamo_almacen_reposicion_recepcion'  => $id_recepcion,
            'id_prestamo_almacen_reposicion_detalle'     => $id_reposicion_detalle,
            'id_lote_producto'                           => $id_activo_fijo ? null : $id_lote_producto,
            'id_activo_fijo'                             => $id_activo_fijo,
            'es_ajuste_stock'                            => $es_ajuste_stock ? 1 : 0,
            'cantidad_recepcionada_base'                 => $cantidad_recep_base,
            'estado'                                     => $estado->value,
        ]);
    }

    /**
     * Actualiza el lote asociado a un detalle de recepción.
     */
    public static function update_detalle_lote(int $id_detalle, int $id_lote): void
    {
        PrestamoAlmacenReposicionRecepcionDetalle::where('id', $id_detalle)->update(['id_lote_producto' => $id_lote]);
    }

    /**
     * Obtener el historial de recepciones de una reposición.
     */
    public static function get_historial_recepciones(int $id_reposicion)
    {
        return PrestamoAlmacenReposicionRecepcion::get_recepciones(ids_reposiciones: $id_reposicion);
    }

    /**
     * Obtener los detalles de una recepción de reposición.
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return PrestamoAlmacenReposicionRecepcionDetalle::get_detalles($id_recepcion);
    }

    /**
     * Obtener un detalle de reposición por su ID.
     */
    public static function get_reposicion_detalle_by_id(int $id_reposicion_detalle)
    {
        return DB::selectOne("SELECT * FROM prestamo_almacen_reposicion_detalle WHERE id = :id", ['id' => $id_reposicion_detalle]);
    }

    /**
     * Obtener la cantidad total recibida hasta ahora para un detalle de reposición.
     */
    public static function get_cantidad_recepcionada_total_base_detalle(int $id_reposicion_detalle): float
    {
        $res = DB::selectOne("
            SELECT SUM(cantidad_recepcionada_base) as total 
            FROM prestamo_almacen_reposicion_recepcion_detalle 
            WHERE id_prestamo_almacen_reposicion_detalle = :id
        ", ['id' => $id_reposicion_detalle]);

        return (float) ($res->total ?? 0);
    }

    /**
     * Actualiza el estado de un detalle de reposición.
     */
    public static function update_reposicion_detalle_estado(int $id_reposicion_detalle, string $estado)
    {
        return DB::statement("UPDATE prestamo_almacen_reposicion_detalle SET estado = :estado WHERE id = :id", [
            'estado' => $estado,
            'id' => $id_reposicion_detalle
        ]);
    }

    /**
     * Actualiza el estado de la cabecera de la reposición.
     */
    public static function update_reposicion_estado(int $id_reposicion, string $estado)
    {
        return DB::statement("UPDATE prestamo_almacen_reposicion SET estado = :estado WHERE id = :id", [
            'estado' => $estado,
            'id' => $id_reposicion
        ]);
    }

    /**
     * Obtener todos los detalles de una reposición.
     */
    public static function get_reposicion_detalles(int $id_reposicion)
    {
        return DB::select("SELECT * FROM prestamo_almacen_reposicion_detalle WHERE id_prestamo_almacen_reposicion = :id", ['id' => $id_reposicion]);
    }

    /**
     * Obtener información de la reposición junto con el almacén prestamista.
     */
    public static function get_reposicion_info_with_almacen(int $id_reposicion)
    {
        $sql = "
            SELECT 
                pr.id as id_reposicion,
                pa.id_almacen_prestamista,
                pa.id as id_prestamo
            FROM prestamo_almacen_reposicion pr
            INNER JOIN prestamo_almacen pa ON pa.id = pr.id_prestamo_almacen
            WHERE pr.id = :id
        ";
        return DB::selectOne($sql, ['id' => $id_reposicion]);
    }

    /**
     * Obtener el ID del producto de un detalle de reposición.
     */
    public static function get_producto_id_by_repo_det(int $id_repo_det)
    {
        $sql = "
            SELECT pd.id_producto
            FROM prestamo_almacen_reposicion_detalle rd
            INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
            WHERE rd.id = :id
        ";
        return DB::selectOne($sql, ['id' => $id_repo_det]);
    }

    /**
     * Obtiene los detalles de una reposición formateados para el proceso de recepción.
     * Utiliza SQL Puro con Joins para cumplir con la arquitectura.
     */
    public static function get_detalles_para_recepcion(int $id_reposicion)
    {
        $sql = "
            SELECT 
                rd.id as id_solicitud_reabastecimiento_detalle,
                rd.id as id_entrega_detalle,
                rd.id_prestamo_almacen_reposicion as id_reabastecimiento_entrega,
                p.id as id_producto,
                p.nombre as producto,
                p.tipo_bien as tipo_bien,
                rd.id_activo_fijo as id_activo_fijo,
                act.correlativo as correlativo_activo_fijo,
                rd.cantidad_solicitud as cantidad_solicitud,
                um.abreviatura as unidad_base_abv,
                p.id_unidad_medida_almacen as id_unidad_medida_base,
                rd.id_unidad_medida as id_unidad_medida_solicitada,
                rd.cantidad_base as cantidad_base,
                p.es_perecible,
                'Reposicion' as tipo_entrega
            FROM prestamo_almacen_reposicion_detalle rd
            INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
            INNER JOIN productos p ON p.id = pd.id_producto
            INNER JOIN unidades_medida um ON um.id = p.id_unidad_medida_almacen
            LEFT JOIN activo_fijo act ON act.id = rd.id_activo_fijo
            WHERE rd.id_prestamo_almacen_reposicion = :id
        ";

        return DB::select($sql, ['id' => $id_reposicion]);
    }
}
