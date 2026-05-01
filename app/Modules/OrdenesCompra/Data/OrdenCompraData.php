<?php

namespace App\Modules\OrdenesCompra\Data;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\OrdenCompraDetalleLog;

class OrdenCompraData
{
    /**
     * Obtener el listado de órdenes de compra con empresa y cotización
     */
    public static function get_ordenes(?int $mes = null, ?int $yearcito = null): array
    {
        return OrdenCompra::get_ordenes(mes: $mes, year: $yearcito);
    }

    /**
     * Obtener los detalles de una OC específica
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        return OrdenCompraDetalle::get_detalles($id_orden_compra);
    }

    /**
     * Obtener el seguimiento de un detalle de OC
     */
    public static function get_seguimiento(int $id_detalle): array
    {
        return OrdenCompraDetalleLog::get_logs($id_detalle);
    }
}
