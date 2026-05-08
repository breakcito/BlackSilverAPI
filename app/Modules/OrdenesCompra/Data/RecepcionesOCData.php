<?php

namespace App\Modules\OrdenesCompra\Data;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\OrdenCompraRecepcion;
use App\Models\OrdenCompraRecepcionDetalle;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcion;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcionDetalle;

class RecepcionesOCData
{
    /**
     * Crear una cabecera de recepción de OC
     */
    public static function crear_recepcion(
        int $id_orden_compra,
        int $id_almacen,
        int $id_empleado,
        int $numero_correlativo,
        ?string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia,
        ?string $serie_guia = null,
        ?string $numero_guia = null,
        EstadoOrdenCompraRecepcion $estado = EstadoOrdenCompraRecepcion::RecepcionCompleta
    ) {
        return OrdenCompraRecepcion::crear_recepcion(
            id_orden_compra: $id_orden_compra,
            id_almacen_recepcionista: $id_almacen,
            id_empleado_recepcion: $id_empleado,
            numero_correlativo: $numero_correlativo,
            observacion: $observacion,
            fecha_hora_recepcion: $fecha_hora_recepcion,
            serie_guia_remision: $serie_guia,
            numero_guia_remision: $numero_guia,
            con_incidencia: $con_incidencia,
            evidencias: $evidencias,
            estado: $estado
        );
    }

    /**
     * Crear un detalle de recepción de OC
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_oc_detalle,
        int $id_lote_producto,
        bool $es_ajuste_stock,
        float $cantidad_recepcionada,
        float $cantidad_recepcionada_base,
        ?string $comentario = null,
        EstadoOrdenCompraRecepcionDetalle $estado = EstadoOrdenCompraRecepcionDetalle::RecepcionCompleta
    ) {
        return OrdenCompraRecepcionDetalle::crear_detalle(
            id_recepcion: $id_recepcion,
            detalles: [
                'id_orden_compra_detalle' => $id_oc_detalle,
                'id_lote_producto' => $id_lote_producto,
                'es_ajuste_stock' => $es_ajuste_stock,
                'cantidad_recepcionada' => $cantidad_recepcionada,
                'cantidad_recepcionada_base' => $cantidad_recepcionada_base,
                'comentario' => $comentario,
                'estado' => $estado,
            ]
        );
    }

    /**
     * Obtener el historial de recepciones de una OC
     */
    public static function get_historial_recepciones(int $id_orden_compra)
    {
        return OrdenCompraRecepcion::get_recepciones(id_orden_compra: $id_orden_compra);
    }

    /**
     * Obtener detalles de una recepción
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return OrdenCompraRecepcionDetalle::get_detalles(ids_recepciones: $id_recepcion);
    }

    /**
     * Obtener la cantidad total recepcionada base para un detalle de OC
     */
    public static function get_cantidad_recepcionada_total_base_detalle(int $id_oc_detalle): float
    {
        return OrdenCompraRecepcionDetalle::where('id_orden_compra_detalle', $id_oc_detalle)
            ->sum('cantidad_recepcionada_base');
    }

    /**
     * Obtener un detalle de OC por ID
     */
    public static function get_oc_detalle_by_id(int $id_oc_detalle)
    {
        return OrdenCompraDetalle::where('id', $id_oc_detalle)
            ->first();
    }

    /**
     * Actualiza el estado de un detalle de OC
     */
    public static function update_oc_detalle_estado(int $id_oc_detalle, string $estado)
    {
        return OrdenCompraDetalle::where('id', $id_oc_detalle)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtiene todos los detalles de una OC
     */
    public static function get_oc_detalles(int $id_orden_compra)
    {
        return OrdenCompraDetalle::where('id_orden_compra', $id_orden_compra)
            ->get();
    }

    /**
     * Actualiza el estado de la cabecera de OC
     */
    public static function update_oc_estado(int $id_orden_compra, string $estado)
    {
        return OrdenCompra::where('id', $id_orden_compra)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtener el correlativo de una OC
     */
    public static function get_correlativo_oc(int $id_orden_compra)
    {
        return OrdenCompra::where('id', $id_orden_compra)
            ->value('correlativo');
    }

    /**
     * Obtener el próximo correlativo de recepción para una OC
     */
    public static function get_proximo_numero_correlativo(int $id_orden_compra): int
    {
        $max = OrdenCompraRecepcion::where('id_orden_compra', $id_orden_compra)
            ->max('numero_correlativo');
        return ($max ?? 0) + 1;
    }
}
