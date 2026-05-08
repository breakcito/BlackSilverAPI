<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionEmpresa;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoDespachoCompra;
use App\Shared\Enums\Cotizacion\EstadoCotizacion;
use App\Shared\Enums\Cotizacion\EstadoCotizacionDetalle;

class CotizacionesData
{
    /**
     * -------------------------------------------------------
     * CABECERA
     * -------------------------------------------------------
     */

    public static function get_nuevo_correlativo(): array
    {
        return Cotizacion::get_nuevo_correlativo();
    }

    public static function crear_cotizacion(
        int $id_comparativo,
        int $id_proveedor,
        //
        string $correlativo,
        int $numero_correlativo,
        //
        string $fecha_hora_cotizacion,
        //
        string $metodo_pago,
        string $moneda,
        ?float $tipo_cambio_venta_referencial = null,
        bool $es_auditable = false,
        //
        float $costo_flete,
        float $otros_gastos,
        //
        float $total_antes_igv,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $total_despues_igv,
        //
        ?string $observacion = null,
        ?string $fecha_vencimiento_pago = null,
        ?string $evidencias = null,
        EstadoCotizacion $estado = EstadoCotizacion::Generada,
    ): int {
        return Cotizacion::crear_cotizacion(
            id_comparativo: $id_comparativo,
            id_proveedor: $id_proveedor,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_cotizacion: $fecha_hora_cotizacion,
            metodo_pago: $metodo_pago,
            moneda: $moneda,
            tipo_cambio_venta_referencial: $tipo_cambio_venta_referencial,
            es_auditable: $es_auditable,
            costo_flete: $costo_flete,
            otros_gastos: $otros_gastos,
            total_antes_igv: $total_antes_igv,
            incluye_igv: $incluye_igv,
            porcentaje_igv: $porcentaje_igv,
            monto_igv: $monto_igv,
            total_despues_igv: $total_despues_igv,
            observacion: $observacion,
            fecha_vencimiento_pago: $fecha_vencimiento_pago,
            evidencias: $evidencias,
            estado: $estado,
        );
    }

    /**
     * Asignar las empresas compradoras a una cotización
     */
    public static function asignar_empresas(int $id_cotizacion, array $empresas_ids): void
    {
        CotizacionEmpresa::asignar_empresa($id_cotizacion, $empresas_ids);
    }

    public static function get_cotizaciones(
        ?int $id_cotizacion = null,
        null|int|array $ids_comparativos = null
    ) {
        return Cotizacion::get_cotizaciones(
            id_cotizacion: $id_cotizacion,
            ids_comparativos: $ids_comparativos,
        );
    }

    /**
     * Obtener las empresas asociadas a un grupo de cotizaciones
     */
    public static function get_empresas_cotizacion(array $ids_cotizaciones): array
    {
        return CotizacionEmpresa::get_empresas($ids_cotizaciones);
    }

    /**
     * Cambiar el estado de una cotización
     */
    public static function actualizar_estado(int $id, EstadoCotizacion $estado): void
    {
        Cotizacion::where('id', $id)->update(['estado' => $estado->value]);
    }

    /**
     * -------------------------------------------------------
     * DETALLE
     * -------------------------------------------------------
     */

    public static function crear_detalle(
        int $id_cotizacion,
        int $id_comparativo_detalle,
        int $id_unidad_medida,
        int $id_almacen_recepcionista,
        //
        TipoDespachoCompra $tipo_despacho,
        //
        int $tiempo_entrega,
        Periodo $tiempo_entrega_periodo,
        int $tiempo_entrega_dias,
        //
        float $cantidad,
        float $contenido_por_presentacion,
        float $cantidad_base,
        //
        float $precio_unitario,
        float $precio_unitario_base,
        //
        ?string $comentario = null,
        ?string $lugar_recojo = null,
        //
        EstadoCotizacionDetalle $estado = EstadoCotizacionDetalle::Pendiente
    ): int {
        return CotizacionDetalle::crear_detalle(
            id_cotizacion: $id_cotizacion,
            id_comparativo_detalle: $id_comparativo_detalle,
            id_unidad_medida: $id_unidad_medida,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            tipo_despacho: $tipo_despacho,
            lugar_recojo: $lugar_recojo,
            tiempo_entrega: $tiempo_entrega,
            tiempo_entrega_periodo: $tiempo_entrega_periodo,
            tiempo_entrega_dias: $tiempo_entrega_dias,
            cantidad: $cantidad,
            contenido_por_presentacion: $contenido_por_presentacion,
            cantidad_base: $cantidad_base,
            precio_unitario: $precio_unitario,
            precio_unitario_base: $precio_unitario_base,
            comentario: $comentario,
            estado: $estado,
        );
    }

    public static function get_detalles_cotizacion(
        ?int $id_detalle = null,
        null|int|array $ids_cotizaciones = null
    ) {
        return CotizacionDetalle::get_detalles(
            id_detalle: $id_detalle,
            ids_cotizaciones: $ids_cotizaciones,
        );
    }


    /**
     * Marca como Aprobados los detalles incluidos y como Rechazados los demás,
     * dentro de una cotización específica.
     */
    public static function actualizar_estados_aprobacion(
        int $id_cotizacion,
        array $ids_aprobados
    ): void {
        CotizacionDetalle::where('id_cotizacion', $id_cotizacion)
            ->whereIn('id', $ids_aprobados)
            ->update(['estado' => EstadoCotizacionDetalle::Aprobado->value]);

        CotizacionDetalle::where('id_cotizacion', $id_cotizacion)
            ->whereNotIn('id', $ids_aprobados)
            ->update(['estado' => EstadoCotizacionDetalle::Rechazado->value]);
    }
}
