<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\OrdenCompraDetalleLog;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoDespachoCompra;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalleLog;

class OrdenesCompraData
{
    /**
     * -------------------------------------------------------
     * QUERYS PARA LA CABECERA
     * -------------------------------------------------------
     */

    public static function get_nuevo_correlativo(): array
    {
        return OrdenCompra::get_nuevo_correlativo();
    }

    public static function crear_orden(
        int $id_cotizacion,
        int $id_empresa,
        int $id_proveedor,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_orden,
        string $moneda,
        string $metodo_pago,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $costo_flete,
        float $otros_gastos,
        float $total_antes_igv,
        float $total_despues_igv,
        ?string $observacion = null,
        ?string $fecha_vencimiento_pago = null,
    ): int {
        return OrdenCompra::crear_orden(
            id_cotizacion: $id_cotizacion,
            id_empresa: $id_empresa,
            id_proveedor: $id_proveedor,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_orden: $fecha_hora_orden,
            moneda: $moneda,
            metodo_pago: $metodo_pago,
            incluye_igv: $incluye_igv,
            porcentaje_igv: $porcentaje_igv,
            monto_igv: $monto_igv,
            costo_flete: $costo_flete,
            otros_gastos: $otros_gastos,
            total_antes_igv: $total_antes_igv,
            total_despues_igv: $total_despues_igv,
            observacion: $observacion,
            fecha_vencimiento_pago: $fecha_vencimiento_pago,
        );
    }

    public static function get_orden_compra(int $id_orden_compra): array
    {
        return (array) OrdenCompra::get_ordenes($id_orden_compra);
    }

    /**
     * -------------------------------------------------------
     * QUERYS PARA EL DETALLE
     * -------------------------------------------------------
     */

    public static function crear_detalle_orden(
        int $id_orden_compra,
        int $id_cotizacion_detalle,
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen_recepcionista,
        //
        TipoDespachoCompra $tipo_despacho,
        int $tiempo_entrega,
        Periodo $tiempo_entrega_periodo,
        int $tiempo_entrega_dias,
        //
        float $contenido_por_presentacion,
        float $cantidad_requerida,
        float $cantidad_requerida_base,
        //
        float $precio_unitario,
        float $precio_unitario_base,
        //
        ?string $lugar_recojo,
        ?string $comentario = null,
    ): int {
        return OrdenCompraDetalle::crear_detalle(
            id_orden_compra: $id_orden_compra,
            id_cotizacion_detalle: $id_cotizacion_detalle,
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            //
            tipo_despacho: $tipo_despacho,
            tiempo_entrega: $tiempo_entrega,
            tiempo_entrega_periodo: $tiempo_entrega_periodo,
            tiempo_entrega_dias: $tiempo_entrega_dias,
            contenido_por_presentacion: $contenido_por_presentacion,
            cantidad_requerida: $cantidad_requerida,
            cantidad_requerida_base: $cantidad_requerida_base,
            precio_unitario: $precio_unitario,
            precio_unitario_base: $precio_unitario_base,
            lugar_recojo: $lugar_recojo,
            comentario: $comentario,
        );
    }

    public static function get_detalles_orden_compra(int $id_orden_compra): array
    {
        return OrdenCompraDetalle::get_detalles($id_orden_compra);
    }

    public static function crear_logs(
        int $id_orden_compra_detalle,
        int $id_empleado,
        EstadoOrdenCompraDetalleLog $estado,
        ?string $dinamico = null,
    ) {
        return OrdenCompraDetalleLog::crear_log(
            id_orden_compra_detalle: $id_orden_compra_detalle,
            id_empleado: $id_empleado,
            estado: $estado,
            dinamico: $dinamico,
        );
    }
}
