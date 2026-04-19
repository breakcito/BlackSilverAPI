<?php

namespace App\Modules\OrdenCompra\Data;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;

class OrdenCompraData
{
    /**
     * Obtener el listado de órdenes de compra con empresa y cotización
     */
    public static function get_listado(): array
    {
        return OrdenCompra::get_ordenes();
    }

    /**
     * Obtener los detalles de una OC específica
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        return OrdenCompraDetalle::get_detalles($id_orden_compra);
    }
}
