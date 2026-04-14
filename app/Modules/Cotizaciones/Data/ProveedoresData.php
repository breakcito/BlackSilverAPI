<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Proveedor;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProveedoresData
{
    /**
     * Listado maestro de proveedores para selectores
     */
    public static function get_proveedores_maestro()
    {
        return DB::select('
            SELECT 
                id AS id_proveedor,
                razon_social,
                ruc,
                dni
            FROM proveedor
            WHERE estado = ?
            ORDER BY razon_social ASC
        ', [EstadoBase::Activo->value]);
    }
}
