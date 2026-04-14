<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listado maestro de productos para cargue de selectores/modales
     */
    public static function get_productos_maestro()
    {
        return DB::select('
            SELECT 
                p.id AS id_producto,
                p.nombre,
                c.nombre AS categoria_nombre,
                p.id_unidad_medida_base,
                um.nombre AS unidad_medida_base,
                um.abreviatura AS unidad_medida_abreviatura
            FROM producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE p.estado = ?
            ORDER BY p.nombre ASC
        ', [EstadoBase::Activo->value]);
    }
}
