<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Data;

use App\Models\OrdenCompraTransferencia;
use App\Models\OrdenCompraTransferenciaDetalle;

class TransferenciasData
{
    /**
     * ---------------------
     * PARA LA CABECERA
     * ---------------------
     */


    public static function get_transferencias(
        int $id_almacen_destino,
        int $mes,
        int $yearcito
    ) {
        return OrdenCompraTransferencia::get_transferencias(
            id_almacen_destino: $id_almacen_destino,
            mes: $mes,
            yearcito: $yearcito
        );
    }


    /**
     * ---------------------
     * PARA EL DETALLE
     * ---------------------
     */

    public static function get_detalles_transferencia(array|int $ids_transferencias)
    {
        return OrdenCompraTransferenciaDetalle::get_detalles(ids_transferencias: $ids_transferencias);
    }
}
