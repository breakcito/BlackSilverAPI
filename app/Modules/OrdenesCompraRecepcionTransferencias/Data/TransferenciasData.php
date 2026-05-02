<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Data;

use App\Models\OrdenCompraTransferencia;
use App\Models\OrdenCompraTransferenciaDetalle;

class TransferenciasData
{
    // -----------------------------------------------
    // CABECERA
    // -----------------------------------------------

    public static function get_transferencias(
        int $id_almacen_destino,
        int $mes,
        int $yearcito
    ): array {
        return OrdenCompraTransferencia::get_transferencias(
            id_almacen_destino: $id_almacen_destino,
            mes: $mes,
            yearcito: $yearcito
        );
    }

    // -----------------------------------------------
    // DETALLE
    // -----------------------------------------------

    public static function get_detalles_transferencia(array|int $ids_transferencias): array
    {
        return OrdenCompraTransferenciaDetalle::get_detalles(
            ids_transferencias: $ids_transferencias
        );
    }

    /**
     * Obtiene un único detalle de transferencia por su ID.
     * Usado en RecepcionesService para conocer producto, unidades y cantidad_transferida_base.
     */
    public static function get_detalle_by_id(int $id_detalle_transferencia): ?object
    {
        return OrdenCompraTransferenciaDetalle::get_detalles(
            id_transferencia_detalle: $id_detalle_transferencia
        );
    }
}
