<?php

namespace App\Modules\OrdenCompra\Data;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;

class OrdenCompraData
{
    /**
     * Obtener el listado de órdenes de compra con empresa y cotización
     */
    public static function get_listado(?int $id = null, ?int $mes = null, ?int $year = null): array
    {
        $res = OrdenCompra::get_ordenes($id, $mes, $year);
        if ($id && !is_array($res)) return [$res];
        return $res;
    }

    /**
     * Obtener los detalles de una OC específica
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        return OrdenCompraDetalle::get_detalles($id_orden_compra);
    }
}
