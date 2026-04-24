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
     * QUERYS PARA LA CABECERA
     * -------------------------------------------------------
     */


    /**
     * Obtener el siguiente número correlativo usando el helper
     */
    public static function get_nuevo_correlativo(): array
    {
        return Cotizacion::get_nuevo_correlativo();
    }

    /**
     * Crear cabecera de cotización
     */
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
            $id_comparativo,
            $id_proveedor,
            //
            $correlativo,
            $numero_correlativo,
            //
            $fecha_hora_cotizacion,
            //
            $metodo_pago,
            $moneda,
            //
            $costo_flete,
            $otros_gastos,
            //
            $total_antes_igv,
            $incluye_igv,
            $porcentaje_igv,
            $monto_igv,
            $total_despues_igv,
            //
            $observacion,
            $fecha_vencimiento_pago,
            $evidencias,
            $estado
        );
    }

    /**
     * Asignar empresas a una cotización
     */
    public static function asignar_empresa(int $id_cotizacion, array $empresas_ids): bool
    {
        return CotizacionEmpresa::asignar_empresa(
            $id_cotizacion,
            $empresas_ids
        );
    }


    public static function get_cotizaciones(
        ?int $id_cotizacion = null,
        null|int|array $ids_comparativos = null
    ) {
        return Cotizacion::get_cotizaciones(
            id_cotizacion: $id_cotizacion,
            ids_comparativos: $ids_comparativos
        );
    }


    /**
     * -------------------------------------------------------
     * QUERYS PARA EL DETALLE
     * -------------------------------------------------------
     */


    /**
     * Crear detalle de cotización
     */
    public static function crear_detalle(
        int $id_cotizacion,
        int $id_comparativo_detalle,
        int $id_unidad_medida,
        int $id_almacen_recepcionista,
        //
        TipoDespachoCompra $tipo_despacho,
        ?string $lugar_recojo = null,
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
        //
        EstadoCotizacionDetalle $estado = EstadoCotizacionDetalle::Generada
    ): int {
        return CotizacionDetalle::crear_detalle(
            $id_cotizacion,
            $id_comparativo_detalle,
            $id_unidad_medida,
            $id_almacen_recepcionista,
            //
            $tipo_despacho,
            $lugar_recojo,
            //
            $tiempo_entrega,
            $tiempo_entrega_periodo,
            $tiempo_entrega_dias,
            //
            $cantidad,
            $contenido_por_presentacion,
            $cantidad_base,
            //
            $precio_unitario,
            $precio_unitario_base,
            //
            $comentario,
            //
            $estado
        );
    }


    /**
     * Obtener el o los detalles de una cotizacion
     */
    public static function get_detalles_cotizacion(
        ?int $id_detalle = null,
        null|int|array $ids_cotizaciones = null
    ) {
        return CotizacionDetalle::get_detalles(
            id_detalle: $id_detalle,
            ids_cotizaciones: $ids_cotizaciones
        );
    }
}
