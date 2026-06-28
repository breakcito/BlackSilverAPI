<?php

namespace App\Modules\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenReposicion;
use App\Models\PrestamoAlmacenReposicionDetalle;
use App\Shared\Helpers\ArchivoHelper;

class ReposicionesData
{

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo()
    {
        return PrestamoAlmacenReposicion::get_nuevo_correlativo();
    }

    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_reposiciones_by_prestamo(int $id_prestamo_almacen): array
    {
        return PrestamoAlmacenReposicion::get_reposiciones(id_prestamo_almacen: $id_prestamo_almacen);
    }

    /**
     * Registra una nueva reposicion por prestamo
     */
    public static function crear_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        ?int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        ?string $fecha_hora_reposicion,
        ?string $observacion = null,
        ?array $evidencias = null,
        ?string $medio_entrega = null,
        ?int $id_proveedor_transporte = null,
        ?int $id_agencia_transporte = null,
        ?string $numero_factura = null,
        ?string $serie_factura = null,
        ?string $serie_guia_transportista = null,
        ?string $numero_guia_transportista = null,
        ?string $serie_guia_remitente = null,
        ?string $numero_guia_remitente = null,
        ?float $costo_envio = null
    ): int {
        return PrestamoAlmacenReposicion::crear_reposicion(
            id_prestamo_almacen: $id_prestamo_almacen,
            id_almacen_entrega: $id_almacen_entrega,
            id_empleado_entrega: $id_empleado_entrega,
            id_empleado_recibe: $id_empleado_recibe,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_reposicion: $fecha_hora_reposicion,
            observacion: $observacion,
            evidencias: $evidencias,
            medio_entrega: $medio_entrega,
            id_proveedor_transporte: $id_proveedor_transporte,
            id_agencia_transporte: $id_agencia_transporte,
            numero_factura: $numero_factura,
            serie_factura: $serie_factura,
            serie_guia_transportista: $serie_guia_transportista,
            numero_guia_transportista: $numero_guia_transportista,
            serie_guia_remitente: $serie_guia_remitente,
            numero_guia_remitente: $numero_guia_remitente,
            costo_envio: $costo_envio
        );
    }

    /**
     * Metodo que guarda las evidencias de una reposicion
     */
    public static function guardar_evidencias(array $evidencias)
    {
        return ArchivoHelper::guardarArchivos('prestamos_almacen_reposiciones', $evidencias);
    }



    /**
     * ------------------------------------------------
     * METODOS PARA LOS DETALLES DE UNA REPOSICION
     * ------------------------------------------------
     */



    /**
     * Obtiene los detalles de una reposición.
     */
    public static function get_detalles_reposicion(int $id_reposicion): array
    {
        return PrestamoAlmacenReposicionDetalle::get_detalles(id_reposicion: $id_reposicion);
    }

    /**
     * Registrar un detalle de una reposicion.
     * Exactamente uno de $id_lote_producto o $id_activo_fijo debe ser provisto.
     */
    public static function crear_detalle_reposicion(
        int $id_reposicion,
        int $id_prestamo_detalle,
        ?int $id_lote_producto,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_prestamo,
        ?int $id_activo_fijo = null,
    ): bool {
        return PrestamoAlmacenReposicionDetalle::crear_detalle(
            $id_reposicion,
            $id_prestamo_detalle,
            $id_lote_producto,
            $cantidad_base,
            $cantidad_lote,
            $cantidad_prestamo,
            $id_activo_fijo,
        );
    }
}
