<?php

namespace App\Modules\OrdenesCompra\Data;

use App\Models\OrdenCompraTransferencia;
use App\Models\OrdenCompraTransferenciaDetalle;

class TransferenciaOCData
{
    /**
     * Crear cabecera de transferencia
     */
    public static function crear_transferencia(
        ?int $id_almacen_destino,
        int $id_orden_compra_recepcion,
        int $id_empleado_transferencia,
        int $id_personal_recibe,
        string $correlativo,
        int $numero_correlativo,
        ?array $evidencias = null,
        ?string $fecha_hora_transferencia = null,
        ?string $observacion = null,
        ?int $id_mina_destino = null
    ): int {
        return (int) OrdenCompraTransferencia::crear_transferencia(
            id_almacen_destino: $id_almacen_destino,
            id_orden_compra_recepcion: $id_orden_compra_recepcion,
            id_empleado_transferencia: $id_empleado_transferencia,
            id_personal_recibe: $id_personal_recibe,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_transferencia: $fecha_hora_transferencia,
            observacion: $observacion,
            evidencias: $evidencias,
            id_mina_destino: $id_mina_destino
        );
    }

    /**
     * Crear detalles de transferencia
     */
    public static function crear_detalles(int $id_transferencia, array $detalles): bool
    {
        return OrdenCompraTransferenciaDetalle::crear_detalle($id_transferencia, $detalles);
    }

    /**
     * Obtener una transferencia por ID
     */
    public static function get_transferencia_by_id(int $id_transferencia)
    {
        // El modelo ya retorna DB::selectOne (stdClass)
        return OrdenCompraTransferencia::get_transferencias(id_transferencia: $id_transferencia);
    }
}
