<?php

namespace App\Modules\OrdenesCompra\Data;

use App\Models\OrdenCompraComprobante;
use App\Models\OrdenCompraComprobanteRecepcion;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoComprobante;

class OCComprobanteData
{
    public static function crear_comprobante(
        int $id_empleado_registro,
        int $id_orden_compra,
        TipoComprobante $tipo_comprobante,
        string $serie,
        string $numero,
        string $fecha_emision,
        ?string $observacion,
        ?string $evidencias,
        Moneda $moneda,
        float $tipo_cambio_venta_aplicado,
        int $es_auditable,
        float $total_antes_igv,
        float $total_antes_igv_soles,
        int $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $monto_igv_soles,
        float $total_despues_igv,
        float $total_despues_igv_soles,
        string $estado
    ): int {
        return OrdenCompraComprobante::insertGetId([
            'id_empleado_registro' => $id_empleado_registro,
            'id_orden_compra' => $id_orden_compra,
            'tipo_comprobante' => $tipo_comprobante->value,
            'serie' => $serie,
            'numero' => $numero,
            'fecha_emision' => $fecha_emision,
            'observacion' => $observacion,
            'evidencias' => $evidencias,
            'moneda' => $moneda->value,
            'tipo_cambio_venta_aplicado' => $tipo_cambio_venta_aplicado,
            'es_auditable' => $es_auditable,
            'total_antes_igv' => $total_antes_igv,
            'total_antes_igv_soles' => $total_antes_igv_soles,
            'incluye_igv' => $incluye_igv,
            'porcentaje_igv' => $porcentaje_igv,
            'monto_igv' => $monto_igv,
            'monto_igv_soles' => $monto_igv_soles,
            'total_despues_igv' => $total_despues_igv,
            'total_despues_igv_soles' => $total_despues_igv_soles,
            'created_at' => now(),
            'estado' => $estado
        ]);
    }

    public static function vincular_recepcion(int $id_comprobante, int $id_recepcion): void
    {
        OrdenCompraComprobanteRecepcion::insert([
            'id_orden_compra_comprobante' => $id_comprobante,
            'id_orden_compra_recepcion' => $id_recepcion
        ]);
    }

    public static function get_comprobantes(?int $id_comprobante = null, ?int $id_orden_compra = null)
    {
        return OrdenCompraComprobante::get_comprobantes($id_comprobante, $id_orden_compra);
    }

    public static function get_recepciones_agrupadas(int $id_comprobante)
    {
        return OrdenCompraComprobanteRecepcion::get_recepciones_agrupadas($id_comprobante);
    }
}
